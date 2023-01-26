php artisan config:cache --no-interaction --verbose
php artisan view:cache --no-interaction --verbose
php artisan event:cache --no-interaction --verbose
php artisan route:cache --no-interaction --verbose
exec php-fpm8.2 --force-stderr --nodaemonize --fpm-config /etc/php/8.2/fpm/php-fpm.conf
