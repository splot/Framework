<?php
namespace Splot\Framework\Tests\DependencyInjection\Fixtures;

class TestService
{

    protected $id = 0;

    public function setId($id) {
        $this->id = $id;
    }

    public function getId() {
        return $this->id;
    }
    
}