<?php

const SCHEMA_VERSION = 15;

/** Changes to tables that already exist; new tables just go in schema.sql. Keyed by the version they bring the DB to. */
const MIGRATIONS = [
    3 => [
        'ALTER TABLE products ADD COLUMN top_rank INTEGER',
        'ALTER TABLE products ADD COLUMN last_synced_at TEXT',
    ],
    6 => [
        "ALTER TABLE breeds ADD COLUMN photo TEXT NOT NULL DEFAULT ''",
        'ALTER TABLE breeds ADD COLUMN photo_credit TEXT',
    ],
    7 => [
        'ALTER TABLE articles ADD COLUMN cover_credit TEXT',
    ],
    // 8: promos + promo_clicks are new tables (schema.sql)
    9 => [
        'ALTER TABLE articles ADD COLUMN fb_post_id TEXT',
        'ALTER TABLE articles ADD COLUMN fb_posted_at TEXT',
        'ALTER TABLE articles ADD COLUMN fb_error TEXT',
    ],
    10 => [
        "ALTER TABLE visits ADD COLUMN source TEXT NOT NULL DEFAULT ''",
        "ALTER TABLE visits ADD COLUMN landing TEXT NOT NULL DEFAULT ''",
    ],
    11 => [
        'ALTER TABLE articles ADD COLUMN indexnow_at TEXT',
    ],
    // Where on the page a click came from, and clicks left out of reports (no page to come from, or a burst).
    12 => [
        "ALTER TABLE clicks ADD COLUMN placement TEXT NOT NULL DEFAULT ''",
        'ALTER TABLE clicks ADD COLUMN suspect INTEGER NOT NULL DEFAULT 0',
        "ALTER TABLE promo_clicks ADD COLUMN placement TEXT NOT NULL DEFAULT ''",
        'ALTER TABLE promo_clicks ADD COLUMN suspect INTEGER NOT NULL DEFAULT 0',
        "UPDATE clicks SET suspect = 1 WHERE referer = ''",
        "UPDATE promo_clicks SET suspect = 1 WHERE referer = ''",
    ],
    // 13: share_groups + share_log are new tables (schema.sql)
    // 14: price_alerts is a new table (schema.sql)
    15 => [
        'ALTER TABLE articles ADD COLUMN pin_id TEXT',
        'ALTER TABLE articles ADD COLUMN pinned_at TEXT',
        'ALTER TABLE articles ADD COLUMN pin_error TEXT',
    ],
];

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $path = (string) config('db_path');
    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0775, true);
    }

    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');

    $version = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
    if ($version < SCHEMA_VERSION) {
        $pdo->exec((string) file_get_contents(APP_ROOT . '/inc/schema.sql'));
        if ($version > 0) {
            foreach (MIGRATIONS as $to => $statements) {
                if ($to > $version) {
                    foreach ($statements as $sql) {
                        $pdo->exec($sql);
                    }
                }
            }
        }
        $pdo->exec('PRAGMA user_version = ' . SCHEMA_VERSION);
    }

    return $pdo;
}

function db_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function db_one(string $sql, array $params = [])
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function db_value(string $sql, array $params = [])
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function db_run(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}
