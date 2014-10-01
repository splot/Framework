<?php
namespace Splot\Framework\Tests\Routes\Fixtures\TestModule\Controllers\Item;

use Splot\Framework\Controller\AbstractController;

class Gallery extends AbstractController
{

    protected static $_url = '/item/{id:int}/gallery.html';

    public function index($id) {
        return '';
    }

}