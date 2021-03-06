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

use CampaignChain\CoreBundle\Util\CommandUtil;
use CampaignChain\CoreBundle\Util\VariableUtil;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\Filesystem\Filesystem;
use CampaignChain\CoreBundle\Wizard\Install\Driver\YamlConfig;

class Kernel
{
    private $logger;

    private $command;

    private $kernelConfig;

    public function __construct($kernelRootDir, CommandUtil $command, Logger $logger)
    {
        $this->appDir = $kernelRootDir;
        $this->command = $command;
        $this->logger = $logger;
    }

    public function register(
        KernelConfig $kernelConfig,
        array $types = array(
            'classes' => true,
            'configs' => true,
            'routings' => true,
            'security' => true,
        )
    )
    {
        $this->kernelConfig = $kernelConfig;

        if(isset($types['classes']) && $types['classes']){
            $this->registerClasses();
        }
        if(isset($types['configs']) && $types['configs']){
            $this->registerConfigs();
        }
        if(isset($types['routings']) && $types['routings']){
            $this->registerRoutings();
        }
        if(isset($types['security']) && $types['security']){
            $this->registerSecurity();
        }
    }

    protected function registerClasses()
    {
        $campaignchainBundlesFile = $this->appDir.DIRECTORY_SEPARATOR.'campaignchain_bundles.php';
        $symfonyBundlesFile = $this->appDir.DIRECTORY_SEPARATOR.'AppKernel.php';
        $campaignchainBundlesContent = file_get_contents($campaignchainBundlesFile);
        $symfonyBundlesContent = file_get_contents($symfonyBundlesFile);

        $hasNewBundles = false;

        $classes = $this->kernelConfig->getClasses();

        if(!count($classes)){
            return false;
        }

        foreach ($classes as $class) {
            // Check if the bundle is already registered in the kernel.
            if(
                strpos($campaignchainBundlesContent, $class) === false &&
                strpos($symfonyBundlesContent, $class) === false
            ){
                $hasNewBundles = true;

                // Add the bundle class path to the CampaignChain bundles registry file.
                $campaignchainContentBundle = "\$bundles[] = new ".$class."();";

                $campaignchainBundlesContent .= "\xA".$campaignchainContentBundle;
            }
        }

        if($hasNewBundles){
            $fs = new Filesystem();
            $fs->dumpFile($campaignchainBundlesFile, $campaignchainBundlesContent);
        }
    }

    protected function registerConfigs()
    {
        $configFile = DIRECTORY_SEPARATOR . 'config' .
            DIRECTORY_SEPARATOR . 'campaignchain' .
            DIRECTORY_SEPARATOR . 'config_bundles.yml';

        $yamlConfig = new YamlConfig($this->appDir, $configFile);
        $parameters = $yamlConfig->read();

        $hasNewConfigs = false;

        $configs = $this->kernelConfig->getConfigs();

        if(!count($configs)){
            return false;
        }

        foreach ($configs as $config) {
            // Check if the config is already being imported.
            if($this->recursiveArraySearch($config, $parameters['imports'])
                === false){
                $hasNewConfigs = true;

                // Add the config to the imports
                $parameters['imports'][]['resource'] = $config;
            }
        }

        if($hasNewConfigs){
            $yamlConfig = new YamlConfig($this->appDir, $configFile);
            $yamlConfig->write($parameters);
            $yamlConfig->clean();
        }
    }

    protected function registerSecurity()
    {
        $appSecurityFile = DIRECTORY_SEPARATOR.'config'.
            DIRECTORY_SEPARATOR.'campaignchain'.
            DIRECTORY_SEPARATOR.'security.yml';

        /*
         * Re-create the security.yml file to avoid duplicates in merged array
         * that occur upon multiple parsing.
         */
        $fs = new Filesystem();
        if(!$fs->exists($appSecurityFile)){
            $fs->copy(
                $this->appDir.$appSecurityFile.'.dist',
                $this->appDir.$appSecurityFile,
                true
            );
        }

        $yamlConfig = new YamlConfig($this->appDir, $appSecurityFile);
        $appParameters = $yamlConfig->read();

        // Read content of all security.yml files and merge the arrays.
        $securityFiles = $this->kernelConfig->getSecurities();
        if(count($securityFiles)) {
            foreach ($securityFiles as $securityFile) {
                $yamlConfig = new YamlConfig('', $securityFile);
                $bundleParameters = $yamlConfig->read();
                $appParameters = VariableUtil::arrayMerge($appParameters, $bundleParameters);
            }
            
            $yamlConfig = new YamlConfig($this->appDir, $appSecurityFile);
            $yamlConfig->write($appParameters, 5);
            $yamlConfig->clean();
        }
    }

    protected function registerRoutings()
    {
        $campaignchainRoutingsFile = DIRECTORY_SEPARATOR.'config'.
            DIRECTORY_SEPARATOR.'routing.yml';

        $yamlConfig = new YamlConfig($this->appDir, $campaignchainRoutingsFile);
        $parameters = $yamlConfig->read();

        $hasNewRoutings = false;

        $routings = $this->kernelConfig->getRoutings();

        if(!count($routings)){
            return false;
        }

        foreach ($routings as $routing) {
            // Check if the routing is already defined.
            if(!isset($parameters[$routing['name']])){
                $hasNewRoutings = true;

                // Add the config to the imports
                $parameters[$routing['name']] = array(
                    'resource' => $routing['resource'],
                    'prefix' => $routing['prefix'],
                );
            }
        }

        if($hasNewRoutings){
            $yamlConfig = new YamlConfig($this->appDir, $campaignchainRoutingsFile);
            $yamlConfig->write($parameters);
            $yamlConfig->clean();
        }
    }

    public function recursiveArraySearch($needle, $haystack) {
        if(is_array($haystack) && count($haystack)) {
            foreach ($haystack as $key => $value) {
                $currentKey = $key;
                if ($needle === $value OR (is_array($value) && $this->recursiveArraySearch($needle, $value) !== false)) {
                    return $currentKey;
                }
            }
        }
        return false;
    }
}