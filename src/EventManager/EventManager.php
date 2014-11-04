<?php
/**
 * Splot Event Manager that allows for services to subscribe to events
 * without instantiation.
 * 
 * @package SplotFramework
 * @subpackage DependencyInjection
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2014, Michał Dudek
 * @license MIT
 */
namespace Splot\Framework\EventManager;

use Psr\Log\LoggerInterface;

use Splot\EventManager\EventManager as Base_EventManager;

use Splot\Framework\DependencyInjection\ServiceContainer;

class EventManager extends Base_EventManager
{

    /**
     * Splot DI Container.
     * 
     * @var ServiceContainer
     */
    protected $container;

    /**
     * Constructor.
     *
     * @param ServiceContainer $container Splot DI Container for retrieving subscriber services.
     * @param LoggerInterface  $logger    [optional] Logger into which info about called events will be sent. Default: `null`.
     */
    public function __construct(ServiceContainer $container, LoggerInterface $logger = null) {
        parent::__construct($logger);
        $this->container = $container;
    }

    /**
     * Subscribe a service as a listener for an event.
     * 
     * @param  string  $name     Event name.
     * @param  string  $service  Subscriber service name.
     * @param  string  $method   Service method name to be called.
     * @param  integer $priority [optional] Listener priority. Default: `0`.
     * @return bool
     */
    public function subscribeService($name, $service, $method, $priority = 0) {
        $container = $this->container;
        $this->subscribe($name, function($event) use ($container, $service, $method) {
            $subscriber = $container->get($service);
            return $subscriber->$method($event);
        }, $priority);

        return true;
    }

}
