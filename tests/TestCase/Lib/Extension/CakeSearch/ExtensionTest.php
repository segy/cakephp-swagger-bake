<?php

namespace SwaggerBake\Test\TestCase\Lib\Extension\CakeSearch;

use Cake\Event\Event;
use Cake\Routing\RouteBuilder;
use Cake\Routing\Router;
use Cake\TestSuite\TestCase;
use SwaggerBake\Lib\Configuration;
use SwaggerBake\Lib\Exception\SwaggerBakeRunTimeException;
use SwaggerBake\Lib\Extension\CakeSearch\Extension;
use SwaggerBake\Lib\ExtensionLoader;
use SwaggerBake\Lib\Extension\CakeSearch\Attribute\OpenApiSearch;
use SwaggerBake\Lib\Model\ModelScanner;
use SwaggerBake\Lib\OpenApi\Operation;
use SwaggerBake\Lib\Route\RouteScanner;
use SwaggerBake\Lib\Swagger;
use SwaggerBake\Test\TestCase\Helper\ReflectionAttributeTrait;
use SwaggerBakeTest\App\Model\Table\DepartmentsTable;

class ExtensionTest extends TestCase
{
    use ReflectionAttributeTrait;

    /** @var string[] */
    public $fixtures = [
        'plugin.SwaggerBake.Employees',
        'plugin.SwaggerBake.Departments',
    ];

    private array $config;

    private Router $router;

    public function setUp(): void
    {
        parent::setUp(); // TODO: Change the autogenerated stub
        $router = new Router();
        $router::scope('/', function (RouteBuilder $builder) {
            $builder->setExtensions(['json']);
            $builder->resources('Employees', [
                'only' => ['search'],
                'map' => [
                    'search' => [
                        'action' => 'search',
                        'method' => 'GET',
                        'path' => 'search'
                    ]
                ]
            ]);
        });
        $this->router = $router;

        $this->config = [
            'prefix' => '/',
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
        ];
        $this->assertTrue(class_exists(OpenApiSearch::class));
        $this->loadPlugins(['Search']);

        ExtensionLoader::load();
    }

    public function test_search_parameters_exist_in_openapi_output(): void
    {
        $configuration = new Configuration($this->config, SWAGGER_BAKE_TEST_APP);

        $cakeRoute = new RouteScanner($this->router, $configuration);
        $swagger = new Swagger(new ModelScanner($cakeRoute, $configuration), $configuration);

        $arr = json_decode($swagger->toString(), true);

        $this->assertArrayHasKey('get', $arr['paths']['/employees/search']);
        $search = $arr['paths']['/employees/search']['get'];

        $this->assertEquals('first_name', $search['parameters'][0]['name']);
    }

    public function test_getOperation_throws_exception_when_event_subject_is_invalid(): void
    {
        $this->expectException(SwaggerBakeRunTimeException::class);
        $event = new Event('test', 'no');
        $this->assertInstanceOf(Operation::class, (new Extension())->getOperation($event));
        $this->assertStringContainsString('subject must be an instance of', $this->getExpectedExceptionMessage());
    }

    public function test_getOperation_returns_early(): void
    {
        /*
         * Should exit early when Operation is not HTTP Get
         */
        $event = new Event('test', (new Operation('id', 'POST')));
        $this->assertInstanceOf(Operation::class, (new Extension())->getOperation($event));

        /*
         * Should exit early when no reflectionMethod attribute exists in event data
         */
        $event = new Event('test', (new Operation('id', 'GET')));
        $this->assertInstanceOf(Operation::class, (new Extension())->getOperation($event));

        /*
         * Should exit early when reflectionMethod attribute is not an instance of ReflectionMethod
         */
        $event = new Event('test', (new Operation('id', 'GET')), [
            'reflectionMethod' => 'blah'
        ]);
        $this->assertInstanceOf(Operation::class, (new Extension())->getOperation($event));

        /*
         * Should exit early when reflectionMethod attribute does not contain OpenApiSearch Attribute
         */
        $event = new Event('test', (new Operation('id', 'GET')), [
            'reflectionMethod' => new \ReflectionMethod($this, 'test_getOperation_returns_early')
        ]);
        $this->assertInstanceOf(Operation::class, (new Extension())->getOperation($event));
    }

    public function test_getOperation_throws_exception_when_table_class_does_not_exist(): void
    {
        $mockReflectionMethod = $this->mockReflectionMethod(OpenApiSearch::class, [
            'tableClass' => 'nope',
        ]);

        $event = new Event('test', (new Operation('id', 'GET')), [
            'reflectionMethod' => $mockReflectionMethod
        ]);
        $this->expectException(SwaggerBakeRunTimeException::class);
        $this->assertInstanceOf(Operation::class, (new Extension())->getOperation($event));
        $this->assertStringContainsString('Unable to build OpenApiSearch', $this->getExpectedExceptionMessage());
    }

    public function test_getOperation_when_no_filters_exist(): void
    {
        $mockReflectionMethod = $this->mockReflectionMethod(OpenApiSearch::class, [
            'tableClass' => DepartmentsTable::class,
        ]);

        $event = new Event('test', (new Operation('id', 'GET')), [
            'reflectionMethod' => $mockReflectionMethod
        ]);
        $this->assertEmpty((new Extension())->getOperation($event)->getParameters());
    }
}