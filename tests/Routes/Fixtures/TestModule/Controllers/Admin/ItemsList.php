<?php
namespace Splot\Framework\Tests\Routes\Fixtures\TestModule\Controllers\Admin;

use Splot\Framework\Controller\AbstractController;

class ItemsList extends AbstractController
{

    protected static $_url = '/items/';

    public function index() {
        return '';
    }

}