rm --force /var/opt/nomad/run/${NOMAD_JOB_NAME}-${NOMAD_ALLOC_ID}.sock
php artisan config:cache --no-interaction --verbose
php artisan view:cache --no-interaction --verbose
php artisan event:cache --no-interaction --verbose
php artisan route:cache --no-interaction --verbose
exec php-fpm8.5 --force-stderr --nodaemonize --fpm-config /etc/php/8.5/fpm/php-fpm.conf
