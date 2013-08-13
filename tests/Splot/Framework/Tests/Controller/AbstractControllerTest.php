<?php
namespace Splot\Framework\Tests\Controller;

use Splot\Framework\DependencyInjection\ServiceContainer;
use Splot\Framework\Tests\Controller\Fixtures\TestController;
use Splot\Framework\Tests\Controller\Fixtures\NonEmptyController;
use Splot\Framework\Tests\DependencyInjection\Fixtures\TestService;


class AbstractControllerTest extends \PHPUnit_Framework_TestCase
{

    public function testGettingClassName() {
        $this->assertEquals('Splot\Framework\Tests\Controller\Fixtures\TestController', TestController::__class());
    }

    public function testUsingContainer() {
        $container = new ServiceContainer();
        $controller = new TestController($container);
        $this->assertSame($container, $controller->getContainer());

        $container->set('test', function() {
            return new TestService();
        }, true, true);
        $this->assertSame($container->get('test'), $controller->get('test'));

        $container->set('test.multi', function() {
            return new TestService();
        });
        $this->assertTrue($controller->get('test.multi') instanceof TestService);

        $container->setParameter('test', true);
        $this->assertEquals(true, $controller->getParameter('test'));
    }

    public function testGettingUrl() {
        $this->assertEquals('/non-empty/', NonEmptyController::_getUrl());
        $this->assertEquals('/', TestController::_getUrl());
    }

    public function testGettingAvailableResponseMethods() {
        $this->assertEquals(array(
            'get' => 'index',
            'post' => 'index',
            'put' => 'index',
            'delete' => 'index'
        ), TestController::_getMethods());

        $this->assertEquals(array(
            'get' => 'index',
            'post' => 'save',
            'put' => 'new',
            'delete' => false
        ), NonEmptyController::_getMethods());
    }

    public function testCheckingIfWillRespondToMethod() {
        $this->assertTrue(TestController::_hasMethod('get'));
        $this->assertTrue(TestController::_hasMethod('POST'));
        $this->assertTrue(TestController::_hasMethod('pOsT'));

        $this->assertTrue(NonEmptyController::_hasMethod('get'));
        $this->assertTrue(NonEmptyController::_hasMethod('put'));
        $this->assertFalse(NonEmptyController::_hasMethod('delete'));
    }

    public function testGettingMethodFunction() {
        $this->assertEquals('index', TestController::_getMethodFunction('get'));
        $this->assertEquals('index', TestController::_getMethodFunction('POST'));
        $this->assertEquals('index', TestController::_getMethodFunction('pOSt'));

        $this->assertEquals('index', NonEmptyController::_getMethodFunction('get'));
        $this->assertEquals('new', NonEmptyController::_getMethodFunction('put'));
        $this->assertEquals(false, NonEmptyController::_getMethodFunction('delete'));
    }

}
