<?php
require_once dirname(__FILE__) .'/../../vendor/autoload.php';
require_once 'DevModule.php';
require_once 'DummyModule.php';
require_once 'TestModule.php';

use Splot\Framework\Application\AbstractApplication;

class DevApplication extends AbstractApplication
{

    protected $name = 'DevApplication';

    public function loadModules($env, $debug) {
        return array(
            new DevModule()
        );
    }

    public function loadParameters() {
        return array(
            'config_dir' => $this->container->getParameter('application_dir'),
            'root_dir' => $this->container->getParameter('application_dir'),
            'web_dir' => $this->container->getParameter('application_dir')
        );
    }

    public function configure() {
        
    }

    public function run() {

    }

}