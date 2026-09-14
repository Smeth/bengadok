<?php

require __DIR__.'/../vendor/autoload.php';

$file = $argv[1] ?? '';
$s = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
$r = $s->getActiveSheet()->toArray(null, true, true, false);
for ($i = 0; $i < min(5, count($r)); $i++) {
    echo implode(' | ', array_slice($r[$i], 0, 10)).PHP_EOL;
}
