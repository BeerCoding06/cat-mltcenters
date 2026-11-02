<?php
/**
 * Cat news: headlines + links only (no article text), from the RSS feeds in config 'news_feeds'.
 * Only items whose title mentions a keyword (default: แมว) and none of 'news_exclude' are kept;
 * readers click through to the source.
 * Run bin/fetch-news.php a few times a day or press the button in Admin.
 */

function news_latest(int $limit, int $offset = 0): array
{
    return db_all('SELECT * FROM news_items ORDER BY published_at DESC LIMIT ? OFFSET ?', [$limit, $offset]);
}

function fetch_news(): array
{
    $feeds = (array) config('news_feeds', []);
    $keywords = (array) config('news_keywords', ['แมว']);
    $exclude = (array) config('news_exclude', []);
    $keepDays = (int) config('news_keep_days', 365);
    $result = ['feeds' => count($feeds), 'added' => 0, 'seen' => 0, 'errors' => []];
    $now = now();

    foreach ($feeds as $feed) {
        try {
            $xml = news_parse((string) http_get((string) $feed['url'], ['Accept: application/rss+xml, application/xml, text/xml']));
        } catch (Throwable $ex) {
            $result['errors'][] = $feed['name'] . ': ' . $ex->getMessage();
            continue;
        }
        foreach ($xml as $item) {
            $result['seen']++;
            $title = $item['title'];
            $match = false;
            foreach ($keywords as $k) {
                if (mb_stripos($title, $k) !== false) {
                    $match = true;
                    break;
                }
            }
            foreach ($exclude as $k) {
                if (mb_stripos($title, $k) !== false) {
                    $match = false;
                }
            }
            if (!$match || !is_http_url($item['link']) || $item['time'] < time() - $keepDays * 86400) {
                continue;
            }
            $added = db_run('INSERT OR IGNORE INTO news_items (url, title, source, published_at, fetched_at) VALUES (?, ?, ?, ?, ?)',
                [$item['link'], mb_substr($title, 0, 300), (string) $feed['name'], date('Y-m-d H:i:s', $item['time']), $now]);
            $result['added'] += $added;
        }
    }
    db_run('DELETE FROM news_items WHERE published_at < ?', [date('Y-m-d H:i:s', time() - $keepDays * 86400)]);
    foreach ($exclude as $k) {
        db_run("DELETE FROM news_items WHERE title LIKE ? ESCAPE '\\'", ['%' . addcslashes($k, '%_\\') . '%']);
    }
    return $result;
}

/** RSS 2.0 / Atom -> [['title', 'link', 'time'], ...]. External entities and network access are disabled. */
function news_parse(string $body): array
{
    $prev = libxml_use_internal_errors(true);
    $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
    libxml_use_internal_errors($prev);
    if ($xml === false) {
        throw new RuntimeException('Not a valid feed');
    }
    $out = [];
    $items = isset($xml->channel->item) ? $xml->channel->item : (isset($xml->entry) ? $xml->entry : []);
    foreach ($items as $it) {
        $title = trim(html_entity_decode(strip_tags((string) $it->title), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $link = trim((string) $it->link);
        if ($link === '' && isset($it->link['href'])) {
            $link = (string) $it->link['href']; // Atom
        }
        $date = (string) ($it->pubDate ?: $it->updated ?: $it->published);
        $time = $date !== '' ? strtotime($date) : false;
        if ($title !== '' && $link !== '') {
            $out[] = ['title' => preg_replace('/\s+/u', ' ', $title), 'link' => $link, 'time' => $time ?: time()];
        }
    }
    return $out;
}
