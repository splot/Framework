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

/**
 * @coversDefaultClass Splot\Framework\Routes\Router
 */
class RouterTest extends \PHPUnit_Framework_TestCase
{

    protected function provideRouter() {
        return new Router(new NullLogger());
    }

    /**
     * @covers ::addRoute
     * @covers ::getRoutes
     */
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
     * @covers ::addRoute
     */
    public function testAddingRouteNotForValidController() {
        $router = $this->provideRouter();
        $router->addRoute('invalid', 'stdClass');
    }

    /**
     * @expectedException \Splot\Framework\Routes\Exceptions\InvalidRouteException
     * @covers ::addRoute
     */
    public function testAddingPublicRouteWithNoUrlPattern() {
        $router = $this->provideRouter();
        $router->addRoute('empty', EmptyController::__class());
    }

    /**
     * @covers ::getRouteForUrl
     */
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

    /**
     * @covers ::getRouteForRequest
     */
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
     * @covers ::getRoute
     */
    public function testGettingUndefinedRoute() {
        $router = $this->provideRouter();
        $router->getRoute('undefined');
    }

    /**
     * @covers ::generate
     */
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
     * @covers ::generate
     */
    public function testGenerateWithHost() {
        $router = $this->provideRouter();
        $router->setHost('www.host.com');

        $router->addRoute('test', TestController::__class(), null, '/test/{id:int}/{day:int}/{month}/{year:int}/{slug}/{page:int}.html');

        $this->assertEquals(
            'http://www.host.com/test/123/13/jul/2013/lipsum/1.html',
            $router->generate('test', array(
                'id' => 123,
                'slug' => 'lipsum',
                'page' => 1,
                'day' => '13',
                'month' => 'jul',
                'year' => 2013
            ), true)
        );
    }

    /**
     * @expectedException \Splot\Framework\Routes\Exceptions\RouteParameterNotFoundException
     * @covers ::generate
     */
    public function testGenerateInsufficientParams() {
        $router = $this->provideRouter();

        $router->addRoute('test.insufficient', TestController::__class(), null, '/test/{id:int}/{day:int}/{month}/{year:int}/{slug}/{page:int}.html');
        $this->assertEquals('/test/123/13/jul/2013/lipsum/1.html', $router->generate('test.insufficient', array(
            'id' => 123,
            'slug' => 'lipsum',
            'page' => 1
        )));
    }

    /**
     * @covers ::expose
     */
    public function testExpose() {
        $router = $this->provideRouter();

        $router->addRoute('test', TestController::__class(), null, '/test/{id:int}/{data:int}/{month}/{year:int}/{slug}/{page:int}.html');

        $this->assertEquals('/test/{id}/{data}/{month}/{year}/{slug}/{page}.html', $router->expose('test'));
    }

    /**
     * @covers ::readModuleRoutes
     */
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

    /**
     * @covers ::readModuleRoutes
     */
    public function testReadingModuleWhenEmpty() {
        $router = $this->provideRouter();
        $module = new TestModule();
        $router->readModuleRoutes($module);

        $foundRoutes = $router->getRoutes();
        $this->assertEmpty($foundRoutes);
    }

    /**
     * @covers ::setProtocol
     * @covers ::getProtocol
     */
    public function testSettingAndGettingProtocol() {
        $router = $this->provideRouter();
        $router->setProtocol('http');
        $this->assertEquals('http://', $router->getProtocol());
        $router->setProtocol('http://');
        $this->assertEquals('http://', $router->getProtocol());
    }

    /**
     * @covers ::setHost
     * @covers ::getHost
     */
    public function testSettingAndGettingHost() {
        $router = $this->provideRouter();
        $router->setHost('www.host.com');
        $this->assertEquals('www.host.com', $router->getHost());
        $router->setHost('www.host.com/');
        $this->assertEquals('www.host.com', $router->getHost());
    }

    /**
     * @covers ::setPort
     * @covers ::getPort
     */
    public function testSettingAndGettingPort() {
        $router = $this->provideRouter();
        $router->setPort(80);
        $this->assertEquals(80, $router->getPort());
        $router->setPort('80');
        $this->assertEquals(80, $router->getPort());
    }

    /**
     * @covers ::getProtocolAndHost
     */
    public function testGettingProtocolAndHost() {
        $router = $this->provideRouter();
        $router->setProtocol('https');
        $router->setHost('www.host.com/');
        $router->setPort('8080');
        $this->assertEquals('https://www.host.com:8080/', $router->getProtocolAndHost());

        $router->setPort(80);
        $this->assertEquals('https://www.host.com/', $router->getProtocolAndHost());
    }

}
