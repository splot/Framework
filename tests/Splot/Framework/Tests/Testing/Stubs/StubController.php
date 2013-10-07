<?php
namespace Splot\Framework\Tests\Testing\Stubs;

use Splot\Framework\Controller\AbstractController;

class StubController extends AbstractController
{

    public function index() {
        return 'lorem ipsum dolor sit amet';
    }

}