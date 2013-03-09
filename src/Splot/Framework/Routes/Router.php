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

use Splot\Log\LogContainer;
use Splot\Log\Logger;

use Splot\Framework\HTTP\Request;
use Splot\Framework\Modules\AbstractModule;
use Splot\Framework\Routes\AbstractRoute;
use Splot\Framework\Routes\RouteMeta;
use Splot\Framework\Routes\Exceptions\RouteNotFoundException;
use Splot\Framework\Routes\Exceptions\InvalidRouteException;

class Router
{

    /**
     * Router's logger.
     * 
     * @var Logger
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
    public function __construct() {
        $this->_logger = LogContainer::create('Routing');
    }

    /**
     * Reads routes for the given module and registers them automatically.
     * 
     * @param AbstractModule $module Module object.
     */
    public function readModuleRoutes(AbstractModule $module) {
        $name = $module->getName();
        $routesDir = $module->getModuleDir() .'Routes';
        if (!is_dir($routesDir)) {
            return;
        }

        $moduleNamespace = $module->getNamespace() . NS .'Routes'. NS;
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
                    $router->addRoute($name .':'. $namespace . $rawClass, $class, $module->getName(), $module->getRoutesPrefix() . $class::_getPattern());
                }
            }
        };

        // scan the module
        $scan($routesDir, '', $scan);
    }

    /**
     * Registers a route.
     * 
     * @param string $name Name of the route.
     * @param string $class Class name for the route.
     * @param string $moduleName [optional] Module name to which this route belongs.
     * @param string $pattern [optional] Optional URL matching pattern for this route, will override the one specified in route's class.
     * @param array $methods [optional] Optional map of HTTP methods to class methods, as defined in routes.
     * @return RouteMeta Object containing meta information about the created route.
     * 
     * @throws InvalidRouteException When given route class is not extending AbstractRoute.
     * @throws InvalidRouteException When cannot find a non-empty pattern.
     * @throws InvalidRouteException When route method specified for an HTTP method is not implemented or not callable by router (must be non-static and public).
     */
    public function addRoute($name, $class, $moduleName = null, $pattern = null, array $methods = array()) {
        // must extend AbstractRoute
        if (!is_subclass_of($class, 'Splot\Framework\Routes\AbstractRoute', true)) {
            throw new InvalidRouteException('Route "'. $class .'" must extend "Splot\Framework\Routes\AbstractRoute".');
        }

        /**
         * @var string Pattern under which this route is reachable.
         */
        $pattern = (empty($pattern)) ? $class::_getPattern() : $pattern;

        if (empty($pattern)) {
            throw new InvalidRouteException('Route "'. $class .'" must specify a pattern under which it will be visible.');
        }

        $methods = (empty($methods)) ? $class::_getMethods() : array_merge(array(
            'get' => 'execute',
            'post' => 'execute',
            'put' => 'execute',
            'delete' => 'execute'
        ), $methods);

        // store various metadata about this route
        $routeMeta = new RouteMeta($name, $class, $pattern, $methods, $moduleName);
        $this->_routes[$name] = $routeMeta;

        return $routeMeta;
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

        foreach($routes as $name => $routeMeta) {
            if ($routeMeta->willRespondToRequest($url, $method)) {
                return $routeMeta;
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

        $routeMeta = $this->_routes[$name];
        return $routeMeta->generateUrl($params);
    }

    /*
     * SETTERS AND GETTERS
     */
    /**
     * Returns all registered routes.
     * 
     * @return array
     */
    public function getRoutes() {
        return $this->_routes;
    }

    /**
     * Returns meta data about the given route.
     * 
     * @return array
     */
    public function getRoute($name) {
        return $this->_routes[$name];
    }

}