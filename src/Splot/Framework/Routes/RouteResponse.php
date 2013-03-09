<?php
/**
 * A container for a response that was returned from a called route's method.
 * 
 * @package SplotFramework
 * @subpackage Routes
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\Routes;

class RouteResponse
{

    /**
     * Whatever the route has returned.
     * 
     * @var mixed
     */
    private $_response;

    /**
     * Constructor.
     * 
     * @param mixed $response Whatever the route has returned.
     */
    public function __construct($response) {
        $this->_response = $response;
    }

    /**
     * Returns whatever the route has returned.
     * 
     * @return mixed
     */
    public function getResponse() {
        return $this->_response;
    }

    /**
     * Sets a new route response.
     * 
     * @param mixed $response
     */
    public function setResponse($response) {
        $this->_response = $response;
    }

}