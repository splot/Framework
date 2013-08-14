<?php
namespace Splot\Framework\Tests\Routes\Fixtures\TestModule\Controllers\Item\Gallery;

use Splot\Framework\Controller\AbstractController;

class Photo extends AbstractController
{

    protected static $_url = '/item/{id:int}/gallery/photo/{photoId:int}/{photoSlug}?';

    public function index($id, $photoId, $photoSlug = null) {
        return '';
    }

}