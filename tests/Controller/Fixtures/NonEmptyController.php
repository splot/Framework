<?php
namespace Splot\Framework\Tests\Controller\Fixtures;

use Splot\Framework\Controller\AbstractController;

class NonEmptyController extends AbstractController
{

    protected static $_url = '/non-empty/';

    protected static $_methods = array(
        'POST' => 'save',
        'PUT' => 'new',
        'DELETE' => false
    );

}