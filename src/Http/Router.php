<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Minimální router. Vzory cest: "/nabidky", "/nabidky/{id}" (jen číslice).
 * Handler: callable(Request $request, array<string,string> $params): Response
 */
final class Router
{
    /** @var array<string, array<string, callable>> vzor => metoda => handler */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function add(string $method, string $pattern, callable $handler): void
    {
        $this->routes[$pattern][strtoupper($method)] = $handler;
    }

    public function dispatch(Request $request): Response
    {
        foreach ($this->routes as $pattern => $handlers) {
            $params = $this->match($pattern, $request->path);
            if ($params === null) {
                continue;
            }
            $method = $request->method === 'HEAD' ? 'GET' : $request->method;
            if (!isset($handlers[$method])) {
                throw HttpException::methodNotAllowed(array_keys($handlers));
            }
            return ($handlers[$method])($request, $params);
        }
        throw HttpException::notFound();
    }

    /** @return array<string, string>|null */
    private function match(string $pattern, string $path): ?array
    {
        // preg_quote escapuje i složené závorky: "{id}" -> "\{id\}"
        $regex = preg_replace_callback(
            '#\\\\\{([a-z_]+)\\\\\}#',
            static fn (array $m): string => '(?P<' . $m[1] . '>[1-9][0-9]{0,9})',
            preg_quote($pattern, '#')
        );
        if (!preg_match('#^' . $regex . '$#', $path, $matches)) {
            return null;
        }
        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }
}
