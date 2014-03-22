<?php
use Splot\Framework\Modules\AbstractModule;

class TestModule extends AbstractModule
{

    public function loadModules() {
        return array(
            new DevModule()
        );
    }

}