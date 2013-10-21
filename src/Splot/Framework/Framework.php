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

use Psr\Log\LoggerInterface;

use MD\Foundation\Debug\Timer;
use MD\Foundation\Debug\Debugger;
use MD\Foundation\Utils\ArrayUtils;
use MD\Foundation\Utils\StringUtils;

use Splot\Log\Provider\LogProvider;
use Splot\Log\Provider\LogProviderInterface;
use Splot\Log\LogContainer;
use Splot\Log\Logger;

use Splot\Framework\Application\AbstractApplication;
use Splot\Framework\Application\CommandApplication;
use Splot\Framework\Config\Config;
use Splot\Framework\DependencyInjection\ServiceContainer;
use Splot\Framework\Events\ErrorDidOccur;
use Splot\Framework\Events\FatalErrorDidOccur;
use Splot\Framework\HTTP\Exceptions\NoAccessException;
use Splot\Framework\HTTP\Exceptions\NotFoundException;
use Splot\Framework\HTTP\Request;
use Splot\Framework\HTTP\Response;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Input\ArgvInput;

class Framework
{

    const ENV_PRODUCTION    = 'production';
    const ENV_STAGING       = 'staging';
    const ENV_DEV           = 'dev';
    const ENV_TEST          = 'test';

    private static $_framework;

    private $_rootDir;
    private $_frameworkDir;
    private $_vendorDir;

    private $_options = array();

    private $_console = false;

    /**
     * Global framework logger.
     * 
     * @var LoggerInterface
     */
    private $_logger;
    private $_timer;

    private $_application;

    /*****************************************
     * ENTRY POINTS
     *****************************************/
    /**
     * Initialize application for web and handle default HTTP requests.
     * 
     * @param AbstractApplication $application Application to boot.
     * @param array $options [optional] Options for framework and application.
     * 
     * @codeCoverageIgnore
     */
    final public static function web(AbstractApplication $application, array $options = array()) {
        try {
            // Splot Framework and application initialization
            $splot = static::init($options);
            $application = $splot->bootApplication($application, $options);

            // handling the request
            $request = Request::createFromGlobals();
            $response = $application->handleRequest($request);

            // rendering the response
            $application->sendResponse($response, $request);
        } catch (\Exception $e) {
            // set a valid response code
            $httpResponseCode = 500;
            if ($e instanceof NotFoundException) {
                $httpResponseCode = 404;
            } elseif ($e instanceof NoAccessException) {
                $httpResponseCode = 403;
            }

            Debugger::handleException($e, LogContainer::exportLogs(), $httpResponseCode);
        }
    }

    /**
     * Initialize application for testing.
     * 
     * @param AbstractApplication $application Application to be tested.
     * @param array $options [optional] Options for framework and application.
     * 
     * @codeCoverageIgnore
     */
    final public static function test(AbstractApplication $application, array $options = array()) {
        $options['env'] = self::ENV_TEST;

        $splot = static::init($options);
        $application = $splot->bootApplication($application, $options);
    }

    /**
     * Initialize application for command line interface (console) and handle the comand.
     * 
     * @param AbstractApplication $application Application to boot.
     * @param array $options [optional] Options for framework and application.
     * @param bool $suppressInput [optional] For internal use. Default: false.
     * 
     * @codeCoverageIgnore
     */
    final public static function console(AbstractApplication $application, array $options = array(), $suppressInput = false) {
        // remove time limit for console
        set_time_limit(0);

        // Splot Framework and application initialization
        $splot = static::init($options, true);
        $application = $splot->bootApplication($application, $options);

        $console = $application->getContainer()->get('console');
        if (!$suppressInput) {
            $console->run();
        }
    }

    /**
     * Initialize application for single command app comman line interface (console) and run the command.
     * 
     * @param string $commandClass [optional] Command class. Default: '\App'.
     * @param array $config [optional] Application config.
     * @param array $options [optional] Options for framework and application.
     * @param bool $suppressInput [optional] For internal use. Default: false.
     * 
     * @codeCoverageIgnore
     */
    final public static function command($commandClass = '\App', array $config = array(), $options = array(), $suppressInput = false) {
        // remove time limit for console
        set_time_limit(0);

        $options['env'] = self::ENV_DEV;
        $options['applicationDir'] = realpath(dirname(Debugger::getClassFile($commandClass)));

        // Splot Framework and application initialization
        $splot = static::init($options, true);

        // if a config was set then use it
        if (!empty($config)) {
            $options['config'] = $config;
        }

        $application = $splot->bootApplication(new CommandApplication($commandClass), $options);

        if ($suppressInput) {
            return;
        }

        $console = $application->getContainer()->get('console');
        $console->addCommand('app', $commandClass);

        $argv = new ArgvInput();
        $console->call('app', (string)$argv);
    }

    /*****************************************
     * BOOTING
     *****************************************/
    /**
     * Initialize the framework as a singleton.
     * 
     * @param array $options [optional] Array of options.
     * @param bool $console [optional] Running in console mode? Default: false.
     * @return Framework
     */
    final public static function init(array $options = array(), $console = false) {
        if (self::$_framework) {
            return self::$_framework;
        }

        // create log provider for default "log_provider" service.
        $logProvider = new LogProvider();

        $options = ArrayUtils::merge(array(
            'logger' => null,
            'timezone' => 'Europe/London',
            'services' => array(
                'log_provider' => function($c) use ($logProvider) {
                    return $logProvider;
                }
            )
        ), $options);

        // set default timezone from options, for now
        date_default_timezone_set($options['timezone']);

        // for debug, set as early as possible
        LogContainer::setStartTime(Timer::getMicroTime());

        // but now just get reference to the real log provider, in case it was overwritten in options
        $realLogProvider = call_user_func_array($options['services']['log_provider'], array(null));

        self::$_framework = new self(
            $options,
            ($options['logger']) ? $options['logger'] : $realLogProvider->provide('Splot Framework'),
            $console
        );
        return self::$_framework;
    }

    /**
     * Constructor.
     * 
     * @param array $options [optional] Array of options.
     * @param LoggerInterface $logger [optional] Global logger.
     * @param bool $console [optional] Running in console mode? Default: false.
     */ 
    final private function __construct(array $options = array(), LoggerInterface $logger = null, $console = false) {
        $this->_timer = new Timer();
        $this->_logger = $logger;
        $this->_console = $console;

        ini_set('default_charset', 'utf8');

        if (!$console) {
            $this->_registerErrorHandlers();
        }

        $this->_rootDir = @$options['rootDir'] ?: realpath(dirname(__FILE__) .'/../../../../../../') .'/';
        $this->_rootDir = rtrim($this->_rootDir, '/') .'/';
        $this->_frameworkDir = @$options['frameworkDir'] ?: realpath(dirname(__FILE__)) .'/';
        $this->_frameworkDir = rtrim($this->_frameworkDir, '/') .'/';
        $this->_vendorDir = @$options['vendorDir'] ?: $this->_rootDir .'vendor/';
        $this->_vendorDir = rtrim($this->_vendorDir, '/') .'/';

        $this->_options = $options;

        $this->_logger->info('Splot Framework successfully initialized.', array(
            'rootDir' => $this->_rootDir,
            'frameworkDir' => $this->_frameworkDir,
            'vendorDir' => $this->_vendorDir,
            '_timer' => $this->_timer->step('Initialization')
        ));
    }

    /**
     * Boots the passed application.
     * 
     * @param AbstractApplication $application Application to boot into the framework.
     * @param array $options [optional] Array of optional options that will be passed to the application's boot function.
     * @return AbstractApplication The booted application.
     */
    public function bootApplication(AbstractApplication $application, array $options = array()) {
        // get and verify application name
        $applicationName = $application->getName();
        if (!is_string($applicationName) || empty($applicationName)) {
            throw new \RuntimeException('You have to specify an application name inside the Application class by defining protected property "$name".');
        }

        if (!StringUtils::isClassName($applicationName)) {
            throw new \RuntimeException('Application name must conform to variable naming rules and therefore can only start with a letter and only contain letters, numbers and _, "'. $applicationName .'" given.');
        }

        // get application's class name for some debugging and logging
        $applicationClass = Debugger::getClass($application);

        // figure out the application directory exactly
        $applicationDir = (isset($options['applicationDir']))
            ? rtrim($options['applicationDir'], DS) . DS
            : realpath(dirname(Debugger::getClassFile($application))) . DS;
        $cacheDir = $applicationDir .'cache'. DS;

        /*****************************************
         * DECIDE ON ENVIRONMENT
         *****************************************/
        $env = @$options['env'] ?: self::envFromConfigs($applicationDir .'config'. DS);

        /*****************************************
         * READ CONFIG FOR THE APPLICATON
         *****************************************/
        // default framework config first (to make sure all required settings are there)
        $defaultConfigFile = $this->_frameworkDir .'Config'. DS .'default.php';
        $config = new Config(include $defaultConfigFile);
        $config->extend(Config::read($applicationDir .'config'. DS, $env));

        if (isset($options['config'])) {
            $config->apply($options['config']);
        }

        // set the timezone based on config
        date_default_timezone_set($config->get('timezone'));

        // update the logger settings based on config
        if ($this->_logger instanceof Logger) {
            $this->_logger->setEnabled($config->get('debugger.enabled'));
        }
        LogContainer::setEnabled($config->get('debugger.enabled'));

        /*****************************************
         * INITIALIZE DEPENDENCY INJECTION CONTAINER
         *****************************************/
        // create dependency injection container and reference itself as a service
        $serviceContainer = new ServiceContainer();
        $serviceContainer->set('container', function($container) use ($serviceContainer) {
            return $serviceContainer;
        }, true);

        // set some parameters
        $serviceContainer->setParameter('root_dir', $this->_rootDir);
        $serviceContainer->setParameter('framework_dir', $this->_frameworkDir);
        $serviceContainer->setParameter('vendor_dir', $this->_vendorDir);
        $serviceContainer->setParameter('application_dir', $applicationDir);
        $serviceContainer->setParameter('cache_dir', $cacheDir);

        // define filesystem service
        $serviceContainer->set('filesystem', function($c) {
            return new Filesystem();
        }, true, true);

        // now register all custom services that may have been sent in framework options
        foreach($this->_options['services'] as $name => $service) {
            $serviceContainer->set($name, $service);
        }

        /*****************************************
         * INITIALIZE & BOOT APPLICATION
         *****************************************/
        // inject the config, dependency injection container and environment to it
        $applicationLogger = (isset($this->_options['applicationLogger'])) ? $this->_options['applicationLogger'] : $serviceContainer->get('log_provider')->provide('Application');
        $application->init($config, $serviceContainer, $env, $applicationDir, $this->_timer, $applicationLogger, $serviceContainer->get('log_provider'));

        // also define the application as a read-only service
        $serviceContainer->set('application', function($container) use ($application) {
            return $application;
        }, true, true);

        $this->_logger->info('Started application "{applicationClass}".', array(
            'applicationClass' => $applicationClass,
            'env' => $env,
            'configFiles' => $config->getReadFiles(),
            'applicationDir' => $applicationDir,
            'cacheDir' => $cacheDir,
            '_timer' => $this->_timer->step('Application Start')
        ));

        // boot the application
        $application->boot($options);
        $this->_logger->info('Booted application "{applicationClass}".', array(
            'applicationClass' => $applicationClass,
            'options' => $options,
            '_timer' => $this->_timer->step('Application Boot')
        ));

        /*****************************************
         * LOAD APPLICATION MODULES
         *****************************************/
        $modules = $application->loadModules();
        foreach($modules as $module) {
            $application->bootModule($module);

            $this->_logger->info('Module "{name}" loaded.', array(
                'name' => $module->getName(),
                'class' => $module->getClass(),
                'dir' => $module->getModuleDir(),
                '_timer' => $this->_timer->step('Module "'. $module->getName() .'" loaded'),
                '_tags' => 'module, boot'
            ));
        }

        /*****************************************
         * LOAD APPLICATION CONTROLLERS
         *****************************************/
        // read application routes
        $routes = $application->getRouter()->getRoutes();
        $routesLog = array();
        foreach($routes as $route) {
            $routesLog[$route->getName()] = $route->getUrlPattern();
        }
        $this->_logger->info('Registered {count} routes to controllers.', array(
            'count' => count($routes),
            'routes' => $routesLog,
            '_timer' => $this->_timer->step('Routes loaded.'),
            '_tags' => 'routing, boot'
        ));

        /*****************************************
         * INITIALIZE MODULES
         *****************************************/
        foreach($modules as $module) {
            $application->initModule($module);
        }

        $this->_application = $application;
        return $this->_application;
    }

    /*****************************************************
     * HELPERS
     *****************************************************/
    /**
     * Tries to determine in what environement the application should run based on available configs in application's /config/ dir.
     * 
     * If no specific configs available it will return Production environment.
     * The order of checking is: dev, staging, production.
     * 
     * @param string $configDir Path to directory with application configs.
     * @return string The guessed environment.
     */
    public static function envFromConfigs($configDir) {
        $configDir = rtrim($configDir, '/') .'/';

        // check for development
        if (file_exists($configDir .'config.dev.php')) {
            return self::ENV_DEV;
        }

        // check for staging
        if (file_exists($configDir .'config.staging.php')) {
            return self::ENV_STAGING;
        }

        // check for production
        if (file_exists($configDir .'config.production.php')) {
            return self::ENV_PRODUCTION;
        }

        // return production environment by default to prevent accidental display of various debug stuff (debug should be turned off for production)
        return self::ENV_PRODUCTION;
    }

    /**
     * Resets the framework singleton.
     */
    public static function reset() {
        self::$_framework = null;
    }

    /**
     * Registers error handles that trigger ErrorDidOccur and FatalErrorDidOccur events if application has been already booted.
     * 
     * @codeCoverageIgnore
     */
    protected function _registerErrorHandlers() {
        $self = $this;

        // register standard error handler
        set_error_handler(function($code, $message, $file, $line, $context) use ($self) {
            // if application is already booted then handle error using event manager
            if ($self->getApplication()) {
                $eventManager = $self->getApplication()->getEventManager();
                $errorEvent = new ErrorDidOccur($code, $message, $file, $line, $context);
                $eventManager->trigger($errorEvent);

                if ($errorEvent->isDefaultPrevented() || $errorEvent->isHandled()) {
                    return true;
                }
            }

            // if there was no error event or if the error wasn't properly handled by it,
            // then do standard error handling by Foundation framework
            return Debugger::handleError($code, $message, $file, $line, $context);
        });

        // register fatal error handler
        register_shutdown_function(function() use ($self) {
            // if application is already booted then handle error using event manager
            if ($self->getApplication()) {
                $error = error_get_last();

                if ($error !== null) {
                    $eventManager = $self->getApplication()->getEventManager();
                    $fatalErrorEvent = new FatalErrorDidOccur($error['type'], $error['message'], $error['file'], $error['line']);
                    $eventManager->trigger($fatalErrorEvent);

                    if ($fatalErrorEvent->isDefaultPrevented() || $fatalErrorEvent->isHandled()) {
                        return true;
                    }
                }
            }

            // if there was no error event or if the error wasn't properly handled by it,
            // then do standard error handling by Foundation framework
            return Debugger::handleFatalError();
        });
    }

    /*****************************************************
     * SETTERS AND GETTERS
     *****************************************************/
    /**
     * Returns the root directory of the application.
     * 
     * @return string
     */
    public function getRootDir() {
        return $this->_rootDir;
    }

    /**
     * Returns the Splot Framework installation directory.
     * 
     * @return string
     */
    public function getFrameworkDir() {
        return $this->_frameworkDir;
    }

    /**
     * Returns the vendor directory.
     * 
     * @return string
     */
    public function getVendorDir() {
        return $this->_vendorDir;
    }

    /**
     * Returns the application instance.
     * 
     * @return ApplicationInterface
     */
    public function getApplication() {
        return $this->_application;
    }

    /**
     * Returns information if the framework has been initialized for Web Requests and therefore should handle HTTP requests.
     * 
     * @return bool
     */
    final public function isWeb() {
        return !$this->_console;
    }

    /**
     * Returns information if the framework has been initialized from shell.
     * 
     * @return bool
     */
    final public function isConsole() {
        return $this->_console;
    }

    /**
     * Returns the current instance of Framework.
     * 
     * @return Framework
     */
    final public static function getFramework() {
        return self::$_framework;
    }
    
    /**
     * Prevent from creating instances of this class from the outside.
     */
    final public function __clone() {
        throw new \RuntimeException(get_called_class() .' cannot be cloned (it\'s a singleton).');
    }

}