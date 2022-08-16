<?php

echo "Enabling WP plugins...\n";
passthru("wp plugin activate pantheon-advanced-page-cache wp-native-php-sessions pantheon-se-plugin");

echo "Setting Permalink structure... \n";
passthru("wp rewrite structure /%year%/%monthnum%/%day%/%postname%/");
