<?php
namespace Splot\Framework\Tests\Routes;

use Splot\Framework\Routes\Router;
use Splot\Framework\Routes\Route;
use Splot\Framework\HTTP\Request;

use Splot\Framework\Tests\Routes\Fixtures\EmptyController;
use Splot\Framework\Tests\Routes\Fixtures\TestController;
use Splot\Framework\Tests\Routes\Fixtures\TestPrivateController;
use Splot\Framework\Tests\Routes\Fixtures\TestModule\SplotRouterTestModule;
use Splot\Framework\Tests\Modules\Fixtures\TestModule;

use Psr\Log\NullLogger;

class RouterTest extends \PHPUnit_Framework_TestCase
{

    protected function provideRouter() {
        return new Router(new NullLogger());
    }

    public function testAddingRoutes() {
        $router = $this->provideRouter();

        $testRoute = $router->addRoute('test', TestController::__class());
        $this->assertTrue($testRoute instanceof Route);

        $testMethodsRoute = $router->addRoute('test.methods', TestController::__class(), null, null, array(
            'get' => 'index',
            'pUt' => false,
            'POST' => 'save'
        ));
        $this->assertTrue($testMethodsRoute instanceof Route);

        $registered = $router->getRoutes();
        $this->assertEquals(2, count($registered));
        $this->assertArrayHasKey('test', $registered);
        $this->assertArrayHasKey('test.methods', $registered);

        $this->assertSame($testRoute, $router->getRoute('test'));
        $this->assertSame($testMethodsRoute, $router->getRoute('test.methods'));
    }

    /**
     * @expectedException \Splot\Framework\Routes\Exceptions\InvalidControllerException
     */
    public function testAddingRouteNotForValidController() {
        $router = $this->provideRouter();
        $router->addRoute('invalid', 'stdClass');
    }

    /**
     * @expectedException \Splot\Framework\Routes\Exceptions\InvalidRouteException
     */
    public function testAddingPublicRouteWithNoUrlPattern() {
        $router = $this->provideRouter();
        $router->addRoute('empty', EmptyController::__class());
    }

    public function testGettingRouteForUrl() {
        $router = $this->provideRouter();

        $indexRoute = $router->addRoute('test.index', TestController::__class(), null, '/test/');
        $listRoute = $router->addRoute('test.list', TestController::__class(), null, '/test/{page:int}/{limit:int}?');
        $itemRoute = $router->addRoute('test.item', TestController::__class(), null, '/test/{id:int}/{slug}.html');
        $itemAdminRoute = $router->addRoute('test.item_admin', TestController::__class(), null, '/test/admin/{id:int}/', array(
            'get' => 'index',
            'put' => false,
            'post' => 'index',
            'delete' => 'index'
        ));

        $this->assertFalse($router->getRouteForUrl('/lorem/ipsum/'));
        $this->assertSame($itemRoute, $router->getRouteForUrl('/test/123/lipsum.html'));
        $this->assertSame($listRoute, $router->getRouteForUrl('/test/2/'));
        $this->assertSame($listRoute, $router->getRouteForUrl('/test/2/50', 'post'));
        $this->assertSame($indexRoute, $router->getRouteForUrl('/test/'));
        $this->assertSame($itemAdminRoute, $router->getRouteForUrl('/test/admin/12/'));
        $this->assertFalse($router->getRouteForUrl('/test/admin/12/', 'PUT'));
    }

    public function testGettingRouteForRequest() {
        $router = $this->provideRouter();

        $indexRoute = $router->addRoute('test.index', TestController::__class(), null, '/test/');
        $listRoute = $router->addRoute('test.list', TestController::__class(), null, '/test/{page:int}/{limit:int}?');
        $itemRoute = $router->addRoute('test.item', TestController::__class(), null, '/test/{id:int}/{slug}.html');
        $itemAdminRoute = $router->addRoute('test.item_admin', TestController::__class(), null, '/test/admin/{id:int}/', array(
            'get' => 'index',
            'put' => false,
            'post' => 'index',
            'delete' => 'index'
        ));

        $this->assertFalse($router->getRouteForRequest(Request::create('/lorem/ipsum/')));
        $this->assertSame($itemRoute, $router->getRouteForRequest(Request::create('/test/123/lipsum.html')));
        $this->assertSame($listRoute, $router->getRouteForRequest(Request::create('/test/2/')));
        $this->assertSame($listRoute, $router->getRouteForRequest(Request::create('/test/2/50', 'POST')));
        $this->assertSame($indexRoute, $router->getRouteForRequest(Request::create('/test/')));
        $this->assertSame($itemAdminRoute, $router->getRouteForRequest(Request::create('/test/admin/12/')));
        $this->assertFalse($router->getRouteForRequest(Request::create('/test/admin/12/', 'PUT')));
    }

    /**
     * @expectedException \Splot\Framework\Routes\Exceptions\RouteNotFoundException
     */
    public function testGettingUndefinedRoute() {
        $router = $this->provideRouter();
        $router->getRoute('undefined');
    }

    public function testGenerate() {
        $router = $this->provideRouter();

        $router->addRoute('test', TestController::__class(), null, '/test/{id:int}/{day:int}/{month}/{year:int}/{slug}/{page:int}.html');

        // all params
        $this->assertEquals(
            '/test/123/13/jul/2013/lipsum/1.html',
            $router->generate('test', array(
                'id' => 123,
                'slug' => 'lipsum',
                'page' => 1,
                'day' => '13',
                'month' => 'jul',
                'year' => 2013
            ))
        );

        // additional params
        $this->assertEquals(
            '/test/123/13/jul/2013/lipsum/1.html?from=spotlight&utm_source=google&scores%5B0%5D=1&scores%5B1%5D=23',
            $router->generate('test', array(
                'id' => 123,
                'slug' => 'lipsum',
                'page' => 1,
                'day' => '13',
                'month' => 'jul',
                'year' => '2013',
                'from' => 'spotlight',
                'utm_source' => 'google',
                'scores' => array(1, 23)
            ))
        );

        $router->addRoute('test.optional', TestController::__class(), null, '/test/{id:int}/{slug}?');

        $this->assertEquals('/test/123/', $router->generate('test.optional', array(
            'id' => 123
        )));
        $this->assertEquals('/test/123/lipsum', $router->generate('test.optional', array(
            'id' => '123',
            'slug' => 'lipsum'
        )));
    }

    /**
     * @expectedException \Splot\Framework\Routes\Exceptions\RouteParameterNotFoundException
     */
    public function testgenerateInsufficientParams() {
        $router = $this->provideRouter();

        $router->addRoute('test.insufficient', TestController::__class(), null, '/test/{id:int}/{day:int}/{month}/{year:int}/{slug}/{page:int}.html');
        $this->assertEquals('/test/123/13/jul/2013/lipsum/1.html', $router->generate('test.insufficient', array(
            'id' => 123,
            'slug' => 'lipsum',
            'page' => 1
        )));
    }

    public function testReadingModuleRoutes() {
        $router = $this->provideRouter();
        $module = new SplotRouterTestModule();
        $router->readModuleRoutes($module);

        $foundRoutes = $router->getRoutes();
        $this->assertEquals(8, count($foundRoutes));

        foreach(array(
            'SplotRouterTestModule:Footer',
            'SplotRouterTestModule:Index',
            'SplotRouterTestModule:Item',
            'SplotRouterTestModule:Item\Comments',
            'SplotRouterTestModule:Item\Gallery',
            'SplotRouterTestModule:Item\Gallery\Photo',
            'SplotRouterTestModule:Admin\Item',
            'SplotRouterTestModule:Admin\ItemsList'
        ) as $routeName) {
            $this->assertArrayHasKey($routeName, $foundRoutes);
            $this->assertTrue($foundRoutes[$routeName] instanceof Route);
        }
    }

    public function testReadingModuleWhenEmpty() {
        $router = $this->provideRouter();
        $module = new TestModule();
        $router->readModuleRoutes($module);

        $foundRoutes = $router->getRoutes();
        $this->assertEmpty($foundRoutes);
    }

}
