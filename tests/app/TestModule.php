<?php
use Splot\Framework\Modules\AbstractModule;

class TestModule extends AbstractModule
{

    public function loadModules($env, $debug) {
        return array(
            new DevModule()
        );
    }

}