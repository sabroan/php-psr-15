<?php

declare(strict_types=1);

namespace Sabroan\Http\Tests;

use Sabroan\Http\RequestHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(RequestHandler::class)]
final class RequestHandlerTest extends TestCase
{
    private function createRequestHandler(MiddlewareInterface ...$middlewares): RequestHandler
    {
        return new RequestHandler(...$middlewares);
    }

    #[DataProvider('provideMiddlewareCounts')]
    public function testPipelineExecution(int $count): void
    {
        $request = self::createStub(ServerRequestInterface::class);
        $expectedResponse = self::createStub(ResponseInterface::class);

        $executed = 0;
        $middlewares = [];

        for ($i = 0; $i < $count; $i++) {
            $isLast = ($i === $count - 1);
            $middleware = self::createStub(MiddlewareInterface::class);
            $middleware->method('process')->willReturnCallback(
                static function (
                    ServerRequestInterface $req,
                    RequestHandlerInterface $handler,
                ) use (
                    &$executed,
                    $isLast,
                    $expectedResponse
                ): ResponseInterface {
                    $executed++;
                    return $isLast
                        ? $expectedResponse
                        : $handler->handle($req);
                },
            );
            $middlewares[] = $middleware;
        }

        $response = $this->createRequestHandler(...$middlewares)->handle($request);

        self::assertSame($expectedResponse, $response);
        self::assertSame($count, $executed);
    }

    /** @return array<string, array{int}> */
    public static function provideMiddlewareCounts(): array
    {
        return [
            'single' => [1],
            'multiple' => [3],
        ];
    }

    public function testHandleThrowsExceptionWhenConstructedEmpty(): void
    {
        $request = self::createStub(ServerRequestInterface::class);
        $RequestHandler = $this->createRequestHandler();

        $this->expectException(\OutOfBoundsException::class);

        $RequestHandler->handle($request);
    }

    public function testHandleThrowsExceptionWhenQueueExhausted(): void
    {
        $request = self::createStub(ServerRequestInterface::class);
        $middleware = self::createStub(MiddlewareInterface::class);
        $middleware->method('process')->willReturnCallback(
            static fn(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface => $handler->handle($request),
        );

        $this->expectException(\OutOfBoundsException::class);

        $this->createRequestHandler($middleware)->handle($request);
    }

    public function testPipelineExecutionWithNamedArguments(): void
    {
        $request = self::createStub(ServerRequestInterface::class);
        $expectedResponse = self::createStub(ResponseInterface::class);

        $middleware = self::createStub(MiddlewareInterface::class);
        $middleware->method('process')->willReturn($expectedResponse);

        $RequestHandler = new RequestHandler(...['first' => $middleware]);

        self::assertSame($expectedResponse, $RequestHandler->handle($request));
    }

    public function testRequestHandlerIsImmutableAcrossCalls(): void
    {
        $request = self::createStub(ServerRequestInterface::class);
        $response = self::createStub(ResponseInterface::class);

        $executions = 0;

        $middleware = self::createStub(MiddlewareInterface::class);
        $middleware->method('process')->willReturnCallback(
            static function (
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ) use (&$executions, $response): ResponseInterface {
                $executions++;

                return $response;
            },
        );

        $runner = $this->createRequestHandler($middleware);

        $runner->handle($request);
        $runner->handle($request);

        self::assertSame(2, $executions);
    }

    public function testCloningResetsIndexToZero(): void
    {
        $request = self::createStub(ServerRequestInterface::class);
        $response = self::createStub(ResponseInterface::class);

        $middleware1Executions = 0;
        $middleware2Executions = 0;
        $capturedHandler = null;

        $middleware1 = self::createStub(MiddlewareInterface::class);
        $middleware1->method('process')->willReturnCallback(
            static function (
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ) use (&$middleware1Executions): ResponseInterface {
                $middleware1Executions++;

                return $handler->handle($request);
            },
        );

        $middleware2 = self::createStub(MiddlewareInterface::class);
        $middleware2->method('process')->willReturnCallback(
            static function (
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ) use (&$middleware2Executions, &$capturedHandler, $response): ResponseInterface {
                $middleware2Executions++;
                $capturedHandler = $handler;

                return $response;
            },
        );

        $runner = $this->createRequestHandler($middleware1, $middleware2);

        $runner->handle($request);

        self::assertSame(1, $middleware1Executions);
        self::assertSame(1, $middleware2Executions);
        self::assertInstanceOf(RequestHandlerInterface::class, $capturedHandler);

        $resetRunner = clone $capturedHandler;
        $resetRunner->handle($request);

        self::assertSame(2, $middleware1Executions);
        self::assertSame(2, $middleware2Executions);
    }

    public function testHandlerAdvancesToNextMiddleware(): void
    {
        $request = self::createStub(ServerRequestInterface::class);
        $response = self::createStub(ResponseInterface::class);

        $middleware2Executed = false;

        $middleware1 = self::createStub(MiddlewareInterface::class);
        $middleware1->method('process')->willReturnCallback(
            static fn(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface => $handler->handle($request),
        );

        $middleware2 = self::createStub(MiddlewareInterface::class);
        $middleware2->method('process')->willReturnCallback(
            static function (
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ) use (&$middleware2Executed, $response): ResponseInterface {
                $middleware2Executed = true;
                return $response;
            },
        );

        $runner = $this->createRequestHandler($middleware1, $middleware2);

        self::assertSame($response, $runner->handle($request));
        self::assertTrue($middleware2Executed);
    }
}
