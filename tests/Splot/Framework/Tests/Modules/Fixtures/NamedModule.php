<?php
namespace Splot\Framework\Tests\Modules\Fixtures;

use Splot\Framework\Modules\AbstractModule;

class NamedModule extends AbstractModule
{

    protected $name = 'SplotTestNamedModule';
    protected $urlPrefix = 'splot-test-named-module/';
    protected $commandNamespace = 'splotnamed';

}