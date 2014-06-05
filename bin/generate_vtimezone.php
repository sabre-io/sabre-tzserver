#!/usr/bin/env php
<?php

namespace Sabre\TzServer;

$inputFiles = [
    "africa",
    "antarctica",
    "asia",
    "australasia",
    "europe",
    "northamerica",
    "southamerica",
];

$path = __DIR__ . '/tzdata/';

require __DIR__ . '/../vendor/autoload.php';

$parser = new TzDataParser\Parser();

foreach($inputFiles as $inputFile) {
    $parser->parseFile($path . $inputFile);

}

$vtimezoneGenerator = new TzDataParser\VTimeZoneGenerator($parser);
$vtimezoneGenerator->generate('Europe/Amsterdam');
