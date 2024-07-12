<?php

declare(strict_types=1);

spl_autoload_register(function (string $class) {
  include $class . '.php';
});

$cls = new Feed();
$cls->run();

//try {
//  $cls = new Feed();
//  $cls->run();
//} catch (Exception $e) {
//  echo $e->getMessage();
//}
//
