<?php
namespace Splot\Framework\Tests\Routes\Fixtures\TestModule\Controllers\Admin;

use Splot\Framework\Controller\AbstractController;

class Item extends AbstractController
{

    protected static $_url = '/admin/item/{id:int}/';

    public function index($id) {
        return '';
    }

}