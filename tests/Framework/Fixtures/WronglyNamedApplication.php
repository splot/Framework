<?php
namespace Splot\Framework\Tests\Framework\Fixtures;

use Splot\Framework\Application\AbstractApplication;

class WronglyNamedApplication extends AbstractApplication
{

    protected $name = 'Some invalid name';

    public function boot(array $options = array()) {

    }

    public function loadModules($env, $debug) {
        return array();
    }

}