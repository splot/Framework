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

use Splot\Log\Provider\LogProvider;

/**
 * @coversDefaultClass \Splot\Framework\Resources\Finder
 */
class FinderTest extends ApplicationTestCase
{

    public static $applicationClass = 'Splot\Framework\Tests\Resources\Fixtures\TestApplication';

    /**
     * @covers ::__construct
     * @covers ::getApplication
     */
    public function testInitializing() {
        $finder = new Finder($this->application);

        $this->assertSame($this->application, $finder->getApplication());
    }

    /**
     * @covers ::find
     * @covers ::expand
     * @covers ::findResource
     * @covers ::parseResourceName
     * @covers ::findInApplicationDir
     * @covers ::findInModuleDir
     * @covers ::buildResourcePath
     * @covers ::parseResourceName
     */
    public function testFindingInApplication() {
        $finder = new Finder($this->application);

        $this->assertEquals(realpath(dirname(__FILE__)) .'/Fixtures/Resources/public/js/index.js',
            $finder->find('::index.js', 'public/js'));
        $this->assertEquals(realpath(dirname(__FILE__)) .'/Fixtures/Resources/public/js/index.js',
            $finder->find('::js/index.js', 'public'));
        // make sure 2nd time is the same (to cover cache case)
        $this->assertEquals(realpath(dirname(__FILE__)) .'/Fixtures/Resources/public/js/index.js',
            $finder->find('::index.js', 'public/js'));
    }

    /**
     * @covers ::find
     * @covers ::expand
     * @covers ::findResource
     * @covers ::parseResourceName
     * @covers ::findInApplicationDir
     * @covers ::findInModuleDir
     * @covers ::buildResourcePath
     * @covers ::parseResourceName
     */
    public function testFindingSingleInApplication() {
        $finder = new Finder($this->application);

        $this->assertEquals(realpath(dirname(__FILE__)) .'/Fixtures/Resources/public/js/index.js',
            $finder->findResource('::index.js', 'public/js'));
        $this->assertEquals(realpath(dirname(__FILE__)) .'/Fixtures/Resources/public/js/index.js',
            $finder->findResource('::js/index.js', 'public'));
        // make sure 2nd time is the same (to cover cache case)
        $this->assertEquals(realpath(dirname(__FILE__)) .'/Fixtures/Resources/public/js/index.js',
            $finder->findResource('::index.js', 'public/js'));
    }

    /**
     * @covers ::find
     * @covers ::expand
     * @covers ::findResource
     * @covers ::parseResourceName
     * @covers ::findInApplicationDir
     * @covers ::findInModuleDir
     * @covers ::buildResourcePath
     * @covers ::parseResourceName
     */
    public function testFindingInModule() {
        $this->application->addTestModule(new SplotResourcesTestModule());

        $finder = new Finder($this->application);

        $this->assertEquals(realpath(dirname(__FILE__)) .'/Fixtures/Modules/ResourcesTestModule/Resources/config/config.php',
            $finder->find('SplotResourcesTestModule::config.php', 'config'));
        $this->assertEquals(realpath(dirname(__FILE__)) .'/Fixtures/Modules/ResourcesTestModule/Resources/config/test/config.php',
            $finder->find('SplotResourcesTestModule:test:config.php', 'config'));
    }

    /**
     * @covers ::find
     * @covers ::expand
     * @covers ::findResource
     * @covers ::parseResourceName
     * @covers ::findInApplicationDir
     * @covers ::findInModuleDir
     * @covers ::buildResourcePath
     * @covers ::parseResourceName
     */
    public function testFindingOverwrittenInApplication() {
        $this->application->addTestModule(new SplotResourcesTestModule());

        $finder = new Finder($this->application);

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
     * @covers ::expand
     */
    public function testExpandingGlobPatterns($pattern, array $result) {
        $this->application->addTestModule(new SplotResourcesTestModule());

        $finder = new Finder($this->application);

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
     * @covers ::find
     * @covers ::expand
     * @covers ::findResource
     * @covers ::parseResourceName
     * @covers ::findInApplicationDir
     * @covers ::findInModuleDir
     * @covers ::buildResourcePath
     * @covers ::parseResourceName
     */
    public function testFindingInNotExistingModule() {
        $finder = new Finder($this->application);
        $finder->find('NotExistingModule::index.css', 'public/css');
    }

    /**
     * @expectedException \Splot\Framework\Resources\Exceptions\ResourceNotFoundException
     * @covers ::find
     * @covers ::expand
     * @covers ::findResource
     * @covers ::parseResourceName
     * @covers ::findInApplicationDir
     * @covers ::findInModuleDir
     * @covers ::buildResourcePath
     * @covers ::parseResourceName
     */
    public function testFindingNotExistingFile() {
        $finder = new Finder($this->application);
        $finder->find('::index.js', 'public');
    }

    /**
     * @expectedException \Splot\Framework\Resources\Exceptions\ResourceNotFoundException
     * @covers ::find
     * @covers ::expand
     * @covers ::findResource
     * @covers ::parseResourceName
     * @covers ::findInApplicationDir
     * @covers ::findInModuleDir
     * @covers ::buildResourcePath
     * @covers ::parseResourceName
     */
    public function testFindingNotExistingResourceInApplicationDir() {
        $finder = new Finder($this->application);
        $finder->findResource('::index.js', 'public');
    }

    /**
     * @expectedException \Splot\Framework\Resources\Exceptions\ResourceNotFoundException
     * @covers ::find
     * @covers ::expand
     * @covers ::findResource
     * @covers ::parseResourceName
     * @covers ::findInApplicationDir
     * @covers ::findInModuleDir
     * @covers ::buildResourcePath
     * @covers ::parseResourceName
     */
    public function testFindingNotExistingFileInModule() {
        $this->application->addTestModule(new SplotResourcesTestModule());

        $finder = new Finder($this->application);
        $finder->find('SplotResourcesTestModule::undefined.js', 'public');
    }

    /**
     * @expectedException \Splot\Framework\Resources\Exceptions\ResourceNotFoundException
     * @covers ::find
     * @covers ::expand
     * @covers ::findResource
     * @covers ::parseResourceName
     * @covers ::findInApplicationDir
     * @covers ::findInModuleDir
     * @covers ::buildResourcePath
     * @covers ::parseResourceName
     */
    public function testFindingNotExistingFileInModuleByCallingFindInModuleDir() {
        $this->application->addTestModule(new SplotResourcesTestModule());

        $finder = new Finder($this->application);
        $finder->findinModuleDir('SplotResourcesTestModule::undefined.js', 'public');
    }

    /**
     * @expectedException \MD\Foundation\Exceptions\InvalidArgumentException
     * @covers ::find
     * @covers ::expand
     * @covers ::findResource
     * @covers ::parseResourceName
     * @covers ::findInApplicationDir
     * @covers ::findInModuleDir
     * @covers ::buildResourcePath
     * @covers ::parseResourceName
     */
    public function testFindingInvalidFormat() {
        $finder = new Finder($this->application);
        $finder->find('some.lorem.ipsum_file.js', 'public/js');
    }

    /**
     * @expectedException \MD\Foundation\Exceptions\InvalidArgumentException
     * @covers ::find
     * @covers ::expand
     * @covers ::findResource
     * @covers ::parseResourceName
     * @covers ::findInApplicationDir
     * @covers ::findInModuleDir
     * @covers ::buildResourcePath
     * @covers ::parseResourceName
     */
    public function testFindingInApplicationInvalidFormat() {
        $finder = new Finder($this->application);
        $finder->findInApplicationDir('some.lorem.ipsum_file.js', 'public/js');
    }

    /**
     * @expectedException \Splot\Framework\Resources\Exceptions\ResourceNotFoundException
     * @covers ::find
     * @covers ::expand
     * @covers ::findResource
     * @covers ::parseResourceName
     * @covers ::findInApplicationDir
     * @covers ::findInModuleDir
     * @covers ::buildResourcePath
     * @covers ::parseResourceName
     */
    public function testFindingInApplicationInvalidModule() {
        $finder = new Finder($this->application);
        $finder->findInApplicationDir('RandomModule::index.js', 'public/js');
    }

    /**
     * @expectedException \MD\Foundation\Exceptions\InvalidArgumentException
     * @covers ::find
     * @covers ::expand
     * @covers ::findResource
     * @covers ::parseResourceName
     * @covers ::findInApplicationDir
     * @covers ::findInModuleDir
     * @covers ::buildResourcePath
     * @covers ::parseResourceName
     */
    public function testFindingInModuleInvalidFormat() {
        $finder = new Finder($this->application);
        $finder->findInModuleDir('::some.lorem.ipsum_file.js', 'public/js');
    }

    /**
     * @expectedException \Splot\Framework\Resources\Exceptions\ResourceNotFoundException
     * @covers ::find
     * @covers ::expand
     * @covers ::findResource
     * @covers ::parseResourceName
     * @covers ::findInApplicationDir
     * @covers ::findInModuleDir
     * @covers ::buildResourcePath
     * @covers ::parseResourceName
     */
    public function testFindingInModuleInvalidModule() {
        $finder = new Finder($this->application);
        $finder->findInModuleDir('RandomModule::index.js', 'public/js');
    }

}
