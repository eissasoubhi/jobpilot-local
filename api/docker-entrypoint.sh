#!/bin/sh
set -eu

mkdir -p var/cache var/log var/private/cvs
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
php bin/console app:bootstrap --no-interaction
exec php -S 0.0.0.0:8080 -t public public/index.php
