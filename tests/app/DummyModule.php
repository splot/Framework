<?php
use Splot\Framework\Modules\AbstractModule;

class DummyModule extends AbstractModule
{

    public function loadModules() {
        return array(
            new TestModule()
        );
    }

}