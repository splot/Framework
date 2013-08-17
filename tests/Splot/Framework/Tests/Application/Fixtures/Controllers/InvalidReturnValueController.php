<?php
namespace Splot\Framework\Tests\Application\Fixtures\Controllers;

use Splot\Framework\Controller\AbstractController;

class InvalidReturnValueController extends AbstractController
{

    protected static $_url = '/invalid/';

    public function index() {
        return array(
            'invalid' => 'return value'
        );
    }

}