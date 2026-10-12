<?php

declare(strict_types=1);

namespace App\Support;

/** Jednoduchý souborový logger. Do logu nepatří hesla, tokeny ani osobní údaje. */
final class Logger
{
    public function __construct(private string $directory)
    {
    }

    /** @param array<string, scalar|null> $context */
    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    /** @param array<string, scalar|null> $context */
    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    /** @param array<string, scalar|null> $context */
    private function write(string $level, string $message, array $context): void
    {
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0750, true);
        }
        $line = sprintf(
            "[%s] %s %s %s\n",
            date('c'),
            $level,
            str_replace(["\r", "\n"], ' ', $message),
            $context === [] ? '' : json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        @file_put_contents($this->directory . '/app-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }
}
