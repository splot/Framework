<?php
namespace Splot\Framework\Tests\Config;

use Splot\Framework\Config\Config;

use MD\Foundation\Exceptions\NotFoundException;

/**
 * @coversDefaultClass Splot\Framework\Config\Config
 */
class ConfigTest extends \PHPUnit_Framework_TestCase
{

    protected $configFixturesDir;

    protected $basicConfigArray = array(
        'setting1' => true,
        'group' => array(
            'setting1' => false,
            'lorem' => 'ipsum',
            'dolor' => 'sit amet',
            'adipiscit' => 0,
            'subgroup' => array(
                'lipsum' => 123,
                'lorem' => 'whatever'
            )
        )
    );

    protected $extendingConfigArray = array(
        'setting1' => false,
        'group' => array(
            'lorem' => 'lipsum',
            'adipiscit' => 124259,
            'subgroup' => array(
                'lorem' => 'dolor sit amet',
                'new_one' => 'interesting'
            ),
            'stuff' => 'where?'
        ),
        'newGroup' => array()
    );

    protected $resultingConfigArray = array(
        'setting1' => false,
        'group' => array(
            'setting1' => false,
            'lorem' => 'lipsum',
            'dolor' => 'sit amet',
            'adipiscit' => 124259,
            'subgroup' => array(
                'lipsum' => 123,
                'lorem' => 'dolor sit amet',
                'new_one' => 'interesting'
            ),
            'stuff' => 'where?'
        ),
        'newGroup' => array()
    );

    public function setUp() {
        $this->configFixturesDir = __DIR__ .'/fixtures/';
    }

    /**
     * @covers ::__construct
     * @covers ::getLoadedFiles
     */
    public function testEmptyConfigInstance() {
        $mocks = $this->provideMocks();
        $config = new Config($mocks['container']);

        $this->assertInternalType('array', $config->getLoadedFiles());
        $this->assertEmpty($config->getLoadedFiles());
    }

    /**
     * @covers ::__construct
     * @covers ::getNamespace
     */
    public function testSimpleGetNamespace() {
        $config = $this->provideConfig(array(
            'setting1' => true,
            'setting2' => false
        ));

        $configData = $config->getNamespace();
        $this->assertInternalType('array', $configData);
        $this->assertEquals(array(
            'setting1' => true,
            'setting2' => false
        ), $configData);
    }

    /**
     * @covers ::__construct
     * @covers ::getNamespace
     */
    public function testGetNamespace() {
        $config = $this->provideConfig($this->basicConfigArray);

        $this->assertEquals($this->basicConfigArray['group'], $config->getNamespace('group'));
        $this->assertInternalType('array', $config->getNamespace('undefined'));
        $this->assertEmpty($config->getNamespace('undefined'));
    }

    /**
     * @covers ::__construct
     * @covers ::get
     */
    public function testGet() {
        $config = $this->provideConfig($this->basicConfigArray);

        $this->assertEquals(true, $config->get('setting1'));
        $this->assertEquals('ipsum', $config->get('group.lorem'));
        $this->assertEquals(array(
            'lipsum' => 123,
            'lorem' => 'whatever'
        ), $config->get('group.subgroup'));
        $this->assertEquals(123, $config->get('group.subgroup.lipsum'));
    }

    /**
     * @covers ::__construct
     * @covers ::get
     */
    public function testGetDefaultValue() {
        $config = $this->provideConfig();
        $this->assertEquals('lipsum', $config->get('undefined.item', 'lipsum'));
    }

    /**
     * @expectedException \MD\Foundation\Exceptions\NotFoundException
     * @covers ::__construct
     * @covers ::get
     */
    public function testGetInvalid() {
        $config = $this->provideConfig($this->basicConfigArray);
        $config->get('group.invalid_setting');
    }

    /**
     * @covers ::__construct
     * @covers ::apply
     * @covers ::getNamespace
     */
    public function testApply() {
        $config = $this->provideConfig($this->basicConfigArray);
        $config->apply($this->extendingConfigArray);
        $this->assertEquals($this->resultingConfigArray, $config->getNamespace());
    }

    /**
     * @covers ::__construct
     * @covers ::extend
     * @covers ::getNamespace
     */
    public function testExtend() {
        $config = $this->provideConfig($this->basicConfigArray);
        $anotherConfig = $this->provideConfig($this->extendingConfigArray);
        $config->extend($anotherConfig);

        $this->assertEquals($this->resultingConfigArray, $config->getNamespace());
    }

    /**
     * @covers ::loadFromFile
     */
    public function testLoadingYamlFile() {
        $config = $this->provideConfig();
        $config->loadFromFile($this->configFixturesDir .'config.load.yml');

        $this->assertEquals(true, $config->get('setting'));
        $this->assertEquals(false, $config->get('group.setting1'));
        $this->assertEquals('ipsum', $config->get('group.lorem'));
    }

    /**
     * @covers ::loadFromFile
     */
    public function testLoadingPhpFile() {
        $config = $this->provideConfig();
        $config->loadFromFile($this->configFixturesDir .'config.php');

        $this->assertEquals(true, $config->get('setting1'));
        $this->assertEquals(false, $config->get('group.setting1'));
        $this->assertEquals('ipsum', $config->get('group.lorem'));
    }

    /**
     * @expectedException \MD\Foundation\Exceptions\InvalidFileException
     * @covers ::loadFromFile
     */
    public function testLoadingInvalidPhpFile() {
        $config = $this->provideConfig();
        $config->loadFromFile($this->configFixturesDir .'config.invalid.php');
    }

    /**
     * @expectedException \MD\Foundation\Exceptions\NotFoundException
     */
    public function testLoadingFromInexistentFile() {
        $config = $this->provideConfig();
        $config->loadFromFile($this->configFixturesDir .'inexistent.yml');
    }

    /**
     * @covers ::loadFromFile
     */
    public function testLoadingTwiceFromFile() {
        $mocks = $this->provideMocks();
        $config = $this->getMockBuilder('Splot\Framework\Config\Config')
            ->setConstructorArgs(array($mocks['container']))
            ->setMethods(array('apply'))
            ->getMock();
        $config->expects($this->once())
            ->method('apply');
        
        $config->loadFromFile($this->configFixturesDir .'config.php');
        $config->loadFromFile($this->configFixturesDir .'config.php');
    }

    /**
     * @expectedException \MD\Foundation\Exceptions\InvalidFileException
     * @covers ::loadFromFile
     */
    public function testLoadingUnsupportedFile() {
        $config = $this->provideConfig();
        $config->loadFromFile($this->configFixturesDir .'config.ini');
    }

    /**
     * @covers ::__construct
     * @covers ::readFromDir
     * @covers ::getLoadedFiles
     * @covers ::getNamespace
     */
    public function testReadFromDir() {
        $mocks = $this->provideMocks();
        $config = Config::readFromDir($mocks['container'], $this->configFixturesDir, 'test');

        $this->assertEquals(array(
            $this->configFixturesDir .'config.php',
            $this->configFixturesDir .'config.test.php'
        ), $config->getLoadedFiles());

        $this->assertEquals($this->resultingConfigArray, $config->getNamespace());
    }

    protected function provideMocks() {
        $mocks = array();
        $mocks['container'] = $this->getMock('Splot\Framework\DependencyInjection\ServiceContainer');
        $mocks['container']->expects($this->any())
            ->method('resolveParameters')
            ->will($this->returnArgument(0));
        return $mocks;
    }

    protected function provideConfig(array $options = array(), array $mocks = array()) {
        $mocks = !empty($mocks) ? $mocks : $this->provideMocks();
        $config = new Config($mocks['container']);
        $config->apply($options);
        return $config;
    }

}
