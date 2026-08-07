# A community image for Pluck 5. No vendor namespace, no build service: this
# builds anywhere `docker build .` runs.
#
# Pluck's normal habitat is shared hosting, so the image deliberately mirrors
# that — Apache with mod_php, one directory, no orchestration — rather than
# splitting into php-fpm and a web server. The one thing it does differently from
# a hand install is keep `data/` on a volume.

FROM php:8.3-apache

# pdo_sqlite is what makes the database storage option available at install time;
# without it the installer offers files only. intl gives better addresses for
# non-latin titles. Everything else Pluck needs is in the base image.
RUN set -eux; \
	apt-get update; \
	apt-get install -y --no-install-recommends libicu-dev libsqlite3-dev; \
	docker-php-ext-configure intl; \
	docker-php-ext-install -j"$(nproc)" pdo_sqlite intl; \
	apt-get purge -y --auto-remove libicu-dev libsqlite3-dev; \
	rm -rf /var/lib/apt/lists/*

# .htaccess files under data/ and media/ do the security work, so they have to be
# read. AllowOverride is off by default in this image.
RUN set -eux; \
	a2enmod rewrite headers; \
	printf '%s\n' \
		'<Directory /var/www/html>' \
		'    AllowOverride All' \
		'    Options -Indexes -ExecCGI +FollowSymLinks' \
		'</Directory>' \
		'ServerTokens Prod' \
		'ServerSignature Off' \
		> /etc/apache2/conf-available/pluck.conf; \
	a2enconf pluck

RUN { \
		echo 'expose_php = Off'; \
		echo 'display_errors = Off'; \
		echo 'log_errors = On'; \
		echo 'error_log = /dev/stderr'; \
		echo 'upload_max_filesize = 16M'; \
		echo 'post_max_size = 20M'; \
		echo 'session.cookie_httponly = 1'; \
		echo 'session.cookie_samesite = Lax'; \
		echo 'session.use_strict_mode = 1'; \
	} > /usr/local/etc/php/conf.d/pluck.ini

WORKDIR /var/www/html
COPY --chown=www-data:www-data . /var/www/html

# The two directories Pluck writes to. Everything else stays read-only as far as
# the web server is concerned.
RUN set -eux; \
	rm -f Dockerfile .dockerignore docker-compose.yml; \
	mkdir -p data media; \
	chown -R www-data:www-data data media; \
	find . -type d -exec chmod 755 {} +; \
	find . -type f -exec chmod 644 {} +

VOLUME ["/var/www/html/data", "/var/www/html/media"]

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
	CMD php -r 'exit(@file_get_contents("http://127.0.0.1/index.php") === false ? 1 : 0);'
