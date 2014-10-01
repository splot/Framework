<?php
namespace Splot\Framework\Testing\Stubs;

use Splot\Framework\Framework;
use Splot\Framework\Application\AbstractApplication;
use Splot\Framework\Config\Config;
use Splot\Framework\Modules\AbstractModule;

class TestApplication extends AbstractApplication
{

    protected $name = 'TestApplication';
    protected $version = 'test';

    public function loadModules() {
        return array();
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

        // set the module config
        $moduleConfig = new Config($this->getContainer());
        $moduleConfig->apply($config);
        $module->setConfig($moduleConfig);

        $this->setPhase(Framework::PHASE_CONFIGURE);
        $module->configure();

        $this->setPhase(Framework::PHASE_RUN);
        $module->run();
    }

}