<?php
/*
 * This file is part of the CampaignChain package.
 *
 * (c) CampaignChain, Inc. <info@campaignchain.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CampaignChain\CoreBundle\Module;

class Package
{
    private $packages;

    public function __construct($root, $dev = false)
    {
        $composerLock = json_decode(file_get_contents(
                $root.
                DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'composer.lock')
        );

        $this->packages = $composerLock->packages;

        // Also get required dev packages if in dev mode
        if($dev){
            $this->packages = array_merge($this->packages, $composerLock->{'packages-dev'});
        }
    }

    public function getVersion($name) {
        foreach($this->packages as $package) {
            if($package->name == $name){
                return $package->version;
            }
        }
        return null;
    }
}