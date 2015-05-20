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
use MD\Foundation\Utils\FilesystemUtils;
use MD\Foundation\Utils\StringUtils;

use Splot\DependencyInjection\Exceptions\CacheDataNotFoundException;
use Splot\DependencyInjection\CachedContainer;
use Splot\DependencyInjection\ContainerInterface;

use Splot\Framework\Application\AbstractApplication;
use Splot\Framework\Application\CommandApplication;

use Splot\Framework\Config\Config;

use Splot\Framework\Composer\AbstractScriptHandler;

use Splot\Framework\HTTP\Request;

use Splot\Framework\Log\LoggerProviderInterface;

use Symfony\Component\Console\Input\ArgvInput;

class Framework
{

    const MODE_WEB = 'web';
    const MODE_CONSOLE = 'console';
    const MODE_COMMAND = 'command';
    const MODE_TEST = 'test';

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
     * Runs the framework with the given application.
     *
     * Main entry point.
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

        /*****************************************************
         * BOOTSTRAP PHASE
         *****************************************************/
        // set everything we need
        $this->application = $application;
        $this->env = $env;
        $this->debug = $debug;
        $this->mode = $mode;

        // LOAD MODULES
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

        /*****************************************************
         * CONFIGURATION PHASE
         *****************************************************/
        $configTimer = new Timer();
        $application->setPhase(self::PHASE_CONFIGURE);
        $this->configureApplication($application);

        /*****************************************************
         * RUN PHASE
         *****************************************************/
        $application->setPhase(self::PHASE_RUN);
        $this->runApplication($application);
    }

    /**
     * Configure the application.
     *
     * Creates a dependency injection container and passes it for configuration to the application.
     * 
     * @param  AbstractApplication $application Application to be configured.
     * @return ContainerInterface
     */
    protected function configureApplication(AbstractApplication $application) {
        $timer = new Timer();

        $container = new CachedContainer($application->provideContainerCache($this->env, $this->debug));

        // inject the container to the app and all modules
        $application->setContainer($container);
        foreach($application->getModules() as $module) {
            $module->setContainer($container);
        }

        // just try to read the configuration from cache, but if it fails, configure and cache the container
        try {
            $container->loadFromCache();

            // @codeCoverageIgnoreStart
            // for web mode also make sure that Whoops is handling errors
            if ($this->mode === self::MODE_WEB) {
                $container->get('whoops')->register();
            }
            // @codeCoverageIgnoreEnd
        } catch(CacheDataNotFoundException $e) {
            // load framework parameters and services definition from YML file
            $container->loadFromFile(__DIR__ .'/framework.yml');

            // set parameters to be what the framework has been initialized with
            $container->setParameter('framework_dir', __DIR__);
            $container->setParameter('application_dir', dirname(Debugger::getClassFile($application)));
            $container->setParameter('env', $this->env);
            $container->setParameter('debug', $this->debug);

            // @codeCoverageIgnoreStart
            // configure Whoops error handler only for web mode
            if ($this->mode === self::MODE_WEB) {
                // if in debug mode also use the pretty page handler
                if ($this->debug) {
                    $whoopsDefinition = $container->getDefinition('whoops');
                    $whoopsDefinition->addMethodCall('pushHandler', array('@whoops.handler.pretty_page'));
                }

                // already register Whoops to handle errors, so it also works during config
                $container->get('whoops')->register();
            }
            // @codeCoverageIgnoreEnd

            // maybe application wants to provide some high-priority parameters as well?
            $container->loadFromArray(array(
                'parameters' => $application->loadParameters($this->env, $this->debug)
            ));

            // we're gonna stick to the config dir defined at this point
            $configDir = rtrim($container->getParameter('config_dir'), DS) . DS;

            // prepare the config, but make it reusable in the cached container as well
            // so we're gonna modify the definition to include existing files when needed
            $configDefinition = $container->getDefinition('config');
            $configFiles = FilesystemUtils::glob($configDir .'config{,.'. $this->env .'}.{yml,yaml,php}', GLOB_BRACE);
            foreach($configFiles as $file) {
                $configDefinition->addMethodCall('loadFromFile', array($file));
            }

            // now that we have the config built, let's get it
            $config = $container->get('config');

            // pass some necessary parameters from the config
            $container->setParameter('log_file', $config->get('log_file'));
            $container->setParameter('log_level', $config->get('log_level'));

            // configure modules one by one
            foreach($application->getModules() as $module) {
                // define config for the module
                $container->register('config.'. $module->getName(), array(
                    'extends' => 'config_module.abstract'
                ));

                // add method calls to load appropriate config files to the definition
                $moduleConfigDefinition = $container->getDefinition('config.'. $module->getName());
                $moduleConfigFiles = FilesystemUtils::glob($module->getConfigDir() .'config{,.'. $this->env .'}.{yml,yaml,php}', GLOB_BRACE);
                foreach($moduleConfigFiles as $file) {
                    $moduleConfigDefinition->addMethodCall('loadFromFile', array($file));
                }

                // add method call to apply the config from the application config
                $moduleConfigDefinition->addMethodCall('apply', array($config->getNamespace($module->getName())));

                // run configuration for each module
                $module->configure();
            }

            // configure the application
            $application->configure();
            
            // in the end, write the current state to cache
            $container->cacheCurrentState();
        }

        // and after reading from cache / writing to cache, just set some additional services
        // that can't be cached
        $container->set('application', $application);
        $container->set('splot.timer', $this->timer);

        // log benchmark data
        $time = $timer->stop(3);
        $memory = $timer->getMemoryUsage();
        $memoryString = StringUtils::bytesToString($memory);
        $container->get('splot.logger')->debug('Application configuration phase in mode {mode} and env {env} with {modulesCount} modules and debug {debug} finished in {time} ms and used {memory} memory.', array(
            'application' => $application->getName(),
            'mode' => $this->mode,
            'env' => $this->env,
            'debug' => $this->debug ? 'on' : 'off',
            'modulesCount' => count($application->getModules()),
            'modules' => $application->listModules(),
            'time' => $time,
            'memory' => $memoryString,
            '@stat' => 'splot.configure',
            '@time' => $time,
            '@memory' => $memory
        ));

        return $container;
    }

    /**
     * Run the application.
     * 
     * @param  AbstractApplication $application Application to be run.
     */
    protected function runApplication(AbstractApplication $application) {
        $timer = new Timer();
        $benchmarkLogInfo = array();

        $container = $application->getContainer();

        // set the application's logger
        $application->setLogger($container->get('logger'));

        foreach($application->getModules() as $module) {
            $module->run();
        }
        $application->run();

        // perform some actions depending on the run mode
        switch($this->mode) {
            case self::MODE_CONSOLE:
                set_time_limit(0);

                // in case we're gonna run Composer script, inject the application there
                AbstractScriptHandler::setApplication($application);

                $console = $container->get('console');
                $console->run();

                $benchmarkLogInfo['@stat'] = 'splot.run.console';
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

                $benchmarkLogInfo['@stat'] = 'splot.run.command';
            break;
            
            case self::MODE_TEST:
                $benchmarkLogInfo['@stat'] = 'splot.run.test';
            break;

            case self::MODE_WEB:
            default:
                if (!$application->isDebug()) {
                    // @todo register a http response code error handler (is it needed?)
                }

                $request = Request::createFromGlobals();
                $response = $application->handleRequest($request);
                $application->sendResponse($response, $request);

                $benchmarkLogInfo['@stat'] = 'splot.run.web';
        }

        // log benchmark data
        $time = $timer->stop();
        $memory = $timer->getMemoryUsage();
        $modeNames = array(
            self::MODE_COMMAND => 'command',
            self::MODE_CONSOLE => 'console',
            self::MODE_TEST => 'test',
            self::MODE_WEB => 'web'
        );
        $container->get('splot.logger')->debug('Application run phase in mode "{mode}" finished in {time} ms and used {memory} memory.', array_merge(array(
            'mode' => $modeNames[$this->mode],
            'time' => $time,
            'memory' => StringUtils::bytesToString($memory),
            '@time' => $time,
            '@memory' => $memory
        ), $benchmarkLogInfo));
    }

}
