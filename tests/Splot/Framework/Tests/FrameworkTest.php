<?php
namespace Splot\Framework\Tests;

use Splot\Framework\Framework;

use Splot\Framework\Tests\Framework\Fixtures\TestApplication\Application;
use Splot\Framework\Tests\Framework\Fixtures\UnnamedApplication;
use Splot\Framework\Tests\Framework\Fixtures\WronglyNamedApplication;

use Psr\Log\NullLogger;
use Splot\Log\LogContainer;
use Splot\Framework\DependencyInjection\ServiceContainer;
use Symfony\Component\Filesystem\Filesystem;

class FrameworkTest extends \PHPUnit_Framework_TestCase
{

    public function tearDown() {
        Framework::reset();
        LogContainer::clear();
    }

    public function testInitializingWithDefaults() {
        $splot = Framework::init();

        // make sure it's a singleton
        $this->assertSame($splot, Framework::init());
        $this->assertSame($splot, Framework::getFramework());

        // assert things have been properly set up
        $this->assertEquals('Europe/London', date_default_timezone_get());
        $this->assertEquals('utf8', ini_get('default_charset'));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testCloning() {
        $splot = Framework::init();
        $clone = clone $splot;
    }

    public function testInitializingWithOptions() {
        $rootDir = realpath(dirname(__FILE__) .'/../../../../') .'/';
        $splot = Framework::init(array(
            'timezone' => 'Europe/Warsaw',
            'rootDir' => $rootDir
        ));

        $this->assertEquals('Europe/Warsaw', date_default_timezone_get());
        $this->assertEquals($rootDir, $splot->getRootDir());
        $this->assertTrue(is_dir($splot->getRootDir()));
        $this->assertTrue(is_dir($splot->getFrameworkDir()));
        $this->assertTrue(is_dir($splot->getVendorDir()));
    }

    public function testIsWebAndIsConsole() {
        $splot = Framework::init();
        $this->assertTrue($splot->isWeb());
        $this->assertFalse($splot->isConsole());

        $this->tearDown();

        $splotConsole = Framework::init(array(), true);
        $this->assertFalse($splotConsole->isWeb());
        $this->assertTrue($splotConsole->isConsole());
    }

    public function testGuessingEnvFromConfigs() {
        $configsDirs = realpath(dirname(__FILE__) .'/Framework/Fixtures/configs') .'/';

        $this->assertEquals(Framework::ENV_DEV, Framework::envFromConfigs($configsDirs .'test1-dev'));
        $this->assertEquals(Framework::ENV_PRODUCTION, Framework::envFromConfigs($configsDirs .'test2-production/'));
        $this->assertEquals(Framework::ENV_STAGING, Framework::envFromConfigs($configsDirs .'test3-staging'));
        $this->assertEquals(Framework::ENV_PRODUCTION, Framework::envFromConfigs($configsDirs .'test4-production'));
    }

    public function testBootingApplication() {
        $splot = Framework::init();
        $options = array(
            'config' => array(
                'someInjectedSetting' => true,
                'timezone' => 'Europe/Berlin'
            ),
            'env' => Framework::ENV_TEST,
            'timezone' => 'Europe/Warsaw'
        );
        $initApp = new Application();
        $app = $splot->bootApplication($initApp, $options);
        $this->assertSame($app, $initApp);

        $this->assertEquals(realpath(dirname(__FILE__) .'/Framework/Fixtures/TestApplication') .'/', $app->getApplicationDir());
        $this->assertEquals(Framework::ENV_TEST, $app->getEnv());
        $this->assertEquals('Europe/Berlin', date_default_timezone_get());
        $this->assertTrue($app->getContainer() instanceof ServiceContainer);

        $container = $app->getContainer();
        $this->assertSame($container, $container->get('container'));
        $this->assertTrue($container->hasParameter('root_dir'));
        $this->assertTrue($container->hasParameter('framework_dir'));
        $this->assertTrue($container->hasParameter('vendor_dir'));
        $this->assertTrue($container->hasParameter('application_dir'));
        $this->assertTrue($container->hasParameter('cache_dir'));
        $this->assertTrue($container->has('filesystem'));
        $this->assertTrue($container->get('filesystem') instanceof Filesystem);
        $this->assertTrue($container->has('log_provider'));
        $this->assertSame($app, $container->get('application'));
        $this->assertEquals($options, $app->getOptions());
        $this->assertTrue($app->getConfig()->get('someInjectedSetting'));

        $this->assertTrue($app->hasModule('SplotTestModule'));
        $this->assertFalse($app->hasModule('SomeRandomModule'));
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testBootingUnnamedApplication() {
        $splot = Framework::init();
        $splot->bootApplication(new UnnamedApplication());
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testBootingWronglyNamedApplication() {
        $splot = Framework::init();
        $splot->bootApplication(new WronglyNamedApplication());
    }

}
