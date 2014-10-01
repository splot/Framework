<?php
namespace Splot\Framework\Tests\Framework\Fixtures\TestApplication;

use Splot\Framework\Application\AbstractApplication;

use Splot\Framework\Tests\Framework\Fixtures\TestApplication\Modules\TestModule\SplotTestModule;

class Application extends AbstractApplication
{

    protected $name = 'SplotTestApplication';

    protected $options = array();

    public function boot(array $options = array()) {
        $this->options = $options;
    }

    public function loadModules() {
        return array(
            new SplotTestModule()
        );
    }

    public function getOptions() {
        return $this->options;
    }

}