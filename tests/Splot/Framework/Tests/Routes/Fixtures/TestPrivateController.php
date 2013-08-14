<?php
namespace Splot\Framework\Tests\Routes\Fixtures;

use Splot\Framework\Controller\AbstractController;

class TestPrivateController extends AbstractController
{

    public function index($id) {
        return 'ID: '. $id;
    }

    private function save($id) {
        return 'Saved: '. $id;
    }

}