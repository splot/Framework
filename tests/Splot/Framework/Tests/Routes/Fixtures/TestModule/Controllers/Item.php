<?php
namespace Splot\Framework\Tests\Routes\Fixtures\TestModule\Controllers;

use Splot\Framework\Controller\AbstractController;

class Item extends AbstractController
{

    protected static $_url = '/item/{id:int}/{slug}?';

    public function index($id, $slug = null) {
        return '';
    }

}