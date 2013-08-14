<?php
namespace Splot\Framework\Tests\Routes\Fixtures\TestModule\Controllers;

use Splot\Framework\Controller\AbstractController;

class Index extends AbstractController
{

    protected static $_url = '';

    public function index() {
        return '';
    }

}