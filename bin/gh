#!/usr/bin/env php
<?php

namespace Luceos\GitHelpers;

error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Console\Application;

$console = new Application();
$console->add(new Commands\StatusHelper);
$console->run();