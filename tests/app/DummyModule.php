<?php
use Splot\Framework\Modules\AbstractModule;

class DummyModule extends AbstractModule
{

    public function loadModules($env, $debug) {
        return array(
            new TestModule()
        );
    }

}