<?php

declare(strict_types=1);

namespace App\Http;

use InvalidArgumentException;

final class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        public string $body = '',
        public int $status = 200,
        public array $headers = [],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * Přesměrování jen v rámci webu. Brání otevřenému přesměrování
     * (např. "//zlo.cz" nebo "https://zlo.cz").
     */
    public static function redirect(string $path, int $status = 303): self
    {
        if (!str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '\\')) {
            throw new InvalidArgumentException('Přesměrovat lze jen na cestu v rámci webu.');
        }
        return new self('', $status, ['Location' => $path]);
    }

    /** Výslovné přesměrování mimo web (jen na známé adresy, např. Logto). */
    public static function redirectExternal(string $url): self
    {
        if (!preg_match('#^https?://#i', $url)) {
            throw new InvalidArgumentException('Neplatná adresa pro přesměrování.');
        }
        return new self('', 303, ['Location' => $url]);
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            header_remove('X-Powered-By');
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . str_replace(["\r", "\n"], '', $value));
            }
        }
        echo $this->body;
    }
}
