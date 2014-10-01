<?php
/**
 * A container for a response that was returned from a called controller's method.
 * 
 * @package SplotFramework
 * @subpackage Controller
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\Controller;

class ControllerResponse
{

    /**
     * Whatever the controller has returned.
     * 
     * @var mixed
     */
    private $_response;

    /**
     * Constructor.
     * 
     * @param mixed $response Whatever the controller has returned.
     */
    public function __construct($response) {
        $this->_response = $response;
    }

    /**
     * Returns whatever the controller has returned.
     * 
     * @return mixed
     */
    public function getResponse() {
        return $this->_response;
    }

    /**
     * Sets a new controller response.
     * 
     * @param mixed $response
     */
    public function setResponse($response) {
        $this->_response = $response;
    }

}