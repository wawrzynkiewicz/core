<?php
/*
 * This file is part of the CampaignChain package.
 *
 * (c) CampaignChain Inc. <info@campaignchain.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CampaignChain\CoreBundle\Controller\Module;

use CampaignChain\CoreBundle\Entity\Activity;
use CampaignChain\CoreBundle\Entity\Location;
use CampaignChain\CoreBundle\Entity\Medium;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Session\Session;
use CampaignChain\CoreBundle\Entity\Operation;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;

class ActivityModuleController extends Controller
{
    protected $parameters;

    protected $handler;

    protected $campaign;

    protected $activity;

    protected $location;

    protected $operations = array();

    private $locationBundleName;
    private $locationModuleIdentifier;
    private $contentBundleName;
    private $contentModuleIdentifier;
    private $contentFormType;

    public function setParameters($parameters){
        $this->parameters = $parameters;

        if(!isset($this->parameters['handler'])){
            throw new \Exception('No Activity handler defined in services.yml.');
        }
        $this->handler = $this->get($this->parameters['handler']);

        if(!isset($this->parameters['equals_operation'])){
            $this->parameters['equals_operation'] = false;
        }

        $this->locationBundleName = $this->parameters['location']['bundle_name'];
        $this->locationModuleIdentifier = $this->parameters['location']['module_identifier'];
        if($this->parameters['equals_operation']) {
            $this->contentBundleName = $this->parameters['operations'][0]['bundle_name'];
            $this->contentModuleIdentifier = $this->parameters['operations'][0]['module_identifier'];
            $this->contentFormType = $this->parameters['operations'][0]['form_type'];
        } else {
            $this->contentFormType = $this->parameters['content_form_type'];
        }
    }

    /**
     * Symfony controller action for creating a new CampaignChain Activity.
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|Response
     * @throws \Exception
     */
    public function newAction(Request $request)
    {
        /*
         * Get context from user's choice.
         */
        $wizard = $this->get('campaignchain.core.activity.wizard');
        $campaignService = $this->get('campaignchain.core.campaign');
        $this->campaign = $campaignService->getCampaign($wizard->getCampaign());
        $this->activity = $wizard->getActivity();
        $this->activity->setEqualsOperation($this->parameters['equals_operation']);
        $locationService = $this->get('campaignchain.core.location');
        $this->location = $locationService->getLocation($wizard->getLocation());

        $this->location = $this->handler->processActivityLocation($this->location);

        $form = $this->createForm(
            $this->getActivityFormType('new'), $this->activity
        );

        $form->handleRequest($request);

        if ($form->isValid()) {
            $this->activity = $wizard->end();

            $this->handler->postFormSubmitNewEvent(
                $this->activity,
                $form->get($this->contentModuleIdentifier)->getData()
            );

            // Allow a module's handler to modify the Activity data.
            $this->activity = $this->handler->processActivity(
                $this->activity,
                $form->get($this->contentModuleIdentifier)->getData()
            );

            // Get the operation module.
            $operationService = $this->get('campaignchain.core.operation');
            $operationModule = $operationService->getOperationModule(
                $this->contentBundleName,
                $this->contentModuleIdentifier
            );

            if($this->parameters['equals_operation']) {
                // The activity equals the operation. Thus, we create a new operation with the same data.
                $operation = new Operation();
                $operation->setName($this->activity->getName());
                $operation->setStartDate($this->activity->getStartDate());
                $operation->setEndDate($this->activity->getEndDate());
                $operation->setTriggerHook($this->activity->getTriggerHook());
                $operation->setActivity($this->activity);
                $this->activity->addOperation($operation);
                $operationModule->addOperation($operation);
                $operation->setOperationModule($operationModule);

                // The Operation creates a Location, i.e. the Operation
                // will be accessible through a URL after publishing.

                // Get the location module.
                $locationModule = $locationService->getLocationModule(
                    $this->locationBundleName,
                    $this->locationModuleIdentifier
                );

                $contentLocation = new Location();
                $contentLocation->setLocationModule($locationModule);
                $contentLocation->setParent($this->activity->getLocation());
                $contentLocation->setName($this->activity->getName());
                $contentLocation->setStatus(Medium::STATUS_UNPUBLISHED);
                $contentLocation->setOperation($operation);
                $operation->addLocation($contentLocation);
                // Allow a module's handler to modify the Operation's Location.
                $contentLocation = $this->handler->processContentLocation(
                    $contentLocation,
                    $form->get($this->contentModuleIdentifier)->getData()
                );

                // Process the Operation details.
                $content = $this->handler->processContent(
                    $operation,
                    $form->get($this->contentModuleIdentifier)->getData()
                );

                // Link the Operation details with the operation.
                $content->setOperation($operation);
            } else {
                throw new \Exception(
                    'Multiple Operations for one Activity not implemented yet.'
                );
            }

            $repository = $this->getDoctrine()->getManager();

            // Make sure that data stays intact by using transactions.
            try {
                $repository->getConnection()->beginTransaction();

                $repository->persist($this->activity);
                $repository->persist($content);

                // We need the activity ID for storing the hooks. Hence we must
                // flush here.
                $repository->flush();

                $hookService = $this->get('campaignchain.core.hook');
                $this->activity = $hookService->processHooks(
                    $this->parameters['bundle_name'],
                    $this->parameters['module_identifier'],
                    $this->activity,
                    $form,
                    true
                );
                $repository->flush();

                $repository->getConnection()->commit();
            } catch (\Exception $e) {
                $repository->getConnection()->rollback();
                throw $e;
            }

            $this->get('session')->getFlashBag()->add(
                'success',
                'Your new activity <a href="'.$this->generateUrl('campaignchain_core_activity_edit', array('id' => $this->activity->getId())).'">'.$this->activity->getName().'</a> was created successfully.'
            );

            $this->handler->postPersistNewEvent($operation, $form, $content);

            return $this->redirect($this->generateUrl('campaignchain_core_activities'));
        }

        return $this->render(
            'CampaignChainCoreBundle:Operation:new.html.twig',
            array(
                'page_title' => 'New Activity',
                'activity' => $this->activity,
                'campaign' => $this->campaign,
                'campaign_module' => $this->campaign->getCampaignModule(),
                'channel_module' => $wizard->getChannelModule(),
                'channel_module_bundle' => $wizard->getChannelModuleBundle(),
                'location' => $this->location,
                'form' => $form->createView(),
                'form_submit_label' => 'Save',
                'form_cancel_route' => 'campaignchain_core_activities_new'
            ));

    }

    /**
     * Symfony controller action for editing a CampaignChain Activity.
     *
     * @param Request $request
     * @param $id
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|Response
     * @throws \Exception
     */
    public function editAction(Request $request, $id)
    {
        $activityService = $this->get('campaignchain.core.activity');
        $this->activity = $activityService->getActivity($id);
        $this->campaign = $this->activity->getCampaign();
        $this->location = $this->activity->getLocation();

        if($this->parameters['equals_operation']) {
            // Get the one operation.
            $this->operations[0] = $activityService->getOperation($id);
        } else {
            throw new \Exception(
                'Multiple Operations for one Activity not implemented yet.'
            );
        }

        $content = $this->handler->preFormSubmitEditEvent($this->operations[0]);

        $form = $this->createForm(
            $this->getActivityFormType('edit'), $this->activity
        );

        $form->handleRequest($request);

        if ($form->isValid()) {
            $repository = $this->getDoctrine()->getManager();

            // Make sure that data stays intact by using transactions.
            try {
                $repository->getConnection()->beginTransaction();

                if($this->handler->hasContent('edit')) {
                    // Get the status data from request.
                    $content =
                        $form->get($this->contentModuleIdentifier)
                            ->getData();

                    if ($this->parameters['equals_operation']) {
                        // The activity equals the operation. Thus, we update the operation with the same data.
                        $this->operations[0]->setName($this->activity->getName());
                        $repository->persist($this->operations[0]);
                    } else {
                        throw new \Exception(
                            'Multiple Operations for one Activity not implemented yet.'
                        );
                    }

                    $repository->persist($content);
                }

                $hookService = $this->get('campaignchain.core.hook');
                $this->activity = $hookService->processHooks(
                    $this->parameters['bundle_name'],
                    $this->parameters['module_identifier'],
                    $this->activity,
                    $form
                );
                $repository->persist($this->activity);

                $repository->flush();

                $repository->getConnection()->commit();
            } catch (\Exception $e) {
                $repository->getConnection()->rollback();
                throw $e;
            }

            $this->get('session')->getFlashBag()->add(
                'success',
                'Your activity <a href="'.$this->generateUrl('campaignchain_core_activity_edit', array('id' => $this->activity->getId())).'">'.$this->activity->getName().'</a> was edited successfully.'
            );

            $this->handler->postPersistEditEvent($this->operations[0], $form, $content);

            if ($form->get('campaignchain_hook_campaignchain_due')->has('execution_choice') && $form->get('campaignchain_hook_campaignchain_due')->get('execution_choice')->getData() == 'now') {
                $job = $this->get($this->parameters['operation_job']);
                $job->execute($this->operations[0]->getId());
                // TODO: Add different flashbag which includes link to posted message on Facebook
            }

            return $this->redirect($this->generateUrl('campaignchain_core_activities'));
        }

        /*
         * Define default rendering options and then apply those defined by the
         * module's handler if applicable.
         */
        $defaultRenderOptions = array(
            'template' => 'CampaignChainCoreBundle:Operation:new.html.twig',
            'vars' => array(
                'page_title' => 'Edit Activity',
                'activity' => $this->activity,
                'form' => $form->createView(),
                'form_submit_label' => 'Save',
                'form_cancel_route' => 'campaignchain_core_activities'
            )
        );

        $handlerRenderOptions = $this->handler->getEditRenderOptions(
            $this->operations[0]
        );

        return $this->renderWithHandlerOptions($defaultRenderOptions, $handlerRenderOptions);
    }

    /**
     * Symfony controller action for editing a CampaignChain Activity in a
     * pop-up window.
     *
     * @param Request $request
     * @param $id
     * @return Response
     * @throws \Exception
     */
    public function editModalAction(Request $request, $id)
    {
        $activityService = $this->get('campaignchain.core.activity');
        $this->activity = $activityService->getActivity($id);
        $this->location = $this->activity->getLocation();
        $this->campaign = $this->activity->getCampaign();

        if($this->parameters['equals_operation']) {
            // Get the one operation.
            $this->operations[0] = $activityService->getOperation($id);
        } else {
            throw new \Exception(
                'Multiple Operations for one Activity not implemented yet.'
            );
        }

        $activityFormType = $this->getActivityFormType('editModal');
        $activityFormType->setView('default');

        $this->handler->preFormSubmitEditModalEvent($this->operations[0]);

        $form = $this->createForm($activityFormType, $this->activity);

        $form->handleRequest($request);

        /*
         * Define default rendering options and then apply those defined by the
         * module's handler if applicable.
         */
        $defaultRenderOptions = array(
            'template' => 'CampaignChainCoreBundle:Base:new_modal.html.twig',
            'vars' => array(
                'page_title' => 'Edit Activity',
                'form' => $form->createView()
            )
        );

        $handlerRenderOptions = $this->handler->getEditModalRenderOptions(
            $this->operations[0]
        );

        return $this->renderWithHandlerOptions($defaultRenderOptions, $handlerRenderOptions);
    }

    /**
     * Symfony controller action that takes the data posted by the editModalAction
     * and persists it.
     *
     * @param Request $request
     * @param $id
     * @return Response
     * @throws \Exception
     */
    public function editApiAction(Request $request, $id)
    {
        $responseData = array();

        $data = $request->get('campaignchain_core_activity');

        $activityService = $this->get('campaignchain.core.activity');
        $this->activity = $activityService->getActivity($id);
        $this->activity->setName($data['name']);

        if($this->parameters['equals_operation']) {
            // Get the one operation.
            $operation = $activityService->getOperation($id);
            // The activity equals the operation. Thus, we update the operation
            // with the same data.
            $operation->setName($data['name']);
            $this->operations[0] = $operation;

            if($this->handler->hasContent('editModal')){
                $content = $this->handler->processContent(
                    $this->operations[0],
                    $data[$this->contentModuleIdentifier]
                );
            }
        } else {
            throw new \Exception(
                'Multiple Operations for one Activity not implemented yet.'
            );
        }

        $repository = $this->getDoctrine()->getManager();
        $repository->persist($this->activity);
        $repository->persist($this->operations[0]);
        if($this->handler->hasContent('editModal')) {
            $repository->persist($content);
        }

        $hookService = $this->get('campaignchain.core.hook');
        $this->activity = $hookService->processHooks(
            $this->parameters['bundle_name'],
            $this->parameters['module_identifier'],
            $this->activity,
            $data
        );

        $repository->flush();

        $responseData['start_date'] =
        $responseData['end_date'] =
            $this->activity->getStartDate()->format(\DateTime::ISO8601);

        $encoders = array(new JsonEncoder());
        $normalizers = array(new GetSetMethodNormalizer());
        $serializer = new Serializer($normalizers, $encoders);

        $response = new Response($serializer->serialize($responseData, 'json'));
        return $response->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Symfony controller action for viewing the data of a CampaignChain Activity.
     *
     * @param Request $request
     * @param $id
     * @return mixed
     * @throws \Exception
     */
    public function readAction(Request $request, $id)
    {
        $activityService = $this->get('campaignchain.core.activity');
        $this->activity = $activityService->getActivity($id);

        if($this->parameters['equals_operation']) {
            // Get the one operation.
            $this->operations[0] = $activityService->getOperation($id);
        } else {
            throw new \Exception(
                'Multiple Operations for one Activity not implemented yet.'
            );
        }

        if($this->handler){
            return $this->handler->readAction($this->operations[0]);
        } else {
            throw new \Exception('No read handler defined.');
        }
    }

    /**
     * Configure an Activity's form type.
     *
     * @return object
     */
    private function getActivityFormType($view)
    {
        $activityFormType = $this->get('campaignchain.core.form.type.activity');
        $activityFormType->setBundleName($this->parameters['bundle_name']);
        $activityFormType->setModuleIdentifier(
            $this->parameters['module_identifier']
        );
        if($this->handler->hasContent($view)) {
            $activityFormType->setContentForms(
                $this->getContentFormTypes()
            );
        }
        $activityFormType->setCampaign($this->campaign);

        return $activityFormType;
    }

    /**
     * Set the Operation forms for this Activity.
     *
     * @return array
     * @throws \Exception
     */
    private function getContentFormTypes()
    {
        if($this->parameters['equals_operation']) {
            $operationForm = $this->contentFormType;
            $contentFormType = new $operationForm(
                $this->getDoctrine()->getManager(),
                $this->get('service_container')
            );

            if($this->location) {
                $contentFormType->setLocation($this->location);
            }

            if($this->handler){
                if(isset($this->operations[0])){
                    $content = $this->handler->getContent(
                        $this->location, $this->operations[0]
                    );
                } else {
                    $content = $this->handler->createContent($this->location, $this->campaign);
                }
                $contentFormType->setContent($content);
            }

            $operationForms[] = array(
                'identifier' => $this->contentModuleIdentifier,
                'form' => $contentFormType
            );
        } else {
            throw new \Exception(
                'Multiple Operations for one Activity not implemented yet.'
            );
        }

        return $operationForms;
    }

    /**
     * Applies handler's template render options to default ones.
     *
     * @param $default
     * @param $handler
     * @return array
     */
    private function renderWithHandlerOptions($default, $handler)
    {
        if($handler){
            if(isset($handler['template'])){
                $default['template'] = $handler['template'];
            }
            if(isset($handler['vars'])) {
                $default['vars'] = $default['vars'] + $handler['vars'];
            }
        }

        return $this->render($default['template'], $default['vars']);
    }
}