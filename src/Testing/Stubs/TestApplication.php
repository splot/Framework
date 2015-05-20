<?php
namespace Splot\Framework\Testing\Stubs;

use Splot\Cache\Store\MemoryStore;

use Splot\Framework\Framework;
use Splot\Framework\Application\AbstractApplication;
use Splot\Framework\Config\Config;
use Splot\Framework\DependencyInjection\ContainerCache;
use Splot\Framework\Modules\AbstractModule;

class TestApplication extends AbstractApplication
{

    protected $name = 'TestApplication';
    protected $version = 'test';

    public function loadModules() {
        return array();
    }

    public function provideContainerCache($env, $debug) {
        return new ContainerCache(new MemoryStore());
    }

    /**
     * Helper function for quickly adding a test module from the outside.
     * 
     * @param AbstractModule $module Module to be added.
     * @param array $config [optional] Module config you may want to pass?
     */
    public function addTestModule(AbstractModule $module, array $config = array()) {
        $this->setPhase(Framework::PHASE_BOOTSTRAP);
        $this->addModule($module);
        $module->setContainer($this->getContainer());

        // set the module config
        $moduleConfig = new Config();
        $moduleConfig->apply($config);
        $this->getContainer()->set('config.'. $module->getName(), $moduleConfig);

        $this->setPhase(Framework::PHASE_CONFIGURE);
        $module->configure();

        $this->setPhase(Framework::PHASE_RUN);
        $module->run();
    }

}
