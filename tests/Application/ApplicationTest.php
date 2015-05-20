<?php
namespace Splot\Framework\Tests\Application;

use Splot\Framework\Testing\TestCase;

/**
 * @coversDefaultClass Splot\Framework\Application\AbstractApplication
 */
class ApplicationTest extends TestCase
{

    /**
     * @covers ::configure
     */
    public function testConfigure() {
        $container = $this->_application->getContainer();

        $this->assertInstanceOf('Splot\Framework\Log\Clog', $container->get('clog'));
        $this->assertInstanceOf('Splot\Framework\Log\LoggerProviderInterface', $container->get('logger_provider'));
        $this->assertInstanceOf('Psr\Log\LoggerInterface', $container->get('logger'));
        $this->assertInstanceOf('Splot\EventManager\EventManager', $container->get('event_manager'));
        $this->assertInstanceOf('Splot\Framework\Routes\Router', $container->get('router'));
        $this->assertInstanceOf('Splot\Framework\Resources\Finder', $container->get('resource_finder'));
        $this->assertInstanceOf('Splot\Framework\Process\Process', $container->get('process'));
        $this->assertInstanceOf('Splot\Framework\Console\Console', $container->get('console'));
        $this->assertInstanceOf('Symfony\Component\Filesystem\Filesystem', $container->get('filesystem'));
        $this->assertInstanceOf('Splot\Cache\CacheProvider', $container->get('cache_provider'));
        $this->assertInstanceOf('Splot\Cache\CacheInterface', $container->get('cache'));
    }

}
