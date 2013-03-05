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

use Splot\Framework\EventManager\AbstractEvent;
use Splot\Framework\HTTP\Request;
use Splot\Framework\Routes\RouteMeta;

class FoundRouteForRequest extends AbstractEvent
{

	/**
	 * The found route meta info.
	 * 
	 * @var RouteMeta
	 */
	private $_routeMeta;

	/**
	 * The received request.
	 * 
	 * @var Request
	 */
	private $_request;

	/**
	 * Constructor.
	 * 
	 * @param Request $request The received request.
	 */
	public function __construct(RouteMeta $routeMeta, Request $request) {
		$this->_routeMeta = $routeMeta;
		$this->_request = $request;
	}

	/**
	 * Returns the found route meta info.
	 * 
	 * @return RouteMeta
	 */
	public function getRouteMeta() {
		return $this->_routeMeta;
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