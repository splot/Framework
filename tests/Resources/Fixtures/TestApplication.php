<?php
namespace Splot\Framework\Tests\Resources\Fixtures;

use Splot\Framework\Testing\Stubs\TestApplication as Base_TestApplication;

class TestApplication extends Base_TestApplication
{

    protected $name = 'TestApplication';
    protected $version = 'test';

    public function loadModules($env, $debug) {
        return array();
    }

}