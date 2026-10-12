<?php

declare(strict_types=1);

namespace App\View;

use RuntimeException;

/**
 * Šablony v čistém PHP. Každý výstup proměnné se escapuje funkcí e().
 * Šablona dostane proměnné z $data; layout dostane navíc $content.
 */
final class View
{
    /** @param array<string, mixed> $shared proměnné dostupné ve všech šablonách */
    public function __construct(private string $templateDir, private array $shared = [])
    {
    }

    public function share(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = [], ?string $layout = 'layout/base'): string
    {
        $content = $this->renderFile($template, $data);
        if ($layout === null) {
            return $content;
        }
        return $this->renderFile($layout, $data + ['content' => $content]);
    }

    /** @param array<string, mixed> $data */
    private function renderFile(string $template, array $data): string
    {
        if (!preg_match('#^[a-z0-9_/-]+$#', $template)) {
            throw new RuntimeException('Neplatný název šablony.');
        }
        $file = $this->templateDir . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("Šablona {$template} neexistuje.");
        }
        $vars = $data + $this->shared;
        ob_start();
        try {
            (static function (string $__file, array $__vars): void {
                extract($__vars, EXTR_SKIP);
                require $__file;
            })($file, $vars);
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
