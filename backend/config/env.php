<?php
$envFile = __DIR__ . '/../../.env';
if (!file_exists($envFile)) {
    return;
}

$lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || strpos($line, '#') === 0) {
        continue;
    }

    $parts = explode('=', $line, 2);
    if (count($parts) !== 2) {
        continue;
    }

    $name = trim($parts[0]);
    $value = trim($parts[1]);
    if ($name === '') {
        continue;
    }

    putenv("$name=$value");
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}
?>