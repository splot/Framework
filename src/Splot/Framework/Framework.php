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

use Splot\Foundation\Debug\Timer;
use Splot\Foundation\Debug\Debugger;

use Splot\Log\LogContainer;

use Splot\Framework\Application\AbstractApplication;
use Splot\Framework\Application\ApplicationInterface;
use Splot\Framework\Config;
use Splot\Framework\DependencyInjection\ServiceContainer;

use Symfony\Component\Filesystem\Filesystem;

class Framework
{

    const ENV_PRODUCTION    = 'PRODUCTION';
    const ENV_STAGING       = 'STAGING';
    const ENV_DEV           = 'DEV';


    private static $_framework;

    private $_rootDir;
    private $_cacheDir;
    private $_frameworkDir;
    private $_vendorDir;
    private $_rootApplicationDir;
    private $_applicationDir;

    private $_env = 'PRODUCTION';
    private $_cli = false;


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

        $cli = isset($_ENV['SPLOT_CLI']) ? $_ENV['SPLOT_CLI'] : false;

        self::$_framework = new self($options, $cli);
        return self::$_framework;
    }

    /**
     * Constructor.
     * 
     * @param array $options [optional] Array of options.
     * @param bool $cli [optional] Is it command line interface? Default: false.
     */ 
    final private function __construct(array $options = array(), $cli = false) {
        LogContainer::setStartTime(Timer::getMicroTime());

        $this->_timer = new Timer();
        $this->_logger = LogContainer::create('Splot Framework');

        $this->_cli = $cli;
        define('SPLOT_CLI', $cli);

        ini_set('default_charset', 'utf8');

        // register error handlers
        set_error_handler(array('Splot\Foundation\Debug\Debugger', 'handleError'));
        register_shutdown_function(array('Splot\Foundation\Debug\Debugger', 'handleFatalError'));

        $this->_rootDir = @$options['rootDir'] ?: realpath(dirname(__FILE__) .'/../../../../../../') .'/';
        $this->_cacheDir = @$options['cacheDir'] ?: $this->_rootDir .'cache/';
        $this->_frameworkDir = @$options['frameworkDir'] ?: realpath(dirname(__FILE__) .'/../../../') .'/';
        $this->_vendorDir = @$options['vendorDir'] ?: $this->_rootDir .'vendor/';
        $this->_rootApplicationDir = @$options['rootApplicationDir'] ?: $this->_rootDir .'application/';

        $this->_logger->info('Splot Framework successfully initialized.', array(
            'rootDir' => $this->_rootDir,
            'cacheDir' => $this->_cacheDir,
            'frameworkDir' => $this->_frameworkDir,
            'vendorDir' => $this->_vendorDir,
            'rootApplicationDir' => $this->_rootApplicationDir,
            '_timer' => $this->_timer->step('Initialization')
        ));
    }

    /**
     * Boots the passed application.
     * 
     * @param string $applicationClass Application class to boot.
     * @param array $options [optional] Array of optional options that will be passed to the application's boot function.
     * @return ApplicationInterface The booted application.
     */
    public function bootApplication($applicationClass, array $options = array()) {
        // must subclass AbstractApplication
        if (!is_subclass_of($applicationClass, 'Splot\Framework\Application\AbstractApplication', true)) {
            throw new \InvalidArgumentException('Application Class must extend "Splot\Framework\Application\AbstractApplication".');
        }

        // figure out the application directory exactly
        $this->_applicationDir = $this->getRootApplicationDir() . Debugger::getNamespace($applicationClass) . DS;

        /*
         * Decide on environment
         */
        $this->_env = @$options['env'] ?: self::envFromConfigs($this->_applicationDir .'config/');
        define('SPLOT_ENV', $this->getEnv());

        // set logger on or off based on environment
        $this->_logger->setEnabled($this->getEnv() !== static::ENV_PRODUCTION);
        LogContainer::setEnabled($this->getEnv() !== static::ENV_PRODUCTION);

        // read the appropriate application's config (based on env)
        $config = Config::read($this->_applicationDir .'config/', $this->getEnv());

        // set the timezone based on config
        date_default_timezone_set($config->get('timezone'));

        // update the logger settings based on config
        $this->_logger->setEnabled($config->get('debugger.enabled'));
        LogContainer::setEnabled($config->get('debugger.enabled'));

        // create dependency injection container and reference itself as a service
        $serviceContainer = new ServiceContainer();
        $serviceContainer->set('container', function($container) use ($serviceContainer) {
            return $serviceContainer;
        }, true);

        // set some parameters
        $serviceContainer->setParameter('root_dir', $this->_rootDir);
        $serviceContainer->setParameter('cache_dir', $this->_cacheDir);
        $serviceContainer->setParameter('framework_dir', $this->_frameworkDir);
        $serviceContainer->setParameter('vendor_dir', $this->_vendorDir);
        $serviceContainer->setParameter('root_application_dir', $this->_rootApplicationDir);
        $serviceContainer->setParameter('application_dir', $this->_applicationDir);

        // define filesystem service
        $serviceContainer->set('filesystem', function($c) {
            return new Filesystem();
        }, true, true);

        // instantiate the class and inject the config, dependency injection container and environment to it
        $application = new $applicationClass($config, $serviceContainer, $this->getEnv());
        // also define the application as a read-only service
        $serviceContainer->set('application', function($container) use ($application) {
            return $application;
        }, true);

        $this->_logger->info('Started application "'. $applicationClass .'".', array(
            'env' => $this->getEnv(),
            'configFiles' => $config->getReadFiles(),
            '_timer' => $this->_timer->step('Application Start')
        ));

        // boot the application
        $application->boot($options);
        $this->_logger->info('Booted application "'. $applicationClass .'".', array(
            'options' => $options,
            '_timer' => $this->_timer->step('Application Boot')
        ));

        // load all modules for this application
        $modules = $application->loadModules();
        foreach($modules as $module) {
            $application->bootModule($module);

            $this->_logger->info('Module "'. $module->getName() .'" loaded.', array(
                'name' => $module->getName(),
                'class' => $module->getClass(),
                'dir' => $module->getModuleDir(),
                '_timer' => $this->_timer->step('Module "'. $module->getName() .'" loaded'),
                '_tags' => 'module, boot'
            ));
        }

        $routes = $application->getRouter()->getRoutes();
        $routesLog = array();
        foreach($routes as $route) {
            $routesLog[$route->getName()] = $route->getPattern();
        }
        $this->_logger->info('Registered '. count($routes) .' routes.', array(
            'routes' => $routesLog,
            '_timer' => $this->_timer->step('Routes loaded.'),
            '_tags' => 'routing, boot'
        ));

        $this->_application = $application;
        return $this->_application;
    }

    /*
     * HELPERS
     */
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
     * Adds the given path to the PHP include path.
     * 
     * @param string $path Path to be added to the PHP include path.
     */
    public static function addToIncludePath($path) {
        set_include_path(get_include_path() . PATH_SEPARATOR . $path);
    }

    /*
     * SETTERS AND GETTERS
     */
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
     * Returns the root application directory.
     * 
     * @return string
     */
    public function getRootApplicationDir() {
        return $this->_rootApplicationDir;
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
        return !$this->_cli;
    }

    /**
     * Returns information if the framework has been initialized from shell.
     * 
     * @return bool
     */
    final public function isCli() {
        return $this->_cli;
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