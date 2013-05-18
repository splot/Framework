<?php
/**
 * Route registered for a controller.
 * 
 * @package SplotFramework
 * @subpackage Routes
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\Routes;

use MD\Foundation\Debug\Debugger;
use MD\Foundation\Exceptions\NotFoundException;
use MD\Foundation\Utils\ArrayUtils;

use Splot\Framework\HTTP\Request;
use Splot\Framework\Routes\Exceptions\InvalidControllerException;
use Splot\Framework\Routes\Exceptions\RouteParameterNotFoundException;

class Route
{

    /**
     * Name of the route.
     * 
     * @var string
     */
    private $_name;

    /**
     * Associated controller class name.
     * 
     * @var string
     */
    private $_controllerClass;

    /**
     * URL pattern for the route.
     * 
     * @var string
     */
    private $_urlPattern;

    /**
     * URL pattern parsed into RegEx for the route.
     * 
     * @var string
     */
    private $_regexp;

    /**
     * Map of HTTP methods translated to the controller class methods and their arguments.
     * 
     * @var array
     */
    private $_methods = array();

    /**
     * Module name to which this route belongs.
     * 
     * @var string
     */
    private $_moduleName;

    /**
     * Constructor.
     * 
     * @param string $name Name of the route.
     * @param string $controllerClass Associated controller class name.
     * @param string $urlPattern URL pattern for the route.
     * @param array $methods Map of HTTP methods to the controller methods.
     * @param string $moduleName [optional] Module name to which this route belongs.
     */
    public function __construct($name, $controllerClass, $urlPattern, $methods, $moduleName = null) {
        $this->_name = $name;
        $this->_controllerClass = $controllerClass;
        $this->_urlPattern = $urlPattern;
        $this->_moduleName = $moduleName;

        // prepare regexp for this route
        $this->_regexp = $this->regexpFromUrlPattern($urlPattern);
        $this->_methods = $this->prepareMethodsInfo($methods);
    }

    /**
     * Checks if this route will respond to the given request.
     * 
     * @param string $url Request URL.
     * @param string $httpMethod Request's HTTP method.
     * @return bool
     */
    public function willRespondToRequest($url, $httpMethod) {
        $matched = preg_match('#^'. $this->getRegExp() .'$#is', $url, $matches);

        // found matching controller for this URL that also accepts this HTTP method
        if ($matched === 1) {
            if ($this->getControllerMethodForHttpMethod($httpMethod)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Figures out what arguments should be passed to route method based on the given request.
     * 
     * @param string $url Request URL.
     * @param string $httpMethod HTTP method (GET/PUT/POST/DELETE).
     * @param Request $request The request for this route.
     * @return array Array of arguments.
     */
    public function getRouteMethodArgumentsForUrl($url, $httpMethod, Request $request) {
        $matched = preg_match('#^'. $this->getRegExp() .'$#is', $url, $matches);

        if ($matched === 0) {
            throw new NotFoundException();
        }

        $method = $this->_methods[strtolower($httpMethod)];
        if (!$method['method']) {
            throw new NotFoundException();
        }

        $arguments = array();

        foreach($method['params'] as $i => $param) {
            // inject request instead of a match
            if ($param['class'] && Debugger::isExtending($param['class'], Request::__class(), true)) {
                $arguments[$i] = $request;
                continue;
            }

            $arguments[$i] = (isset($matches[$param['name']])) ? $matches[$param['name']] : $param['default'];
        }

        return $arguments;
    }

    /**
     * Generates a URL for this route, using the given parameters.
     * 
     * If some parameters aren't in the pattern, then they will be attached as a query string.
     * 
     * @param array $params Route parameters.
     */
    public function generateUrl(array $params = array()) {
        $routeName = $this->getName();

        $url = preg_replace_callback('/(\{([\w:]+)\})(\?)?/is', function($matches) use (&$params, $routeName) {
            if (empty($matches[2])) {
                return $matches[0];
            }

            $constraints = explode(':', $matches[2]);
            $name = array_shift($constraints);
            $optional = (isset($matches[3]) && $matches[3] === '?');

            if (!isset($params[$name])) {
                if (!$optional) {
                    throw new RouteParameterNotFoundException('Could not find parameter "'. $name .'" for route "'. $routeName .'".');
                } else {
                    return '';
                }
            }

            $param = $params[$name];
            unset($params[$name]);

            /** @todo Should also check the constraints before injecting the item. */

            return $param;
        }, $this->getUrlPattern());

        // remove all optional characters from the route
        $url = preg_replace('/(\/\/\?)/is', '/', $url); // two slashes followed by a ? (ie. //? ) change to a single slash (ie. /).
        $url = str_replace('?', '', $url); // remove any other ? marks

        if (!empty($params)) {
            $url .= '?'. ArrayUtils::toQueryString($params);
        }

        return $url;
    }

    /*****************************************
     * HELPERS
     *****************************************/
    /**
     * Transforms URL pattern from a route into a fully working RegExp pattern.
     * 
     * @param string $urlPattern URL pattern to be transformed.
     * @return string RegExp pattern.
     */
    private function regexpFromUrlPattern($urlPattern) {
        $regexp = addslashes($urlPattern);
        $regexp = preg_replace_callback('/(\{([\w:]+)\})/is', function($matches) {
            if (empty($matches[2])) {
                return $matches[0];
            }

            $constraints = explode(':', $matches[2]);
            $name = array_shift($constraints);

            /**
             * @var string Constraints translated to regexp.
             */
            $regexpConstraints = '\w+';

            // if any constraints specified then parse them
            if (!empty($constraints)) {
                $regexpConstraints = '';
                foreach($constraints as $constraint) {
                    if ($constraint === 'int') {
                        $regexpConstraints .= '\d+';
                    }
                }
            }

            return '(?P<'. $name .'>'. $regexpConstraints .')';
        }, $regexp);

        return $regexp;
    }

    /**
     * Parses information about controller class methods that respond to HTTP methods.
     * 
     * @param array $methodsMap The result of Controller::_getMethods().
     * @return array An array of info about the parsed methods.
     */
    private function prepareMethodsInfo(array $methodsMap) {
        /**
         * @var array Configuration for HTTP methods for this route.
         */
        $methods = array(
            'get' => array(
                'method' => $methodsMap['get'],
                'params' => array()
            ),
            'post' => array(
                'method' => $methodsMap['post'],
                'params' => array()
            ),
            'put' => array(
                'method' => $methodsMap['put'],
                'params' => array()
            ),
            'delete' => array(
                'method' => $methodsMap['delete'],
                'params' => array()
            )
        );

        // check methods availability
        // use reflection to do this
        // this way we also make sure that inherited methods don't count toward this, each route has to specifically implement it itself!
        $controllerReflection = new \ReflectionClass($this->getControllerClass());

        foreach(array_keys($methods) as $method) {
            // if responds to this method than should implement it appropriately
            if ($methods[$method]['method']) {

                try {
                    $methodReflection = $controllerReflection->getMethod($methods[$method]['method']);

                    if (!$methodReflection->isPublic() || $methodReflection->isStatic()) {
                        throw new InvalidControllerException('Controller "'. $this->getControllerClass() .'" does not have a public non-static method called "'. $methods[$method]['method'] .'" for '. strtoupper($method) .' requests.');
                    }

                    // also, while we're at it, create parameters map
                    $parametersReflection = $methodReflection->getParameters();

                    foreach($parametersReflection as $param) {
                        $paramClass = $param->getClass();
                        $optional = $param->isDefaultValueAvailable();

                        $methods[$method]['params'][] = array(
                            'name' => $param->getName(),
                            'class' => ($paramClass !== null) ? $paramClass->getName() : null,
                            'optional' => $optional,
                            'default' => ($optional) ? $param->getDefaultValue() : null
                        );
                    }
                } catch(\ReflectionException $e) {
                    // reroute the exception to more understandable
                    throw new InvalidControllerException('Controller "'. $this->getControllerClass() .'" does not have a method called "'. $methods[$method]['method'] .'" for '. strtoupper($method) .' requests.', $e->getCode(), $e);
                }
            }
        }

        return $methods;
    }

    /*****************************************
     * GETTERS
     *****************************************/
    /**
     * Returns name of the route.
     * 
     * @return string
     */
    public function getName() {
        return $this->_name;
    }

    /**
     * Returns class name of the controller.
     * 
     * @return string
     */
    public function getControllerClass() {
        return $this->_controllerClass;
    }

    /**
     * Returns URL pattern for the route.
     * 
     * @return string
     */
    public function getUrlPattern() {
        return $this->_urlPattern;
    }

    /**
     * Returns URL pattern parsed into RegEx for the route.
     * 
     * @return string
     */
    public function getRegExp() {
        return $this->_regexp;
    }

    /**
     * Returns map of HTTP methods translated to the route class methods and their arguments.
     * 
     * @return array
     */
    public function getMethods() {
        return $this->_methods;
    }

    /**
     * Returns module name to which this route belongs.
     * 
     * @return string
     */
    public function getModuleName() {
        return $this->_moduleName;
    }

    /**
     * Returns name of the controller class method that should be called for the given HTTP method.
     * 
     * @param string $httpMethod One of GET/POST/PUT/DELETE.
     * @return string
     */
    public function getControllerMethodForHttpMethod($httpMethod) {
        $methods = $this->getMethods();
        return $methods[strtolower($httpMethod)]['method'];
    }

}