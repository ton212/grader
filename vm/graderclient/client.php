<?php
require_once __DIR__.'/vendor/autoload.php';

use Symfony\Component\Console\Application;

$console = new Application('Grader Client', '0.1');

$grader = new Grader\Client\Client();
$grader->setConsole($console);
require_once "config.php";

$console->run();