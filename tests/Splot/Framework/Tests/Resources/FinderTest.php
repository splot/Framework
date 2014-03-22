<?php
namespace Splot\Framework\Tests\Routes;

use Splot\Framework\Resources\Finder;

use Splot\Framework\Tests\Application\Fixtures\TestApplication;
use Splot\Framework\Testing\ApplicationTestCase;
use Splot\Framework\Tests\Resources\Fixtures\Modules\ResourcesTestModule\SplotResourcesTestModule;
use Splot\Framework\Application\AbstractApplication;

use Psr\Log\NullLogger;

use MD\Foundation\Utils\ArrayUtils;
use MD\Foundation\Debug\Timer;

use Splot\Framework\Config\Config;
use Splot\Framework\DependencyInjection\ServiceContainer;

use Splot\Log\Provider\LogProvider;

class FinderTest extends ApplicationTestCase
{

    public static $_applicationClass = 'Splot\Framework\Tests\Resources\Fixtures\TestApplication';

    public function testInitializing() {
        $finder = new Finder($this->_application);

        $this->assertSame($this->_application, $finder->getApplication());
    }

    public function testFindingInApplication() {
        $finder = new Finder($this->_application);

        $this->assertEquals(realpath(dirname(__FILE__)) .'/Fixtures/Resources/public/js/index.js',
            $finder->find('::index.js', 'public/js'));
        $this->assertEquals(realpath(dirname(__FILE__)) .'/Fixtures/Resources/public/js/index.js',
            $finder->find('::js/index.js', 'public'));
        // make sure 2nd time is the same (to cover cache case)
        $this->assertEquals(realpath(dirname(__FILE__)) .'/Fixtures/Resources/public/js/index.js',
            $finder->find('::index.js', 'public/js'));
    }

    public function testFindingSingleInApplication() {
        $finder = new Finder($this->_application);

        $this->assertEquals(realpath(dirname(__FILE__)) .'/Fixtures/Resources/public/js/index.js',
            $finder->findResource('::index.js', 'public/js'));
        $this->assertEquals(realpath(dirname(__FILE__)) .'/Fixtures/Resources/public/js/index.js',
            $finder->findResource('::js/index.js', 'public'));
        // make sure 2nd time is the same (to cover cache case)
        $this->assertEquals(realpath(dirname(__FILE__)) .'/Fixtures/Resources/public/js/index.js',
            $finder->findResource('::index.js', 'public/js'));
    }

    public function testFindingInModule() {
        $this->_application->addTestModule(new SplotResourcesTestModule());

        $finder = new Finder($this->_application);

        $this->assertEquals(realpath(dirname(__FILE__)) .'/Fixtures/Modules/ResourcesTestModule/Resources/config/config.php',
            $finder->find('SplotResourcesTestModule::config.php', 'config'));
        $this->assertEquals(realpath(dirname(__FILE__)) .'/Fixtures/Modules/ResourcesTestModule/Resources/config/test/config.php',
            $finder->find('SplotResourcesTestModule:test:config.php', 'config'));
    }

    public function testFindingOverwrittenInApplication() {
        $this->_application->addTestModule(new SplotResourcesTestModule());

        $finder = new Finder($this->_application);

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
        $this->_application->addTestModule(new SplotResourcesTestModule());

        $finder = new Finder($this->_application);

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
        $finder = new Finder($this->_application);
        $finder->find('NotExistingModule::index.css', 'public/css');
    }

    /**
     * @expectedException \Splot\Framework\Resources\Exceptions\ResourceNotFoundException
     */
    public function testFindingNotExistingFile() {
        $finder = new Finder($this->_application);
        $finder->find('::index.js', 'public');
    }

    /**
     * @expectedException \Splot\Framework\Resources\Exceptions\ResourceNotFoundException
     */
    public function testFindingNotExistingFileInModule() {
        $this->_application->addTestModule(new SplotResourcesTestModule());

        $finder = new Finder($this->_application);
        $finder->find('SplotResourcesTestModule::undefined.js', 'public');
    }

    /**
     * @expectedException \MD\Foundation\Exceptions\InvalidArgumentException
     */
    public function testFindingInvalidFormat() {
        $finder = new Finder($this->_application);
        $finder->find('some.lorem.ipsum_file.js', 'public/js');
    }

    /**
     * @expectedException \MD\Foundation\Exceptions\InvalidArgumentException
     */
    public function testFindingInApplicationInvalidFormat() {
        $finder = new Finder($this->_application);
        $finder->findInApplicationDir('some.lorem.ipsum_file.js', 'public/js');
    }

    /**
     * @expectedException \Splot\Framework\Resources\Exceptions\ResourceNotFoundException
     */
    public function testFindingInApplicationInvalidModule() {
        $finder = new Finder($this->_application);
        $finder->findInApplicationDir('RandomModule::index.js', 'public/js');
    }

    /**
     * @expectedException \MD\Foundation\Exceptions\InvalidArgumentException
     */
    public function testFindingInModuleInvalidFormat() {
        $finder = new Finder($this->_application);
        $finder->findInModuleDir('::some.lorem.ipsum_file.js', 'public/js');
    }

    /**
     * @expectedException \Splot\Framework\Resources\Exceptions\ResourceNotFoundException
     */
    public function testFindingInModuleInvalidModule() {
        $finder = new Finder($this->_application);
        $finder->findInModuleDir('RandomModule::index.js', 'public/js');
    }

}
