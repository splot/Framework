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
use MD\Foundation\Debug\Timer;
use MD\Foundation\Exceptions\InvalidReturnValueException;
use MD\Foundation\Exceptions\NotFoundException;
use MD\Foundation\Exceptions\NotUniqueException;

use Splot\Framework\Framework;
use Splot\Framework\Config\Config;
use Splot\Framework\Controller\ControllerResponse;
use Splot\Framework\HTTP\Request;
use Splot\Framework\HTTP\Response;
use Splot\Framework\DependencyInjection\ServiceContainer;
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
     * @var ServiceContainer
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
    private $phase = Framework::PHASE_INIT;

    /**
     * Bootstrap the application.
     *
     * This method is called right at the beginning of the process (request) lifecycle.
     * It's purpose is to register required services in the container. You can overwrite
     * this method if you want to register your custom services, but you need to
     * register specific services under specific names.
     *
     * If you overwrite it, it is recommended that you call the parent at one point.
     *
     * See documentation for more.
     */
    public function bootstrap() {
        if ($this->phase > Framework::PHASE_BOOTSTRAP) {
            throw new \RuntimeException('Application has already been bootstrapped.');
        }

        // failsafe
        if (!$this->logger) {
            $this->logger = new NullLogger();
        }

        // load application's parameters
        $this->container->loadFromArray(array(
            'parameters' => $this->loadParameters()
        ));

        // also load other potential files into the container
        $configDir = $this->container->getParameter('config_dir');
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
     * This method is called by Splot Framework during the configuration phase.
     *
     * At this point all modules have been added to the module list and all parameters and configs have
     * been loaded. Therefore it is a good place to configure some behavior based on that
     * information.
     *
     * The purpose of it is to perform any additional configuration on the application's part
     * and register any application wide services. This is a better place to register
     * your services than ::bootstrap() method as generally, bootstrap() method should not be
     * overwritten unless you know what you're doing.
     */
    public function configure() {
        if ($this->phase > Framework::PHASE_CONFIGURE) {
            throw new \RuntimeException('Application has already been configured.');
        }

        $config = $this->getConfig();

        $this->container->setParameter('log_file', $config->get('log_file'));
        $this->container->setParameter('log_threshold', $config->get('log_threshold'));
        
        $this->setLogger($this->container->get('logger'));
    }

    /**
     * This method is called by Splot Framework during the run phase, so right before it will
     * handle a request or a CLI command.
     *
     * This is a place where all services from all modules have been registered and the whole app
     * is fully bootstrapped and fully configured so you can add some global application-wide
     * logic or behavior here.
     */
    public function run() {
        
    }

    /**
     * This method should return an array of any custom parameters that you want to register
     * in the dependency injection container.
     *
     * By default, it will search for a file `parameters.php` in the config dir and include it 
     * if it exists and return the array this file should return.
     *
     * However, you can overwrite this method and load the parameters from whatever source you want.
     *
     * @return array
     */
    public function loadParameters() {
        $parametersFile = $this->container->getParameter('config_dir') .'parameters.php';
        return is_file($parametersFile) ? include $parametersFile : array();
    }

    /**
     * Loads modules for the application.
     *
     * You must implement this method and it should return an array of module objects that you want
     * loaded in your application.
     *
     * @return array
     */
    abstract public function loadModules();

    /**
     * Adds a module to the application.
     * 
     * @param AbstractModule $module Module to be added.
     *
     * @throws NotUniqueException When module name is not unique and its already been registered.
     * @throws \RuntimeException When application has already been bootstrapped and its too late.
     */
    final public function addModule(AbstractModule $module) {
        if ($this->phase > Framework::PHASE_BOOTSTRAP) {
            throw new \RuntimeException('Application has been already bootstrapped and it is too late to add new modules.');
        }

        $name = $module->getName();
        if ($this->hasModule($name)) {
            throw new NotUniqueException('Module with name "'. $name .'" is already registered in the application.');
        }

        $this->modules[$name] = $module;

        // inject the container
        $module->setContainer($this->container);
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

            $this->logger->debug('Received request for {uri}', array(
                'uri' => $request->getRequestUri(),
                'request' => $request,
                '_timer' => $this->container->get('splot.timer')->step('Received request')
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

        $timer = $this->container->get('splot.timer');
        $this->logger->debug('Will send response', array(
            'time' => $timer->getDuration(),
            'memory' => $timer->getCurrentMemoryPeak(),
            '_timer' => $timer->step('Will send response')
        ));

        // trigger WillSendResponse event for any last minute changes
        $this->container->get('event_manager')->trigger(new WillSendResponse($response, $request));

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
     * @return ServiceContainer
     */
    public function getContainer() {
        return $this->container;
    }

    /**
     * Sets the dependency injection service container.
     * 
     * @param ServiceContainer $container The container.
     *
     * @throws \RuntimeException When trying to override a previously set container.
     */
    final public function setContainer(ServiceContainer $container) {
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
    final public function getPhase() {
        return $this->phase;
    }

    /**
     * Sets the current application phase.
     * 
     * @param int $phase One of `Framework::PHASE_*` constants.
     */
    final public function setPhase($phase) {
        $this->phase = $phase;
    }

    /**
     * Returns class name of the application.
     * 
     * @return string
     */
    final public static function __class() {
        return get_called_class();
    }

}