<?php
use Illuminate\Database\Capsule\Manager as Capsule;

date_default_timezone_set("Asia/Bangkok");

$capsule = new Capsule;

$capsule->addConnection(array(
    'driver'    => 'mysql',
    'host'      => 'localhost',
    'database'  => 'grader',
    'username'  => 'root',
    'password'  => '',
    'charset'   => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix'    => '',
));

$capsule->setAsGlobal();
$capsule->bootEloquent();
