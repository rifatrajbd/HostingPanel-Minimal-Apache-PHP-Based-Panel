<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$factory = require dirname(__DIR__) . '/src/bootstrap.php';
$factory()->run();
