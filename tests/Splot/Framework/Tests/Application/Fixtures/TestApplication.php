<?php
namespace Splot\Framework\Tests\Application\Fixtures;

use Splot\Framework\Application\AbstractApplication;

class TestApplication extends AbstractApplication
{

    protected $name = 'TestApplication';
    protected $version = 'test';

    public function boot(array $options = array()) {

    }

    public function loadModules() {
        return array();
    }

}