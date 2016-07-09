<?php
namespace Splot\Framework\Tests;

trait MockTrait
{
    public function getMock(
        $originalClassName,
        $methods = [],
        array $arguments = [],
        $mockClassName = '',
        $callOriginalConstructor = true,
        $callOriginalClone = true,
        $callAutoload = true,
        $cloneArguments = false,
        $callOriginalMethods = false,
        $proxyTarget = NULL
    ) {
        return call_user_func_array([$this, 'createMock'], func_get_args());
    }
}