<?php
/*
Plugin Name: WP Deploy Flow
Plugin URI: http://pacificsky.co
Description: Deploy to and from remote environments
Version: 0.1
Author: Tyler Shuster, Arnaud Sellenet
Author URI: https://tyler.shuster.house
License: GPL2
*/
include 'vendor/autoload.php';
$loader = new \Composer\Autoload\ClassLoader();
$loader->addPsr4('phpseclib\\', __DIR__ . '/../vendor/phpseclib');
$loader->register();

include 'lib/api.php';
