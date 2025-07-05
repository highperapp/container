<?php

declare(strict_types=1);

namespace HighPerApp\HighPer\Container\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\Group;
use HighPerApp\HighPer\Container\ResponsePool;

#[Group('unit')]
#[Group('response-pool')]
class ResponsePoolTest extends TestCase
{
    private ResponsePool $responsePool;

    protected function setUp(): void
    {
        $this->responsePool = new ResponsePool();
    }

    #[Test]
    #[TestDox('ResponsePool can get and return response objects')]
    public function testResponsePoolCanGetAndReturnResponseObjects(): void
    {
        $response1 = $this->responsePool->getResponse();
        $response2 = $this->responsePool->getResponse();

        $this->assertIsObject($response1);
        $this->assertIsObject($response2);
        $this->assertNotSame($response1, $response2);

        $this->responsePool->returnResponse($response1);
        $response3 = $this->responsePool->getResponse();

        $this->assertSame($response1, $response3);
    }

    #[Test]
    #[TestDox('ResponsePool can get and return request objects')]
    public function testResponsePoolCanGetAndReturnRequestObjects(): void
    {
        $request1 = $this->responsePool->getRequest();
        $request2 = $this->responsePool->getRequest();

        $this->assertIsObject($request1);
        $this->assertIsObject($request2);
        $this->assertNotSame($request1, $request2);

        $this->responsePool->returnRequest($request1);
        $request3 = $this->responsePool->getRequest();

        $this->assertSame($request1, $request3);
    }

    #[Test]
    #[TestDox('ResponsePool generates optimized JSON responses')]
    public function testResponsePoolGeneratesOptimizedJsonResponses(): void
    {
        $data = ['message' => 'test', 'status' => 'ok'];
        $response = $this->responsePool->jsonResponse($data);

        $this->assertIsArray($response);
        $this->assertArrayHasKey('status', $response);
        $this->assertArrayHasKey('headers', $response);
        $this->assertArrayHasKey('body', $response);
        $this->assertEquals(200, $response['status']);
        $this->assertStringContainsString('application/json', $response['headers']['Content-Type']);
        $this->assertStringContainsString('test', $response['body']);
    }

    #[Test]
    #[TestDox('ResponsePool caches JSON responses')]
    public function testResponsePoolCachesJsonResponses(): void
    {
        $data = ['test' => 'data'];

        $response1 = $this->responsePool->jsonResponse($data);
        $response2 = $this->responsePool->jsonResponse($data);

        $this->assertEquals($response1, $response2);

        $stats = $this->responsePool->getStats();
        $this->assertGreaterThan(0, $stats['json_cache']['hits']);
    }

    #[Test]
    #[TestDox('ResponsePool optimizes headers')]
    public function testResponsePoolOptimizesHeaders(): void
    {
        $headers = ['content-type' => 'text/plain', 'x-custom-header' => 'value'];
        $optimized = $this->responsePool->optimizeHeaders($headers);

        $this->assertArrayHasKey('Content-Type', $optimized);
        $this->assertArrayHasKey('X-Custom-Header', $optimized);
        $this->assertEquals('text/plain', $optimized['Content-Type']);
    }

    #[Test]
    #[TestDox('ResponsePool provides pool statistics')]
    public function testResponsePoolProvidesPoolStatistics(): void
    {
        $this->responsePool->getResponse();
        $this->responsePool->getRequest();
        $this->responsePool->jsonResponse(['test' => 'data']);

        $stats = $this->responsePool->getStats();

        $this->assertArrayHasKey('response_pool', $stats);
        $this->assertArrayHasKey('request_pool', $stats);
        $this->assertArrayHasKey('json_cache', $stats);
        $this->assertArrayHasKey('header_cache', $stats);
        $this->assertArrayHasKey('memory_efficiency', $stats);

        $this->assertArrayHasKey('hits', $stats['response_pool']);
        $this->assertArrayHasKey('misses', $stats['response_pool']);
        $this->assertArrayHasKey('hit_ratio', $stats['response_pool']);
    }

    #[Test]
    #[TestDox('ResponsePool can clear all pools and caches')]
    public function testResponsePoolCanClearAllPoolsAndCaches(): void
    {
        $this->responsePool->getResponse();
        $this->responsePool->getRequest();
        $this->responsePool->jsonResponse(['test' => 'data']);

        $this->responsePool->clear();

        $stats = $this->responsePool->getStats();
        $this->assertEquals(0, $stats['response_pool']['hits']);
        $this->assertEquals(0, $stats['request_pool']['hits']);
        $this->assertEquals(0, $stats['json_cache']['hits']);
    }

    #[Test]
    #[TestDox('ResponsePool can be configured')]
    public function testResponsePoolCanBeConfigured(): void
    {
        $this->responsePool->configure(500, 2500);

        $stats = $this->responsePool->getStats();
        $this->assertEquals(500, $stats['memory_efficiency']['max_pool_size']);
        $this->assertEquals(2500, $stats['memory_efficiency']['max_cache_size']);
    }

    #[Test]
    #[TestDox('ResponsePool response objects have expected methods')]
    public function testResponsePoolResponseObjectsHaveExpectedMethods(): void
    {
        $response = $this->responsePool->getResponse();

        $this->assertTrue(method_exists($response, 'withStatus'));
        $this->assertTrue(method_exists($response, 'withHeader'));
        $this->assertTrue(method_exists($response, 'withBody'));
        $this->assertTrue(method_exists($response, 'getStatus'));
        $this->assertTrue(method_exists($response, 'getHeaders'));
        $this->assertTrue(method_exists($response, 'getBody'));

        $response->withStatus(201)->withHeader('X-Test', 'value')->withBody('test body');

        $this->assertEquals(201, $response->getStatus());
        $this->assertEquals('value', $response->getHeaders()['X-Test']);
        $this->assertEquals('test body', $response->getBody());
    }

    #[Test]
    #[TestDox('ResponsePool request objects have expected methods')]
    public function testResponsePoolRequestObjectsHaveExpectedMethods(): void
    {
        $request = $this->responsePool->getRequest();

        $this->assertTrue(method_exists($request, 'withMethod'));
        $this->assertTrue(method_exists($request, 'withPath'));
        $this->assertTrue(method_exists($request, 'withHeader'));
        $this->assertTrue(method_exists($request, 'withBody'));
        $this->assertTrue(method_exists($request, 'getMethod'));
        $this->assertTrue(method_exists($request, 'getPath'));
        $this->assertTrue(method_exists($request, 'getHeaders'));
        $this->assertTrue(method_exists($request, 'getBody'));

        $request->withMethod('POST')->withPath('/api/test')->withHeader('Content-Type', 'application/json');

        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('/api/test', $request->getPath());
        $this->assertEquals('application/json', $request->getHeaders()['Content-Type']);
    }

    #[Test]
    #[TestDox('ResponsePool handles different JSON response status codes')]
    public function testResponsePoolHandlesDifferentJsonResponseStatusCodes(): void
    {
        $data = ['error' => 'Not found'];
        $response = $this->responsePool->jsonResponse($data, 404);

        $this->assertEquals(404, $response['status']);
        $this->assertStringContainsString('Not found', $response['body']);
    }

    #[Test]
    #[TestDox('ResponsePool handles custom headers in JSON responses')]
    public function testResponsePoolHandlesCustomHeadersInJsonResponses(): void
    {
        $data = ['message' => 'test'];
        $headers = ['X-Custom' => 'custom-value', 'X-API-Version' => 'v1'];
        $response = $this->responsePool->jsonResponse($data, 200, $headers);

        $this->assertEquals('custom-value', $response['headers']['X-Custom']);
        $this->assertEquals('v1', $response['headers']['X-Api-Version']);
        $this->assertEquals('application/json', $response['headers']['Content-Type']);
    }
}
