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

use Splot\DependencyInjection\ContainerInterface;
use Splot\EventManager\EventManager;

use Splot\Framework\Events\ExceptionDidOccur;
use Splot\Framework\Framework;

class EventErrorHandler extends Handler
{

    /**
     * Event manager.
     *
     * @var EventManager
     */
    protected $eventManager;

    /**
     * Container.
     *
     * @var ContainerInterface
     */
    protected $container;

    /**
     * Constructor.
     *
     * @param WhoopsRun $whoops Whoops.
     * @param EventManager $eventManager Splot Event Manager.
     * @param ContainerInterface $container Container.
     */
    public function __construct(EventManager $eventManager, ContainerInterface $container)
    {
        $this->eventManager = $eventManager;
        $this->container = $container;
    }

    /**
     * Handle the error by triggering Splot\Framework\Events\ExceptionDidOccur event.
     */
    public function handle()
    {
        $event = new ExceptionDidOccur($this->getException());

        // trigger the event
        $this->eventManager->trigger($event);

        // only send the response in actual web mode
        $mode = $this->container->getParameter('mode');
        if ($mode == Framework::MODE_WEB && $event->isHandled() && ($response = $event->getResponse())) {
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
