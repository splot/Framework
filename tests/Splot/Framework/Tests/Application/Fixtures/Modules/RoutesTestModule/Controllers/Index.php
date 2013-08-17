<?php
namespace Splot\Framework\Tests\Application\Fixtures\Modules\RoutesTestModule\Controllers;

use Splot\Framework\Controller\AbstractController;

class Index extends AbstractController
{

    protected static $_url = '/index/';

    public function index() {
        return '';
    }

}