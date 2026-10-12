<?php

declare(strict_types=1);

/*
 * Vzor konfigurace. Zkopírujte jako config/config.php (lokálně), nebo na WEDOS
 * jako ../buchlovska-config.php MIMO veřejnou složku www.
 * Skutečný config.php NIKDY nedávejte do gitu ani nikomu neposílejte.
 */
return [
    'app' => [
        'url' => 'http://localhost:8080',      // produkce: https://<finální doména>
        'env' => 'development',                // production | development | test
        'debug' => true,                       // v produkci se ignoruje
        'timezone' => 'Europe/Prague',
    ],
    'session' => [
        'idle_minutes' => 120,
    ],
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'buchlovska_spiz',
        'user' => 'buchlovska',
        'pass' => '',
    ],
    'logto' => [
        // Aplikace typu "Traditional web" v Logto Console
        'endpoint' => 'https://<tenant-id>.logto.app',
        'app_id' => '',
        'app_secret' => '',
        // Aplikace typu "Machine-to-machine" s přístupem k Management API
        'm2m_app_id' => '',
        'm2m_app_secret' => '',
        'management_resource' => 'https://<tenant-id>.logto.app/api',
    ],
];
