<?php
namespace Splot\Framework\Tests\Application\Fixtures\Modules\RoutesTestModule\Controllers;

use Splot\Framework\Controller\AbstractController;

class Item extends AbstractController
{

    protected static $_url = '/item/{id:int}';

    public function index($id) {
        return 'Received Item ID: '. $id;
    }

}