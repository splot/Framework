<?php
namespace Splot\Framework\Tests\Routes\Fixtures\TestModule\Controllers;

use Splot\Framework\Controller\AbstractController;

class Footer extends AbstractController
{

    protected static $_url = false;

    public function index() {
        return '';
    }

}