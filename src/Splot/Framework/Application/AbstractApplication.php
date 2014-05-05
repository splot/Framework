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

use MD\Clog\Writers\FileLogger;
use MD\Clog\Writers\MemoryLogger;

use Symfony\Component\Filesystem\Filesystem;

use Splot\Cache\Store\FileStore;
use Splot\Cache\CacheProvider;

use Splot\EventManager\EventManager;

use Splot\Framework\Framework;
use Splot\Framework\Config\Config;
use Splot\Framework\Console\Console;
use Splot\Framework\Controller\ControllerResponse;
use Splot\Framework\HTTP\Request;
use Splot\Framework\HTTP\Response;
use Splot\Framework\DependencyInjection\ServiceContainer;
use Splot\Framework\Modules\AbstractModule;
use Splot\Framework\Routes\Exceptions\RouteNotFoundException;
use Splot\Framework\Routes\Route;
use Splot\Framework\Routes\Router;
use Splot\Framework\Events\ControllerDidRespond;
use Splot\Framework\Events\ControllerWillRespond;
use Splot\Framework\Events\DidReceiveRequest;
use Splot\Framework\Events\DidNotFindRouteForRequest;
use Splot\Framework\Events\DidFindRouteForRequest;
use Splot\Framework\Events\ExceptionDidOccur;
use Splot\Framework\Events\WillSendResponse;
use Splot\Framework\Log\Clog;
use Splot\Framework\Process\Process;
use Splot\Framework\Resources\Finder;

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
     * Internal flag for marking that bootstrap phase has finished.
     * 
     * @var boolean
     */
    protected $bootstrapped = false;

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
        if ($this->bootstrapped) {
            throw new \RuntimeException('Application has already been bootstrapped.');
        }

        // set required directories in parameters
        $applicationDir = dirname(Debugger::getClassFile(get_called_class())) . DS;
        $this->container->setParameter('application_dir', $applicationDir);
        $this->container->setParameter('root_dir', $applicationDir . '..' . DS);
        $this->container->setParameter('config_dir', $applicationDir . 'config' . DS);
        $this->container->setParameter('cache_dir', $applicationDir . 'cache' . DS);
        $this->container->setParameter('web_dir', $this->container->getParameter('root_dir') . 'web' . DS);

        // load application's parameters
        $loadedParameters = $this->loadParameters();
        foreach($loadedParameters as $key => $value) {
            $this->container->setParameter($key, $value);
        }

        $this->container->set('clog', function() {
            return new Clog();
        });

        // now register some required services
        $this->container->set('logger_provider', function($c) {
            return $c->get('clog');
        });

        $this->setLogger($this->container->get('logger_provider')->provide('Application')); // going through a setter for type hinting
        $this->container->set('logger', $this->logger);

        $this->container->set('event_manager', function($c) {
            return new EventManager($c->get('logger_provider')->provide('Event Manager'));
        });

        $this->container->set('router', function($c) {
            $config = $c->get('config');
            return new Router(
                $c->get('logger_provider')->provide('Router'),
                $config->get('router.host'),
                $config->get('router.protocol'),
                $config->get('router.port')
            );
        });

        $this->container->set('resource_finder', function($c) {
            return new Finder($c->get('application'));
        });

        $this->container->set('process', function() {
            return new Process();
        });

        $this->container->set('console', function($c) {
            return new Console(
                $c->get('application'),
                $c->get('logger_provider')->provide('Console')
            );
        });
    }

    /**
     * This method should return an array of any custom parameters that you want to register
     * in the dependency injection container.
     *
     * By default, it will search for a file "config/parameters.php" in the application dir and include it 
     * if it exists and return the array this file should return.
     *
     * However, you can overwrite this method and load the parameters from whatever source you want.
     *
     * @return array
     */
    public function loadParameters() {
        $parametersFile = $this->container->getParameter('config_dir') .'parameters.php';
        if (is_file($parametersFile)) {
            // load it here so that $parameters is available in that parameters file
            $parameters = $this->container->getParameters();
            return include $parametersFile;
        }

        return array();
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
        if ($this->bootstrapped) {
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
        $config = $this->getConfig();

        // register filesystem service
        $this->container->set('filesystem', function() {
            return new Filesystem();
        });

        // set file writer in Clog
        $this->container->set('clog.writer.file', new FileLogger($config->get('log_file'), $config->get('log_threshold')));
        $this->container->set('clog.writer.memory', new MemoryLogger());
        $this->container->get('clog')->addWriter($this->container->get('clog.writer.file'));

        // only register memory writer for web requests, otherwise it could easily fill up all memory
        // (especially for long lasting processes, e.g. workers)
        if ($this->container->getParameter('mode') === Framework::MODE_WEB) {
            $this->container->get('clog')->addWriter($this->container->get('clog.writer.memory'));
        }

        /*****************************************************
         * REGISTER CACHES
         *****************************************************/
        $this->container->set('cache.store.file', function($c) {
            return new FileStore(array(
                'dir' => $c->getParameter('cache_dir')
            ));
        });

        $this->container->set('cache_provider', function($c) {
            return new CacheProvider($c->get('cache.store.file'), array(
                'stores' => array(
                    'file' => $c->get('cache.store.file')
                ),
                'global_namespace' => $c->getParameter('env')
            ));
        });

        $this->container->set('cache', function($c) {
            return $c->get('cache_provider')->provide('application');
        });

        // register other stores
        foreach($config->get('cache.stores') as $name => $storeConfig) {
            if (!isset($storeConfig['class'])) {
                throw new \RuntimeException('Store config has to have a "class" key defined.');
            }

            $store = new $storeConfig['class']($storeConfig);

            // register in cache provider and service
            $this->container->get('cache_provider')->registerStore($name, $store);
            $this->container->set('cache.store.'. $name, $store);
        }

        // add other defined caches
        foreach($config->get('cache.caches') as $name => $storeName) {
            $this->container->set('cache.'. $name, function($c) use ($name, $storeName) {
                return $c->get('cache_provider')->provide($name, $storeName);
            });
        }
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
     * Mark the bootstrap phase as finished.
     *
     * For internal Splot use only.
     */
    final public function finishBootstrap() {
        $this->bootstrapped = true;
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