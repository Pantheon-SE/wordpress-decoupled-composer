<?php

echo "Enabling WP plugins and themes...\n";
passthru("wp plugin activate pantheon-advanced-page-cache wp-native-php-sessions pantheon-se-plugin wp-graphql");
passthru("wp theme install oceanwp --activate");

echo "Setting Permalink structure... \n";
passthru("wp rewrite structure /%year%/%monthnum%/%day%/%postname%/");
