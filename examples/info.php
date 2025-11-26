<?php

use Devhammed\Photoshop\Application;

require_once __DIR__ . '/../vendor/autoload.php';

$app = new Application('2025');

$app->bringToFront();

echo "Active Document:".$app->activeDocument.PHP_EOL;

echo "Name: " . $app->name . PHP_EOL;

echo "Locale: " . $app->locale . PHP_EOL;

echo "Build Version: " . $app->build . PHP_EOL;

echo "Scripting Version: " . $app->scriptingVersion . PHP_EOL;

echo "Scripting Build Date: " . $app->scriptingBuildDate . PHP_EOL;

echo "Free Memory: " . $app->freeMemory . PHP_EOL;
