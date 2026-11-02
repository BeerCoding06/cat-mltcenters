<?php
// Copy to config.php — `php bin/setup.php` does this and fills in the secrets.
return [
    'site_name'    => 'คัดมาเหมียว',
    'site_tagline' => 'รวมดีลอาหารแมว ทรายแมว และของใช้แมว จาก Lazada และ Shopee พร้อมกราฟราคาย้อนหลัง',
    'home_heading' => 'ดีลของใช้แมววันนี้',
    'search_placeholder' => 'ค้นหา เช่น อาหารแมว, ทรายเต้าหู้, ขนมแมว',
    'logo_mark'    => '🐾',
    // Public URL without a trailing slash, e.g. https://example.com.
    // Leave empty to detect it from the request (fine for local development).
    'site_url'     => '',
    'timezone'     => 'Asia/Bangkok',
    'db_path'      => __DIR__ . '/data/app.sqlite',
    'per_page'     => 24,

    // Affiliate links must point to one of these hosts (subdomains included).
    'allowed_affiliate_hosts' => ['lazada.co.th', 'shopee.co.th', 'shope.ee'],

    // Daily best sellers (bin/fetch-top-products.php). Keys = category shown on the site, values = search keyword.
    'daily_top' => [
        'count'           => 15,
        'hide_after_days' => 14,   // hide auto-fetched products that have not been seen for this long
        'keywords'        => [
            'อาหารแมวเม็ด'          => 'อาหารแมวเม็ด',
            'อาหารแมวเปียก'         => 'อาหารแมวเปียก',
            'ทรายแมว'               => 'ทรายแมว',
            'ขนมแมว'                => 'ขนมแมว',
            'คอนโดแมว & ของเล่น'    => 'ของเล่นแมว',
            'อุปกรณ์แมว'            => 'อุปกรณ์แมว',
        ],
    ],

    // Shopee Affiliate Open API — from your Shopee Affiliate account once approved. Keep the secret private.
    'shopee_api' => [
        'app_id'   => '',
        'secret'   => '',
        'endpoint' => 'https://open-api.affiliate.shopee.co.th/graphql',
    ],

    // Cat news (bin/fetch-news.php): RSS feeds; only headlines mentioning a keyword are kept, shown as links to the source.
    'news_feeds' => [
        ['name' => 'มติชน',           'url' => 'https://www.matichon.co.th/tag/%E0%B9%81%E0%B8%A1%E0%B8%A7/feed'],
        ['name' => 'ประชาชาติธุรกิจ',  'url' => 'https://www.prachachat.net/tag/%E0%B9%81%E0%B8%A1%E0%B8%A7/feed'],
        ['name' => 'MarketingOops',   'url' => 'https://www.marketingoops.com/tag/%E0%B9%81%E0%B8%A1%E0%B8%A7/feed/'],
    ],
    'news_keywords'  => ['แมว'],
    // Headlines containing any of these are skipped (keeps the news section in tune with a cheerful shopping site).
    'news_exclude'   => ['เชือด', 'ฆ่า', 'ทารุณ', 'ลักแมว', 'วางยา', 'ตาย', 'เลือด'],
    'news_keep_days' => 365,

    // Optional: TheCatAPI key (free sign-up at thecatapi.com) adds breed traits such as energy and shedding.
    'thecatapi_key' => '',

    // Set by bin/setup.php — do not edit by hand.
    'admin_password_hash' => '',
    'click_salt'          => '',
];
