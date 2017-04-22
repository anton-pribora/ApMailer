<?php

require __DIR__ . '/../src/lib.php';

spl_autoload_register(function ($className) {
    $filePath = dirname(__DIR__) . '/' . strtr($className, '\\', '/') . '.php';

    if (is_readable($filePath)) {
        include $filePath;
    }
});
