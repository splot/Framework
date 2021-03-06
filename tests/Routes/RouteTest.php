<?php
namespace Splot\Framework\Tests\Routes;

use Splot\Framework\Routes\Route;

use Splot\Framework\Tests\Routes\Fixtures\TestController;
use Splot\Framework\Tests\Routes\Fixtures\TestPrivateController;

/**
 * @coversDefaultClass \Splot\Framework\Routes\Route
 */
class RouteTest extends \PHPUnit_Framework_TestCase
{
    use \Splot\Framework\Tests\MockTrait;

    /**
     * @covers ::__construct
     * @covers ::getName
     * @covers ::getControllerClass
     * @covers ::getUrlPattern
     * @covers ::getModuleName
     * @covers ::getPrivate
     * @covers ::isPrivate
     * @covers ::getRegExp
     * @covers ::getMethods
     * @covers ::regexpFromUrlPattern
     * @covers ::prepareMethodsInfo
     * @covers ::getControllerMethodForHttpMethod
     */
    public function testInitialization() {
        $route = new Route(
            'test_route',
            TestController::class,
            '/test/{id:int}',
            array(
                'GET' => 'index',
                'post' => 'save',
                'pUt' => 'newItem',
                'delete' => false
            ),
            'RoutesTesting',
            false
        );

        $this->assertEquals('test_route', $route->getName());
        $this->assertEquals(TestController::class, $route->getControllerClass());
        $this->assertEquals('/test/{id:int}', $route->getUrlPattern());
        $this->assertEquals('RoutesTesting', $route->getModuleName());
        $this->assertFalse($route->getPrivate());
        $this->assertFalse($route->isPrivate());

        $regexp = $route->getRegExp();
        $this->assertEquals(1, preg_match($regexp, '/test/1'));
        $this->assertEquals(1, preg_match($regexp, '/test/123'));
        $this->assertEquals(0, preg_match($regexp, '/test/'));
        $this->assertEquals(0, preg_match($regexp, '/test/lipsum'));
        $this->assertEquals(0, preg_match($regexp, '/test/l'));

        $methods = $route->getMethods();
        $this->assertArrayHasKey('get', $methods);
        $this->assertArrayHasKey('post', $methods);
        $this->assertArrayHasKey('put', $methods);
        $this->assertArrayHasKey('delete', $methods);

        foreach($methods as $method => $info) {
            $this->assertArrayHasKey('method', $info);
            $this->assertArrayHasKey('params', $info);
        }

        $this->assertEquals('index', $route->getControllerMethodForHttpMethod('get'));
        $this->assertEquals('save', $route->getControllerMethodForHttpMethod('POST'));
        $this->assertEquals(false, $route->getControllerMethodForHttpMethod('delete'));
    }

    /**
     * @expectedException Splot\Framework\Routes\Exceptions\InvalidControllerException
     * 
     * @covers ::__construct
     * @covers ::prepareMethodsInfo
     */
    public function testInitializationWithPrivateControllerMethod() {
        $route = new Route(
            'test_invalid_route',
            TestPrivateController::class,
            '/test/',
            array(
                'get' => 'index',
                'post' => 'save',
                'put' => false,
                'delete' => false
            )
        );
    }

    /**
     * @expectedException Splot\Framework\Routes\Exceptions\InvalidControllerException
     * 
     * @covers ::__construct
     * @covers ::prepareMethodsInfo
     */
    public function testInitializationWithNoControllerMethod() {
        $route = new Route(
            'test_invalid_route_2',
            TestController::class,
            '/test/',
            array(
                'get' => 'list',
                'post' => false
            )
        );
    }

    /**
     * @covers ::willRespondToRequest
     * @covers ::regexpFromUrlPattern
     */
    public function testWillRespondToRequest() {
        $route = new Route(
            'lipsum',
            TestController::class,
            '/test/{id:int}',
            array(
                'get' => 'index',
                'post' => 'save',
                'put' => 'newItem',
                'delete' => false
            )
        );

        $this->assertTrue($route->willRespondToRequest('/test/1', 'get'));
        $this->assertTrue($route->willRespondToRequest('/test/1', 'POST'));
        $this->assertTrue($route->willRespondToRequest('/test/123', 'PUT'));
        $this->assertFalse($route->willRespondToRequest('/test/1', 'delete'));
        $this->assertFalse($route->willRespondToRequest('/test/lipsum', 'get'));

        $routePrivate = new Route(
            'lipsum',
            TestController::class,
            '/test/{id:int}',
            array(
                'get' => 'index',
                'post' => 'save',
                'put' => 'newItem',
                'delete' => false
            ),
            'TestModule',
            true
        );
        $this->assertTrue($routePrivate->getPrivate());
        $this->assertTrue($routePrivate->isPrivate());
        $this->assertFalse($routePrivate->willRespondToRequest('/test/1', 'get'));
        $this->assertFalse($routePrivate->willRespondToRequest('/test/1', 'POST'));
        $this->assertFalse($routePrivate->willRespondToRequest('/test/123', 'PUT'));
        $this->assertFalse($routePrivate->willRespondToRequest('/test/1', 'delete'));
        $this->assertFalse($routePrivate->willRespondToRequest('/test/lipsum', 'get'));

        $routeEncoded = new Route(
            'lipsum',
            TestController::class,
            '/test/{id:int}/{slug}/',
            array(
                'get' => 'index',
                'post' => false,
                'put' => false,
                'delete' => false
            ),
            'TestModule'
        );
        $this->assertTrue($routeEncoded->willRespondToRequest('/test/123/lorem%3Aipsum.dolor-sit%25amet+adipiscit+elit++::html/', 'get'));
    }

    /**
     * @covers ::willRespondToRequest
     * @covers ::regexpFromUrlPattern
     */
    public function testCatchAllConstraint() {
        $route = new Route('lipsum', TestController::class, '/test/{url:all}', array(
            'get' => 'index',
            'post' => 'save',
            'put' => 'newItem',
            'delete' => false
        ));

        $this->assertTrue($route->willRespondToRequest('/test/lorem-ipsum/dolor/sit/amet', 'get'));
        $this->assertFalse($route->willRespondToRequest('/test/', 'get'));
        $this->assertFalse($route->willRespondToRequest('/not-test/lorem/test', 'get'));

        // with optional url param
        $routeOptional = new Route('lipsum', TestController::class, '/test/{url:all}?', array(
            'get' => 'index',
            'post' => 'save',
            'put' => 'newItem',
            'delete' => false
        ));
        $this->assertTrue($routeOptional->willRespondToRequest('/test/', 'get'));


        $routeWithParams = new Route('lipsum', TestController::class, '/test/{id:int}/{slug:all}', array(
            'get' => 'index',
            'post' => 'save',
            'put' => 'newItem',
            'delete' => false
        ));

        $this->assertEquals(array(5, 'lorem-ipsum-dolor/sit-amet.html'), $routeWithParams->getControllerMethodArgumentsForUrl('/test/5/lorem-ipsum-dolor/sit-amet.html', 'get'));
    }

    /**
     * @covers ::getControllerMethodArgumentsFromArray
     */
    public function testControllerMethodArgumentsFromArray() {
        $route = new Route(
            'lipsum',
            TestController::class,
            '/test/{id:int}/{slug}/',
            array(
                'get' => 'index',
                'post' => 'save',
                'put' => 'newItem',
                'delete' => false
            )
        );

        $this->assertEquals(array(123, 'lipsum'), $route->getControllerMethodArgumentsFromArray('get', array(
            'id' => 123,
            'slug' => 'lipsum'
        )));

        $route2 = new Route(
            'lipsum',
            TestController::class,
            '/test/{slug}/{id:int}',
            array(
                'get' => 'index',
                'post' => 'save',
                'put' => 'newItem',
                'delete' => false
            )
        );

        $this->assertEquals(array(123, 'lipsum'), $route->getControllerMethodArgumentsFromArray('get', array(
            'id' => 123,
            'slug' => 'lipsum'
        )));

        $this->assertEquals(array(123, 'lorem:ipsum.dolor-sit%amet adipiscit  elit'), $route->getControllerMethodArgumentsFromArray('get', array(
            'id' => 123,
            'slug' => 'lorem%3Aipsum.dolor-sit%25amet+adipiscit++elit'
        )));
    }

    /**
     * @expectedException \MD\Foundation\Exceptions\NotFoundException
     * @covers ::getControllerMethodArgumentsFromArray
     */
    public function testControllerMethodArgumentsFromArrayInvalid() {
        $route = new Route(
            'lipsum',
            TestController::class,
            '/test/{id:int}/{slug}/',
            array(
                'get' => 'index',
                'post' => 'save',
                'put' => 'newItem',
                'delete' => false
            )
        );

        $route->getControllerMethodArgumentsFromArray('lipsum', array(
            'id' => 123,
            'slug' => 'lipsum'
        ));
    }

    /**
     * @covers ::getControllerMethodArgumentsForUrl
     */
    public function testControllerMethodArgumentsForUrl() {
        $route = new Route(
            'lipsum',
            TestController::class,
            '/test/{id:int}/{slug}/',
            array(
                'get' => 'index',
                'post' => 'save',
                'put' => 'newItem',
                'delete' => false
            )
        );

        $this->assertEquals(array(123, 'lipsum'), $route->getControllerMethodArgumentsForUrl('/test/123/lipsum/', 'get'));
        $this->assertEquals(array(123, 'lorem:ipsum.dolor-sit%amet'), $route->getControllerMethodArgumentsForUrl('/test/123/lorem%3Aipsum.dolor-sit%25amet/', 'get'));

        $route2 = new Route(
            'lipsum',
            TestController::class,
            // inverted order of parameters
            '/test/{slug}/{id:int}/',
            array(
                'get' => 'index',
                'post' => 'save',
                'put' => 'newItem',
                'delete' => false
            )
        );

        $this->assertEquals(array(123, 'lipsum'), $route2->getControllerMethodArgumentsForUrl('/test/lipsum/123/', 'get'));
    }

    /**
     * @expectedException \MD\Foundation\Exceptions\NotFoundException
     * @covers ::getControllerMethodArgumentsForUrl
     */
    public function testControllerMethodArgumentsForUrlInvalid() {
        $route = new Route(
            'lipsum',
            TestController::class,
            '/test/{id:int}/{slug}/',
            array(
                'get' => 'index',
                'post' => 'save',
                'put' => 'newItem',
                'delete' => false
            )
        );

        $route->getControllerMethodArgumentsForUrl('/testing/123/lipsum', 'get');
    }

    /**
     * @covers ::generateUrl
     */
    public function testGenerateUrl() {
        $route = new Route(
            'lipsum',
            TestController::class,
            '/test/{id:int}/{day:int}/{month}/{year:int}/{slug}/{page:int}.html',
            array(
                'get' => 'index',
                'post' => 'save',
                'put' => 'newItem',
                'delete' => false
            )
        );

        $this->assertEquals('/test/123/13/jul/2013/lipsum/1.html', $route->generateUrl(array(
            'id' => 123,
            'slug' => 'lipsum',
            'page' => 1,
            'day' => '13',
            'month' => 'jul',
            'year' => 2013
        )));

        $this->assertEquals('/test/123/13/jul/2013/lorem%3Aipsum.dolor-sit%25amet+adipiscit++elit/1.html', $route->generateUrl(array(
            'id' => 123,
            'slug' => 'lorem:ipsum.dolor-sit%amet adipiscit  elit',
            'page' => 1,
            'day' => '13',
            'month' => 'jul',
            'year' => 2013
        )));

        $this->assertEquals('/test/123/13/jul/2013/lipsum/1.html?from=spotlight&utm_source=google&scores%5B0%5D=1&scores%5B1%5D=23', $route->generateUrl(array(
            'id' => 123,
            'slug' => 'lipsum',
            'page' => 1,
            'day' => '13',
            'month' => 'jul',
            'year' => '2013',
            'from' => 'spotlight',
            'utm_source' => 'google',
            'scores' => array(1, 23)
        )));
    }

    /**
     * @covers ::generateUrl
     */
    public function testGenerateUrlWithOptionalParam() {
        $route = new Route(
            'lipsum',
            TestController::class,
            '/test/{id:int}/{slug}?',
            array(
                'get' => 'index',
                'post' => 'save',
                'put' => 'newItem',
                'delete' => false
            )
        );

        $this->assertEquals('/test/123/lipsum', $route->generateUrl(array(
            'id' => '123',
            'slug' => 'lipsum'
        )));

        $this->assertEquals('/test/123/', $route->generateUrl(array(
            'id' => '123'
        )));
    }

    /**
     * @covers ::generateUrl
     */
    public function testGenerateUrlWithHost() {
        $route = new Route(
            'lipsum',
            TestController::class,
            '/test/{id:int}/{slug}?',
            array(
                'get' => 'index',
                'post' => 'save',
                'put' => 'newItem',
                'delete' => false
            )
        );

        $this->assertEquals('http://www.host.com/test/123/lipsum', $route->generateUrl(array(
            'id' => '123',
            'slug' => 'lipsum'
        ), 'http://www.host.com/'));
    }

    /**
     * @expectedException \Splot\Framework\Routes\Exceptions\RouteParameterNotFoundException
     * @covers ::generateUrl
     */
    public function testgenerateUrlInsufficientParams() {
        $route = new Route(
            'lipsum',
            TestController::class,
            '/test/{id:int}/{day:int}/{month}/{year:int}/{slug}/{page:int}.html',
            array(
                'get' => 'index',
                'post' => 'save',
                'put' => 'newItem',
                'delete' => false
            )
        );

        $this->assertEquals('/test/123/13/jul/2013/lipsum/1.html', $route->generateUrl(array(
            'id' => 123,
            'slug' => 'lipsum',
            'page' => 1
        )));
    }

    /**
     * @expectedException \RuntimeException
     * @covers ::generateUrl
     */
    public function testGenerateUrlPrivate() {
        $routePrivate = new Route(
            'lipsum',
            TestController::class,
            '/test/{id:int}',
            array(
                'get' => 'index',
                'post' => 'save',
                'put' => 'newItem',
                'delete' => false
            ),
            'TestModule',
            true
        );

        $routePrivate->generateUrl(array(
            'id' => 123
        ));
    }

    /**
     * @covers ::expose
     */
    public function testExpose() {
        $route = new Route(
            'lipsum',
            TestController::class, 
            '/test/{id:int}/{data:int}/{month}/{year:int}/{slug}/{page:int}.html',
            array(
                'get' => 'index',
                'post' => 'save',
                'put' => 'newItem',
                'delete' => false
            )
        );

        $this->assertEquals('/test/{id}/{data}/{month}/{year}/{slug}/{page}.html', $route->expose());
    }

    /**
     * @expectedException \RuntimeException
     * @covers ::expose
     */
    public function testExposePrivate() {
        $routePrivate = new Route(
            'lipsum',
            TestController::class,
            '/test/{id:int}',
            array(
                'get' => 'index',
                'post' => 'save',
                'put' => 'newItem',
                'delete' => false
            ),
            'TestModule',
            true
        );

        $routePrivate->expose();
    }

}
