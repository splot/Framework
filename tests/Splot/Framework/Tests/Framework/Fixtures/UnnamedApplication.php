<?php
namespace Splot\Framework\Tests\Framework\Fixtures;

use Splot\Framework\Application\AbstractApplication;

class UnnamedApplication extends AbstractApplication
{

    public function boot(array $options = array()) {

    }

    public function loadModules() {
        return array();
    }

}