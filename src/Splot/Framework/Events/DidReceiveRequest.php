<?php
/**
 * Event triggered when a request has been received.
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
use Splot\Framework\Request\HttpRequest;

class DidReceiveRequest extends AbstractEvent
{

	/**
	 * The received request.
	 * 
	 * @var HttpRequest
	 */
	private $_request;

	/**
	 * Constructor.
	 * 
	 * @param HttpRequest $request The received request.
	 */
	public function __construct(HttpRequest $request) {
		$this->_request = $request;
	}

	/**
	 * Returns the received request.
	 * 
	 * @return HttpRequest
	 */
	public function getRequest() {
		return $this->_request;
	}

}