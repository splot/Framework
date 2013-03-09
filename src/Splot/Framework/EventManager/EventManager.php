<?php
/**
 * Event Manager for Splot Framework.
 * 
 * @package SplotFramework
 * @subpackage EventManager
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2013, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\EventManager;

use Splot\Foundation\Debug\Debugger;
use Splot\Foundation\Utils\ArrayUtils;

use Splot\Log\LogContainer;
use Splot\Log\Logger;

use Splot\Framework\EventManager\AbstractEvent;

class EventManager
{

    /**
     * Holds list of subscribed listeners.
     * 
     * @var array
     */
    private $_listeners = array();

    /**
     * Logger for events.
     * 
     * @var Logger
     */
    private $_logger;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->_logger = LogContainer::create('Events Manager');
    }

    /**
     * Triggers an event and all its listeners.
     * 
     * @param AbstractEvent $event Event to be triggered.
     */
    public function trigger(AbstractEvent $event) {
        $name = call_user_func(array(Debugger::getClass($event), 'getName'));
        $event->setEventManager($this);

        if (!isset($this->_listeners[$name])) {
            $this->_listeners[$name] = array();
        }

        $this->_logger->info('Triggered event "'. $name .'" with '. count($this->_listeners[$name]) .' listeners.');

        foreach($this->_listeners[$name] as $i => $listener) {
            call_user_func_array($listener['callable'], array($event));

            if ($event->isPropagationStopped()) {
                $this->_logger->info('Stopped propagation of event "'. $name .'" at listener '. $i .'.');
                break;
            }
        }
    }

    /**
     * Subscribe to an event.
     * 
     * @param string $name Name of the event to subscribe to.
     * @param callable $listener Listener. Anything that can be callable.
     * @param int $priority Priority of the execution. The higher, the sooner in the list it will be called. Default: 0.
     * 
     * @throws \InvalidArgumentException When $listener isn't callable.
     */
    public function subscribe($name, $listener, $priority = 0) {
        if (!is_callable($listener)) {
            throw new \InvalidArgumentException('Listener has to be a callable, "'. $listener .'" given."');
        }

        if (!isset($this->_listeners[$name])) {
            $this->_listeners[$name] = array();
        }

        $this->_listeners[$name][] = array(
            'callable' => $listener,
            'priority' => $priority
        );

        // already sort right after adding a listener
        $this->_listeners[$name] = ArrayUtils::multiSort($this->_listeners[$name], 'priority', true);
    }

    /**
     * Unsubscribes the given $listener from an event.
     * 
     * @param string $name Name of the event to unsubscribe from.
     * @param callable $listener Listener.
     */
    public function unsubscribe($name, $listener) {
        if (!isset($this->_listeners[$name])) {
            return;
        }

        $p = ArrayUtils::search($this->_listeners[$name], 'callable', $listener);
        if ($p !== false) {
            unset($this->_listeners[$name][$p]);
        }
    }

    /*
     * SETTERS AND GETTERS
     */
    /**
     * Returns all listeners.
     * 
     * @return array
     */
    public function getListeners() {
        return $this->_listeners;
    }

    /**
     * Returns all listeners for the given event.
     * 
     * @param string Name Name of the event.
     * @return array
     */
    public function getEventListeners($name) {
        if (!isset($this->_listeners[$name])) {
            return array();
        }

        return $this->_listeners[$name];
    }

}