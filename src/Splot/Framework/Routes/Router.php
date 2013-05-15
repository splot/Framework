<?php
/**
 * Splot Framework router.
 * 
 * @package SplotFramework
 * @subpackage Routes
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\Routes;

use Psr\Log\LoggerInterface;

use Splot\Framework\Controller\AbstractController;
use Splot\Framework\HTTP\Request;
use Splot\Framework\Modules\AbstractModule;
use Splot\Framework\Routes\Route;
use Splot\Framework\Routes\Exceptions\RouteNotFoundException;
use Splot\Framework\Routes\Exceptions\InvalidControllerException;

class Router
{

    /**
     * Router's logger.
     * 
     * @var LoggerInterface
     */
    private $_logger;

    /**
     * Routes container.
     * 
     * @var array
     */
    private $_routes = array();

    /**
     * Constructor.
     */
    public function __construct(LoggerInterface $logger) {
        $this->_logger = $logger;
    }

    /**
     * Reads routes for the given module and registers them automatically.
     * 
     * @param AbstractModule $module Module object.
     */
    public function readModuleRoutes(AbstractModule $module) {
        $name = $module->getName();
        $routesDir = $module->getModuleDir() .'Controllers';
        if (!is_dir($routesDir)) {
            return;
        }

        $moduleNamespace = $module->getNamespace() . NS .'Controllers'. NS;
        $router = $this;

        // register a closure so we can recursively scan the routes directory
        $scan = function($dir, $namespace, $self) use ($name, $moduleNamespace, $module, $router) {
            $namespace = ($namespace) ? trim($namespace, NS) . NS : '';
            
            $files = scandir($dir);
            foreach($files as $file) {
                // ignore . and ..
                if (in_array($file, array('.', '..'))) continue;

                // if directory then go recursively
                if (is_dir($dir . DS . $file)) {
                    $self($dir . DS . $file, $namespace . $file, $self);
                    continue;
                }

                $file = explode('.', $file);
                $rawClass = $file[0];
                $class = $moduleNamespace . $namespace . $rawClass;

                // class_exists autoloads a file
                if (class_exists($class)) {
                    $router->addRoute($name .':'. $namespace . $rawClass, $class, $module->getName(), $module->getUrlPrefix() . $class::_getUrl());
                }
            }
        };

        // scan the module
        $scan($routesDir, '', $scan);
    }

    /**
     * Registers a route.
     * 
     * @param string $name Name of the controller.
     * @param string $controllerClass Class name for the controller.
     * @param string $moduleName [optional] Module name to which this route belongs.
     * @param string $urlPattern [optional] Optional URL matching pattern for this controller, will override the one specified in controller's class.
     * @param array $methods [optional] Optional map of HTTP methods to class methods, as defined in controller.
     * @return Route
     * 
     * @throws InvalidRouteException When given route class is not extending AbstractController.
     * @throws InvalidRouteException When cannot find a non-empty pattern.
     * @throws InvalidRouteException When controller method specified for an HTTP method is not implemented or not callable by router (must be non-static and public).
     */
    public function addRoute($name, $controllerClass, $moduleName = null, $urlPattern = null, array $methods = array()) {
        // must extend AbstractController
        $abstractControllerClass = AbstractController::__class();
        if (!is_subclass_of($controllerClass, $abstractControllerClass, true)) {
            throw new InvalidControllerException('Route "'. $controllerClass .'" must extend "'. $abstractControllerClass .'".');
        }

        /** @var string URL pattern under which this controller is reachable. */
        $urlPattern = (empty($urlPattern)) ? $controllerClass::_getUrl() : $urlPattern;

        if (empty($urlPattern)) {
            throw new InvalidRouteException('Controller "'. $controllerClass .'" must specify a URL pattern under which it will be visible. Please set "'. $controllerClass .'::$_url" property.');
        }

        $methods = (empty($methods)) ? $controllerClass::_getMethods() : array_merge(array(
            'get' => 'execute',
            'post' => 'execute',
            'put' => 'execute',
            'delete' => 'execute'
        ), $methods);

        // register this as a route
        $route = new Route($name, $controllerClass, $urlPattern, $methods, $moduleName);
        $this->_routes[$name] = $route;

        return $route;
    }

    /**
     * Tries to find a route for the given request.
     * 
     * @param Request $request
     * @return array|bool Array of information about a found route or false if no route found.
     */
    public function getRouteForRequest(Request $request) {
        return $this->getRouteForUrl($request->getPathInfo(), $request->getMethod());
    }

    /**
     * Tries to find a route for the given URL and HTTP method.
     * 
     * @param string $url URL to look for in routes.
     * @param string $method [optional] HTTP method. One of the following: GET, POST, PUT, DELETE. Default: GET.
     * @return array|bool Array of information about a found route or false if no route found.
     */
    public function getRouteForUrl($url, $method = 'GET') {
        $method = strtolower($method);
        $routes = $this->getRoutes();

        foreach($routes as $name => $route) {
            if ($route->willRespondToRequest($url, $method)) {
                return $route;
            }
        }

        return false;
    }

    /**
     * Generates a URL for the given route name with the given parameters.
     * 
     * Parameters that aren't included in the route will be added as query string.
     * 
     * @param string $name Name of the route to generate URL for.
     * @param array $params [optional] Array of route parameters.
     * @return string
     * 
     * @throws RouteNotFoundException When there is no route with the given name.
     */
    public function generate($name, array $params = array()) {
        if (!isset($this->_routes[$name])) {
            throw new RouteNotFoundException('There is no route called "'. $name .'" registered.');
        }

        $route = $this->_routes[$name];
        return $route->generateUrl($params);
    }

    /*****************************************
     * SETTERS AND GETTERS
     *****************************************/
    /**
     * Returns all registered routes.
     * 
     * @return array
     */
    public function getRoutes() {
        return $this->_routes;
    }

    /**
     * Returns the route with the given name.
     * 
     * @return Route
     */
    public function getRoute($name) {
        return $this->_routes[$name];
    }

}