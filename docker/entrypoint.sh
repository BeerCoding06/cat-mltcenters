#!/bin/sh
set -e
cd /var/www/html
# The data volume may be new and owned by root.
chown www-data:www-data data
su -s /bin/sh www-data -c 'php docker/init.php'
# New files in content/articles go live by themselves at their publish_at (existing articles are left alone).
su -s /bin/sh www-data -c 'php bin/import-articles.php --publish' || echo "Article import had errors (see above)"
# Share articles that go live on the Facebook Page (does nothing until FB_PAGE_TOKEN is set),
# tell Bing & co. about them through IndexNow, email price-drop alerts (does nothing until RESEND_API_KEY is set),
# and pin articles to Pinterest (does nothing until the PINTEREST_* values are set).
(while true; do
  su -s /bin/sh www-data -c 'php bin/post-facebook.php' || true
  su -s /bin/sh www-data -c 'php bin/indexnow.php' || true
  su -s /bin/sh www-data -c 'php bin/send-price-alerts.php' || true
  su -s /bin/sh www-data -c 'php bin/post-pinterest.php' || true
  sleep 600
done) &
exec "$@"
