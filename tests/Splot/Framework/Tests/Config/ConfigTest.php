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
        $this->configFixturesDir = realpath(dirname(__FILE__) .'/fixtures') .'/';
    }

    /**
     * @covers ::__construct
     * @covers ::getReadFiles
     */
    public function testEmptyConfigInstance() {
        $config = new Config(array());

        $this->assertInternalType('array', $config->getReadFiles());
        $this->assertEmpty($config->getReadFiles());
    }

    /**
     * @covers ::__construct
     * @covers ::getNamespace
     */
    public function testSimpleGetNamespace() {
        $config = new Config(array(
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
        $config = new Config($this->basicConfigArray);

        $this->assertEquals($this->basicConfigArray['group'], $config->getNamespace('group'));
        $this->assertInternalType('array', $config->getNamespace('undefined'));
        $this->assertEmpty($config->getNamespace('undefined'));
    }

    /**
     * @covers ::__construct
     * @covers ::get
     */
    public function testGet() {
        $config = new Config($this->basicConfigArray);

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
        $config = new Config(array());
        $this->assertEquals('lipsum', $config->get('undefined.item', 'lipsum'));
    }

    /**
     * @expectedException \MD\Foundation\Exceptions\NotFoundException
     * @covers ::__construct
     * @covers ::get
     */
    public function testGetInvalid() {
        $config = new Config($this->basicConfigArray);
        $config->get('group.invalid_setting');
    }

    /**
     * @covers ::__construct
     * @covers ::apply
     * @covers ::getNamespace
     */
    public function testApply() {
        $config = new Config($this->basicConfigArray);
        $config->apply($this->extendingConfigArray);
        $this->assertEquals($this->resultingConfigArray, $config->getNamespace());
    }

    /**
     * @covers ::__construct
     * @covers ::extend
     * @covers ::getNamespace
     */
    public function testExtend() {
        $config = new Config($this->basicConfigArray);
        $anotherConfig = new Config($this->extendingConfigArray);
        $config->extend($anotherConfig);

        $this->assertEquals($this->resultingConfigArray, $config->getNamespace());
    }

    /**
     * @covers ::__construct
     * @covers ::read
     * @covers ::getReadFiles
     * @covers ::getNamespace
     */
    public function testRead() {
        $config = Config::read($this->configFixturesDir, 'test');

        $this->assertEquals(array(
            $this->configFixturesDir .'config.php',
            $this->configFixturesDir .'config.test.php'
        ), $config->getReadFiles());

        $this->assertEquals($this->resultingConfigArray, $config->getNamespace());
    }

    /**
     * @expectedException \MD\Foundation\Exceptions\InvalidFileException
     * @covers ::read
     */
    public function testReadInvalidBase() {
        $config = Config::read($this->configFixturesDir .'invalid_base', 'test');
    }

    /**
     * @expectedException \MD\Foundation\Exceptions\InvalidFileException
     * @covers ::read
     */
    public function testReadInvalidEnv() {
        $config = Config::read($this->configFixturesDir .'invalid_env', 'test');
    }

}
