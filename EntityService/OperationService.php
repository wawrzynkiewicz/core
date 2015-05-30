<?php
/*
 * This file is part of the CampaignChain package.
 *
 * (c) Sandro Groganz <sandro@campaignchain.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CampaignChain\CoreBundle\EntityService;

use Doctrine\ORM\EntityManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use CampaignChain\CoreBundle\Entity\Operation;
use CampaignChain\CoreBundle\Entity\Activity;

class OperationService
{
    protected $em;
    protected $container;

    public function __construct(EntityManager $em, ContainerInterface $container)
    {
        $this->em = $em;
        $this->container = $container;
    }

    public function getOperationModule($bundleIdentifier, $operationIdentifier){
        // Get bundle.
        $bundle = $this->em
            ->getRepository('CampaignChainCoreBundle:Bundle')
            ->findOneByName($bundleIdentifier);
        if (!$bundle) {
            throw new \Exception(
                'No bundle found for identifier '.$bundleIdentifier
            );
        }

        // Get the operation module's config.
        $operationModule = $this->em
            ->getRepository('CampaignChainCoreBundle:OperationModule')
            ->findOneBy(array(
                    'bundle' => $bundle,
                    'identifier' => $operationIdentifier,
                )
            );
        if (!$operationModule) {
            throw new \Exception(
                'No operation module found for bundle '.$bundle->getName().' and identifier '.$operationIdentifier
            );
        }

        return $operationModule;
    }

    public function moveOperation(Operation $operation, $interval){
        $hookService = $this->container->get($operation->getTriggerHook()->getServices()['entity']);
        $hook = $hookService->getHook($operation);
        if($hook->getStartDate() !== null){
            $hook->setStartDate(new \DateTime($hook->getStartDate()->add($interval)->format(\DateTime::ISO8601)));
        }
        if($hook->getEndDate() !== null){
            $hook->setEndDate(new \DateTime($hook->getEndDate()->add($interval)->format(\DateTime::ISO8601)));
        }

        $operation = $hookService->processHook($operation, $hook);

        $this->em->persist($operation);
        $this->em->flush();

        return $operation;
    }

    public function cloneOperation(Activity $activity, Operation $operation, $status = null)
    {
        $clonedOperation = clone $operation;
        $clonedOperation->setActivity($activity);
        $activity->addOperation($clonedOperation);

        if($status != null){
            $clonedOperation->setStatus($status);
        }

        $this->em->persist($clonedOperation);
        $this->em->flush();

        return $clonedOperation;
    }
}