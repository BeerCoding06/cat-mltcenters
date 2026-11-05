#!/bin/sh
set -e
cd /var/www/html
# The data volume may be new and owned by root.
chown www-data:www-data data
su -s /bin/sh www-data -c 'php docker/init.php'
# New files in content/articles go live by themselves at their publish_at (existing articles are left alone).
su -s /bin/sh www-data -c 'php bin/import-articles.php --publish' || echo "Article import had errors (see above)"
exec "$@"
