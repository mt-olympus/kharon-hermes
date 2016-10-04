<?php
namespace Kharon\Hermes;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class CollectorMiddleware
{
    private $collector;

    public function __construct(Collector $collector)
    {
        $this->collector = $collector;
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param null|callable $next
     * @return null|Response
     */
    public function __invoke(Request $request, Response $response, callable $next = null)
    {
        $this->collector->setSourceRequest($request);

        return $next($request, $response);
    }
}
