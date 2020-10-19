<?php


namespace SwaggerBake\Test\TestCase\Lib\Annotations;


use Cake\Routing\Route\DashedRoute;
use Cake\Routing\Router;
use Cake\Routing\RouteBuilder;
use Cake\TestSuite\TestCase;
use SwaggerBake\Lib\AnnotationLoader;
use SwaggerBake\Lib\Model\ModelScanner;
use SwaggerBake\Lib\Route\RouteScanner;
use SwaggerBake\Lib\Configuration;
use SwaggerBake\Lib\Swagger;

class SwagEntityAttributeTest extends TestCase
{
    public $fixtures = [
        'plugin.SwaggerBake.Employees'
    ];

    private $router;

    public function setUp(): void
    {
        parent::setUp(); // TODO: Change the autogenerated stub
        $router = new Router();
        $router::scope('/api', function (RouteBuilder $builder) {
            $builder->setExtensions(['json']);
            $builder->resources('Employees');
        });
        $this->router = $router;

        $this->config = new Configuration([
            'prefix' => '/api',
            'yml' => '/config/swagger-bare-bones.yml',
            'json' => '/webroot/swagger.json',
            'webPath' => '/swagger.json',
            'hotReload' => false,
            'exceptionSchema' => 'Exception',
            'requestAccepts' => ['application/x-www-form-urlencoded'],
            'responseContentTypes' => ['application/json'],
            'namespaces' => [
                'controllers' => ['\SwaggerBakeTest\App\\'],
                'entities' => ['\SwaggerBakeTest\App\\'],
                'tables' => ['\SwaggerBakeTest\App\\'],
            ]
        ], SWAGGER_BAKE_TEST_APP);

        AnnotationLoader::load();
    }

    public function testEntityAttribute()
    {
        $cakeRoute = new RouteScanner($this->router, $this->config);

        $swagger = new Swagger(new ModelScanner($cakeRoute, $this->config));

        $arr = json_decode($swagger->toString(), true);

        $employee = $arr['components']['schemas']['Employee'];

        $this->assertNotEmpty($employee['properties']['gender']['enum']);
        $this->assertEquals('female', $employee['properties']['gender']['example']);
        $this->assertEquals(3, $employee['properties']['last_name']['minLength']);
        $this->assertEquals(59, $employee['properties']['last_name']['maxLength']);
        $this->assertEquals('/\W/', $employee['properties']['last_name']['pattern']);
    }
}