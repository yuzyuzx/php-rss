<?php

declare(strict_types=1);

spl_autoload_register(function (string $class) {
  include '../src/' . $class . '.php';
});

$cls = new ZennFeed();
$cls->run();
