<?php
/**
 * Container class for some meta information about a route.
 * 
 * @package SplotFramework
 * @subpackage Routes
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\Routes;

use Splot\Foundation\Debug\Debugger;
use Splot\Foundation\Exceptions\NotFoundException;
use Splot\Foundation\Utils\ArrayUtils;

use Splot\Framework\Request\HttpRequest;
use Splot\Framework\Routes\Exceptions\RouteParameterNotFoundException;

class RouteMeta
{

	/**
	 * Name of the route.
	 * 
	 * @var string
	 */
	private $_name;

	/**
	 * Class name of the route.
	 * 
	 * @var string
	 */
	private $_class;

	/**
	 * URL pattern for the route.
	 * 
	 * @var string
	 */
	private $_pattern;

	/**
	 * URL pattern parsed into RegEx for the route.
	 * 
	 * @var string
	 */
	private $_regexp;

	/**
	 * Map of HTTP methods translated to the route class methods and their arguments.
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
	 * @param string $class Class name of the route.
	 * @param string $pattern URL pattern for the route.
	 * @param array $methods Map of HTTP methods to the class methods.
	 * @param string $moduleName [optional] Module name to which this route belongs.
	 */
	public function __construct($name, $class, $pattern, $methods, $moduleName = null) {
		$this->_name = $name;
		$this->_class = $class;
		$this->_pattern = $pattern;
		$this->_moduleName = $moduleName;

		// prepare regexp for this route
		$this->_regexp = $this->regexpFromPattern($pattern);
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

		// found matching route for this URL that also accepts this HTTP method
		if ($matched === 1) {
			if ($this->getRouteMethodForHttpMethod($httpMethod)) {
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
	 * @param HttpRequest $request The request for this route.
	 * @return array Array of arguments.
	 */
	public function getRouteMethodArgumentsForUrl($url, $httpMethod, HttpRequest $request) {
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
			if ($param['class'] && Debugger::isExtending($param['class'], 'Splot\Framework\Request\HttpRequest', true)) {
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
		}, $this->getPattern());

		// remove all optional characters from the route
		$url = preg_replace('/(\/\/\?)/is', '/', $url); // two slashes followed by a ? (ie. //? ) change to a single slash (ie. /).
		$url = str_replace('?', '', $url); // remove any other ? marks

		if (!empty($params)) {
			$url .= '?'. ArrayUtils::toQueryString($params);
		}

		return $url;
	}

	/*
	 * HELPERS
	 */
	/**
	 * Transforms URL pattern from a route into a fully working RegExp pattern.
	 * 
	 * @param string $pattern URL pattern to be transformed.
	 * @return string RegExp pattern.
	 */
	private function regexpFromPattern($pattern) {
		$regexp = addslashes($pattern);
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
	 * Parses information about class methods that resport to HTTP methods.
	 * 
	 * @param array $methodsMap The result of Route::_getMethods().
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
		$routeReflection = new \ReflectionClass($this->getRouteClass());

		foreach(array_keys($methods) as $method) {
			// if responds to this method than should implement it appropriately
			if ($methods[$method]['method']) {

				try {
					$methodReflection = $routeReflection->getMethod($methods[$method]['method']);

					if (!$methodReflection->isPublic() || $methodReflection->isStatic()) {
						throw new InvalidRouteException('Route "'. $this->getRouteClass() .'" does not have a public non-static method called "'. $methods[$method] .'" for "'. strtoupper($method) .'" requests.');
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
					throw new InvalidRouteException('Route "'. $this->getRouteClass() .'" does not have a method called "'. $methods[$method] .'" for "'. strtoupper($method) .'" requests.');
				}
			}
		}

		return $methods;
	}

	/*
	 * GETTERS
	 */
	/**
	 * Returns name of the route.
	 * 
	 * @return string
	 */
	public function getName() {
		return $this->_name;
	}

	/**
	 * Returns class name of the route.
	 * 
	 * @return string
	 */
	public function getRouteClass() {
		return $this->_class;
	}

	/**
	 * Returns URL pattern for the route.
	 * 
	 * @return string
	 */
	public function getPattern() {
		return $this->_pattern;
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
	 * Returns name of the route class method that should be executed for the given HTTP method.
	 * 
	 * @param string $httpMethod One of GET/POST/PUT/DELETE.
	 * @return string
	 */
	public function getRouteMethodForHttpMethod($httpMethod) {
		$methods = $this->getMethods();
		return $methods[strtolower($httpMethod)]['method'];
	}

}