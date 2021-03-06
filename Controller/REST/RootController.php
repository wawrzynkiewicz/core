<?php
/*
 * This file is part of the CampaignChain package.
 *
 * (c) CampaignChain, Inc. <info@campaignchain.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CampaignChain\CoreBundle\Controller\REST;

use CampaignChain\CoreBundle\Entity\Action;
use CampaignChain\CoreBundle\Entity\Activity;
use CampaignChain\CoreBundle\Entity\Module;
use FOS\RestBundle\Controller\Annotations as REST;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use FOS\RestBundle\Request\ParamFetcher;

/**
 * @REST\NamePrefix("campaignchain_core_rest_")
 *
 * Class RootController
 * @package CampaignChain\CoreBundle\Controller\REST
 */
class RootController extends BaseController
{
    /**
     * Get a list of all installed CampaignChain modules.
     *
     * Example Request
     * ===============
     *
     *      GET /api/v1/modules
     *
     * Example Response
     * ================
     *
    [
        {
            "composerPackage": "campaignchain/location-facebook",
            "moduleIdentifier": "campaignchain-facebook-page",
            "displayName": "Facebook page stream",
            "hooks": {
                "default": {
                    "campaignchain-assignee": true
                }
            },
            "createdDate": "2015-11-26T11:08:29+0000"
        },
        {
            "composerPackage": "campaignchain/activity-facebook",
            "moduleIdentifier": "campaignchain-facebook-publish-status",
            "displayName": "Publish Status",
            "routes": {
                "new": "campaignchain_activity_facebook_publish_status_new",
                "edit": "campaignchain_activity_facebook_publish_status_edit",
                "edit_modal": "campaignchain_activity_facebook_publish_status_edit_modal",
                "edit_api": "campaignchain_activity_facebook_publish_status_edit_api",
                "read": "campaignchain_activity_facebook_publish_status_read"
            },
            "hooks": {
                "default": {
                    "campaignchain-due": true,
                    "campaignchain-assignee": true
                    }
                },
            "createdDate": "2015-11-26T11:08:29+0000"
        },
        {
            "composerPackage": "campaignchain/operation-facebook",
            "moduleIdentifier": "campaignchain-facebook-publish-status",
            "displayName": "Publish Status",
            "services": {
                "operation": "campaignchain.operation.facebook.status",
                "job": "campaignchain.job.operation.facebook.publish_status",
                "report": "campaignchain.job.report.facebook.publish_status"
            },
            "params": {
                "owns_location": true
            },
            "createdDate": "2015-11-26T11:08:29+0000"
        }
    ]
     *
     * @ApiDoc(
     *  section="Core"
     * )
     */
    public function getModulesAction()
    {
        $qb = $this->getQueryBuilder();
        $qb->select(ModuleController::SELECT_STATEMENT);
        $qb->from('CampaignChain\CoreBundle\Entity\Module', 'm');
        $qb->join('m.bundle', 'b');
        $qb->where('b.id = m.bundle');
        $qb->orderBy('m.identifier');
        $query = $qb->getQuery();

        return $this->response(
            $query->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY)
        );
    }

    /**
     * Get a list of all installed Composer packages containing CampaignChain modules.
     *
     * Example Request
     * ===============
     *
     *      GET /api/v1/packages
     *
     * Example Response
     * ================
     *
    [
        {
            "id": 22,
            "packageType": "campaignchain-activity",
            "composerPackage": "campaignchain/activity-facebook",
            "description": "Collection of various Facebook activities, such as post or share a message.",
            "license": "Apache-2.0",
            "authors": {
                "name": "CampaignChain, Inc.",
                "email": "info@campaignchain.com\""
            },
            "homepage": "http://www.campaignchain.com",
            "version": "dev-master",
            "createdDate": "2015-11-26T11:08:29+0000"
        },
        {
            "id": 25,
            "packageType": "campaignchain-activity",
            "composerPackage": "campaignchain/activity-gotowebinar",
            "description": "Include a Webinar into a campaign.",
            "license": "Apache-2.0",
            "authors": {
                "name": "CampaignChain, Inc.",
                "email": "info@campaignchain.com\""
            },
            "homepage": "http://www.campaignchain.com",
            "version": "dev-master",
            "createdDate": "2015-11-26T11:08:29+0000"
        },
        {
            "id": 24,
            "packageType": "campaignchain-activity",
            "composerPackage": "campaignchain/activity-linkedin",
            "description": "Collection of various LinkedIn activities, such as tweeting and re-tweeting.",
            "license": "Apache-2.0",
            "authors": {
                "name": "CampaignChain, Inc.",
                "email": "info@campaignchain.com\""
            },
            "homepage": "http://www.campaignchain.com",
            "version": "dev-master",
            "createdDate": "2015-11-26T11:08:29+0000"
        }
    ]
     *
     * @ApiDoc(
     *  section="Core"
     * )
     */
    public function getPackagesAction()
    {
        $qb = $this->getQueryBuilder();
        $qb->select(PackageController::SELECT_STATEMENT);
        $qb->from('CampaignChain\CoreBundle\Entity\Bundle', 'b');
        $qb->orderBy('b.name');
        $query = $qb->getQuery();

        return $this->response(
            $query->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY)
        );
    }

    /**
     * Get a list of Campaigns.
     *
     * Example Request
     * ===============
     *
     *      GET /api/v1/campaigns?fromNow[]=ongoing&moduleUri[]=campaignchain/campaign-scheduled/campaignchain-scheduled&status[]=open
     *
     * Example Response
     * ================
     *
    [
        {
            "id": 2,
            "timezone": "America/Paramaribo",
            "hasRelativeDates": false,
            "name": "Company Anniversary",
            "startDate": "2015-06-10T22:01:32+0000",
            "endDate": "2015-12-21T05:04:27+0000",
            "status": "open",
            "createdDate": "2015-11-26T11:08:29+0000"
        },
        {
            "id": 3,
            "timezone": "Asia/Tashkent",
            "hasRelativeDates": false,
            "name": "Customer Win Story",
            "startDate": "2015-09-28T07:02:39+0000",
            "endDate": "2016-04-18T01:44:23+0000",
            "status": "open",
            "createdDate": "2015-11-26T11:08:29+0000"
        }
    ]
     *
     * @ApiDoc(
     *  section="Core"
     * )
     *
     * @REST\QueryParam(
     *      name="fromNow",
     *      map=true,
     *      requirements="(done|ongoing|upcoming)",
     *      description="Filters per start and end date, options: 'upcoming' (start date is after now), 'ongoing' (start date is before and end date after now), 'done' (end date is before now)."
     * )
     * @REST\QueryParam(
     *      name="status",
     *      map=true,
     *      requirements="(open|closed|paused|background process|interaction required)",
     *      description="Workflow status of a campaign."
     * )
     * @REST\QueryParam(
     *      name="moduleUri",
     *      map=true,
     *      requirements="[A-Za-z0-9][A-Za-z0-9_.-]*\/[A-Za-z0-9][A-Za-z0-9_.-]*\/[A-Za-z0-9][A-Za-z0-9_.-]*",
     *      description="The module URI of a campaign module, e.g. campaignchain/campaign-scheduled/campaignchain-scheduled."
     *  )
     */
    public function getCampaignsAction(ParamFetcher $paramFetcher)
    {
        try {
            $params = $paramFetcher->all();

            $qb = $this->getQueryBuilder();
            $qb->select('c');
            $qb->from('CampaignChain\CoreBundle\Entity\Campaign', 'c');

            if($params['moduleUri']){
                $qb->from('CampaignChain\CoreBundle\Entity\Bundle', 'b');
                $qb->from('CampaignChain\CoreBundle\Entity\Module', 'm');
                $qb->andWhere('b.id = m.bundle');
                $qb->andWhere('c.campaignModule = m.id');

                foreach($params['moduleUri'] as $key => $moduleUri) {
                    $moduleUriParts = explode('/', $moduleUri);
                    $vendor = $moduleUriParts[0];
                    $project = $moduleUriParts[1];
                    $identifier = $moduleUriParts[2];
                    $moduleUriQuery[] = '(b.name = :package'.$key.' AND '.'m.identifier = :identifier'.$key.')';
                    $qb->setParameter('package'.$key, $vendor . '/' . $project);
                    $qb->setParameter('identifier'.$key, $identifier);
                }

                $qb->andWhere(implode(' OR ', $moduleUriQuery));
            }

            if($params['fromNow']){
                foreach($params['fromNow'] as $fromNow) {
                    switch($fromNow){
                        case 'done':
                            $fromNowQuery[] = '(c.startDate < CURRENT_TIMESTAMP() AND c.endDate < CURRENT_TIMESTAMP())';
                            break;
                        case 'ongoing':
                            $fromNowQuery[] = '(c.startDate < CURRENT_TIMESTAMP() AND c.endDate > CURRENT_TIMESTAMP())';
                            break;
                        case 'upcoming':
                            $fromNowQuery[] = '(c.startDate > CURRENT_TIMESTAMP() AND c.endDate > CURRENT_TIMESTAMP())';
                            break;
                    }
                }

                $qb->andWhere(implode(' OR ', $fromNowQuery));
            }

            if($params['status']){
                foreach($params['status'] as $key => $status) {
                    $statusQuery[] = 'c.status = :status' . $key;
                    $qb->setParameter('status' . $key, $status);
                }

                $qb->andWhere(implode(' OR ', $statusQuery));
            }

            $qb->orderBy('c.name');
            $query = $qb->getQuery();


            return $this->response(
                $query->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY)
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        }
    }

    /**
     * Get a list of all installed Channels.
     *
     * Example Request
     * ===============
     *
     *      GET /api/v1/channels
     *
     * Example Response
     * ================
     *
    [
        {
            "composerPackage": "campaignchain/channel-facebook",
            "moduleIdentifier": "campaignchain-facebook",
            "displayName": "Facebook",
            "routes": {
                "new": "campaignchain_channel_facebook_create"
            },
            "hooks": {
                "default": {
                    "campaignchain-assignee": true
                }
            },
            "createdDate": "2015-11-26T11:08:29+0000"
        },
        {
            "composerPackage": "campaignchain/channel-linkedin",
            "moduleIdentifier": "campaignchain-linkedin",
            "displayName": "LinkedIn",
            "routes": {
                "new": "campaignchain_channel_linkedin_create"
            },
            "hooks": {
                "default": {
                    "campaignchain-assignee": true
                }
            },
            "createdDate": "2015-11-26T11:08:29+0000"
        },
        {
            "composerPackage": "campaignchain/channel-twitter",
            "moduleIdentifier": "campaignchain-twitter",
            "displayName": "Twitter",
            "routes": {
                "new": "campaignchain_channel_twitter_create"
            },
            "hooks": {
                "default": {
                    "campaignchain-assignee": true
                }
            },
            "createdDate": "2015-11-26T11:08:29+0000"
        }
    ]
     *
     * @ApiDoc(
     *  section="Core"
     * )
     */
    public function getChannelsAction()
    {
        $qb = $this->getQueryBuilder();
        $qb->select(ModuleController::SELECT_STATEMENT);
        $qb->from('CampaignChain\CoreBundle\Entity\ChannelModule', 'm');
        $qb->join('m.bundle', 'b');
        $qb->where('b.id = m.bundle');
        $qb->orderBy('m.identifier');
        $query = $qb->getQuery();

        return $this->response(
            $query->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY)
        );
    }

    /**
     * Get a list of system URLs, so-called routes.
     *
     * Example Request
     * ===============
     *
     *      GET /api/v1/routes
     *
     * Example Response
     * ================
     *
    [
        {
            "url": "https://www.example.com/",
            "name": "campaignchain_core_homepage",
            "description": "The start page"
        },
        {
            "url": "https://www.example.com/about/",
            "name": "campaignchain_core_about",
            "description": "Read about CampaignChain"
        },
        {
            "url": "https://www.example.com/plan",
            "name": "campaignchain_core_plan",
            "description": "Plan campaigns"
        },
        {
            "url": "https://www.example.com/execute/",
            "name": "campaignchain_core_execute",
            "description": "View upcoming actions"
        },
        {
            "url": "https://www.example.com/campaign/new/",
            "name": "campaignchain_core_campaign_new",
            "description": "Create a new campaign"
        }
    ]
     *
     * @ApiDoc(
     *  section="Core"
     * )
     */
    public function getRoutesAction()
    {
        $response = array();

        $schemeAndHost = $this->get('request')->getSchemeAndHttpHost();
        // Get rid of dev environment (app_dev.php).
        $baseUrl = $this->get('router')->getContext()->getBaseUrl();
        $this->get('router')->getContext()->setBaseUrl('');

        $routeCollection = $this->get('router')->getRouteCollection();

        foreach ($routeCollection->all() as $name => $route)
        {
            $options = $route->getOptions();
            if(
                isset($options['campaignchain']) &&
                isset($options['campaignchain']['rest']) &&
                isset($options['campaignchain']['rest']['expose']) &&
                $options['campaignchain']['rest']['expose']
            ){
                $routeData = array(
                    'url' => $schemeAndHost.$this->generateUrl($name),
                    'name' => $name,
                );

                if(isset($options['campaignchain']['description'])){
                    $routeData['description'] = $options['campaignchain']['description'];
                }

                $response[] = $routeData;
            }
        }

        // Reset to previous environment.
        $this->get('router')->getContext()->setBaseUrl($baseUrl);

        return $this->response(
            $response
        );
    }

    /**
     * Get a list of all users.
     *
     * Example Request
     * ===============
     *
     *      GET /api/v1/users
     *
     * Example Response
     * ================
     *
    [
        {
            "id": 1,
            "username": "admin",
            "firstName": "Sandro",
            "lastName": "Groganz",
            "email": "admin@example.com",
            "roles": [
                "ROLE_SUPER_ADMIN"
            ],
            "language": "en_US",
            "locale": "en_US",
            "timezone": "UTC",
            "currency": "USD",
            "dateFormat": "yyyy-MM-dd",
            "timeFormat": "HH:mm",
            "profileImage": "avatar/4d6e7d832be2ab4c.jpg"
        },
        {
            "id": 2,
            "username": "hipolito_marks",
            "firstName": "Danial",
            "lastName": "Smith",
            "email": "user1@example.com",
            "roles": [
                "ROLE_ADMIN"
            ],
            "language": "en_US",
            "locale": "en_US",
            "timezone": "Antarctica/Mawson",
            "currency": "USD",
            "dateFormat": "yyyy-MM-dd",
            "timeFormat": "HH:mm",
            "profileImage": "avatar/c44d95581d3b5df4.jpg"
        }
    ]
     *
     * @ApiDoc(
     *  section="Core"
     * )
     */
    public function getUsersAction()
    {
        $qb = $this->getQueryBuilder();
        $qb->select(UserController::SELECT_STATEMENT);
        $qb->from('CampaignChain\CoreBundle\Entity\User', 'u');
        $qb->orderBy('u.username');
        $query = $qb->getQuery();

        return $this->response(
            $query->getResult(\Doctrine\ORM\Query::HYDRATE_ARRAY)
        );
    }
}