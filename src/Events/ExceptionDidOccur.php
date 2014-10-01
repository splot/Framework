<?php
/**
 * Event triggered when an uncaught exception occurs during handling of a request.
 * 
 * If you want to handle the exception you should call ExceptionDidOccur->setResponse() with the response
 * that should be sent.
 * 
 * If you will not handle the exception by setting a response it will be rethrown.
 * 
 * @package SplotFramework
 * @subpackage Events
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\Events;

use Exception;

use Splot\EventManager\AbstractEvent;

use Splot\Framework\HTTP\Response;

class ExceptionDidOccur extends AbstractEvent
{

    /**
     * The exception.
     * 
     * @var Exception
     */
    protected $_exception;

    /**
     * Response to the exception.
     * 
     * @var Response
     */
    protected $_response;

    /**
     * Flag has the exception been handled or not.
     * 
     * @var bool
     */
    protected $_handled = false;

    /**
     * Constructor.
     * 
     * @param Exception $exception The exception that occurred.
     */
    public function __construct(Exception $exception) {
        $this->_exception = $exception;
    }

    /*****************************************************
     * SETTERS AND GETTERS
     *****************************************************/
    /**
     * Returns the exception.
     * 
     * @return Exception
     */
    public function getException() {
        return $this->_exception;
    }

    /**
     * Sets the response that should be returned and automatically makes this exception handled.
     * 
     * @param Response $response Response with which to respond.
     */
    public function setResponse(Response $response) {
        $this->_response = $response;
        $this->_handled = true;
    }

    /**
     * Returns the response with which to respond.
     * 
     * @return Response
     */
    public function getResponse() {
        return $this->_response;
    }

    /**
     * Returns info if the error has been handled or not.
     * 
     * @return bool
     */
    public function getHandled() {
        return $this->_handled;
    }

    /**
     * Returns info if the error has been handled or not.
     * 
     * @return bool
     */
    public function isHandled() {
        return $this->getHandled();
    }

}