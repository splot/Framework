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
use Splot\Framework\HTTP\Request;

class DidReceiveRequest extends AbstractEvent
{

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
	public function __construct(Request $request) {
		$this->_request = $request;
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