<?php
/**
 * Event triggered when a route has been found for the given request.
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
use Splot\Framework\Routes\Route;

class DidFindRouteForRequest extends AbstractEvent
{

    /**
     * The found route meta info.
     * 
     * @var Route
     */
    private $_route;

    /**
     * The received request.
     * 
     * @var Request
     */
    private $_request;

    /**
     * Constructor.
     * 
     * @param Route $route The matched route.
     * @param Request $request The received request.
     */
    public function __construct(Route $route, Request $request) {
        $this->_route = $route;
        $this->_request = $request;
    }

    /**
     * Returns the matched route.
     * 
     * @return Route
     */
    public function getRoute() {
        return $this->_route;
    }

    /**
     * Returns the received request.
     * 
     * @return Request
     */
    public function getRequest() {
        return $this->_request;
    }

}