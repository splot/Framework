<?php
/**
 * Event triggered when an error occurs (php error, user error, uncaught exception, etc.)
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

class ErrorDidOccur extends AbstractEvent
{

    /**
     * Error code.
     * 
     * @var int
     */
    protected $_code;

    /**
     * Error message.
     * 
     * @var string
     */
    protected $_message;

    /**
     * Path to file where the error occurred.
     * 
     * @var string
     */
    protected $_file;

    /**
     * Line of the file on which the error occurred.
     * 
     * @var int
     */
    protected $_line;

    /**
     * Context of the error.
     * 
     * @var array
     */
    protected $_context = array();

    /**
     * Flag has the error been handled or not.
     * 
     * @var bool
     */
    protected $_handled = false;

    /**
     * Constructor.
     * 
     * @param int $code Error code.
     * @param string $message Error message.
     * @param string $file Path to file where the error occurred.
     * @param int $line Line of the file on which the error occurred.
     * @param array $context [optional] Context of the error.
     */
    public function __construct($code, $message, $file, $line, array $context = array()) {
        $this->_code = $code;
        $this->_message = $message;
        $this->_file = $file;
        $this->_line = $line;
        $this->_context = $context;
    }

    /*****************************************************
     * SETTERS AND GETTERS
     *****************************************************/
    /**
     * Returns the error code.
     * 
     * @return int
     */
    public function getCode() {
        return $this->_code;
    }

    /**
     * Returns the error message.
     * 
     * @return string
     */
    public function getMessage() {
        return $this->_message;
    }

    /**
     * Returns the path to file where the error occurred.
     * 
     * @return string
     */
    public function getFile() {
        return $this->_file;
    }

    /**
     * Returns the line of the file on which the error occurred.
     * 
     * @return int
     */
    public function getLine() {
        return $this->_line;
    }

    /**
     * Returns the context of the error.
     * 
     * @return array
     */
    public function getContext() {
        return $this->_context;
    }

    /**
     * Sets the error as handled or not.
     * 
     * @param bool $handled
     */
    public function setHandled($handled) {
        $this->_handled = $handled;
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