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

use Whoops\Run as WhoopsRun;
use Whoops\Handler\PrettyPageHandler as WhoopsPrettyPageHandler;

use MD\Foundation\Debug\Timer;
use MD\Foundation\Debug\Debugger;
use MD\Foundation\Exceptions\InvalidArgumentException;
use MD\Foundation\Utils\StringUtils;

use Splot\Framework\Application\AbstractApplication;
use Splot\Framework\Application\CommandApplication;

use Splot\Framework\Config\Config;

use Splot\Framework\Composer\AbstractScriptHandler;

use Splot\Framework\DependencyInjection\ServiceContainer;

use Splot\Framework\ErrorHandlers\EventErrorHandler;
use Splot\Framework\ErrorHandlers\LogErrorHandler;
use Splot\Framework\ErrorHandlers\NullErrorHandler;

use Splot\Framework\Events\ErrorDidOccur;
use Splot\Framework\Events\FatalErrorDidOccur;
use Splot\Framework\HTTP\Exceptions\HTTPExceptionInterface;
use Splot\Framework\HTTP\Request;
use Splot\Framework\HTTP\Response;

use Splot\Framework\Log\LoggerProviderInterface;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Input\ArgvInput;

class Framework
{

    const MODE_WEB = 'web';
    const MODE_CONSOLE = 'console';
    const MODE_COMMAND = 'command';
    const MODE_TEST = 'test';

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
        ini_set('default_charset', 'utf8');

        /*****************************************************
         * BOOTSTRAP PHASE
         *****************************************************/
        $framework = new static($application, $env, $debug, $mode);
        $container = $application->getContainer();
        $debug = $container->getParameter('debug');

        // add default Whoops error handlers
        $whoops = $container->get('whoops');
        $whoops->pushHandler($container->get('whoops.handler.log'));
        $whoops->pushHandler($container->get('whoops.handler.event'));
        
        // @codeCoverageIgnoreStart
        // don't register Whoops when testing
        if ($mode !== self::MODE_TEST) {
            $whoops->register();
        }

        // if in debug mode and web mode then also add pretty page handler
        if ($debug && $mode === self::MODE_WEB) {
            $whoops->pushHandler($container->get('whoops.handler.pretty_page'));
        }
        // @codeCoverageIgnoreEnd

        $logger = $container->get('splot.logger');
        $timer = $container->get('splot.timer');

        // now load all modules
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

            $logger->debug('Added module {name} ({class}) to the application.', array(
                'name' => $module->getName(),
                'class' => $module->getClass(),
                '_time' => $timer->step('Module "'. $module->getName() .'" loaded')
            ));
        }

        // mark the bootstrap phase as finished
        $application->finishBootstrap();

        /*****************************************************
         * CONFIG PHASE
         *****************************************************/
        // default framework config first (to make sure all required settings are there)
        $config = new Config(include dirname(__FILE__) . DS .'Config'. DS .'default.php');
        $container->set('config', $config);

        // read application config
        $config->extend(Config::read(
            $container->getParameter('config_dir'),
            $container->getParameter('env'),
            $container->getParameters()
        ));

        // read modules' configs
        foreach($application->getModules() as $module) {
            $moduleConfig = Config::read(
                $module->getConfigDir(),
                $container->getParameter('env'),
                $container->getParameters()
            );
            $moduleConfig->apply($config->getNamespace($module->getName()));
            $module->setConfig($moduleConfig);
        }

        // set the timezone based on config
        date_default_timezone_set($config->get('timezone'));

        // run application's and modules configure hooks
        $application->configure();
        foreach($application->getModules() as $module) {
            $module->configure();
        }

        /*****************************************************
         * RUN PHASE
         *****************************************************/
        $application->run();
        foreach($application->getModules() as $module) {
            $module->run();
        }

        // perform some actions depending on the run mode
        switch($mode) {
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
                // add nice error handler to whoops if in debug mode
                if (!$debug) {
                    // @todo register a http response code error handler (is it needed?)
                }

                $request = Request::createFromGlobals();
                $response = $application->handleRequest($request);
                $application->sendResponse($response, $request);
        }

        return $framework;
    }

    /**
     * Constructor.
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
        $this->application = $application;
        $this->env = $env;
        $this->debug = $debug;
        $this->mode = $mode;

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

        // get application's class name for some debugging and logging
        $applicationClass = Debugger::getClass($application);

        /*****************************************************
         * INITIALIZE DEPENDENCY INJECTION CONTAINER
         *****************************************************/
        $container = new ServiceContainer();
        $container->set('container', $container);
        $container->set('application', $application);
        $container->set('splot.timer', $this->timer);
        $container->setParameter('env', $env);
        $container->setParameter('debug', $debug);
        $container->setParameter('mode', $mode);

        $container->set('whoops', function() {
            return new WhoopsRun();
        });

        $container->set('whoops.handler.null', function() {
            return new NullErrorHandler();
        });

        $container->set('whoops.handler.pretty_page', function() {
            return new WhoopsPrettyPageHandler();
        });

        $container->set('whoops.handler.log', function($c) {
            if (!$c->has('logger')) {
                return $c->get('whoops.handler.null');
            }
            return new LogErrorHandler($c->get('logger'));
        });

        $container->set('whoops.handler.event', function($c) {
            if (!$c->has('event_manager')) {
                return $c->get('whoops.handler.null');
            }
            return new EventErrorHandler($c->get('event_manager'));
        });

        /*****************************************************
         * BOOTSTRAP THE APPLICATION
         *****************************************************/
        $application->setContainer($container);
        $application->bootstrap();

        // check if all required parameters have been registered
        foreach(array(
            'application_dir',
            'config_dir',
            'cache_dir',
            'root_dir',
            'web_dir'
        ) as $parameterName) {
            if (!$container->hasParameter($parameterName)) {
                throw new \RuntimeException('Splot Framework requires a parameter "'. $parameterName .'" to be registered on application\'s bootstrap.');
            }
        }

        // check if all required services have been registered
        foreach(array(
            'logger_provider',
            'logger',
            'event_manager',
            'router',
            'resource_finder',
            'process',
            'console'
        ) as $serviceName) {
            if (!$container->has($serviceName)) {
                throw new \RuntimeException('Splot Framework requires a service "'. $serviceName .'" to be registered on application\'s bootstrap.');
            }
        }

        $loggerProvider = $container->get('logger_provider');
        if (!$loggerProvider instanceof LoggerProviderInterface) {
            throw new \RuntimeException('Splot Framework requires the service "logger_provider" to implement Splot\Framework\Log\LoggerProviderInterface.');
        }

        $this->logger = $loggerProvider->provide('Splot');
        $container->set('splot.logger', $this->logger);

        $this->logger->debug('Splot Framework successfully bootstrapped with application "{application}".', array(
            'application' => $application->getName(),
            'parameters' => $container->getParameters(),
            '_time' =>  $this->timer->step('Bootstrap')
        ));
    }

}