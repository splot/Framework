<?php
namespace Splot\Framework\Tests\Application\Fixtures\Modules\ConfiguredTestModule;

use Splot\Framework\Modules\AbstractModule;

class SplotConfiguredTestModule extends AbstractModule
{

    protected $_booted = false;

    public function boot() {
        $this->_booted = true;
    }

    public function isBooted() {
        return $this->_booted;
    }

    protected $_initialized = false;

    public function init(){
        $this->_initialized = true;
    }

    public function isInitialized() {
        return $this->_initialized;
    }

}