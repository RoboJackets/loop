php artisan config:cache --no-interaction --verbose
php artisan view:cache --no-interaction --verbose
php artisan event:cache --no-interaction --verbose
php artisan route:cache --no-interaction --verbose
exec php-fpm8.1 --force-stderr --nodaemonize --fpm-config /etc/php/8.1/fpm/php-fpm.conf
