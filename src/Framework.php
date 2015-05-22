<?php
/**
 * Splot Framework class.
 *
 * `Framework` is mostly responsible for configuring and running an application,
 * so it's best considered to be an "application runner".
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

use Symfony\Component\Console\Input\ArgvInput;

use Splot\DependencyInjection\Exceptions\CacheDataNotFoundException;
use Splot\DependencyInjection\CachedContainer;
use Splot\DependencyInjection\ContainerInterface;

use Splot\Framework\Application\AbstractApplication;
use Splot\Framework\Application\CommandApplication;
use Splot\Framework\Config\Config;
use Splot\Framework\Composer\AbstractScriptHandler;
use Splot\Framework\HTTP\Request;
use Splot\Framework\Log\LoggerProviderInterface;

class Framework
{

    const MODE_INDETERMINATE = -1;
    const MODE_WEB = 0;
    const MODE_CONSOLE = 1;
    const MODE_COMMAND = 2;
    const MODE_TEST = 3;

    const PHASE_BOOTSTRAP = 0;
    const PHASE_CONFIGURE = 1;
    const PHASE_RUN = 2;

    /*****************************************************
     * CONVENIENCE METHODS
     *****************************************************/

    /**
     * Single entry point for most of use cases in which the passed application will be bootstrapped,
     * configured and run in appropriate mode.
     *
     * Most of applications should use this method as it controls the whole application flow from start to the end
     * of the process in a most standard way.
     *
     * It returns whatever result of running the application.
     * 
     * @param  AbstractApplication $application Application to be ran.
     * @param  string              $env         [optional] Environment in which the application should be ran. Default: `dev`.
     * @param  boolean             $debug       [optional] Should application be ran in debug mode? Default: `true`.
     * @param  int                 $mode        [optional] Mode in which the application should be ran.
     *                                          This is one of the `Framework::MODE_*` constants. Default: `Framework::MODE_WEB`.
     * @return mixed
     */
    public static function run(AbstractApplication $application, $env = 'dev', $debug = true, $mode = self::MODE_WEB) {
        $framework = new static();

        $framework->warmup($application, $env, $debug);

        switch($mode) {
            case self::MODE_CONSOLE:
                $result = $framework->runConsole($application);
            break;

            case self::MODE_COMMAND:
                $input = new ArgvInput();
                $result = $framework->runCommand($application, $input);
            break;

            case self::MODE_TEST:
                $result = $framework->runTest($application);
            break;

            case self::MODE_WEB:
            default:
                $request = Request::createFromGlobals();
                $result = $framework->runWebRequest($application, $request);
        }

        return $result;
    }

    /**
     * Warms up the given application by bootstrapping and configuring it and its'
     * dependency injection container.
     *
     * Returns the application's DI container for convenience.
     * 
     * @param  AbstractApplication $application Application to be ran.
     * @param  string              $env         [optional] Environment in which the application should be ran. Default: `dev`.
     * @param  boolean             $debug       [optional] Should application be ran in debug mode? Default: `true`.
     * @return ContainerInterface
     */
    public function warmup(AbstractApplication $application, $env = 'dev', $debug = true) {
        $timer = new Timer();

        // verify the application
        $applicationName = $application->getName();
        if (!is_string($applicationName) || empty($applicationName)) {
            throw new \RuntimeException('You have to specify an application name inside the Application class by defining protected property "$name".');
        }

        if (!StringUtils::isClassName($applicationName)) {
            throw new \RuntimeException('Application name must conform to variable naming rules and therefore can only start with a letter and only contain letters, numbers and _, "'. $applicationName .'" given.');
        }

        $this->bootstrapApplication($application);

        $container = $this->configureApplication($application, $env, $debug);
        $container->set('splot.timer', $timer);

        return $container;
    }

    /*****************************************************
     * BOOTSTRAP PHASE
     *****************************************************/

    /**
     * Bootstraps the application by loading all its modules.
     * 
     * @param  AbstractApplication $application Application to be bootstrapped.
     */
    public function bootstrapApplication(AbstractApplication $application) {
        $application->setPhase(self::PHASE_BOOTSTRAP);

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
    }

    /*****************************************************
     * CONFIGURATION PHASE
     *****************************************************/

    /**
     * Configure the application.
     *
     * Creates a dependency injection container and passes it for configuration to the application.
     *
     * If `$debug` is `false` then it will try to load the configuration from cache and if not found - cache it.
     * If loading from cache succeeds, then the application configuration step is skipped.
     * 
     * @param  AbstractApplication $application Application to be ran.
     * @param  string              $env         [optional] Environment in which the application should be ran. Default: `dev`.
     * @param  boolean             $debug       [optional] Should application be ran in debug mode? Default: `true`.
     * @return ContainerInterface
     */
    public function configureApplication(AbstractApplication $application, $env = 'dev', $debug = true) {
        $application->setPhase(self::PHASE_CONFIGURE);

        $timer = new Timer();

        $container = new CachedContainer($application->provideContainerCache($env, $debug));

        // inject the container to the app and all modules
        $application->setContainer($container);
        foreach($application->getModules() as $module) {
            $module->setContainer($container);
        }

        // just try to read the configuration from cache, but if it fails, configure and cache the container
        // unless we're in the debug mode
        if ($debug) {
            $this->doConfigureApplication($application, $env, $debug);
        } else {
            try {
                $container->loadFromCache();
            } catch(CacheDataNotFoundException $e) {
                $this->doConfigureApplication($application, $env, $debug);
                $container->cacheCurrentState();
            }
        }

        // make sure that Whoops is handling errors
        $container->get('whoops')->register();

        // after reading from cache / writing to cache 
        // set some additional services that can't be cached
        $container->set('application', $application);

        // set the application's logger
        $application->setLogger($container->get('logger'));

        // log benchmark data
        $time = $timer->stop(3);
        $memory = $timer->getMemoryUsage();
        $memoryString = StringUtils::bytesToString($memory);
        $container->get('splot.logger')->debug('Application configuration phase in env {env} with {modulesCount} modules and debug {debug} finished in {time} ms and used {memory} memory.', array(
            'application' => $application->getName(),
            'env' => $env,
            'debug' => $debug ? 'on' : 'off',
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
     * Performs application and its dependency injection container configuration by loading appropriate files
     * into the config and the container from the application dir and all the modules.
     * 
     * @param  AbstractApplication $application Application to be ran.
     * @param  string              $env         [optional] Environment in which the application should be ran. Default: `dev`.
     * @param  boolean             $debug       [optional] Should application be ran in debug mode? Default: `true`.
     */
    protected function doConfigureApplication(AbstractApplication $application, $env = 'dev', $debug = true) {
        $container = $application->getContainer();

        // load framework parameters and services definition from YML file
        $container->loadFromFile(__DIR__ .'/framework.yml');

        // set parameters to be what the framework has been initialized with
        $container->setParameter('framework_dir', __DIR__);
        $container->setParameter('application_dir', dirname(Debugger::getClassFile($application)));
        $container->setParameter('env', $env);
        $container->setParameter('debug', $debug);

        // already register Whoops to handle errors, so it also works during config
        $container->get('whoops')->register();

        // maybe application wants to provide some high-priority parameters as well?
        $container->loadFromArray(array(
            'parameters' => $application->loadParameters($env, $debug)
        ));

        // we're gonna stick to the config dir defined at this point
        $configDir = rtrim($container->getParameter('config_dir'), DS) . DS;

        // prepare the config, but make it reusable in the cached container as well
        // so we're gonna modify the definition to include existing files when needed
        $configDefinition = $container->getDefinition('config');
        $configFiles = FilesystemUtils::glob($configDir .'config{,.'. $env .'}.{yml,yaml,php}', GLOB_BRACE);
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
            $this->doConfigureModule($module, $application);
        }

        // configure the application
        $application->configure();
    }

    /**
     * Configures a module in context of the given application.
     * 
     * @param  AbstractModule      $module      Module to be configured.
     * @param  AbstractApplication $application Application for which this module should be configured.
     * @param  string              $env         [optional] Environment in which the application is running. Default: `dev`.
     * @param  boolean             $debug       [optional] Debug mode status for the application. Default: `true`.
     */
    protected function doConfigureModule(AbstractModule $module, AbstractApplication $application, $env = 'dev', $debug = true) {
        $container = $application->getContainer();
        $config = $application->getConfig();

        // define config for the module
        $container->register('config.'. $module->getName(), array(
            'extends' => 'config_module.abstract'
        ));

        // add method calls to load appropriate config files to the definition
        $moduleConfigDefinition = $container->getDefinition('config.'. $module->getName());
        $moduleConfigFiles = FilesystemUtils::glob($module->getConfigDir() .'config{,.'. $env .'}.{yml,yaml,php}', GLOB_BRACE);
        foreach($moduleConfigFiles as $file) {
            $moduleConfigDefinition->addMethodCall('loadFromFile', array($file));
        }

        // add method call to apply the config from the application config
        $moduleConfigDefinition->addMethodCall('apply', array($config->getNamespace($module->getName())));

        // let it configure itself
        $module->configure();
    }

    /*****************************************************
     * RUN PHASE
     *****************************************************/

    /**
     * Runs the application in context of a web request.
     *
     * Returns the response rendered by the application.
     * 
     * @param  AbstractApplication $application Application to be ran.
     * @param  Request             $request     The HTTP Request.
     * @return Response
     */
    public function runWebRequest(AbstractApplication $application, Request $request) {
        if (!$application->isDebug()) {
            // @todo register a http response code error handler (is it needed?)
        }

        return $this->doRunApplication($application, self::MODE_WEB, function() use ($application, $request) {
            $response = $application->handleRequest($request);
            $application->sendResponse($response, $request);
            return $response;
        });
    }

    /**
     * Runs the application in context of a CLI console.
     *
     * Returns whatever value the command ran returns.
     * 
     * @param  AbstractApplication $application Application to be ran.
     * @return mixed
     */
    public function runConsole(AbstractApplication $application) {
        set_time_limit(0);

        return $this->doRunApplication($application, self::MODE_CONSOLE, function() use ($application) {
            // in case we're gonna run Composer script, inject the application there
            AbstractScriptHandler::setApplication($application);

            return $application->getContainer()->get('console')->run();
        });
    }

    /**
     * Runs the application as a command application - meaning that the application is suppose to be
     * only ran in context of a console and performs only one functionality.
     *
     * Returns whatever value the application returns.
     * 
     * @param  CommandApplication $application Command application to be ran.
     * @param  ArgvInput           $input      Console input gathered.
     * @return mixed
     */
    public function runCommand(CommandApplication $application, ArgvInput $input) {
        set_time_limit(0);

        return $this->doRunApplication($application, self::MODE_COMMAND, function() use ($application, $input) {
            $console = $application->getContainer()->get('console');
            $console->addCommand('app', $application->getCommand());

            return $console->call('app', (string)$input);
        });
    }

    /**
     * Runs the application in context of a test.
     * 
     * @param  AbstractApplication $application Application to be ran.
     * @return null
     */
    public function runTest(AbstractApplication $application) {
        return $this->doRunApplication($application, self::MODE_TEST, function() {
            return null;
        });
    }

    /**
     * Runs the application in context of the given mode and using the given runner function.
     *
     * The last argument is a callback that will be invoked by this method and it should contain
     * execution logic specific to the given mode.
     *
     * Returns whatever the runner returns (which should be a result of running the application).
     * 
     * @param  AbstractApplication $application Application to be ran.
     * @param  int                 $mode        Mode in which the application should be ran.
     *                                          One of the `Framework::MODE_*` constants.
     * @param  callable            $runner      Closure that is responsible for actually running
     *                                          the application in appropriate mode.
     * @return mixed
     */
    protected function doRunApplication(AbstractApplication $application, $mode, $runner) {
        $timer = new Timer();

        if (!is_callable($runner)) {
            throw new InvalidArgumentException('callable', $runner, 3);
        }

        $container = $application->getContainer();
        // container can now know in what mode its running
        $container->setParameter('mode', $mode);

        // run modules and the app
        foreach($application->getModules() as $module) {
            $module->run();
        }
        $application->run();

        // run using the passed runner
        $result = call_user_func($runner);

        // log benchmark data
        $time = $timer->stop();
        $memory = $timer->getMemoryUsage();
        $container->get('splot.logger')->debug('Application run phase in mode "{mode}" finished in {time} ms and used {memory} memory.', array(
            'mode' => self::modeName($mode),
            'env' => $container->getParameter('env'),
            'debug' => $container->getParameter('debug'),
            'time' => $time,
            'memory' => StringUtils::bytesToString($memory),
            '@stat' => 'splot.run.'. self::modeName($mode),
            '@time' => $time,
            '@memory' => $memory
        ));

        return $result;
    }

    /*****************************************************
     * HELPERS
     *****************************************************/

    /**
     * Resolves a phase constant to a string.
     * 
     * @param  int $phase One of the `Framework::PHASE_*` constants.
     * @return string
     *
     * @throws \InvalidArgumentException When not given a valid phase.
     */
    public static function phaseName($phase) {
        $names = array(
            self::PHASE_BOOTSTRAP => 'bootstrap',
            self::PHASE_CONFIGURE => 'configure',
            self::PHASE_RUN => 'run'
        );
        if (!isset($names[$phase])) {
            throw new \InvalidArgumentException('Invalid phase given for resolving its name ("'. $phase .'").');
        }
        return $names[$phase];
    }

    /**
     * Resolves a mode constant to a string.
     *  
     * @param  int $mode One of the `Framework::MODE_*` constants.
     * @return string
     *
     * @throws \InvalidArgumentException When not given a valid mode.
     */
    public static function modeName($mode) {
        $names = array(
            self::MODE_INDETERMINATE => 'indeterminate',
            self::MODE_WEB => 'web',
            self::MODE_CONSOLE => 'console',
            self::MODE_COMMAND => 'command',
            self::MODE_TEST => 'test'
        );
        if (!isset($names[$mode])) {
            throw new \InvalidArgumentException('Invalid mode given for resolving its name ("'. $mode .'").');
        }
        return $names[$mode];
    }

}
