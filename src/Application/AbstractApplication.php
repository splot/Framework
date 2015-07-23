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
use Psr\Log\LoggerAwareInterface;
use Psr\Log\NullLogger;

use MD\Foundation\Debug\Debugger;
use MD\Foundation\Exceptions\InvalidReturnValueException;
use MD\Foundation\Exceptions\NotFoundException;
use MD\Foundation\Exceptions\NotUniqueException;
use MD\Foundation\Utils\StringUtils;

use Splot\Cache\Store\FileStore;
use Splot\Cache\Cache;

use Splot\DependencyInjection\ContainerCacheInterface;
use Splot\DependencyInjection\ContainerInterface;

use Splot\Framework\Framework;
use Splot\Framework\Config\Config;
use Splot\Framework\Controller\ControllerResponse;
use Splot\Framework\HTTP\Request;
use Splot\Framework\HTTP\Response;
use Splot\Framework\DependencyInjection\ContainerCache;
use Splot\Framework\Modules\AbstractModule;
use Splot\Framework\Routes\Exceptions\RouteNotFoundException;
use Splot\Framework\Routes\Route;
use Splot\Framework\Events\ControllerDidRespond;
use Splot\Framework\Events\ControllerWillRespond;
use Splot\Framework\Events\DidReceiveRequest;
use Splot\Framework\Events\DidNotFindRouteForRequest;
use Splot\Framework\Events\DidFindRouteForRequest;
use Splot\Framework\Events\ExceptionDidOccur;
use Splot\Framework\Events\WillSendResponse;

abstract class AbstractApplication implements LoggerAwareInterface
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
     * Application's dependency injection service container.
     * 
     * @var ContainerInterface
     */
    protected $container;

    /**
     * Application logger.
     * 
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Container for all application modules.
     * 
     * @var array
     */
    protected $modules = array();

    /**
     * Current application phase.
     * 
     * @var int
     */
    protected $phase = Framework::PHASE_BOOTSTRAP;

    /**
     * Loads modules for the application.
     *
     * You must implement this method and it should return an array of module objects that you want
     * loaded in your application.
     *
     * @param  string  $env   Environment in which the application is running.
     * @param  boolean $debug Is debug mode on or off?
     * @return array
     */
    abstract public function loadModules($env, $debug);

    /**
     * Configures and provides the cache object that should be used for container cache.
     *
     * This method is called by the framework on the very first step of configuration phase
     * at which point the container doesn't exist yet.
     *
     * @param string $env Application environment.
     * @param boolean $debug Debug on or off.
     * @return ContainerCacheInterface
     */
    public function provideContainerCache($env, $debug) {
        $containerCacheDir = dirname(Debugger::getClassFile($this)) .'/cache/'. $env .'/container';
        return new ContainerCache(new FileStore($containerCacheDir), $this->name .'__'. $env);
    }

    /**
     * This method should return an array of any custom parameters that you want to register
     * in the dependency injection container when it is being configured.
     *
     * They are loaded before any other parameters or services.
     *
     * @param string $env Application environment.
     * @param boolean $debug Debug on or off.
     * @return array
     */
    public function loadParameters($env, $debug) {
        return array();
    }

    /**
     * Configure the application, or more specifically the dependency injection container.
     *
     * This method is invoked during configuration phase after all `::configure()` methods of all
     * modules have been invoked.
     *
     * Any configuration of services, parameters, etc. should be done in this method.
     *
     * Note, that after the application has been configured once and the container has been
     * cached, this method will not be invoked until the cache is cleared. Therefore,
     * any configuration to the container should be made in a way that is cacheable, ie.
     * static definitions of services (no use of object or closure services).
     *
     * By default, this method will attempt to load three files from `%config_dir%`:
     *
     *  - `parameters.yml`,
     *  - `parameters.{env}.yml`,
     *  - `services.yml`;
     *
     * where `{env}` is the name of the current environment.
     *
     * None of these files needs to exist.
     *
     * If you are overwriting this method it is advisable to call the parent at one point,
     * unless you are perfectly sure what you are doing.
     */
    public function configure() {
        // copy config values to the container
        $config = $this->getConfig();
        $this->container->setParameter('log_file', $config->get('log_file'));
        $this->container->setParameter('log_level', $config->get('log_level'));
        $this->container->setParameter('cache.enabled', $config->get('cache.enabled'));
        $this->container->setParameter('cache.default_store', '@'. ltrim($config->get('cache.default_store'), '@'));

        $configDir = rtrim($this->container->getParameter('config_dir'), '/') .'/';
        foreach(array(
            'parameters.yml',
            'parameters.'. $this->container->getParameter('env') . '.yml',
            'services.yml'
        ) as $file) {
            try {
                $this->container->loadFromFile($configDir . $file);
            } catch(NotFoundException $e) {}
        }
    }

    /**
     * Run the application.
     *
     * This method is invoked during run phase, after all `::run()` methods of all modules
     * have been invoked.
     *
     * This is a good place to perform any additional configuration that can only be done at
     * runtime or cannot be cached.
     */
    public function run() {
        
    }

    /**
     * Adds a module to the application.
     * 
     * @param AbstractModule $module Module to be added.
     *
     * @throws NotUniqueException When module name is not unique and its already been registered.
     * @throws \RuntimeException When application has already been bootstrapped and its too late.
     */
    public function addModule(AbstractModule $module) {
        if ($this->phase > Framework::PHASE_BOOTSTRAP) {
            throw new \RuntimeException('Application has been already bootstrapped and it is too late to add new modules.');
        }

        $name = $module->getName();
        if ($this->hasModule($name)) {
            throw new NotUniqueException('Module with name "'. $name .'" is already registered in the application.');
        }

        $this->modules[$name] = $module;
    }

    /**
     * Handle the request that was sent to the application.
     * 
     * @param Request $request
     * @return Response
     * 
     * @throws NotFoundException When route has not been found and there wasn't any event listener to handle DidNotFindRouteForRequest event.
     */
    public function handleRequest(Request $request) {
        $eventManager = $this->container->get('event_manager');

        try {
            $this->container->set('request', $request);

            $this->logger->debug('Received request for {method} {uri}', array(
                'uri' => $request->getRequestUri(),
                'method' => $request->getMethod(),
                'request' => $request,
                '@stat' => 'splot.request'
            ));

            // trigger DidReceiveRequest event
            $eventManager->trigger(new DidReceiveRequest($request));

            /** @var Route Meta information about the found route. */
            $route = $this->container->get('router')->getRouteForRequest($request);
            if (!$route) {
                $notFoundEvent = new DidNotFindRouteForRequest($request);
                $eventManager->trigger($notFoundEvent);

                if ($notFoundEvent->isHandled()) {
                    return $notFoundEvent->getResponse();
                } else {
                    throw new RouteNotFoundException('Could not find route for "'. $request->getPathInfo() .'".');
                }
            }

            // trigger DidFindRouteForRequest event
            if (!$eventManager->trigger(new DidFindRouteForRequest($route, $request))) {
                $notFoundEvent = new DidNotFindRouteForRequest($request);
                $eventManager->trigger($notFoundEvent);

                if ($notFoundEvent->isHandled()) {
                    return $notFoundEvent->getResponse();
                } else {
                    throw new RouteNotFoundException('Could not find route for "'. $request->getPathInfo() .'" (rendering prevented).');
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
            $eventManager->trigger($exceptionEvent);

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
        $route = $this->container->get('router')->getRoute($name);

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
        // prepare the response for sending (will tweak some headers based on the request)
        $response->prepare($request);

        // trigger WillSendResponse event for any last minute changes
        $this->container->get('event_manager')->trigger(new WillSendResponse($response, $request));

        $timer = $this->container->get('splot.timer');
        $time = $timer->getDuration();
        $memory = $timer->getCurrentMemoryPeak();
        $memoryString = StringUtils::bytesToString($memory);

        $this->logger->debug('Rendering response for {method} {uri} took {time} ms and used {memory} memory.', array(
            'method' => $request->getMethod(),
            'uri' => $request->getRequestUri(),
            'memory' => $memoryString,
            'time' => $time,
            '@stat' => 'splot.render',
            '@time' => $time,
            '@memory' => $memory
        ));

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
        $eventManager = $this->container->get('event_manager');

        $controller = new $class($this->container);

        $willRespondEvent = new ControllerWillRespond($name, $controller, $method, $arguments);
        $eventManager->trigger($willRespondEvent);

        $method = $willRespondEvent->getMethod();
        $arguments = $willRespondEvent->getArguments();
        
        $controllerResponse = new ControllerResponse(call_user_func_array(array($controller, $method), $arguments));
        $eventManager->trigger(new ControllerDidRespond($controllerResponse, $name, $controller, $method, $arguments, $request));

        $response = $controllerResponse->getResponse();

        $this->logger->debug('Executed controller: "{name}"', array(
            'name' => $name,
            'function' => $class .'::'. $method,
            'arguments' => $arguments,
            '_timer' => $this->container->get('splot.timer')->step('Matched route')
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
     * Returns the application config.
     * 
     * @return Config
     */
    public function getConfig() {
        return $this->container->get('config');
    }

    /**
     * Returns the dependency injection service container.
     * 
     * @return ContainerInterface
     */
    public function getContainer() {
        return $this->container;
    }

    /**
     * Sets the dependency injection service container.
     * 
     * @param ContainerInterface $container The container.
     *
     * @throws \RuntimeException When trying to override a previously set container.
     */
    public function setContainer(ContainerInterface $container) {
        if ($this->container) {
            throw new \RuntimeException('Service container already set on the application, cannot overwrite it.');
        }

        $this->container = $container;
    }

    /**
     * Returns the application directory.
     * 
     * @return string
     */
    public function getApplicationDir() {
        return $this->container->getParameter('application_dir');
    }

    /**
     * Returns the application environment.
     * 
     * @return string
     */
    public function getEnv() {
        return $this->container->getParameter('env');
    }

    /**
     * Returns information whether or not application is ran in debug mode.
     * 
     * @return boolean
     */
    public function isDebug() {
        return $this->container->getParameter('debug');
    }

    /**
     * Returns names of registered modules.
     * 
     * @return array
     */
    public function listModules() {
        return array_keys($this->modules);
    }

    /**
     * Returns registered modules.
     * 
     * @return array
     */
    public function getModules() {
        return $this->modules;
    }

    /**
     * Checks if a given module is registered.
     * 
     * @return bool
     */
    public function hasModule($name) {
        return isset($this->modules[$name]);
    }

    /**
     * Returns the given module.
     * 
     * @return AbstractModule
     */
    public function getModule($name) {
        return $this->modules[$name];
    }

    /**
     * Returns the application logger.
     * 
     * @return LoggerInterface
     */
    public function getLogger() {
        return $this->logger;
    }

    /**
     * Set the application logger.
     * 
     * @param LoggerInterface $logger Application logger.
     */
    public function setLogger(LoggerInterface $logger) {
        $this->logger = $logger;
    }

    /**
     * Returns the current application phase.
     *
     * One of `Framework::PHASE_*` constants.
     * 
     * @return int
     */
    public function getPhase() {
        return $this->phase;
    }

    /**
     * Sets the current application phase.
     * 
     * @param int $phase One of `Framework::PHASE_*` constants.
     */
    public function setPhase($phase) {
        if ($phase < $this->phase) {
            throw new \RuntimeException('Cannot set an earlier phase than the current application phase, when trying to set "'. Framework::phaseName($phase) .'" (already on "'. Framework::phaseName($this->phase) .'").');
        }
        $this->phase = $phase;
    }

    /**
     * Returns class name of the application.
     * 
     * @return string
     */
    public static function __class() {
        return get_called_class();
    }

}
