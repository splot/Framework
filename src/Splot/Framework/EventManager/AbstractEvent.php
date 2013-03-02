<?php
/**
 * Abstract event class. All events used by Splot Framework's Event Manager should extend this class.
 * 
 * @package SplotFramework
 * @subpackage EventManager
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\EventManager;

use Splot\Framework\EventManager\EventManager;

abstract class AbstractEvent
{

	/**
	 * Event manager.
	 * 
	 * @var EventManager
	 */
	private $_eventManager;

	/**
	 * Has any further propagation of this event been stopped?
	 * 
	 * @var bool
	 */
	private $_propagationStopped = false;

	/**
	 * Sets the event manager.
	 * 
	 * @param EventManager $eventManager
	 */
	public function setEventManager(EventManager $eventManager) {
		$this->_eventManager = $eventManager;
	}

	/**
	 * Gets the event manager.
	 * 
	 * @return EventManager
	 */
	public function getEventManager() {
		return $this->_eventManager;
	}

	/**
	 * Stops the further propagation of this event.
	 */
	public function stopPropagation() {
		$this->_propagationStopped = true;
	}

	/**
	 * Checks if propagation of this event has been stopped.
	 * 
	 * @return bool
	 */
	public function isPropagationStopped() {
		return $this->_propagationStopped;
	}

	/**
	 * Returns the name of this event.
	 * 
	 * @return string
	 */
	public static function getName() {
		return get_called_class();
	}

}