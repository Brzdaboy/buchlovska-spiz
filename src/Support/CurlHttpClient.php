<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class CurlHttpClient implements HttpClient
{
    public function __construct(private int $timeoutSeconds = 10)
    {
    }

    public function get(string $url, array $headers = []): array
    {
        return $this->request('GET', $url, null, $headers);
    }

    public function postForm(string $url, array $form, array $headers = []): array
    {
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        return $this->request('POST', $url, http_build_query($form, '', '&', PHP_QUERY_RFC1738), $headers);
    }

    public function sendJson(string $method, string $url, array $json, array $headers = []): array
    {
        $headers['Content-Type'] = 'application/json';
        return $this->request($method, $url, (string) json_encode($json, JSON_THROW_ON_ERROR), $headers);
    }

    /**
     * @param array<string, string> $headers
     * @return array{status: int, body: string}
     */
    private function request(string $method, string $url, ?string $body, array $headers): array
    {
        if (!str_starts_with($url, 'https://') && !str_starts_with($url, 'http://localhost')) {
            throw new RuntimeException('Volání mimo HTTPS není povoleno.');
        }
        $handle = curl_init($url);
        $lines = ['Accept: application/json'];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }
        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
        ]);
        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }
        $response = curl_exec($handle);
        if ($response === false) {
            $error = curl_error($handle);
            throw new RuntimeException('Spojení s přihlašovací službou selhalo: ' . $error);
        }
        // curl_close() není potřeba (od PHP 8 se uvolní samo, v 8.5 je zastaralé).
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        return ['status' => $status, 'body' => (string) $response];
    }
}
