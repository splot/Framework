<?php
/**
 * Event error handler.
 * 
 * @package SplotFramework
 * @subpackage ErrorHandlers
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2014, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\ErrorHandlers;

use Whoops\Handler\Handler;

use Splot\EventManager\EventManager;

use Splot\Framework\Events\ExceptionDidOccur;

class EventErrorHandler extends Handler
{

    /**
     * Event manager.
     * 
     * @var EventManager
     */
    protected $eventManager;

    /**
     * Constructor.
     *
     * @param EventManager $eventManager Splot Event Manager.
     */
    public function __construct(EventManager $eventManager) {
        $this->eventManager = $eventManager;
    }

    /**
     * Handle the error by triggering Splot\Framework\Events\ExceptionDidOccur event.
     */
    public function handle() {
        $event = new ExceptionDidOccur($this->getException());

        // trigger the event
        $this->eventManager->trigger($event);

        if (($response = $event->getResponse()) && $event->isHandled()) {
            $response->send();
        }

        if ($event->isDefaultPrevented()) {
            return Handler::QUIT;
        }

        if ($event->isPropagationStopped()) {
            return Handler::LAST_HANDLER;
        }

        return Handler::DONE;
    }

}
