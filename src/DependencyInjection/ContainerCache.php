<?php
namespace Splot\Framework\DependencyInjection;

use Splot\Cache\Store\StoreInterface;
use Splot\Cache\Cache;

use Splot\DependencyInjection\Exceptions\CacheDataNotFoundException;
use Splot\DependencyInjection\ContainerCacheInterface;

class ContainerCache extends Cache implements ContainerCacheInterface
{

    /**
     * Loads container data from the cache.
     *
     * Returns whatever data was previously stored in the cache.
     * 
     * @return mixed
     *
     * @throws CacheDataNotFoundException When could not find or load any data from cache.
     */
    public function load() {
        if (!$this->has('container_data')) {
            throw new CacheDataNotFoundException('Could not find any cached container data.');
        }

        return $this->get('container_data');
    }

    /**
     * Stores given container data in the cache.
     * 
     * @param  mixed $data Whatever data the container wants to cache.
     */
    public function save($data) {
        return $this->set('container_data', $data);
    }

    /**
     * Clears the container cache.
     */
    public function flush() {
        $this->clear('container_data');
    }

    /**
     * Sets the cache store.
     * 
     * @param StoreInterface $store Cache store.
     */
    public function setStore(StoreInterface $store) {
        $this->store = $store;
    }

    /**
     * Returns the cache store.
     * 
     * @return StoreInterface
     */
    public function getStore() {
        return $this->store;
    }

}
