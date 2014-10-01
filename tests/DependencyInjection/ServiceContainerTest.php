<?php
namespace Splot\Framework\Tests\DependencyInjection;

use Splot\Framework\DependencyInjection\ServiceContainer;

use Splot\Framework\Tests\DependencyInjection\Fixtures\TestService;

class ServiceContainerTest extends \PHPUnit_Framework_TestCase
{

    public function testSettingAndGettingAService() {
        $container = new ServiceContainer();

        $container->set('lipsum', function() {
            return new \stdClass();
        });

        $this->assertTrue($container->get('lipsum') instanceof \stdClass);
    }

    public function testOverwritingAService() {
        $container = new ServiceContainer();

        $container->set('lipsum', function() {
            return new \stdClass();
        });

        $container->set('lipsum', function() {
            return new TestService();
        });

        $this->assertTrue($container->get('lipsum') instanceof TestService);
    }

    /**
     * @expectedException \Splot\DependencyInjection\Exceptions\ReadOnlyException
     */
    public function testOverwritingReadOnlyService() {
        $container = new ServiceContainer();

        $container->set('lipsum', function() {
            return new \stdClass();
        }, true);

        $container->set('lipsum', function() {
            return new TestService();
        });
    }

    public function testInstantiatingNewServices() {
        $container = new ServiceContainer();

        $container->set('test', function() {
            return new TestService();
        }, false, false);

        $test1 = $container->get('test');
        $test1->setId(1);

        $test2 = $container->get('test');
        $test2->setId(2);

        $this->assertNotEquals($test1->getId(), $test2->getId());
    }

    public function testFactoryFunctionReceivingContainerAsArgument() {
        $container = new ServiceContainer();
        $phpunit = $this;

        $container->set('test', function($c) use ($phpunit, $container) {
            $phpunit->assertTrue($c instanceof ServiceContainer);
            $phpunit->assertEquals($c, $container);
            return new TestService();
        });

        $container->get('test');
    }

    public function testFlaggingAsSingletonService() {
        $container = new ServiceContainer();

        $container->set('test', function() {
            return new TestService();
        }, false, true);

        $test1 = $container->get('test');
        $test1->setId(1);

        $test2 = $container->get('test');

        $this->assertEquals($test1->getId(), $test2->getId());
    }

    public function testSettingObjectAsAService() {
        $container = new ServiceContainer();

        $testService = new TestService();

        $container->set('test', $testService);

        $testService->setId(1);
        
        $test1 = $container->get('test');
        $test1->setId(2);

        $test2 = $container->get('test');
        $test2->setId(3);

        $this->assertEquals($test1->getId(), $test2->getId());
        $this->assertEquals($testService->getId(), $test1->getId());
        $this->assertEquals($testService->getId(), $test2->getId());
    }

    /**
     * @expectedException \MD\Foundation\Exceptions\NotFoundException
     */
    public function testGettingUndefinedService() {
        $container = new ServiceContainer();
        $container->get('undefined');
    }

    public function testHas() {
        $container = new ServiceContainer();
        $container->set('test', function() {
            return new TestService();
        });

        $this->assertTrue($container->has('test'));
        $this->assertFalse($container->has('undefined'));
    }

    public function testSettingAndGettingParameters() {
        $container = new ServiceContainer();

        $container->setParameter('test', 'lorem ipsum');
        $this->assertEquals('lorem ipsum', $container->getParameter('test'));
    }

    /**
     * @expectedException \MD\Foundation\Exceptions\NotFoundException
     */
    public function testGettingUndefinedParameter() {
        $container = new ServiceContainer();

        $container->getParameter('undefined');
    }

    public function testHasParameter() {
        $container = new ServiceContainer();

        $container->setParameter('test', 'lorem ipsum');
        $this->assertTrue($container->hasParameter('test'));
        $this->assertFalse($container->hasParameter('undefined'));
    }

}