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
use Splot\Framework\Routes\Exceptions\InvalidRouteException;

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
     * Protocol to use when generating full URL's.
     * 
     * @var string
     */
    private $_scheme = 'http://';

    /**
     * Host to use when generating full URL's.
     * 
     * @var string
     */
    private $_host = 'localhost';

    /**
     * Port to use when generating full URL's.
     * 
     * @var int
     */
    private $_port = 80;

    /**
     * Constructor.
     */
    public function __construct(LoggerInterface $logger, $host = 'localhost', $protocol = 'http://', $port = 80) {
        $this->_logger = $logger;
        $this->setHost($host);
        $this->setProtocol($protocol);
        $this->setPort($port);
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
     * @throws InvalidControllerException When given controller class is not extending AbstractController.
     * @throws InvalidRouteException When cannot find a non-empty pattern.
     */
    public function addRoute($name, $controllerClass, $moduleName = null, $urlPattern = null, array $methods = array()) {
        // must extend AbstractController
        $abstractControllerClass = AbstractController::__class();
        if (!is_subclass_of($controllerClass, $abstractControllerClass, true)) {
            throw new InvalidControllerException('Route "'. $controllerClass .'" must extend "'. $abstractControllerClass .'".');
        }

        // if controller url is set to false then it means it's a "private" controller, so can't be reached via url
        // can only be reached using application::render() method
        $private = ($controllerClass::_getUrl() === false) ? true : false;
        if (!$private) {
            /** @var string URL pattern under which this controller is reachable. */
            $urlPattern = (empty($urlPattern)) ? $controllerClass::_getUrl() : $urlPattern;

            if (empty($urlPattern)) {
                throw new InvalidRouteException('Controller "'. $controllerClass .'" must specify a URL pattern under which it will be visible. Please set "'. $controllerClass .'::$_url" property.');
            }
        }

        $methods = (empty($methods)) ? $controllerClass::_getMethods() : array_merge(array(
            'get' => 'index',
            'post' => 'index',
            'put' => 'index',
            'delete' => 'index'
        ), array_change_key_case($methods, CASE_LOWER));

        // register this as a route
        $route = new Route($name, $controllerClass, $urlPattern, $methods, $moduleName, $private);
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
     * @param bool $includeHost [optional] Should the hostname be included? Hostname and protocol need to be set previously.
     *                          Default: false.
     * @return string
     */
    public function generate($name, array $params = array(), $includeHost = false) {
        $route = $this->getRoute($name);

        $host = null;
        if ($includeHost) {
            $host = $this->getProtocolAndHost();
        }

        return $route->generateUrl($params, $host);
    }

    /**
     * Exposes the route pattern so that it can be generated elsewhere (e.g. in JavaScript).
     * 
     * @param string $name Name of the route to expose.
     * @return string
     */
    public function expose($name) {
        $route = $this->getRoute($name);
        return $route->expose();
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
     * 
     * @throws RouteNotFoundException When there is no route with the given name.
     */
    public function getRoute($name) {
        if (!isset($this->_routes[$name])) {
            throw new RouteNotFoundException('There is no route called "'. $name .'" registered.');
        }

        return $this->_routes[$name];
    }

    /**
     * Sets the protocol to use when generating full URL's.
     * 
     * @param string $protocol Protocol to use. E.g. 'http://' or 'https://'.
     */
    public function setProtocol($protocol) {
        $this->_protocol = rtrim($protocol, ':/') .'://';
    }

    /**
     * Returns the protocol to use when generating full URL's.
     * 
     * @return string
     */
    public function getProtocol() {
        return $this->_protocol;
    }

    /**
     * Sets the host to use when generating full URL's.
     * 
     * @param string $host Host name to use.
     */
    public function setHost($host) {
        $this->_host = trim($host, '/');
    }

    /**
     * Returns the host to use when generating full URL's.
     * 
     * @return string
     */
    public function getHost() {
        return $this->_host;
    }

    /**
     * Sets the port to use when generating full URL's.
     * 
     * @param int $port Port number.
     */
    public function setPort($port) {
        $this->_port = intval($port);
    }

    /**
     * Returns the port to use when generating full URL's.
     * 
     * @return int
     */
    public function getPort() {
        return $this->_port;
    }

    /**
     * Returns the full protocol, host and port number to use when generating full URL's.
     * 
     * @return string
     */
    public function getProtocolAndHost() {
        return $this->getProtocol() . $this->getHost() . ($this->getPort() !== 80 ? ':'. $this->getPort() : '') .'/';
    }

}