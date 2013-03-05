<?php
/**
 * Event triggered when a route has been successfuly executed and returned a response.
 * 
 * @package SplotFramework
 * @subpackage Events
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\Events;

use Splot\Framework\HTTP\Request;
use Splot\Framework\EventManager\AbstractEvent;
use Splot\Framework\Routes\RouteResponse;
use Splot\Framework\Routes\RouteMeta;

class DidExecuteRoute extends AbstractEvent
{

	/**
	 * The received response from the route.
	 * 
	 * @var RouteResponse
	 */
	private $_routeResponse;

	/**
	 * Meta information about the executed route.
	 * 
	 * @var RouteMeta
	 */
	private $_routeMeta;

	/**
	 * HTTP request that called the route.
	 * 
	 * @var Request
	 */
	private $_request;

	/**
	 * Constructor.
	 * 
	 * @param RouteResponse $routeResponse The received response from the route.
	 * @param RouteMeta $routeMeta Meta information about the executed route.
	 * @param Request $request HTTP request that called the route.
	 */
	public function __construct(RouteResponse $routeResponse, RouteMeta $routeMeta, Request $request) {
		$this->_routeResponse = $routeResponse;
		$this->_routeMeta = $routeMeta;
		$this->_request = $request;
	}

	/**
	 * Returns the received response from the route.
	 * 
	 * @return RouteResponse
	 */
	public function getRouteResponse() {
		return $this->_routeResponse;
	}

	/**
	 * Returns meta information about the executed route.
	 * 
	 * @return RouteMeta
	 */
	public function getRouteMeta() {
		return $this->_routeMeta;
	}

	/**
	 * Returns the HTTP request that called the route.
	 * 
	 * @return Request
	 */
	public function getRequest() {
		return $this->_request;
	}

}