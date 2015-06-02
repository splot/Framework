<?php
/**
 * Abstract controller class. All Splot Framework controllers should extend it.
 * 
 * Mostly contains some static meta data about a controller and its route.
 * 
 * @package SplotFramework
 * @subpackage Controller
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\Controller;

use Splot\DependencyInjection\ContainerInterface;

abstract class AbstractController
{

    /**
     * URL pattern under which the controller is reachable.
     * 
     * Can specify parameters in the form of {name} and add constraints in the form of:
     *  - {id:d} - only digits (this is the only possible constraint at the moment)
     * 
     * @var string
     */
    protected static $_url = '/';

    /**
     * HTTP request methods available for this URL as well as function names that should be executed for them.
     * 
     * Keys are prefered to be lowercase. Accepted keys: get/post/put/delete.
     * The controller has to implement the specified functions for specified methods.
     * 
     * Default value for all methods is "index".
     * 
     * If a method is set to false then the route will not be reachable with that method.
     * It has to be specifically specified. If ommitted it will default to "index".
     * 
     * @var array
     */
    protected static $_methods = array(
        'get' => 'index',
        'post' => 'index',
        'put' => 'index',
        'delete' => 'index'
    );

    /**
     * Dependency injection service container.
     * 
     * @var ContainerInterface
     */
    protected $container;

    /**
     * Constructor.
     * 
     * @param ContainerInterface $container Dependency injection service container.
     */
    public function __construct(ContainerInterface $container) {
        $this->container = $container;
    }

    /*****************************************
     * HELPERS
     *****************************************/
    /**
     * Returns a service with the given name.
     * 
     * Shortcut to container.
     * 
     * @param string $name Name of the service to return.
     * @return object
     */
    final public function get($name) {
        return $this->getContainer()->get($name);
    }

    /**
     * Returns a parameter with the given name.
     * 
     * Shortcut to container.
     * 
     * @param string $name Name of the parameter to return.
     * @return mixed
     */
    final public function getParameter($name) {
        return $this->getContainer()->getParameter($name);
    }

    /**
     * Renders a view found under the given name with the given variables to be interpolated.
     * 
     * Uses "templating" service that needs to be registered in the container.
     * 
     * @param string $view View name.
     * @param array $data [optional] Any additional variables to be interpolated in the view template.
     * @return string
     */
    final public function render($view, array $data = array()) {
        return $this->get('templating')->render($view, $data);
    }

    /*****************************************
     * SETTERS AND GETTERS
     *****************************************/
    /**
     * Gets the route's URL pattern.
     * 
     * @return string
     */
    final public static function _getUrl() {
        return static::$_url;
    }

    /**
     * Gets the available methods and their functions for the route.
     * 
     * @return array
     */
    final public static function _getMethods() {
        return array_merge(array(
            'get' => 'index',
            'post' => 'index',
            'put' => 'index',
            'delete' => 'index'
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
     * Returns function name (implemented by the controller) to be executed for the given HTTP request method.
     * 
     * @param string $method Name of the HTTP method.
     * @return string
     */
    final public static function _getMethodFunction($method) {
        $method = strtolower($method);
        $methods = static::_getMethods();

        return $methods[$method];
    }

    /**
     * Returns the dependency injection service container.
     * 
     * @return ContainerInterface
     */
    final public function getContainer() {
        return $this->container;
    }

    /**
     * Returns class name of the controller.
     * 
     * @return string
     */
    final public static function __class() {
        return get_called_class();
    }

}
