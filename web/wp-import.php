<?php

require '../vendor/autoload.php';
require 'app/plugins/ocean-extra/ocean-extra.php';

$demos = OceanWP_Demos::get_demos_data();
$categories = OceanWP_Demos::get_demo_all_categories( $demos );

echo "<pre>";
print_r($categories);
echo "</pre>";
