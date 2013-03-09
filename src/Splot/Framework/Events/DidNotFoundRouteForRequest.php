<?php
/**
 * Event triggered when a route has not been found for the given request.
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

use Splot\Foundation\HTTP\Request;
use Splot\Foundation\HTTP\Response;

class DidNotFoundRouteForRequest extends AbstractEvent
{

    /**
     * The received HTTP Request.
     * 
     * @var Request
     */
    private $_request;

    /**
     * HTTP response that should be returned.
     * 
     * @var Response
     */
    private $_response;

    /**
     * Has the event been handled? If not, normal NotFoundException will be thrown by the application.
     * 
     * @var bool
     */
    private $_isHandled = false;

    /**
     * Constructor.
     * 
     * @param Request $request The received HTTP request.
     */
    public function __construct(Request $request) {
        $this->_request = $request;
    }

    /**
     * Returns the received HTTP Request.
     * 
     * @return Request
     */
    public function getRequest() {
        return $this->_request;
    }

    /**
     * Checks if the event has been handled.
     * 
     * @return bool
     */
    public function isHandled() {
        return $this->_isHandled;
    }

    /**
     * Sets the response to be rendered instead of application throwing NotFoundException.
     * 
     * @param Response $response The response to be rendered.
     */
    public function setResponse(Response $response) {
        $this->_response = $response;
        $this->_isHandled = true;
    }

    /**
     * Returns the response (if any has been set) to be rendered instead of application throwing NotFoundException.
     * 
     * @return HttpResponse
     */
    public function getResponse() {
        return $this->_response;
    }

}