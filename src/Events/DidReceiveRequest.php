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

use Splot\EventManager\AbstractEvent;

use Splot\Framework\HTTP\Request;
use Splot\Framework\HTTP\Response;

class DidReceiveRequest extends AbstractEvent
{

    /**
     * The received request.
     * 
     * @var Request
     */
    private $_request;

    /**
     * Response to the request.
     *
     * @var Response|null
     */
    private $_response = null;

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

    /**
     * Set potential response to the request.
     *
     * @param Response $response Response to the request.
     */
    public function setResponse(Response $response) {
        $this->_response = $response;
    }

    /**
     * Get potential response to the requet.
     *
     * @return Response
     */
    public function getResponse() {
        return $this->_response;
    }
}
