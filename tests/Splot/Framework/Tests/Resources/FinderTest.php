<?php
namespace Splot\Framework\Tests\Routes;

use Splot\Framework\Resources\Finder;

use Splot\Framework\Tests\Application\Fixtures\TestApplication;
use Splot\Framework\Tests\Resources\Fixtures\Modules\ResourcesTestModule\SplotResourcesTestModule;
use Splot\Framework\Application\AbstractApplication;

use Psr\Log\NullLogger;

use MD\Foundation\Utils\ArrayUtils;
use MD\Foundation\Debug\Timer;

use Splot\Framework\Config\Config;
use Splot\Framework\DependencyInjection\ServiceContainer;

use Splot\Log\Provider\LogProvider;

class FinderTest extends \PHPUnit_Framework_TestCase
{

    protected function initApplication(AbstractApplication $app, $env = 'test', array $configArray = array()) {
        $configArray = (!empty($configArray)) ? $configArray : array(
            'cache' => array(
                'stores' => array(),
                'caches' => array()
            )
        );
        $config = new Config($configArray);
        $container = new ServiceContainer();
        $timer = new Timer();
        $logger = new NullLogger();
        $logProvider = new LogProvider();

        // container has to have few things defined
        $container->setParameter('cache_dir', realpath(dirname(__FILE__) .'/../../../..') .'/tmp/cache');
        $applicationDir = realpath(dirname(__FILE__) .'/Fixtures');

        $app->init($config, $container, 'test', $applicationDir, $timer, $logger, $logProvider);
        
        return $app;
    }

    public function tearDown() {
        \Splot\Log\LogContainer::clear();
    }

    public function testInitializing() {
        $app = new TestApplication();
        $this->initApplication($app);

        $finder = new Finder($app);

        $this->assertSame($app, $finder->getApplication());
    }

    public function testFindingInApplication() {
        $app = new TestApplication();
        $this->initApplication($app);

        $finder = new Finder($app);

        $this->assertEquals(realpath(dirname(__FILE__)) .'/Fixtures/Resources/public/js/index.js', $finder->find('::index.js', 'public/js'));
        $this->assertEquals(realpath(dirname(__FILE__)) .'/Fixtures/Resources/public/js/index.js', $finder->find('::js/index.js', 'public'));
        // make sure 2nd time is the same (to cover cache case)
        $this->assertEquals(realpath(dirname(__FILE__)) .'/Fixtures/Resources/public/js/index.js', $finder->find('::index.js', 'public/js'));
    }

    public function testFindingInModule() {
        $app = new TestApplication();
        $this->initApplication($app);

        $app->bootModule(new SplotResourcesTestModule());

        $finder = new Finder($app);

        $this->assertEquals(realpath(dirname(__FILE__)) .'/Fixtures/Modules/ResourcesTestModule/Resources/config/config.php', $finder->find('SplotResourcesTestModule::config.php', 'config'));
        $this->assertEquals(realpath(dirname(__FILE__)) .'/Fixtures/Modules/ResourcesTestModule/Resources/config/test/config.php', $finder->find('SplotResourcesTestModule:test:config.php', 'config'));
    }

    /**
     * @expectedException \Splot\Framework\Resources\Exceptions\ResourceNotFoundException
     */
    public function testFindingInNotExistingModule() {
        $app = new TestApplication();
        $this->initApplication($app);

        $finder = new Finder($app);
        $finder->find('NotExistingModule::index.css', 'public/css');
    }

    /**
     * @expectedException \Splot\Framework\Resources\Exceptions\ResourceNotFoundException
     */
    public function testFindingNotExistingFile() {
        $app = new TestApplication();
        $this->initApplication($app);

        $finder = new Finder($app);
        $finder->find('::index.js', 'public');
    }

}
