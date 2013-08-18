<?php
namespace Splot\Framework\Tests\Events\Fixtures;

use Splot\Framework\Controller\AbstractController;

class TestController extends AbstractController
{

    public static $_url = '/test/{id:int}';

    public function index($id) {
        return 'some response';
    }

}