<?php
/**
 * Event triggered after a controller has been executed.
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
use Splot\Framework\Controller\ControllerResponse;

class ControllerDidRespond extends AbstractEvent
{

    /**
     * Response with which the controller responded.
     * 
     * @var ControllerResponse
     */
    private $controllerResponse;

    /**
     * Name of the controller that was executed.
     * 
     * @var string
     */
    private $controllerName;

    /**
     * Instance of the controller that was executed.
     * 
     * @var AbstractController
     */
    private $controller;

    /**
     * Name of the method that was executed.
     * 
     * @var string
     */
    private $method;

    /**
     * Arguments with which the controller's method was executed.
     * 
     * @var array
     */
    private $arguments = array();

    /**
     * Constructor.
     * 
     * @param ControllerResponse $controllerResponse Response with which the controller responded.
     * @param string $controllerName Name of the controller that was executed.
     * @param AbstractController $controller Instance of the controller that was executed.
     * @param string $method Name of the method that was executed.
     * @param array $arguments [optional] Arguments with which the controller's method was executed.
     */
    public function __construct(ControllerResponse $controllerResponse, $controllerName, AbstractController $controller, $method, array $arguments = array()) {
        $this->controllerResponse = $controllerResponse;
        $this->controllerName = $controllerName;
        $this->controller = $controller;
        $this->method = $method;
        $this->arguments = $arguments;
    }

    /**
     * Returns the response with which the controller responded.
     * 
     * @return ControllerResponse
     */
    public function getControllerResponse() {
        return $this->controllerResponse;
    }

    /**
     * Returns name of the controller that was executed.
     * 
     * @return string
     */
    public function getControllerName() {
        return $this->controllerName;
    }

    /**
     * Returns instance of the controller that was executed.
     * 
     * @return AbstractController
     */
    public function getController() {
        return $this->controller;
    }

    /**
     * Returns name of the method that was executed.
     * 
     * @return string
     */
    public function getMethod() {
        return $this->method;
    }

    /**
     * Returns arguments with which the controller's method was executed.
     * 
     * @return array
     */
    public function getArguments() {
        return $this->arguments;
    }

}