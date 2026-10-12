<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/autoload.php';

// Pomocné třídy testů: jmenný prostor Tests\ odpovídá složce tests/.
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Tests\\')) {
        $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, 6)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

date_default_timezone_set('Europe/Prague');
