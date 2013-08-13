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
     * @expectedException \Splot\Framework\DependencyInjection\Exceptions\ReadOnlyDefinitionException
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
        });

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

    public function testExtending() {
        $container = new ServiceContainer();
        $phpunit = $this;

        $testExtendCalled = false;
        $testExtend2Called = false;
        $container->set('test', function() {
            return new TestService();
        });

        $container->set('test.not_extended', function() {
            return new TestService();
        });

        $container->extend('test', function($s, $c) use ($container, $phpunit, &$testExtendCalled) {
            // make sure arguments are correct
            $phpunit->assertTrue($s instanceof TestService);
            $phpunit->assertTrue($c instanceof ServiceContainer);
            $phpunit->assertEquals($c, $container);

            $testExtendCalled = true;

            $s->setId($s->getId() + 1);
        });

        $container->extend('test', function($s, $c) use (&$testExtend2Called) {
            $testExtend2Called = true;
            $s->setId(($s->getId() + 2) * 10);
        });

        $test = $container->get('test');
        $this->assertTrue($testExtendCalled);
        $this->assertTrue($testExtend2Called);
        $this->assertEquals(30, $test->getId());

        $testNotExtended = $container->get('test.not_extended');
        $this->assertEquals(0, $testNotExtended->getId());
    }

    /**
     * @expectedException \MD\Foundation\Exceptions\NotFoundException
     */
    public function testExtendingNotExistingService() {
        $container = new ServiceContainer();

        $container->extend('undefined', function($s) {
            $s->setId(6);
        });
    }

    /**
     * @expectedException \MD\Foundation\Exceptions\InvalidArgumentException
     */
    public function testExtendindWithInvalidCallable() {
        $container = new ServiceContainer();

        $container->set('test', function() {
            return new TestService();
        });

        $container->extend('test', 123);
    }

    public function testHas() {
        $container = new ServiceContainer();
        $container->set('test', function() {
            return new TestService();
        });

        $this->assertTrue($container->has('test'));
        $this->assertFalse($container->has('undefined'));
    }

    public function testRemove() {
        $container = new ServiceContainer();

        $container->set('test', function() {
            return new TestService();
        });
        $this->assertTrue($container->has('test'));

        $container->remove('test');
        $this->assertFalse($container->has('test'));
    }

    /**
     * @expectedException \Splot\Framework\DependencyInjection\Exceptions\ReadOnlyDefinitionException
     */
    public function testRemovingReadOnlyService() {
        $container = new ServiceContainer();

        $container->set('test', function() {
            return new TestService();
        }, true);
        $this->assertTrue($container->has('test'));

        $container->remove('test');
    }

    public function testListServices() {
        $container = new ServiceContainer();

        $services = array(
            'test',
            'defined',
            'lorem.ipsum',
            'dolor_sit_amet',
            'lorem_ipsum.dolor.sit_amet'
        );

        foreach($services as $name) {
            $container->set($name, function() {
                return new TestService();
            });
        }

        $this->assertEquals($services, $container->listServices());
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

    public function testRemoveParameter() {
        $container = new ServiceContainer();

        $container->setParameter('test', 'lorem ipsum');
        $this->assertTrue($container->hasParameter('test'));

        $container->removeParameter('test');
        $this->assertFalse($container->hasParameter('test'));
    }

    public function testListParameters() {
        $container = new ServiceContainer();

        $parameters = array(
            'test' => 'lorem ipsum',
            'lipsum' => 'lorem ipsum dolor sit amet',
            'defined' => true,
            'version' => 10,
            'enable' => false
        );

        foreach($parameters as $key => $value) {
            $container->setParameter($key, $value);
        }

        $this->assertEquals(array_keys($parameters), $container->listParameters());
    }

}