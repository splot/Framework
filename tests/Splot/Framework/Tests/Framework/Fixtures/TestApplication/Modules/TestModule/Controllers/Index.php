<?php
namespace Splot\Framework\Tests\Framework\Fixtures\TestApplication\Modules\TestModule\Controllers;

use Splot\Framework\Controller\AbstractController;

class Index extends AbstractController
{

    public static $_url = '/index/';

    public function index() {
        return 'Index';
    }

}