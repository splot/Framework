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

use Splot\Foundation\Debug\Debugger;
use Splot\Foundation\Debug\Timer;
use Splot\Foundation\Exceptions\InvalidReturnValueException;
use Splot\Foundation\Exceptions\NotFoundException;
use Splot\Foundation\Exceptions\NotUniqueException;

use Splot\Log\Logger;
use Splot\Log\LogContainer;

use Splot\EventManager\EventManager;

use Splot\Framework\Framework;
use Splot\Framework\Config\Config;
use Splot\Framework\HTTP\Request;
use Splot\Framework\HTTP\Response;
use Splot\Framework\DependencyInjection\ServiceContainer;
use Splot\Framework\Modules\AbstractModule;
use Splot\Framework\Routes\RouteMeta;
use Splot\Framework\Routes\Router;
use Splot\Framework\Routes\RouteResponse;
use Splot\Framework\Events\DidExecuteRoute;
use Splot\Framework\Events\DidReceiveRequest;
use Splot\Framework\Events\DidNotFindRouteForRequest;
use Splot\Framework\Events\DidFindRouteForRequest;
use Splot\Framework\Events\WillSendResponse;
use Splot\Framework\Resources\Finder;

abstract class AbstractApplication
{

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
     * Application logger.
     * 
     * @var Logger
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
     * Constructor.
     * 
     * Should not ever be overwritten.
     * 
     * @param Config $config Application config.
     * @param string $env Current environment name.
     */
    final public function __construct(Config $config, ServiceContainer $container, $env) {
        $this->_timer = new Timer();
        $this->_logger = LogContainer::create('Application');

        $this->_config = $config;
        $this->container = $container;
        $this->_env = $env;

        $this->_router = $router = new Router();
        $this->_eventManager = $eventManager = new EventManager('Application Events');
        $this->_resourceFinder = $resourceFinder = new Finder($this);

        // define all of the above as services as well
        // config
        $container->set('config', function($c) use ($config) {
            return $config;
        }, true);
        // env
        $container->setParameter('env', $env);
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

        /**
         * @var RouteMeta Meta information about the found route.
         */
        $routeMeta = $this->getRouter()->getRouteForRequest($request);
        if (!$routeMeta) {
            $notFoundEvent = new DidNotFindRouteForRequest($request);
            $this->_eventManager->trigger($notFoundEvent);

            if ($notFoundEvent->isHandled()) {
                return $notFoundEvent->getResponse();
            } else {
                throw new NotFoundException('Could not find route for "'. $request->getPathInfo() .'".');
            }
        }

        // trigger DidFindRouteForRequest event
        $this->_eventManager->trigger(new DidFindRouteForRequest($routeMeta, $request));

        $routeClass = $routeMeta->getRouteClass();
        $routeMethod = $routeMeta->getRouteMethodForHttpMethod($request->getMethod());
        $routeArguments = $routeMeta->getRouteMethodArgumentsForUrl($request->getPathInfo(), $request->getMethod(), $request);

        // if route has been found then log it
        $this->_logger->info('Matched route: "'. $routeMeta->getName() .'" ("'. $routeMeta->getRouteClass() .'")', array(
            'name' => $routeMeta->getName(),
            'function' => $routeClass .'::'. $routeMethod,
            'arguments' => $routeArguments,
            'url' => $request->getPathInfo(),
            'method' => $request->getMethod(),
            'module' => $routeMeta->getModuleName(),
            '_timer' => $this->_timer->step('Matched route'),
            '_tags' => 'routing, request'
        ));

        // and finally execute the route
        $routeResponse = new RouteResponse(call_user_func_array(array(new $routeClass($this->container), $routeMethod), $routeArguments));
        $this->_logger->info('Route executed', array(
            '_timer' => $this->_timer->step('Route executed'),
            '_tags' => 'routing'
        ));

        // trigger DidExecuteRoute event
        $this->_eventManager->trigger(new DidExecuteRoute($routeResponse, $routeMeta, $request));

        $response = $routeResponse->getResponse();

        // one exception, if the response is a string then automatically convert it to HttpResponse
        if (is_string($response)) {
            $response = new Response($response);
        }

        if (!is_object($response) || !($response instanceof Response)) {
            throw new InvalidReturnValueException('Executed route method must return Splot\\Framework\\HTTP\\Response instance, "'. Debugger::getType($response) .'" given.');
        }

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
            '_timer' => $this->_timer->step('Will send response')
        ));

        // trigger WillSendResponse event for any last minute changes
        $this->_eventManager->trigger(new WillSendResponse($response, $request));

        // and finally send out the response
        $response->send();
    }

    /*
     * MODULES MANAGEMENT
     */
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
        $config = Config::read($module->getModuleDir() .'config/', $this->getEnv());
        $config->apply($this->getConfig()->getNamespace($name));
        $module->setConfig($config);

        // inject application and the service container
        $module->setApplication($this);
        $module->setContainer($this->container);
        
        // finally add the module to the module registry
        $this->_modules[$name] = $module;

        // let the module boot itself as well
        $module->boot();

        // also read routes from this module
        $this->getRouter()->readModuleRoutes($module);

        return $module;
    }

    /*
     * SETTERS AND GETTERS
     */
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
        return Framework::getFramework()->getApplicationDir();
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
     * @return Logger
     */
    final public function getLogger() {
        return $this->_logger;
    }

    /**
     * Returns the current HTTP Request that is being handled by the application.
     * 
     * @return Request
     */
    public function getRequest() {
        return $this->_request;
    }

}