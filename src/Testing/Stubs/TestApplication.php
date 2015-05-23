<?php
/**
 * Test application stub.
 * 
 * @package SplotFramework
 * @subpackage Testing
 * @author Michał Dudek <michal@michaldudek.pl>
 * 
 * @copyright Copyright (c) 2015, Michał Pałys-Dudek
 * @license MIT
 */
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

    /**
     * Doesn't load any modules.
     *
     * @return array
     */
    public function loadModules() {
        return array();
    }

    /**
     * Provides container cache that uses memory for storage.
     * 
     * @param  string $env    Application's env.
     * @param  boolean $debug Debug mode on or off.
     * @return ContainerCache
     */
    public function provideContainerCache($env, $debug) {
        return new ContainerCache(new MemoryStore());
    }

    /**
     * Adds a module to the application for testing.
     *
     * The application should be already configured in order to be able to add
     * a test module to it.
     *
     * The module will be configured and ran.
     * 
     * @param AbstractModule $module Module to be added.
     * @param array $config [optional] Module config you may want to pass?
     */
    public function addTestModule(AbstractModule $module, array $config = array()) {
        // apply the passed config through the application config
        $this->getConfig()->apply(array(
            $module->getName() => $config
        ));

        $this->setPhase(Framework::PHASE_BOOTSTRAP);
        $this->addModule($module);
        $container = $this->getContainer();
        $module->setContainer($container);

        // configure the module
        $this->setPhase(Framework::PHASE_CONFIGURE);
        $framework = new Framework();
        $framework->configureModule($module, $this, $container->getParameter('env'), $container->getParameter('debug'));

        // run the module
        $this->setPhase(Framework::PHASE_RUN);
        $module->run();
    }

    /**
     * Sets the application phase, but allows a previous phase to be set.
     * 
     * @param int $phase Phase, one of `Framework::PHASE_*` constants.
     */
    public function setPhase($phase) {
        $this->phase = $phase;
    }

}
