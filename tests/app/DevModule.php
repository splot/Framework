<?php
use Splot\Framework\Modules\AbstractModule;

class DevModule extends AbstractModule
{

    public function loadModules($env, $debug) {
        return array(
            new DummyModule()
        );
    }

}