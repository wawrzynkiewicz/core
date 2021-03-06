<?php
/*
 * This file is part of the CampaignChain package.
 *
 * (c) CampaignChain, Inc. <info@campaignchain.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CampaignChain\CoreBundle\Util;

use CampaignChain\CoreBundle\Entity\User;
use Composer\Command\RequireCommand;
use FOS\UserBundle\Doctrine\UserManager;
use Symfony\Bundle\FrameworkBundle\Command\CacheClearCommand;
use Symfony\Bundle\FrameworkBundle\Command\CacheWarmupCommand;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application,
    Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Doctrine\Bundle\DoctrineBundle\Command\Proxy\UpdateSchemaDoctrineCommand;
use Symfony\Bundle\FrameworkBundle\Command\AssetsInstallCommand;
use Symfony\Bundle\AsseticBundle\Command\DumpCommand;
use FOS\UserBundle\Command\CreateUserCommand;

class CommandUtil
{
    private $kernel;
    private $application;
    private $currentDir;

    public function __construct(Kernel $kernel)
    {
        $this->kernel = $kernel;
        $this->application = new Application($kernel);
        $this->currentDir = getcwd();
    }

    protected function chRootDir()
    {
        $rootDir = $this->kernel->getRootDir().DIRECTORY_SEPARATOR.'..';
        chdir($rootDir);
    }

    protected function chCurrentDir()
    {
        chdir($this->currentDir);
    }

    protected function run($command, $arguments)
    {
        $input = new ArrayInput($arguments);
        $output = new BufferedOutput();

        $this->chRootDir();
        $command->run($input, $output);
        $this->chCurrentDir();

        return $output->fetch();
    }

    public function shell($command)
    {
        $this->chRootDir();
        ob_start();
        system($command, $output);
        $this->chCurrentDir();

        return ob_get_clean();
    }

    public function doctrineSchemaUpdate()
    {
//        $this->application->add(new UpdateSchemaDoctrineCommand());
//        $command = $this->application->find('doctrine:schema:update');
//
//        $arguments = array(
//            'doctrine:schema:update',
//            '--force' => true,
//        );
//
//        return $this->run($command, $arguments);
        /*
         * Temporary hack, because above code does not reliably update
         * the database scheme.
         *
         * TODO: Fix this.
         */
        $command = 'php app/console doctrine:schema:update --force';

        return $this->shell($command);
    }

    public function assetsInstallWeb()
    {
        $command = 'php app/console assets:install web';

        return $this->shell($command);

        /*$this->application->add(new AssetsInstallCommand());

        // app/console assets:install web
        $command = $this->application->find('assets:install');
        $arguments = array(
            'assets:install',
            'target' => $this->kernel->getRootDir() . '/../web',
        );
        return $this->run($command, $arguments);*/
    }

    public function asseticDump()
    {
        $command = 'php app/console assetic:dump --env=prod --no-debug';

        return $this->shell($command);

        /*$this->application->add(new DumpCommand());
        $command = $this->application->find('assetic:dump');
        $arguments = array(
            'assets:install',
            '--env' => 'prod',
            '--no-debug' => true,
        );
        return $this->run($command, $arguments);*/
    }

    public function createAdminUser(array $parameters)
    {
        /** @var UserManager $userManager */
        $userManager = $this->kernel->getContainer()->get('fos_user.user_manager');
        /** @var User $user */
        $user = $userManager->createUser();
        $user->setUsername($parameters['username']);
        $user->setEmail($parameters['email']);
        $user->setPlainPassword($parameters['password']);
        $user->setEnabled(true);
        $user->setSuperAdmin(true);
        $user->setFirstName($parameters['firstName']);
        $user->setLastName($parameters['lastName']);

        $userManager->updateUser($user);
    }

    public function composerRequire($name, $version)
    {
        $this->application->add(new RequireCommand());
        $command = $this->application->find('require');
        $arguments = array(
            'require',
            'packages' => $name.':'.$version,
            '-n' => true,
        );
        return $this->run($command, $arguments);
    }

    public function clearCache($warmup = true)
    {
        $command = 'php app/console cache:clear --env=prod --no-debug';

        return $this->shell($command);

        /*$this->application->add(new CacheClearCommand());
        $command = $this->application->find('cache:clear');
        $arguments = array(
            'cache:clear',
            '--no-debug' => true,
            '--env' => 'prod',
        );

        if($warmup == false){
            $arguments['--no-warmup'] = true;
        }

        return $this->run($command, $arguments);*/
    }

    public function warmupCache()
    {
        $this->application->add(new CacheWarmupCommand());
        $command = $this->application->find('cache:warmup');
        $arguments = array(
            'cache:warmup',
        );
        return $this->run($command, $arguments);
    }

    public function bowerInstall()
    {
        $command = 'php app/console sp:bower:install --interactive=false';

        return $this->shell($command);
    }
}