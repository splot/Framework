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

use Splot\Log\LogContainer;

use Splot\Framework\Application\AbstractApplication;
use Splot\Framework\Application\ApplicationInterface;
use Splot\Framework\Config\Config;
use Splot\Framework\DependencyInjection\ServiceContainer;
use Splot\Framework\Events\ErrorDidOccur;
use Splot\Framework\Events\FatalErrorDidOccur;

use Symfony\Component\Filesystem\Filesystem;

class Framework
{

    const ENV_PRODUCTION    = 'PRODUCTION';
    const ENV_STAGING       = 'STAGING';
    const ENV_DEV           = 'DEV';

    private static $_framework;

    private $_rootDir;
    private $_frameworkDir;
    private $_vendorDir;
    private $_cacheDir;
    private $_applicationDir;

    private $_env = 'PRODUCTION';
    private $_console = false;

    private $_logger;
    private $_timer;

    private $_application;

    /**
     * Initialize the framework as a singleton.
     * 
     * @param array $options [optional] Array of options.
     * @return Framework
     */
    final public static function init(array $options = array()) {
        if (self::$_framework) {
            return self::$_framework;
        }

        $console = isset($options['console']) ? $options['console'] : false;

        self::$_framework = new self($options, $console);
        return self::$_framework;
    }

    /**
     * Constructor.
     * 
     * @param array $options [optional] Array of options.
     * @param bool $console [optional] Is it command line interface? Default: false.
     */ 
    final private function __construct(array $options = array(), $console = false) {
        LogContainer::setStartTime(Timer::getMicroTime());

        $this->_timer = new Timer();
        $this->_logger = LogContainer::create('Splot Framework');

        $this->_console = $console;
        define('SPLOT_CONSOLE', $console);

        ini_set('default_charset', 'utf8');

        $this->_registerErrorHandlers();

        $this->_rootDir = @$options['rootDir'] ?: realpath(dirname(__FILE__) .'/../../../../../../') .'/';
        $this->_frameworkDir = @$options['frameworkDir'] ?: realpath(dirname(__FILE__) .'/../../../') .'/';
        $this->_vendorDir = @$options['vendorDir'] ?: $this->_rootDir .'vendor/';

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
        // get application's class name for some debugging and logging
        $applicationClass = Debugger::getClass($application);

        // figure out the application directory exactly
        $this->_applicationDir = realpath(dirname(Debugger::getClassFile($application))) . DS;
        $this->_cacheDir = $this->_applicationDir .'cache'. DS;

        /*****************************************
         * DECIDE ON ENVIRONMENT
         *****************************************/
        $this->_env = @$options['env'] ?: self::envFromConfigs($this->_applicationDir .'config'. DS);
        define('SPLOT_ENV', $this->_env);

        // set logger on or off based on environment
        $this->_logger->setEnabled($this->_env !== static::ENV_PRODUCTION);
        LogContainer::setEnabled($this->_env !== static::ENV_PRODUCTION);

        /*****************************************
         * READ CONFIG FOR THE APPLICATON
         *****************************************/
        // based on env
        $config = Config::read($this->_applicationDir .'config'. DS, $this->getEnv());

        // set the timezone based on config
        date_default_timezone_set($config->get('timezone'));

        // update the logger settings based on config
        $this->_logger->setEnabled($config->get('debugger.enabled'));
        LogContainer::setEnabled($config->get('debugger.enabled'));

        /*****************************************
         * INITIALIZE DIC
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
        $serviceContainer->setParameter('application_dir', $this->_applicationDir);
        $serviceContainer->setParameter('cache_dir', $this->_cacheDir);

        // define filesystem service
        $serviceContainer->set('filesystem', function($c) {
            return new Filesystem();
        }, true, true);

        /*****************************************
         * INITIALIZE & BOOT APPLICATION
         *****************************************/
        // inject the config, dependency injection container and environment to it
        $application->init($config, $serviceContainer, $this->_env);

        // also define the application as a read-only service
        $serviceContainer->set('application', function($container) use ($application) {
            return $application;
        }, true, true);

        $this->_logger->info('Started application "{applicationClass}".', array(
            'applicationClass' => $applicationClass,
            'env' => $this->getEnv(),
            'configFiles' => $config->getReadFiles(),
            'applicationDir' => $this->_applicationDir,
            'cacheDir' => $this->_cacheDir,
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
         * LOAD APPLICATION ROUTES
         *****************************************/
        $routes = $application->getRouter()->getRoutes();
        $routesLog = array();
        foreach($routes as $route) {
            $routesLog[$route->getName()] = $route->getPattern();
        }
        $this->_logger->info('Registered {count} routes.', array(
            'count' => count($routes),
            'routes' => $routesLog,
            '_timer' => $this->_timer->step('Routes loaded.'),
            '_tags' => 'routing, boot'
        ));

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
     * Registers error handles that trigger ErrorDidOccur and FatalErrorDidOccur events if application has been already booted.
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

    /**
     * Adds the given path to the PHP include path.
     * 
     * @param string $path Path to be added to the PHP include path.
     */
    public static function addToIncludePath($path) {
        set_include_path(get_include_path() . PATH_SEPARATOR . $path);
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
     * Returns the cache directory of the application.
     * 
     * @return string
     */
    public function getCacheDir() {
        return $this->_cacheDir;
    }

    /**
     * Returns the application directory.
     * 
     * @return string
     */
    public function getApplicationDir() {
        return $this->_applicationDir;
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
     * Returns the current environement.
     * 
     * @return string
     */
    public function getEnv() {
        return $this->_env;
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
    final private function __clone() {}

}