# Running Pluck 5 behind nginx

Pluck ships an `.htaccess` because most of its users are on Apache-based shared
hosting. nginx ignores that file completely, so everything it does has to be
said again in the server block — including the parts that are security rather
than convenience.

## Minimum

```nginx
server {
	root /var/www/pluck;
	index index.php;

	# Pretty URLs. Anything that exists is served directly; the rest goes to
	# index.php, which resolves it as a page or module address.
	location / {
		try_files $uri $uri/ /index.php$is_args$args;
	}

	# data/ holds the content store, the config and the session files. Nothing
	# in it is ever fetched over HTTP. On Apache this is a .htaccess in the
	# folder; here it has to be stated.
	location ^~ /data/ {
		deny all;
		return 404;
	}

	# media/ is served, but never executed. An upload that got past the policy
	# is a file on disk; this is what keeps it from being a program.
	location ^~ /media/ {
		location ~ \.(php|phar|phtml)$ {
			deny all;
			return 404;
		}
	}

	location ~ \.php$ {
		include fastcgi_params;
		fastcgi_pass unix:/run/php/php8.3-fpm.sock;
		fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;

		# Without this, a request for /media/photo.jpg/x.php is handed to PHP
		# with photo.jpg as the script. It is the single most important line in
		# this file.
		fastcgi_split_path_info ^(.+\.php)(/.+)$;
		fastcgi_param PATH_INFO $fastcgi_path_info;
		try_files $fastcgi_script_name =404;
	}

	add_header X-Content-Type-Options "nosniff" always;
	add_header Referrer-Policy "strict-origin-when-cross-origin" always;
}
```

## Notes

**`try_files $fastcgi_script_name =404;`** is not optional. `cgi.fix_pathinfo`
has defaulted to off since PHP 7, but the directive costs nothing and does not
depend on a php.ini you may not control.

**Pretty URLs off** works with no rewriting at all: `location / { try_files $uri
/index.php; }` is enough, since addresses arrive as `?page=`. Turn the setting on
only after confirming `/some-page` reaches index.php.

**Sub-directory installs** need `root` pointed at the parent and the location
blocks prefixed. Pluck works out its own base path from `SCRIPT_NAME`, so no
configuration inside Pluck changes.
