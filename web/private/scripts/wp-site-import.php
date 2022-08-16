<?php

$categories = [
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
];

$category = array_rand($categories);
$page = rand(1,5);

// Import data into WordPress
echo "Installing random content...\n";
passthru("wp pantheon generate --query='$category' --page='$page'");