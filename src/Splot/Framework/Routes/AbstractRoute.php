<?php
/**
 * Abstract route class. All Splot Framework routes should extend it.
 * 
 * Mostly contains some static meta data about a route.
 * 
 * @package SplotFramework
 * @subpackage Routes
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\Routes;

use Splot\Framework\HTTP\Request;
use Splot\Framework\DependencyInjection\ServiceContainer;

abstract class AbstractRoute
{

	/**
	 * URL pattern under which the route is reachable.
	 * 
	 * Can specify parameters in the form of {name} and add constraints in the form of:
	 *  - {id:d} - only digits (this is the only possible constraint at the moment)
	 * 
	 * @var string
	 */
	protected static $_pattern = '/';

	/**
	 * Methods available for this URL as well as function names that should be executed for them.
	 * 
	 * Keys are prefered to be lowercase. Accepted keys: get/post/put/delete.
	 * The route has to implement the specified functions for specified methods.
	 * 
	 * Default value for all methods is "execute".
	 * 
	 * If a method is set to false then the route will not be reachable with that method. It has to be specifically specified. If ommitted it will default to "execute".
	 * 
	 * @var array
	 */
	protected static $_methods = array(
		'get' => 'execute',
		'post' => 'execute',
		'put' => 'execute',
		'delete' => 'execute'
	);

	/**
	 * Dependency injection service container.
	 * 
	 * @var ServiceContainer
	 */
	protected $container;

	/**
	 * Constructor.
	 * 
	 * @param ServiceContainer $container Dependency injection service container.
	 */
	public function __construct(ServiceContainer $container) {
		$this->container = $container;
	}

	/**
	 * Gets the route's URL pattern.
	 * 
	 * @return string
	 */
	final public static function _getPattern() {
		return static::$_pattern;
	}

	/**
	 * Gets the available methods and their functions for the route.
	 * 
	 * @return array
	 */
	final public static function _getMethods() {
		return array_merge(array(
			'get' => 'execute',
			'post' => 'execute',
			'put' => 'execute',
			'delete' => 'execute'
		), array_change_key_case(static::$_methods, CASE_LOWER));
	}

	/**
	 * Checks if the route can respond to the given method.
	 * 
	 * @param string $method Name of the method.
	 * @return bool
	 */
	final public static function _hasMethod($method) {
		$method = strtolower($method);
		$methods = static::_getMethods();

		return $methods[$method] ? true : false;
	}

	/**
	 * Returns function name (implemented by the route) to be executed for the given method.
	 * 
	 * @param string $method Name of the method.
	 * @return string
	 */
	final public static function _getMethodFunction($method) {
		$method = strtolower($method);
		$methods = static::_getMethods();

		return $methods[$method];
	}

	/**
	 * Sets the dependency injection service container.
	 * 
	 * @param ServiceContainer $serviceContainer
	 */
	final public function setContainer(ServiceContainer $serviceContainer) {
		$this->container = $serviceContainer;
	}

	/**
	 * Returns the dependency injection service container.
	 * 
	 * @return ServiceContainer
	 */
	final public function getContainer() {
		return $this->container;
	}

}