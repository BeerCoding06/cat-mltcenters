#!/bin/sh
set -e
cd /var/www/html
# The data volume may be new and owned by root.
chown www-data:www-data data
su -s /bin/sh www-data -c 'php docker/init.php'
# New files in content/articles go live by themselves at their publish_at (existing articles are left alone).
su -s /bin/sh www-data -c 'php bin/import-articles.php --publish' || echo "Article import had errors (see above)"
# Share articles that go live on the Facebook Page (does nothing until FB_PAGE_TOKEN is set),
# and tell Bing & co. about them through IndexNow.
(while true; do
  su -s /bin/sh www-data -c 'php bin/post-facebook.php' || true
  su -s /bin/sh www-data -c 'php bin/indexnow.php' || true
  sleep 600
done) &
exec "$@"
