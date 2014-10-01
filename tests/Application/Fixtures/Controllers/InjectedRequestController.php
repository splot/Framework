<?php
namespace Splot\Framework\Tests\Application\Fixtures\Controllers;

use Splot\Framework\Controller\AbstractController;

use Splot\Framework\HTTP\Request;

class InjectedRequestController extends AbstractController
{

    protected static $_url = '/request/injector/{id:int}/';

    public function index($id, Request $request) {
        return '#'. $id .': Injected Request with URL: '. $request->getPathInfo();
    }

}