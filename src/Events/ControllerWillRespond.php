<?php
/**
 * Event triggered right before a controller will be executed.
 * 
 * @package SplotFramework
 * @subpackage Events
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\Events;

use Splot\EventManager\AbstractEvent;

use Splot\Framework\Controller\AbstractController;

class ControllerWillRespond extends AbstractEvent
{

    /**
     * Name of the controller that will be executed.
     * 
     * @var string
     */
    private $controllerName;

    /**
     * Instance of the controller that will be executed.
     * 
     * @var AbstractController
     */
    private $controller;

    /**
     * Name of the method that will be executed.
     * 
     * @var string
     */
    private $method;

    /**
     * Arguments with which the controller's method will be executed.
     * 
     * @var array
     */
    private $arguments = array();

    /**
     * Constructor.
     * 
     * @param string $controllerName Name of the controller that will be executed.
     * @param AbstractController $controller Instance of the controller that will be executed.
     * @param string $method Name of the method that will be executed.
     * @param array $arguments [optional] Arguments with which the controller's method will be executed.
     */
    public function __construct($controllerName, AbstractController $controller, $method, array $arguments = array()) {
        $this->controllerName = $controllerName;
        $this->controller = $controller;
        $this->method = $method;
        $this->arguments = $arguments;
    }

    /**
     * Returns name of the controller that will be executed.
     * 
     * @return string
     */
    public function getControllerName() {
        return $this->controllerName;
    }

    /**
     * Returns instance of the controller that will be executed.
     * 
     * @return AbstractController
     */
    public function getController() {
        return $this->controller;
    }

    /**
     * Sets the name of the method that will be executed.
     *
     * @param string $method Name of the method that will be executed.
     */
    public function setMethod($method) {
        $this->method = $method;
    }

    /**
     * Returns name of the method that will be executed.
     * 
     * @return string
     */
    public function getMethod() {
        return $this->method;
    }

    /**
     * Sets the arguments with which the controller's method will be executed.
     * 
     * @param array $arguments Arguments with which the controller's method will be executed.
     */
    public function setArguments(array $arguments) {
        $this->arguments = $arguments;
    }

    /**
     * Returns arguments with which the controller's method will be executed.
     * 
     * @return array
     */
    public function getArguments() {
        return $this->arguments;
    }

}