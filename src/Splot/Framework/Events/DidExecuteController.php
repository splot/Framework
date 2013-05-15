<?php
/**
 * Event triggered when a controller has been successfuly executed and returned a response.
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

use Splot\Framework\HTTP\Request;
use Splot\Framework\Controller\ControllerResponse;
use Splot\Framework\Routes\Route;

class DidExecuteController extends AbstractEvent
{

    /**
     * The received response from the route.
     * 
     * @var ControllerResponse
     */
    private $_controllerResponse;

    /**
     * Route information.
     * 
     * @var Route
     */
    private $_route;

    /**
     * HTTP request that called the controller.
     * 
     * @var Request
     */
    private $_request;

    /**
     * Constructor.
     * 
     * @param ControllerResponse $controllerResponse The received response from the controller.
     * @param Route $routeMeta Meta information about the executed route.
     * @param Request $request HTTP request that called the controller.
     */
    public function __construct(ControllerResponse $controllerResponse, Route $route, Request $request) {
        $this->_controllerResponse = $controllerResponse;
        $this->_route = $route;
        $this->_request = $request;
    }

    /**
     * Returns the received response from the controller.
     * 
     * @return ControllerResponse
     */
    public function getControllerResponse() {
        return $this->_controllerResponse;
    }

    /**
     * Returns information about the executed route.
     * 
     * @return Route
     */
    public function getRoute() {
        return $this->_route;
    }

    /**
     * Returns the HTTP request that called the controller.
     * 
     * @return Request
     */
    public function getRequest() {
        return $this->_request;
    }

}