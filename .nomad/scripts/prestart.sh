php artisan config:cache --no-interaction --verbose
php artisan view:cache --no-interaction --verbose
php artisan event:cache --no-interaction --verbose
php artisan route:cache --no-interaction --verbose
php artisan cache:clear --no-interaction --verbose
php artisan migrate --no-interaction --force --verbose
php artisan config:cache --no-interaction --verbose

mkdir --parents /assets/${NOMAD_JOB_NAME}/
cp --recursive --verbose public/* /assets/${NOMAD_JOB_NAME}/

mkdir --parents /assets/${NOMAD_JOB_NAME}/storage/thumbnail/
ln --symbolic /assets/${NOMAD_JOB_NAME}/storage/thumbnail/ /app/storage/app/public/ || true
