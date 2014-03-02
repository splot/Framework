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
        $clog = $this->getMock('MD\Clog\Clog');
        $logger = $this->getMock('Psr\Log\LoggerInterface');

        $clog->expects($this->any())
            ->method('provideLogger')
            ->will($this->returnValue($logger));

        // container has to have few things defined
        $container->setParameter('cache_dir', realpath(dirname(__FILE__) .'/../../../..') .'/tmp/cache');
        $applicationDir = realpath(dirname(__FILE__) .'/Fixtures');

        $app->init($config, $container, 'test', $applicationDir, $timer, $logger, $clog);
        
        return $app;
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

    public function testFindingSingleInApplication() {
        $app = new TestApplication();
        $this->initApplication($app);

        $finder = new Finder($app);

        $this->assertEquals(realpath(dirname(__FILE__)) .'/Fixtures/Resources/public/js/index.js',
            $finder->findResource('::index.js', 'public/js'));
        $this->assertEquals(realpath(dirname(__FILE__)) .'/Fixtures/Resources/public/js/index.js',
            $finder->findResource('::js/index.js', 'public'));
        // make sure 2nd time is the same (to cover cache case)
        $this->assertEquals(realpath(dirname(__FILE__)) .'/Fixtures/Resources/public/js/index.js',
            $finder->findResource('::index.js', 'public/js'));
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
    public function testExpandingGlobPatterns($pattern, array $result) {
        $app = new TestApplication();
        $this->initApplication($app);
        $app->bootModule(new SplotResourcesTestModule());

        $finder = new Finder($app);

        $this->assertEquals($result, $finder->expand($pattern, 'public'), 'Failed to return valid glob results when finding resources.');
    }

    public function provideGlobPatterns() {
        return array(
            array('::js/*.js', array(
                    '::js/chat.js',
                    '::js/contact.js',
                    '::js/index.js',
                    '::js/map.js'
                )),
            array('::js/**/*.js', array(
                    '::js/lib/angular.js',
                    '::js/lib/jquery.js',
                    '::js/lib/lodash.js',
                    '::js/misc/chuckifier.js',
                    '::js/misc/gmap.js',
                    '::js/plugin/caroufredsel.js',
                    '::js/plugin/infinitescroll.js',
                    '::js/plugin/jquery.appendix.js'
                )),
            array('::js/{,**/}*.js', array(
                    '::js/chat.js',
                    '::js/contact.js',
                    '::js/index.js',
                    '::js/map.js',
                    '::js/lib/angular.js',
                    '::js/lib/jquery.js',
                    '::js/lib/lodash.js',
                    '::js/misc/chuckifier.js',
                    '::js/misc/gmap.js',
                    '::js/plugin/caroufredsel.js',
                    '::js/plugin/infinitescroll.js',
                    '::js/plugin/jquery.appendix.js'
                )),
            array('::js/{lib,plugin}/*.js', array(
                    '::js/lib/angular.js',
                    '::js/lib/jquery.js',
                    '::js/lib/lodash.js',
                    '::js/plugin/caroufredsel.js',
                    '::js/plugin/infinitescroll.js',
                    '::js/plugin/jquery.appendix.js'
                )),
            array('SplotResourcesTestModule::js/*.js', array(
                    'SplotResourcesTestModule::js/overwrite.js',
                    'SplotResourcesTestModule::js/overwritten.js',
                    'SplotResourcesTestModule::js/resources.js',
                    'SplotResourcesTestModule::js/stuff.js',
                    'SplotResourcesTestModule::js/test.js'
                )),
            array('SplotResourcesTestModule::js/Lorem/*.js', array(
                    'SplotResourcesTestModule::js/Lorem/ipsum.js'
                )),
            array('SplotResourcesTestModule::js/{,**/}*.js', array(
                    'SplotResourcesTestModule::js/overwrite.js',
                    'SplotResourcesTestModule::js/overwritten.js',
                    'SplotResourcesTestModule::js/resources.js',
                    'SplotResourcesTestModule::js/stuff.js',
                    'SplotResourcesTestModule::js/test.js',
                    'SplotResourcesTestModule::js/Lorem/ipsum.js'
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
     * @expectedException \Splot\Framework\Resources\Exceptions\ResourceNotFoundException
     */
    public function testFindingInApplicationInvalidModule() {
        $app = new TestApplication();
        $this->initApplication($app);

        $finder = new Finder($app);
        $finder->findInApplicationDir('RandomModule::index.js', 'public/js');
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

    /**
     * @expectedException \Splot\Framework\Resources\Exceptions\ResourceNotFoundException
     */
    public function testFindingInModuleInvalidModule() {
        $app = new TestApplication();
        $this->initApplication($app);

        $finder = new Finder($app);
        $finder->findInModuleDir('RandomModule::index.js', 'public/js');
    }

}
