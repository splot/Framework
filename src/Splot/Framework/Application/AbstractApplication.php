<?php
/**
 * Abstract application class.
 * 
 * All Splot Framework applications should extend this class.
 * 
 * @package SplotFramework
 * @subpackage Application
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\Application;

use Psr\Log\LoggerInterface;

use MD\Foundation\Debug\Debugger;
use MD\Foundation\Debug\Timer;
use MD\Foundation\Exceptions\InvalidReturnValueException;
use MD\Foundation\Exceptions\NotFoundException;
use MD\Foundation\Exceptions\NotUniqueException;

use Splot\Cache\Store\FileStore;
use Splot\Cache\CacheProvider;

use Splot\EventManager\EventManager;

use Splot\Log\Provider\LogProviderInterface;

use Splot\Framework\Framework;
use Splot\Framework\Config\Config;
use Splot\Framework\Console\Console;
use Splot\Framework\Controller\ControllerResponse;
use Splot\Framework\HTTP\Request;
use Splot\Framework\HTTP\Response;
use Splot\Framework\DependencyInjection\ServiceContainer;
use Splot\Framework\Modules\AbstractModule;
use Splot\Framework\Routes\Route;
use Splot\Framework\Routes\Router;
use Splot\Framework\Events\ControllerDidRespond;
use Splot\Framework\Events\ControllerWillRespond;
use Splot\Framework\Events\DidReceiveRequest;
use Splot\Framework\Events\DidNotFindRouteForRequest;
use Splot\Framework\Events\DidFindRouteForRequest;
use Splot\Framework\Events\ExceptionDidOccur;
use Splot\Framework\Events\WillSendResponse;
use Splot\Framework\Process\Process;
use Splot\Framework\Resources\Finder;

abstract class AbstractApplication
{

    /**
     * Application name.
     * 
     * @var string
     */
    protected $name;

    /**
     * Application version.
     * 
     * @var string
     */
    protected $version = 'dev';

    /**
     * Application config.
     * 
     * @var Config
     */
    private $_config;

    /**
     * Application's dependency injection service container.
     * 
     * @var ServiceContainer
     */
    protected $container;

    /**
     * Environment.
     * 
     * @var string
     */
    protected $_env;

    /**
     * Application directory path.
     * 
     * @var string
     */
    protected $_applicationDir;

    /**
     * Application logger.
     * 
     * @var LoggerInterface
     */
    protected $_logger;

    /**
     * Application timer.
     * 
     * @var Timer
     */
    protected $_timer;

    /**
     * Router.
     * 
     * @var Router
     */
    private $_router;

    /**
     * Event manager.
     * 
     * @var EventManager
     */
    private $_eventManager;

    /**
     * Resource finder.
     * 
     * @var Finder
     */
    private $_resourceFinder;

    /**
     * Container for all application modules.
     * 
     * @var array
     */
    private $_modules = array();

    /**
     * Container for all application modules meta data.
     * 
     * @var array
     */
    private $_modulesMetaData = array();

    /**
     * Current HTTP Request that is being handled by the application.
     * 
     * @var Request
     */
    private $_request;

    /**
     * Private flag for checking if application has been initialized.
     * 
     * @var bool
     */
    private $_initialized = false;

    /**
     * Initializator.
     * 
     * Should not ever be overwritten.
     * 
     * @param Config $config Application config.
     * @param ServiceContainer $container Dependency Injection Service Container.
     * @param string $env Current environment name.
     * @param string $applicationDir Path to application directory.
     * @param Timer $timer Global timer from the framework, for profiling.
     * @param LoggerInterface $logger Main application logger.
     * @param LogProviderInterface $logProvider Loggers provider.
     * 
     * @throws \RuntimeException When trying to initialize the application for a second time.
     */
    final public function init(Config $config, ServiceContainer $container, $env, $applicationDir, Timer $timer, LoggerInterface $logger, LogProviderInterface $logProvider) {
        if ($this->_initialized) {
            throw new \RuntimeException('Application "'. Debugger::getClass($this) .'" has already been initialized.');
        }

        $app = $this;

        $this->_timer = $timer;
        $this->_logger = $logger;

        $this->_config = $config;
        $this->container = $container;
        $this->_env = $env;
        $this->_applicationDir = $applicationDir;

        $this->_router = $router = new Router(
            $logProvider->provide('Router'),
            $config->get('router.host'),
            $config->get('router.protocol'),
            $config->get('router.port')
        );
        $this->_eventManager = $eventManager = new EventManager($logProvider->provide('Event Manager'));
        $this->_resourceFinder = $resourceFinder = new Finder($this);

        // define all of the above as services as well
        // config
        $container->set('config', function($c) use ($config) {
            return $config;
        }, true);
        // env
        $container->setParameter('env', $env);
        // application dir
        $container->setParameter('application_dir', $applicationDir);
        // router
        $container->set('router', function($c) use ($router) {
            return $router;
        }, true);
        // event manager
        $container->set('event_manager', function($c) use ($eventManager) {
            return $eventManager;
        }, true);
        // resource finder
        $container->set('resource_finder', function($c) use ($resourceFinder) {
            return $resourceFinder;
        }, true);
        // process
        $container->set('process', function($c) {
            return new Process();
        }, true, true);
        // console
        $container->set('console', function($c) use ($app, $logProvider) {
            return new Console($app, $logProvider->provide('Console'));
        }, true, true);
        // cache
        $this->registerCaches($container, $config);

        /*****************************************************
         * REGISTER LISTENERS
         *****************************************************/
        // register some listeners that add some additional functionality
        // these possibly should be a separate module in the future
        
        // get protocol, hostname and port number from request to use in router
        // @todo move to FrameworkExtra
        if ($config->get('router.use_request')) {
            $eventManager->subscribe(DidReceiveRequest::getName(), function($event) use ($router) {
                $request = $event->getRequest();
                $protocol = $request->getScheme();
                $host = $request->getHost();
                $port = $request->getPort();

                if (!empty($protocol)) {
                    $router->setProtocol($protocol);
                }

                if (!empty($host)) {
                    $router->setHost($host);
                }

                if (!empty($port)) {
                    $router->setPort($port);
                }
            });
        }
        
        // listener that will inject Request object to controller method arguments
        // @todo Move to FrameworkExtra
        $eventManager->subscribe(ControllerWillRespond::getName(), function($event) use ($router, $container) {
            $route = $router->getRoute($event->getControllerName());
            $arguments = $event->getArguments();
            $methodName = $event->getMethod();

            // find the method's meta data
            $method = array();
            foreach($route->getMethods() as $methodInfo) {
                if ($methodInfo['method'] === $methodName) {
                    $method = $methodInfo;
                    break;
                }
            }

            foreach($method['params'] as $i => $param) {
                if ($param['class'] && Debugger::isExtending($param['class'], Request::__class(), true) && !($arguments[$i] instanceof Request)) {
                    try {
                        $arguments[$i] = $container->get('request');
                    } catch(NotFoundException $e) {
                        throw new \RuntimeException('Could not inject Request object into controller\'s method, because it is not executed in web request context.', 0, $e);
                    }
                // @codeCoverageIgnoreStart
                }
                // @codeCoverageIgnoreEnd
            }

            $event->setArguments($arguments);
        });

        $this->_initialized = true;
    }

    /**
     * Boots an application - ie. performs any initialization, etc.
     * 
     * @param array $options [optional] Options that can be passed to the boot function via Splot Framework.
     */
    abstract public function boot(array $options = array());

    /**
     * Loads modules for the application.
     */
    abstract public function loadModules();

    /**
     * Handle the request that was sent to the application.
     * 
     * @param Request $request
     * @return Response
     * 
     * @throws NotFoundException When route has not been found and there wasn't any event listener to handle DidNotFindRouteForRequest event.
     */
    public function handleRequest(Request $request) {
        try {
            $this->_request = $request;
            $this->container->set('request', function($c) use ($request) {
                return $request;
            }, true);

            $this->_logger->info('Received request', array(
                'request' => $request,
                '_timer' => $this->_timer->step('Received request'),
                '_tags' => 'request'
            ));

            // trigger DidReceiveRequest event
            $this->_eventManager->trigger(new DidReceiveRequest($request));

            /** @var Route Meta information about the found route. */
            $route = $this->getRouter()->getRouteForRequest($request);
            if (!$route) {
                $notFoundEvent = new DidNotFindRouteForRequest($request);
                $this->_eventManager->trigger($notFoundEvent);

                if ($notFoundEvent->isHandled()) {
                    return $notFoundEvent->getResponse();
                } else {
                    throw new NotFoundException('Could not find route for "'. $request->getPathInfo() .'".');
                }
            }

            // trigger DidFindRouteForRequest event
            if (!$this->_eventManager->trigger(new DidFindRouteForRequest($route, $request))) {
                $notFoundEvent = new DidNotFindRouteForRequest($request);
                $this->_eventManager->trigger($notFoundEvent);

                if ($notFoundEvent->isHandled()) {
                    return $notFoundEvent->getResponse();
                } else {
                    throw new NotFoundException('Could not find route for "'. $request->getPathInfo() .'" (rendering prevented).');
                }
            }

            $response = $this->renderController(
                $route->getName(),
                $route->getControllerClass(),
                $route->getControllerMethodForHttpMethod($request->getMethod()),
                $route->getControllerMethodArgumentsForUrl($request->getPathInfo(), $request->getMethod()),
                $request
            );

        } catch(\Exception $e) {
            // catch any exceptions that might have occurred during handling of the request
            // and trigger ExceptionDidOccur event to potentially handle them with custom response
            $exceptionEvent = new ExceptionDidOccur($e);
            $this->_eventManager->trigger($exceptionEvent);

            // was the exception handled?
            if ($exceptionEvent->isHandled()) {
                // if so then it should have a response set, so return it
                return $exceptionEvent->getResponse();
            }

            // if it hasn't been handled then rethrow the exception
            throw $e;
        }

        return $response;
    }

    /**
     * Renders a controller with the given arguments as if it was responding to a GET request.
     * 
     * @param string $name Name of the controller/route.
     * @param array $arguments [optional] Arguments for the controller.
     * @return Response
     */
    public function render($name, array $arguments = array()) {
        $route = $this->getRouter()->getRoute($name);

        $response = $this->renderController(
            $route->getName(),
            $route->getControllerClass(),
            $route->getControllerMethodForHttpMethod('get'),
            $route->getControllerMethodArgumentsFromArray('get', $arguments)
        );

        return $response;
    }

    /**
     * Renders the final response and sends it back to the client.
     * 
     * @param Response $response HTTP Response to be sent.
     * @param Request $request The original HTTP request for context.
     */
    public function sendResponse(Response $response, Request $request) {
        $this->_logger->info('Will send response', array(
            'time' => $this->_timer->getDuration(),
            'memory' => $this->_timer->getCurrentMemoryPeak(),
            '_timer' => $this->_timer->step('Will send response'),
            '_tags' => array(
                'profiling', 'execution time', 'memory usage'
            )
        ));

        // trigger WillSendResponse event for any last minute changes
        $this->_eventManager->trigger(new WillSendResponse($response, $request));

        // and finally send out the response
        $response->send();
    }

    /*****************************************************
     * RENDERING
     *****************************************************/
    /**
     * Execute the given controller.
     * 
     * @param string $name Name of the controller or route assigned to this controller.
     * @param string $class Class name of the controller.
     * @param string $method Method name to execute on the controller.
     * @param array $arguments [optional] Arguments to execute the controller with.
     * @return Response
     */
    protected function renderController($name, $class, $method, array $arguments = array(), Request $request = null) {
        $controller = new $class($this->container);

        $willRespondEvent = new ControllerWillRespond($name, $controller, $method, $arguments);
        $this->_eventManager->trigger($willRespondEvent);

        $method = $willRespondEvent->getMethod();
        $arguments = $willRespondEvent->getArguments();
        
        $controllerResponse = new ControllerResponse(call_user_func_array(array($controller, $method), $arguments));
        $this->_eventManager->trigger(new ControllerDidRespond($controllerResponse, $name, $controller, $method, $arguments, $request));

        $response = $controllerResponse->getResponse();

        $this->_logger->info('Executed controller: "{name}"', array(
            'name' => $name,
            'function' => $class .'::'. $method,
            'arguments' => $arguments,
            '_timer' => $this->_timer->step('Matched route'),
            '_tags' => 'routing, request'
        ));

        // special case, if the response is a string then automatically convert it to HttpResponse
        if (is_string($response)) {
            $response = new Response($response);
        }

        if (!is_object($response) || !($response instanceof Response)) {
            throw new InvalidReturnValueException('Executed controller method must return '. Response::__class() .' instance or a string, "'. Debugger::getType($response) .'" given.');
        }

        return $response;
    }

    /*****************************************************
     * MODULES MANAGEMENT
     *****************************************************/
    /**
     * Boots the given module.
     * 
     * @param AbstractModule $module
     * @return AbstractModule The given module.
     * 
     * @throws NotUniqueException When the module name created from it's class name is a duplicate of previously registered module.
     */
    public function bootModule(AbstractModule $module) {
        $name = $module->getName();
        $class = $module->getClass();
        $namespace = $module->getNamespace();

        // but the name has to be unique
        if (isset($this->_modules[$name])) {
            throw new NotUniqueException('Module name "'. $name .'" for module "'. $class .'" is not unique in the application scope.');
        }

        // read config for this module
        // also apply settings from the global config, if it contains any related to this module
        $config = Config::read($module->getModuleDir() .'Resources/config/', $this->getEnv());
        $config->apply($this->getConfig()->getNamespace($name));
        $module->setConfig($config);

        // inject application and the service container
        $module->setApplication($this);
        $module->setContainer($this->container);
        
        // finally add the module to the module registry
        $this->_modules[$name] = $module;

        // also read routes from this module
        $this->getRouter()->readModuleRoutes($module);

        // let the module boot itself as well
        $module->boot();

        return $module;
    }

    /**
     * Initializes the given module.
     * 
     * @param AbstractModule $module
     * 
     * @throws \RuntimeException When trying to initialize a module that hasn't been previously booted.
     */
    public function initModule(AbstractModule $module) {
        if (!isset($this->_modules[$module->getName()])) {
            throw new \RuntimeException('Only previously booted modules can be initialized. Trying to init module called "'. $module->getName() .'".');
        }

        $module->init();
    }

    /*****************************************************
     * HELPERS
     *****************************************************/
    /**
     * Registers all cache related services based on app config.
     * 
     * @param ServiceContainer $container Service container on which the cache should be registered.
     * @param Config $config Application config for cache.
     */
    protected function registerCaches(ServiceContainer $container, Config $config) {
        $enabled = $config->get('cache.enabled', true);

        /* register default file store */
        $fileStore = new FileStore(array(
            'dir' => $container->getParameter('cache_dir')
        ));
        // as a service as well
        $container->set('cache.store.file', $fileStore, true, true);

        /* register cache provider */
        $cacheProvider = new CacheProvider($fileStore, array(
            'stores' => array(
                'file' => $fileStore
            ),
            'global_namespace' => $container->getParameter('env')
        ));
        // as a service as well
        $container->set('cache_provider', $cacheProvider, true, true);

        /* register default cache as a service */
        $container->set('cache', function($c) {
            return $c->get('cache_provider')->provide('global');
        }, true, true);

        /*****************************************************
         * ADD OTHER STORES
         *****************************************************/
        foreach($config->get('cache.stores') as $name => $storeConfig) {
            if (!isset($storeConfig['class'])) {
                throw new \RuntimeException('Store config has to have a "class" key defined.');
            }

            $store = new $storeConfig['class']($storeConfig);

            // register in cache provider
            $cacheProvider->registerStore($name, $store);

            // register as a service as well 
            $container->set('cache.store.'. $name, $store, true);
        }

        /*****************************************************
         * ADD OTHER CACHES
         *****************************************************/
        foreach($config->get('cache.caches') as $name => $storeName) {
            $container->set('cache.'. $name, function($c) use ($name, $storeName) {
                return $c->get('cache_provider')->provide($name, $storeName);
            }, true, true);
        }
    }

    /*****************************************************
     * SETTERS AND GETTERS
     *****************************************************/
    /**
     * Returns name of the application.
     * 
     * @return string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * Returns version of the application.
     * 
     * @return string
     */
    public function getVersion() {
        return $this->version;
    }

    /**
     * Returns class name of the application.
     * 
     * @return string
     */
    final public static function getClass() {
        return get_called_class();
    }

    /**
     * Returns the application config.
     * 
     * @return Config
     */
    final public function getConfig() {
        return $this->_config;
    }

    /**
     * Returns the dependency injection service container.
     * 
     * @return ServiceContainer
     */
    final public function getContainer() {
        return $this->container;
    }

    /**
     * Returns the application directory.
     * 
     * @return string
     */
    final public function getApplicationDir() {
        return $this->_applicationDir;
    }

    /**
     * Returns the application environment.
     * 
     * @return string
     */
    final public function getEnv() {
        return $this->_env;
    }

    /**
     * Checks if the current environment is Dev.
     * 
     * @return bool
     */
    final public function isDevEnv() {
        return $this->getEnv() === Framework::ENV_DEV;
    }

    /**
     * Returns registered modules.
     * 
     * @return array
     */
    final public function getModules() {
        return $this->_modules;
    }

    /**
     * Returns names of registered modules.
     * 
     * @return array
     */
    final public function listModules() {
        return array_keys($this->_modules);
    }

    /**
     * Checks if a given module is registered.
     * 
     * @return bool
     */
    final public function hasModule($name) {
        return isset($this->_modules[$name]);
    }

    /**
     * Returns the given module.
     * 
     * @return AbstractModule
     */
    final public function getModule($name) {
        return $this->_modules[$name];
    }

    /**
     * Returns the router.
     * 
     * @return Router
     */
    final public function getRouter() {
        return $this->_router;
    }

    /**
     * Returns the event manager.
     * 
     * @return EventManager
     */
    final public function getEventManager() {
        return $this->_eventManager;
    }

    /**
     * Returns the resources finder.
     * 
     * @return Finder
     */
    final public function getResourceFinder() {
        return $this->_resourceFinder;
    }

    /**
     * Returns the application logger.
     *
     * @return LoggerInterface
     */
    final public function getLogger() {
        return $this->_logger;
    }

}