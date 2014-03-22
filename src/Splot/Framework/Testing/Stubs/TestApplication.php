<?php
namespace Splot\Framework\Testing\Stubs;

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
        $this->bootstrapped = false;
        $this->addModule($module);

        $module->setConfig(new Config($config));
        $module->configure();
        $module->run();

        $this->bootstrapped = true;
    }

}