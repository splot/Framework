<?php
namespace Splot\Framework\Tests\DependencyInjection;

use Splot\Framework\DependencyInjection\ContainerCache;

/**
 * @coversDefaultClass \Splot\Framework\DependencyInjection\ContainerCache
 */
class ContainerCacheTest extends \PHPUnit_Framework_TestCase
{

    public function testInterface() {
        $mocks = $this->provideMocks();
        $cache = new ContainerCache($mocks['store']);
        $this->assertInstanceOf('\Splot\DependencyInjection\ContainerCacheInterface', $cache);
    }

    /**
     * @covers ::load
     * @covers ::save
     */
    public function testSavingAndLoading() {
        $data = array('services' => array(), 'parameters' => array());

        $cache = $this->getMockBuilder('Splot\Framework\DependencyInjection\ContainerCache')
            ->disableOriginalConstructor()
            ->setMethods(array('has', 'get', 'set', 'clear'))
            ->getMock();

        $cache->expects($this->once())
            ->method('set')
            ->with($this->equalTo('container_data'), $this->equalTo($data))
            ->will($this->returnValue(true));

        $cache->expects($this->once())
            ->method('has')
            ->with($this->equalTo('container_data'))
            ->will($this->returnValue(true));

        $cache->expects($this->once())
            ->method('get')
            ->with($this->equalTo('container_data'))
            ->will($this->returnValue($data));

        $cache->save($data);
        $this->assertEquals($data, $cache->load());
    }

    /**
     * @expectedException \Splot\DependencyInjection\Exceptions\CacheDataNotFoundException
     * @covers ::load
     */
    public function testLoadingNonExistentCacheData() {
        $cache = $this->getMockBuilder('Splot\Framework\DependencyInjection\ContainerCache')
            ->disableOriginalConstructor()
            ->setMethods(array('has', 'get', 'set', 'clear'))
            ->getMock();

        $cache->expects($this->once())
            ->method('has')
            ->with($this->equalTo('container_data'))
            ->will($this->returnValue(false));

        $cache->load();
    }

    /**
     * @covers ::flush
     */
    public function testFlush() {
        $cache = $this->getMockBuilder('Splot\Framework\DependencyInjection\ContainerCache')
            ->disableOriginalConstructor()
            ->setMethods(array('has', 'get', 'set', 'clear'))
            ->getMock();

        $cache->expects($this->once())
            ->method('clear')
            ->with($this->equalTo('container_data'));

        $cache->flush();
    }

    /**
     * @covers ::setStore
     * @covers ::getStore
     */
    public function testSettingAndGettingStore() {
        $mocks = $this->provideMocks();
        $cache = new ContainerCache($mocks['store']);

        $store = $this->getMock('Splot\Cache\Store\StoreInterface');
        $cache->setStore($store);
        $this->assertSame($store, $cache->getStore());
        $this->assertNotSame($mocks['store'], $cache->getStore());
    }

    protected function provideMocks() {
        $mocks = array();
        $mocks['store'] = $this->getMock('Splot\Cache\Store\StoreInterface');
        return $mocks;
    }

}