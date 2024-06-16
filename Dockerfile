# syntax = docker/dockerfile:1.8

FROM python:3.12-bookworm as docs-source

COPY --link docs/ /docs/

WORKDIR /docs/

SHELL ["/bin/bash", "-c"]

RUN set -euxo pipefail && \
    curl -sSL https://install.python-poetry.org | python3 - && \
    /root/.local/bin/poetry install --no-interaction && \
    /root/.local/bin/poetry run sphinx-build -M dirhtml "." "_build"

FROM node:21.7.3 as docs-minification

COPY --link --from=docs-source /docs/_build/dirhtml/ /docs/

RUN set -eux && \
    npm install -g npm@latest && \
    npx html-minifier --input-dir /docs/ --output-dir /docs/ --file-ext html --collapse-whitespace --collapse-inline-tag-whitespace --minify-css --minify-js --minify-urls ROOT_PATH_RELATIVE --remove-comments --remove-empty-attributes --conservative-collapse && \
    find /docs/ -type f -size +0 | while read file; do \
        filename=$(basename -- "$file"); \
        extension="${filename##*.}"; \
        if [ "$extension" = "js" ]; then \
            npx terser "$file" --compress --output "$file"; \
        fi; \
        if [ "$extension" = "css" ]; then \
            npx clean-css-cli "$file" -O2 --output "$file"; \
        fi; \
        if [ "$extension" = "map" ]; then \
            rm -f "$file"; \
        fi; \
    done;

FROM scratch as backend-source

COPY --link app/ /app/app/
COPY --link bootstrap/ /app/bootstrap/
COPY --link config/ /app/config/
COPY --link database/ /app/database/
COPY --link lang/ /app/lang/
COPY --link public/ /app/public/
COPY --link resources/ /app/resources/
COPY --link routes/ /app/routes/
COPY --link storage/ /app/storage/
COPY --link artisan composer.json composer.lock /app/
COPY --link --from=docs-minification /docs/ /app/public/docs/

FROM ubuntu:noble as backend-uncompressed

LABEL maintainer="developers@robojackets.org"

ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_NO_INTERACTION=1 \
    HOME=/tmp

RUN set -eux && \
    apt-get update && \
    apt-get upgrade -qq --assume-yes && \
    apt-get install -qq --assume-yes \
        php8.3-fpm php8.3-mysql php8.3-xml php8.3-mbstring php8.3-curl php8.3-sqlite php8.3-intl php8.3-uuid \
        unzip libfcgi-bin default-mysql-client zopfli php8.3-redis php8.3-ldap poppler-utils file && \
    apt-get autoremove -qq --assume-yes && \
    mkdir /app && \
    chown www-data:www-data /app && \
    sed -i '/pid/c\\' /etc/php/8.3/fpm/php-fpm.conf && \
    sed -i '/systemd_interval/c\systemd_interval = 0' /etc/php/8.3/fpm/php-fpm.conf && \
    sed -i '/error_log/c\error_log = /local/error.log' /etc/php/8.3/fpm/php-fpm.conf && \
    sed -i '/upload_max_filesize/c\upload_max_filesize = 40M' /etc/php/8.3/fpm/php.ini && \
    sed -i '/post_max_size/c\post_max_size = 40M' /etc/php/8.3/fpm/php.ini && \
    sed -i '/max_file_uploads/c\max_file_uploads = 1' /etc/php/8.3/fpm/php.ini && \
    sed -i '/expose_php/c\expose_php = Off' /etc/php/8.3/fpm/php.ini && \
    sed -i '/expose_php/c\expose_php = Off' /etc/php/8.3/cli/php.ini && \
    sed -i '/allow_url_fopen/c\allow_url_fopen = Off' /etc/php/8.3/fpm/php.ini && \
    sed -i '/allow_url_fopen/c\allow_url_fopen = Off' /etc/php/8.3/cli/php.ini && \
    sed -i '/allow_url_include/c\allow_url_include = Off' /etc/php/8.3/fpm/php.ini && \
    sed -i '/allow_url_include/c\allow_url_include = Off' /etc/php/8.3/cli/php.ini

COPY --link --from=composer /usr/bin/composer /usr/bin/composer

COPY --link --from=backend-source --chown=33:33 /app/ /app/

WORKDIR /app/

USER www-data

RUN --mount=type=secret,id=composer_auth,dst=/app/auth.json,uid=33,gid=33,required=true \
    set -eux && \
    composer check-platform-reqs --lock --no-dev && \
    composer install --no-interaction --no-progress --no-dev --optimize-autoloader --classmap-authoritative --no-cache && \
    mkdir --parents /app/resources/views/ && \
    php artisan nova:publish && \
    sed -i '/"\$1\\n\$2"/c\\' /app/vendor/mrclay/minify/lib/Minify/HTML.php && \
    chmod 664 /app/bootstrap/app.php /app/public/index.php && \
    chmod 775 /app/bootstrap/cache/

# This target is the default, but skipped during pull request builds and in our recommended local build invocation
# precompressed_assets var on the Nomad job must match whether this stage ran or not
FROM backend-uncompressed as backend-compressed

RUN set -eux && \
    cd /app/public/ && \
    find . -type f -size +0 | while read file; do \
        filename=$(basename -- "$file"); \
        extension="${filename##*.}"; \
        if [ "$extension" = "css" ] || [ "$extension" = "js" ] || [ "$extension" = "svg" ]; then \
          zopfli --gzip -v --i10 "$file"; \
          touch "$file".gz "$file"; \
        elif [ "$extension" = "png" ]; then \
          zopflipng -m -y --lossy_transparent --lossy_8bit --filters=01234mepb --iterations=5 "$file" "$file"; \
        fi; \
    done;
