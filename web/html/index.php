<?php

declare(strict_types=1);

spl_autoload_register(function (string $class) {
  include $class . '.php';
});

$cls = new Feed();
$cls->run();
