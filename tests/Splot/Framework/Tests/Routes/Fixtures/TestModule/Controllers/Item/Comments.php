<?php
namespace Splot\Framework\Tests\Routes\Fixtures\TestModule\Controllers\Item;

use Splot\Framework\Controller\AbstractController;

class Comments extends AbstractController
{

    protected static $_url = '/item/{id:int}/comments.html';

    public function index($id) {
        return '';
    }

}