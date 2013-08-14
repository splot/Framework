<?php
namespace Splot\Framework\Tests\Routes\Fixtures;

use Splot\Framework\Controller\AbstractController;

class TestController extends AbstractController
{

    public function index($id, $slug = null) {
        return 'ID: '. $id;
    }

    public function save($id, $slug = null) {
        return 'Saved: '. $id;
    }

    public function newItem($id, $slug = null) {
        return 'Created: '. $id;
    }

}