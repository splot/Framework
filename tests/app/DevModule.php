<?php
use Splot\Framework\Modules\AbstractModule;

class DevModule extends AbstractModule
{

    public function loadModules() {
        return array(
            new DummyModule()
        );
    }

}