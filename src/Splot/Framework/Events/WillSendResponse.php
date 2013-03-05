<?php
/**
 * Event triggered just before the given response will be sent back to the client.
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
use Splot\Framework\HTTP\Response;

class WillSendResponse extends AbstractEvent
{

	/**
	 * The received HTTP Request.
	 * 
	 * @var HttpRequest
	 */
	private $_request;

	/**
	 * The HTTP response that will be sent.
	 * 
	 * @var Response
	 */
	private $_response;

	/**
	 * Constructor.
	 * 
	 * @param Response $response The HTTP response that will be rendered.
	 * @param Request $request The received HTTP request.
	 */
	public function __construct(Response $response, Request $request) {
		$this->_response = $response;
		$this->_request = $request;
	}

	/**
	 * Returns the received HTTP Request.
	 * 
	 * @return HttpRequest
	 */
	public function getRequest() {
		return $this->_request;
	}

	/**
	 * Returns the response that will be rendered.
	 * 
	 * @return Response
	 */
	public function getResponse() {
		return $this->_response;
	}

}