<?php

$categories = [
    'health',
    'restaurant',
    'fitness',
    'lifestyle',
    'tech',
    'corporate',
    'fashion',
    'hacker',
    'news',
    'texas',
    'california',
    'michigan',
    'florida'
];

$category = array_rand($categories);
$page = rand(1,5);

// Import data into WordPress
echo "Installing random content...\n";
$cmd = "wp pantheon generate --query=$category --page=$page";
echo $cmd;
passthru($cmd);
