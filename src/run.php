<?php

declare(strict_types=1);

use Keboola\TokenSniffer\Application;

try {
    $app = new Application();
    $app->run();
    exit(0);
} catch (Throwable $e) {
    echo $e->getMessage();
    exit(2);
}
