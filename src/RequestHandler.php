<?php

declare(strict_types=1);

namespace Sabroan\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @api
 */
final readonly class RequestHandler implements RequestHandlerInterface
{
    private int $index;

    /** @var list<MiddlewareInterface> */
    private array $middlewares;

    /**
     * @psalm-capabilities read-props
     */
    public function __construct(MiddlewareInterface ...$middlewares)
    {
        $this->index = 0;
        $this->middlewares = \array_values($middlewares);
    }

    /**
     * @psalm-capabilities read-props|write-this-props|write-refs
     */
    public function __clone()
    {
        $this->index = 0;
    }

    #[\Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $middleware = $this->middlewares[$this->index] ?? null;

        if (null === $middleware) {
            throw new \OutOfBoundsException('Queue is empty');
        }

        $handler = clone($this, [
            'index' => $this->index + 1,
        ]);

        return $middleware->process($request, $handler);
    }
}
