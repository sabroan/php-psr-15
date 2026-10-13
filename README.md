# HTTP Request Handler
A lightweight PSR-15 implementation for PHP 8.5+.

## Features
- Supports variadic and named middleware arguments.
- Immutable readonly handler with no state changes during execution.
- Supports cloning to reset the pipeline in long-running workers like RoadRunner or Swoole.

## Requirements
PHP 8.5 or later

## Installation

Install the package using Composer:

`composer require sabroan/psr-15`

## Usage

Create a request handler by passing one or more PSR-15 middleware instances:

```php
<?php

declare(strict_types=1);

use Sabroan\Http\RequestHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

$middleware = new class implements MiddlewareInterface {
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        return $handler->handle($request);
    }
};

$handler = new RequestHandler($middleware);

/** @var ServerRequestInterface $request */
$response = $handler->handle($request);
```

### Resetting a handler with cloning

In long-running applications, such as RoadRunner or Swoole workers, you can clone a handler to reset index without reconstructing the middleware pipeline:

```php
<?php

declare(strict_types=1);

use OpenSwoole\Core\Psr;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use OpenSwoole\Http\Server;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sabroan\Http\RequestHandler;

$middleware = new class implements MiddlewareInterface {
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        return $handler->handle($request);
    }
};
$handler = new RequestHandler($middleware);

$server = new Server("127.0.0.1", 9501);
$server->on("start", function (Server $server) {
    echo "OpenSwoole http server is started at http://127.0.0.1:9501\n";
});
$server->on("request", function (Request $request, Response $response) {
    $psrRequest = Psr\ServerRequest::from($request);
    $psrResponse = (clone $handler)->handle($psrRequest);
    Psr\Response::emit($response, $psrResponse);
});
$server->start();
```
Cloning resets the handler but reuses the same middleware instances, so ensure middleware does not retain request-specific state between requests.

## License

This package is released under the MIT License. See the LICENSE file for details.