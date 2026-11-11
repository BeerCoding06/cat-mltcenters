<?php

/** utm_source on links shared to Facebook groups; visit_source() reports it as "กลุ่ม Facebook". */
const SHARE_UTM = 'fbgroup';

/** Don't suggest sharing the same article to the same group again within this many days. */
const SHARE_GAP_DAYS = 14;

function share_link(array $article): string
{
    return abs_url('a/' . rawurlencode($article['slug'])) . '?utm_source=' . SHARE_UTM;
}

/**
 * Ready-to-paste posts for one article: [label => text]. Written as the site owner talking to other cat
 * owners (no pretending to be a stranger who "found" the link), with the link at the end.
 */
function share_captions(array $article): array
{
    $title = trim($article['title']);
    $excerpt = trim($article['excerpt']);
    $link = share_link($article);
    return [
        'ชวนคุย' => "เลี้ยงแมวแล้วเคยเจอเรื่องนี้ไหม? $title\n\n"
            . ($excerpt !== '' ? "$excerpt\n\n" : '')
            . "เราสรุปไว้ให้อ่านแบบเข้าใจง่ายที่นี่ $link\n\nบ้านไหนมีวิธีอื่นที่ได้ผล แชร์กันในคอมเมนต์ได้เลย",
        'สรุปสั้น' => "$title\n\n" . ($excerpt !== '' ? "$excerpt\n\n" : '') . "อ่านต่อ: $link",
        'สั้นมาก' => "$title 👉 $link",
    ];
}

function share_groups(): array
{
    return db_all('SELECT g.*, MAX(l.shared_at) AS last_shared, COUNT(l.id) AS shares
                     FROM share_groups g LEFT JOIN share_log l ON l.group_id = g.id
                    GROUP BY g.id ORDER BY g.name');
}

/**
 * Live articles in the order worth sharing next: never shared first, then longest since the last share;
 * newer articles first among equals. Each row carries last_shared and a group_id => last share time map.
 */
function share_queue(int $limit = 30): array
{
    $rows = db_all('SELECT a.id, a.title, a.slug, a.excerpt, a.category, a.published_at, MAX(l.shared_at) AS last_shared
                      FROM articles a LEFT JOIN share_log l ON l.article_id = a.id
                     WHERE ' . ARTICLE_LIVE_SQL . '
                     GROUP BY a.id
                     ORDER BY last_shared IS NOT NULL, last_shared, a.published_at DESC
                     LIMIT ' . (int) $limit, [now()]);
    $ids = array_map('intval', array_column($rows, 'id'));
    $byArticle = [];
    if ($ids) {
        foreach (db_all('SELECT article_id, group_id, MAX(shared_at) AS at FROM share_log
                          WHERE article_id IN (' . implode(',', $ids) . ') GROUP BY article_id, group_id') as $r) {
            $byArticle[(int) $r['article_id']][(int) $r['group_id']] = $r['at'];
        }
    }
    foreach ($rows as &$row) {
        $row['groups'] = $byArticle[(int) $row['id']] ?? [];
    }
    return $rows;
}

function share_record(int $articleId, int $groupId): void
{
    db_run('INSERT INTO share_log (article_id, group_id, shared_at) VALUES (?, ?, ?)', [$articleId, $groupId, now()]);
}
