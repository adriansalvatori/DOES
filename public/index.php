<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Ensure required framework storage directories exist (prevents "Please provide a valid cache path" error)
foreach ([
    __DIR__.'/../storage/framework/views',
    __DIR__.'/../storage/framework/sessions',
    __DIR__.'/../storage/framework/cache/data',
    __DIR__.'/../storage/logs',
] as $storageDir) {
    if (! is_dir($storageDir)) {
        @mkdir($storageDir, 0775, true);
    }
}

// Ensure public/storage symlink exists
$storageLink = __DIR__.'/storage';
if (! file_exists($storageLink) && ! is_link($storageLink)) {
    @symlink('../storage/app/public', $storageLink);
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
