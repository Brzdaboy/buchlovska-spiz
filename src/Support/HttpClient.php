<?php

declare(strict_types=1);

namespace App\Support;

/** Minimální HTTP klient pro volání Logto. V testech se nahrazuje falešným. */
interface HttpClient
{
    /**
     * @param array<string, string> $headers
     * @return array{status: int, body: string}
     */
    public function get(string $url, array $headers = []): array;

    /**
     * @param array<string, string> $form  odešle se jako application/x-www-form-urlencoded
     * @param array<string, string> $headers
     * @return array{status: int, body: string}
     */
    public function postForm(string $url, array $form, array $headers = []): array;

    /**
     * @param array<string, mixed> $json
     * @param array<string, string> $headers
     * @return array{status: int, body: string}
     */
    public function sendJson(string $method, string $url, array $json, array $headers = []): array;
}
