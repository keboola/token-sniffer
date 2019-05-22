<?php

declare(strict_types=1);

use Keboola\TokenSniffer\Application;

$autoloadFromProject = 'vendor/autoload.php';
$autoloadDev = __DIR__ . '/../vendor/autoload.php';
switch (true) {
    case file_exists($autoloadFromProject):
        require_once $autoloadFromProject;
        break;
    case file_exists($autoloadDev):
        require_once  $autoloadDev;
        break;
    default:
        throw new Exception('Missing autoload file');
}

try {
    $app = new Application();
    $app->run();
    exit(0);
} catch (Throwable $e) {
    echo $e->getMessage();
    exit(2);
}
