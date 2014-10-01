<?php
namespace Splot\Framework\Tests\Application\Fixtures;

use Splot\Framework\Testing\Stubs\TestApplication as Base_TestApplication;

class TestApplication extends Base_TestApplication
{

    protected $name = 'TestApplication';
    protected $version = 'test';

    public function loadModules() {
        return array();
    }

}