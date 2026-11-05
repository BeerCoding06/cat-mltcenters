CREATE TABLE IF NOT EXISTS products (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    platform       TEXT    NOT NULL CHECK (platform IN ('lazada', 'shopee')),
    name           TEXT    NOT NULL,
    slug           TEXT    NOT NULL UNIQUE,
    category       TEXT    NOT NULL DEFAULT '',
    group_key      TEXT    NOT NULL DEFAULT '',   -- same value = same item on different platforms
    product_url    TEXT    NOT NULL DEFAULT '',
    affiliate_url  TEXT    NOT NULL,
    image_url      TEXT    NOT NULL DEFAULT '',
    price          REAL    NOT NULL,
    original_price REAL    NOT NULL DEFAULT 0,
    rating         REAL    NOT NULL DEFAULT 0,
    sold           INTEGER NOT NULL DEFAULT 0,
    description    TEXT    NOT NULL DEFAULT '',
    active         INTEGER NOT NULL DEFAULT 1,
    top_rank       INTEGER,                       -- 1..N in today's best-seller list, NULL otherwise
    last_synced_at TEXT,                          -- set when the product comes from the Shopee API
    created_at     TEXT    NOT NULL,
    updated_at     TEXT    NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_products_category ON products (category);
CREATE INDEX IF NOT EXISTS idx_products_group ON products (group_key);

CREATE TABLE IF NOT EXISTS price_history (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    product_id  INTEGER NOT NULL REFERENCES products (id) ON DELETE CASCADE,
    price       REAL    NOT NULL,
    recorded_at TEXT    NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_price_history_product ON price_history (product_id, recorded_at);

CREATE TABLE IF NOT EXISTS clicks (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    product_id   INTEGER NOT NULL REFERENCES products (id) ON DELETE CASCADE,
    clicked_at   TEXT    NOT NULL,
    visitor_hash TEXT    NOT NULL,                 -- salted daily hash, no raw IP stored
    referer      TEXT    NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS idx_clicks_product ON clicks (product_id, clicked_at);
CREATE INDEX IF NOT EXISTS idx_clicks_time ON clicks (clicked_at);

CREATE TABLE IF NOT EXISTS visits (
    day          TEXT    NOT NULL,                 -- Y-m-d
    visitor_hash TEXT    NOT NULL,                 -- salted daily hash, no raw IP stored
    views        INTEGER NOT NULL DEFAULT 1,
    PRIMARY KEY (day, visitor_hash)
);

CREATE TABLE IF NOT EXISTS articles (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    title        TEXT    NOT NULL,
    slug         TEXT    NOT NULL UNIQUE,
    category     TEXT    NOT NULL DEFAULT '',    -- matches a product category to link them together
    excerpt      TEXT    NOT NULL DEFAULT '',    -- meta description / card summary
    cover_image  TEXT    NOT NULL DEFAULT '',
    body         TEXT    NOT NULL DEFAULT '',    -- simple Markdown + product shortcodes
    status       TEXT    NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'published')),
    published_at TEXT,
    created_at   TEXT    NOT NULL,
    updated_at   TEXT    NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_articles_status ON articles (status, published_at);

CREATE TABLE IF NOT EXISTS breeds (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    qid         TEXT    NOT NULL UNIQUE,          -- Wikidata item, e.g. Q42732
    slug        TEXT    NOT NULL UNIQUE,
    name_th     TEXT    NOT NULL,
    name_en     TEXT    NOT NULL DEFAULT '',
    origin      TEXT    NOT NULL DEFAULT '',
    wiki_title  TEXT    NOT NULL,                 -- Thai Wikipedia article the text comes from (CC BY-SA)
    wiki_url    TEXT    NOT NULL,
    intro       TEXT    NOT NULL DEFAULT '',
    sections    TEXT    NOT NULL DEFAULT '[]',    -- JSON [[heading, text], ...]
    traits      TEXT,                             -- JSON from TheCatAPI (optional, needs an API key)
    fetched_at  TEXT    NOT NULL
);

CREATE TABLE IF NOT EXISTS news_items (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    url          TEXT    NOT NULL UNIQUE,
    title        TEXT    NOT NULL,
    source       TEXT    NOT NULL,
    published_at TEXT    NOT NULL,
    fetched_at   TEXT    NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_news_published ON news_items (published_at);
