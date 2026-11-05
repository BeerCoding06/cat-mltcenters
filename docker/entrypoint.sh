#!/bin/sh
set -e
cd /var/www/html
# The data volume may be new and owned by root.
chown www-data:www-data data
su -s /bin/sh www-data -c 'php docker/init.php'
exec "$@"
