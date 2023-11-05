mkdir --parents /assets/${NOMAD_JOB_NAME}/storage/thumbnail/
mkdir --parents /app/storage/app/public/thumbnails/
ln --symbolic /assets/${NOMAD_JOB_NAME}/storage/thumbnail/ /app/storage/app/public/thumbnails/
php artisan config:cache --no-interaction --verbose
php artisan view:cache --no-interaction --verbose
php artisan event:cache --no-interaction --verbose
php artisan route:cache --no-interaction --verbose
exec php artisan horizon --no-interaction --verbose
