<?php
namespace Splot\Framework\Tests\Routes\Fixtures\TestModule\Controllers\Item\Gallery;

use Splot\Framework\Controller\AbstractController;

abstract class AbstractItem extends AbstractController
{

    protected static $_url = '/item/{id:int}/gallery/photo/{photoId:int}/{photoSlug}/abstract/';

    public function index($id, $photoId, $photoSlug = null) {
        return '';
    }

}