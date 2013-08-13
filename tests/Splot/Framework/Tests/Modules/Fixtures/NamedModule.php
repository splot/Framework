<?php
namespace Splot\Framework\Tests\Modules\Fixtures;

use Splot\Framework\Modules\AbstractModule;

class NamedModule extends AbstractModule
{

    protected $_name = 'SplotTestNamedModule';
    protected $_urlPrefix = 'splot-test-named-module/';
    protected $_commandNamespace = 'splotnamed';

    public function boot() {
        
    }

}