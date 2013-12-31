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
            ),
            'router' => array(
                'host' => 'localhost',
                'protocol' => 'http://',
                'port' => 80,
                'use_request' => true
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

        $this->assertEquals(realpath(dirname(__FILE__)) .'/Fixtures/Resources/public/js/index.js',
            $finder->find('::index.js', 'public/js'));
        $this->assertEquals(realpath(dirname(__FILE__)) .'/Fixtures/Resources/public/js/index.js',
            $finder->find('::js/index.js', 'public'));
        // make sure 2nd time is the same (to cover cache case)
        $this->assertEquals(realpath(dirname(__FILE__)) .'/Fixtures/Resources/public/js/index.js',
            $finder->find('::index.js', 'public/js'));
    }

    public function testFindingInModule() {
        $app = new TestApplication();
        $this->initApplication($app);

        $app->bootModule(new SplotResourcesTestModule());

        $finder = new Finder($app);

        $this->assertEquals(realpath(dirname(__FILE__)) .'/Fixtures/Modules/ResourcesTestModule/Resources/config/config.php',
            $finder->find('SplotResourcesTestModule::config.php', 'config'));
        $this->assertEquals(realpath(dirname(__FILE__)) .'/Fixtures/Modules/ResourcesTestModule/Resources/config/test/config.php',
            $finder->find('SplotResourcesTestModule:test:config.php', 'config'));
    }

    public function testFindingOverwrittenInApplication() {
        $app = new TestApplication();
        $this->initApplication($app);
        $app->bootModule(new SplotResourcesTestModule());

        $finder = new Finder($app);

        $this->assertEquals(realpath(dirname(__FILE__)) .'/Fixtures/Resources/SplotResourcesTestModule/public/js/overwrite.js',
            $finder->find('SplotResourcesTestModule::overwrite.js', 'public/js'));
        $this->assertEquals(realpath(dirname(__FILE__)) .'/Fixtures/Resources/SplotResourcesTestModule/public/js/overwrite.js',
            $finder->find('SplotResourcesTestModule::js/overwrite.js', 'public'));
        $this->assertEquals(realpath(dirname(__FILE__)) .'/Fixtures/Resources/SplotResourcesTestModule/config/config.overwrite.php',
            $finder->find('SplotResourcesTestModule::config.overwrite.php', 'config'));
        $this->assertEquals(realpath(dirname(__FILE__)) .'/Fixtures/Resources/SplotResourcesTestModule/config/overwrite/config.php',
            $finder->find('SplotResourcesTestModule:overwrite:config.php', 'config'));
    }

    /**
     * @dataProvider provideGlobPatterns
     */
    public function testFindingGlobPatterns($pattern, array $result) {
        $app = new TestApplication();
        $this->initApplication($app);
        $app->bootModule(new SplotResourcesTestModule());

        $finder = new Finder($app);

        $this->assertEquals($result, $finder->find($pattern, 'public'), 'Failed to return valid glob results when finding resources.');
    }

    public function provideGlobPatterns() {
        $basePath = realpath(dirname(__FILE__)) .'/Fixtures/Resources/';
        $baseAppPath = $basePath .'public/js/';
        $baseModulePath = realpath(dirname(__FILE__)) .'/Fixtures/Modules/ResourcesTestModule/Resources/public/js/';

        return array(
            array('::js/*.js', array(
                    $baseAppPath .'chat.js',
                    $baseAppPath .'contact.js',
                    $baseAppPath .'index.js',
                    $baseAppPath .'map.js'
                )),
            array('::js/**/*.js', array(
                    $baseAppPath .'lib/angular.js',
                    $baseAppPath .'lib/jquery.js',
                    $baseAppPath .'lib/lodash.js',
                    $baseAppPath .'misc/chuckifier.js',
                    $baseAppPath .'misc/gmap.js',
                    $baseAppPath .'plugin/caroufredsel.js',
                    $baseAppPath .'plugin/infinitescroll.js',
                    $baseAppPath .'plugin/jquery.appendix.js'
                )),
            array('::js/{,**/}*.js', array(
                    $baseAppPath .'chat.js',
                    $baseAppPath .'contact.js',
                    $baseAppPath .'index.js',
                    $baseAppPath .'lib/angular.js',
                    $baseAppPath .'lib/jquery.js',
                    $baseAppPath .'lib/lodash.js',
                    $baseAppPath .'map.js',
                    $baseAppPath .'misc/chuckifier.js',
                    $baseAppPath .'misc/gmap.js',
                    $baseAppPath .'plugin/caroufredsel.js',
                    $baseAppPath .'plugin/infinitescroll.js',
                    $baseAppPath .'plugin/jquery.appendix.js'
                )),
            array('::js/{lib,plugin}/*.js', array(
                    $baseAppPath .'lib/angular.js',
                    $baseAppPath .'lib/jquery.js',
                    $baseAppPath .'lib/lodash.js',
                    $baseAppPath .'plugin/caroufredsel.js',
                    $baseAppPath .'plugin/infinitescroll.js',
                    $baseAppPath .'plugin/jquery.appendix.js'
                )),
            array('SplotResourcesTestModule::js/*.js', array(
                    $basePath .'SplotResourcesTestModule/public/js/overwrite.js',
                    $basePath .'SplotResourcesTestModule/public/js/overwritten.js',
                    $baseModulePath .'resources.js',
                    $baseModulePath .'stuff.js',
                    $baseModulePath .'test.js'
                )),
        );
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

    /**
     * @expectedException \Splot\Framework\Resources\Exceptions\ResourceNotFoundException
     */
    public function testFindingNotExistingFileInModule() {
        $app = new TestApplication();
        $this->initApplication($app);

        $app->bootModule(new SplotResourcesTestModule());

        $finder = new Finder($app);
        $finder->find('SplotResourcesTestModule::undefined.js', 'public');
    }

    /**
     * @expectedException \MD\Foundation\Exceptions\InvalidArgumentException
     */
    public function testFindingInvalidFormat() {
        $app = new TestApplication();
        $this->initApplication($app);

        $finder = new Finder($app);
        $finder->find('some.lorem.ipsum_file.js', 'public/js');
    }

    /**
     * @expectedException \MD\Foundation\Exceptions\InvalidArgumentException
     */
    public function testFindingInApplicationInvalidFormat() {
        $app = new TestApplication();
        $this->initApplication($app);

        $finder = new Finder($app);
        $finder->findInApplicationDir('some.lorem.ipsum_file.js', 'public/js');
    }

    /**
     * @expectedException \MD\Foundation\Exceptions\InvalidArgumentException
     */
    public function testFindingInModuleInvalidFormat() {
        $app = new TestApplication();
        $this->initApplication($app);

        $finder = new Finder($app);
        $finder->findInModuleDir('::some.lorem.ipsum_file.js', 'public/js');
    }

}
