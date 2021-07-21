<?php
namespace Bootstrap;


$_ENV['root_folder'] = realpath(dirname(__FILE__).'/../../').'/';
require $_ENV['root_folder'] . '/vendor/autoload.php';

\Grithin\GlobalFunctions::init();