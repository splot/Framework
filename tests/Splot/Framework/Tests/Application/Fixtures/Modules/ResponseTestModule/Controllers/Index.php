<?php
namespace Splot\Framework\Tests\Application\Fixtures\Modules\ResponseTestModule\Controllers;

use Splot\Framework\Controller\AbstractController;

class Index extends AbstractController
{

    protected static $_url = '/';

    public function index() {
        return 'INDEX';
    }

}