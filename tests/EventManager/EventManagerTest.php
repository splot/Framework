<?php
namespace Splot\Framework\Tests\EventManager;

use Splot\Framework\Tests\EventManager\Fixtures\TestEvent;

use Splot\Framework\EventManager\EventManager;

class EventManagerTest extends \PHPUnit_Framework_TestCase
{

    public function testSubscribingAService() {
        $event = new TestEvent();

        $subscriber = $this->getMock('stdClass', array('on'));
        $subscriber->expects($this->once())
            ->method('on')
            ->with($this->equalTo($event));

        $container = $this->getMock('Splot\Framework\DependencyInjection\ServiceContainer');
        $container->expects($this->any())
            ->method('get')
            ->with($this->equalTo('subscriber'))
            ->will($this->returnValue($subscriber));

        $eventManager = new EventManager($container);
        $eventManager->subscribeService(TestEvent::getName(), 'subscriber', 'on');

        $eventManager->trigger($event);
    }

}