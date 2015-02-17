<?php
/**
 * Splot Framework class.
 * 
 * Bootstraps and runs everything.
 * 
 * Singleton.
 * 
 * @package SplotFramework
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework;

use MD\Foundation\Debug\Timer;
use MD\Foundation\Debug\Debugger;
use MD\Foundation\Exceptions\InvalidArgumentException;
use MD\Foundation\Utils\StringUtils;

use Splot\Framework\Application\AbstractApplication;
use Splot\Framework\Application\CommandApplication;

use Splot\Framework\Config\Config;

use Splot\Framework\Composer\AbstractScriptHandler;

use Splot\Framework\DependencyInjection\ServiceContainer;

use Splot\Framework\HTTP\Request;

use Splot\Framework\Log\LoggerProviderInterface;

use Symfony\Component\Console\Input\ArgvInput;

class Framework
{

    const MODE_WEB = 'web';
    const MODE_CONSOLE = 'console';
    const MODE_COMMAND = 'command';
    const MODE_TEST = 'test';

    const PHASE_INIT = -1;
    const PHASE_BOOTSTRAP = 0;
    const PHASE_CONFIGURE = 1;
    const PHASE_RUN = 2;

    /**
     * Application run by the framework.
     * 
     * @var AbstractApplication
     */
    protected $application;

    /**
     * Environment name in which the framework is ran.
     * 
     * @var string
     */
    protected $env;

    /**
     * Is debug mode on?
     * 
     * @var boolean
     */
    protected $debug;

    /**
     * Mode in which the framework is ran.
     *
     * One of the Framework::MODE_* constants.
     * 
     * @var string
     */
    protected $mode;

    /**
     * Benchmark timer.
     * 
     * @var Timer
     */
    protected $timer;

    /**
     * Runs the framework. Main entry point.
     * 
     * @param  AbstractApplication $application Application to be run in the framework.
     * @param  string              $env         [optional] Name of the environment in 
     *                                          which it should run. Default: 'dev'.
     * @param  boolean             $debug       [optional] Should debug be on? Default: true.
     * @param  string              $mode        [optional] Mode in which in should run.
     *                                          One of the Framework::MODE_* constants. Default: Framework::MODE_WEB.
     * @return Framework
     */
    public static function run(AbstractApplication $application, $env = 'dev', $debug = true, $mode = self::MODE_WEB) {
        return new static($application, $env, $debug, $mode);
    }

    /**
     * Constructor.
     *
     * Responsible for the application flow - bootstrap, config and run phases.
     * 
     * @param  AbstractApplication $application Application to be run in the framework.
     * @param  string              $env         [optional] Name of the environment in 
     *                                          which it should run. Default: 'dev'.
     * @param  boolean             $debug       [optional] Should debug be on? Default: true.
     * @param  string              $mode        [optional] Mode in which in should run.
     *                                          One of the Framework::MODE_* constants. Default: Framework::MODE_WEB.
     * @return Framework
     */
    protected function __construct(AbstractApplication $application, $env = 'dev', $debug = true, $mode = self::MODE_WEB) {
        $this->timer = new Timer();

        /*****************************************************
         * VERIFY APPLICATION
         *****************************************************/
        $applicationName = $application->getName();
        if (!is_string($applicationName) || empty($applicationName)) {
            throw new \RuntimeException('You have to specify an application name inside the Application class by defining protected property "$name".');
        }

        if (!StringUtils::isClassName($applicationName)) {
            throw new \RuntimeException('Application name must conform to variable naming rules and therefore can only start with a letter and only contain letters, numbers and _, "'. $applicationName .'" given.');
        }

        // set everything we need
        $this->application = $application;
        $this->env = $env;
        $this->debug = $debug;
        $this->mode = $mode;

        /*****************************************************
         * INITIALIZE DEPENDENCY INJECTION CONTAINER
         *****************************************************/
        $this->initContainer($application);

        /*****************************************************
         * BOOTSTRAP PHASE
         *****************************************************/
        $this->bootstrapApplication($application);

        /*****************************************************
         * CONFIG PHASE
         *****************************************************/
        $this->configureApplication($application);

        /*****************************************************
         * RUN PHASE
         *****************************************************/
        $this->runApplication($application);
    }

    /**
     * Initialize the dependency injection container.
     * 
     * @param  AbstractApplication $application Application for which to initialize the container.
     * @return ServiceContainer
     */
    protected function initContainer(AbstractApplication $application) {
        $container = new ServiceContainer();
        // set two services already
        $container->set('application', $application);
        $container->set('splot.timer', $this->timer);

        // load framework parameters and services definition from YML file
        $container->loadFromFile(__DIR__ .'/framework.yml');

        // set parameters to be what the framework has been initialized with
        $container->setParameter('application_dir', dirname(Debugger::getClassFile($application)) . DS);
        $container->setParameter('env', $this->env);
        $container->setParameter('debug', $this->debug);
        $container->setParameter('mode', $this->mode);

        $application->setContainer($container);

        return $container;
    }

    /**
     * Bootstrap the application.
     * 
     * @param  AbstractApplication $application Application to be bootstrapped.
     * @return bool
     */
    protected function bootstrapApplication(AbstractApplication $application) {
        $application->setPhase(self::PHASE_BOOTSTRAP);

        $container = $application->getContainer();
        $application->bootstrap();

        /*****************************************************
         * LOAD MODULES
         *****************************************************/
        $loadingModules = array();
        $moduleLoader = function(array $loadedModules, $self, array $allModules = array()) use (&$loadingModules) {
            foreach($loadedModules as $module) {
                if (in_array($module->getName(), $loadingModules)) {
                    continue;
                }

                $loadingModules[] = $module->getName();

                $allModules = $self($module->loadModules(), $self, $allModules);
                $allModules[] = $module;
            }
            return $allModules;
        };
        $modules = $moduleLoader($application->loadModules(), $moduleLoader);

        foreach($modules as $module) {
            $application->addModule($module);
        }

        // only register memory writer for web requests, otherwise it could easily fill up all memory
        // (especially for long lasting processes, e.g. workers)
        if ($this->mode === self::MODE_WEB) {
            $container->register('clog.writer.memory', array(
                'class' => 'MD\Clog\Writers\MemoryLogger',
                'notify' => array(
                    array('clog', 'addWriter', array('@'))
                )
            ));
        }

        return true;
    }

    /**
     * Configure the application.
     * 
     * @param  AbstractApplication $application Application to be configured.
     * @return bool
     */
    protected function configureApplication(AbstractApplication $application) {
        $application->setPhase(self::PHASE_CONFIGURE);

        $container = $application->getContainer();

        // default framework config first (to make sure all required settings are there)
        $config = new Config($container, __DIR__ .'/config.yml');
        $container->set('config', $config);

        $env = $container->getParameter('env');

        // read application config
        $config->extend(Config::readFromDir(
            $container,
            $container->getParameter('config_dir'),
            $env
        ));

        // and configure the application
        $application->configure();

        // configure modules
        foreach($application->getModules() as $module) {
            $moduleConfig = Config::readFromDir(
                $container,
                $module->getConfigDir(),
                $env
            );
            $moduleConfig->apply($config->getNamespace($module->getName()));
            $module->setConfig($moduleConfig);

            $module->configure();
        }

        /*****************************************************
         * VERIFY CONFIGURATION
         *****************************************************/
        // verify the logger provider
        $loggerProvider = $container->get('logger_provider');
        if (!$loggerProvider instanceof LoggerProviderInterface) {
            throw new \RuntimeException('Splot Framework requires the service "logger_provider" to implement Splot\Framework\Log\LoggerProviderInterface.');
        }

        // set the framework logger
        $this->logger = $container->get('logger.splot');

        $this->logger->debug('Application "{application}" has been successfully configured in mode "{mode}" and env "{env}" with {modulesCount} modules with debug "{debug}".', array(
            'application' => $application->getName(),
            'mode' => $container->getParameter('mode'),
            'env' => $container->getParameter('env'),
            'debug' => $container->getParameter('debug') ? 'on' : 'off',
            'modulesCount' => count($application->getModules()),
            'modules' => $application->listModules(),
            '_time' =>  $this->timer->step('Configuration')
        ));

        return true;
    }

    /**
     * Run the application.
     * 
     * @param  AbstractApplication $application Application to be run.
     * @return bool
     */
    protected function runApplication(AbstractApplication $application) {
        $container = $application->getContainer();
        
        // @codeCoverageIgnoreStart
        // configure some stuff only for web requests
        if ($this->mode === self::MODE_WEB) {
            // use Whoops to display errors
            // add default Whoops error handlers
            $whoops = $container->get('whoops');
            $whoops->pushHandler($container->get('whoops.handler.log'));
            $whoops->pushHandler($container->get('whoops.handler.event'));

            $whoops->register();

            // and if in debug mode then also add pretty page handler
            if ($application->isDebug()) {
                $whoops->pushHandler($container->get('whoops.handler.pretty_page'));
            }
        }
        // @codeCoverageIgnoreEnd

        $application->setPhase(self::PHASE_RUN);

        $application->run();
        foreach($application->getModules() as $module) {
            $module->run();
        }

        // perform some actions depending on the run mode
        switch($this->mode) {
            case self::MODE_CONSOLE:
                set_time_limit(0);

                // in case we're gonna run Composer script, inject the application there
                AbstractScriptHandler::setApplication($application);

                $console = $application->getContainer()->get('console');
                $console->run();
            break;

            case self::MODE_COMMAND:
                set_time_limit(0);

                if (!$application instanceof CommandApplication) {
                    throw new InvalidArgumentException('instance of Splot\Framework\Application\CommandApplication', $application);
                }

                $console = $application->getContainer()->get('console');
                $console->addCommand('app', $application->getCommand());

                $argv = new ArgvInput();
                $console->call('app', (string)$argv);
            break;
            
            case self::MODE_TEST:
            break;

            case self::MODE_WEB:
            default:
                if (!$application->isDebug()) {
                    // @todo register a http response code error handler (is it needed?)
                }

                $request = Request::createFromGlobals();
                $response = $application->handleRequest($request);
                $application->sendResponse($response, $request);
        }

        return true;
    }

}
