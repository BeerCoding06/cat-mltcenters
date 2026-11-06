<?php

/** Shopping days that get their own landing page at /sale/<slug>. Dates repeat every year. */
const CAMPAIGNS = [
    '11-11' => ['name' => '11.11', 'month' => 11, 'day' => 11],
    '12-12' => ['name' => '12.12', 'month' => 12, 'day' => 12],
];

/** How many days before a shopping day its page is promoted on the site (nav, home banner). */
const CAMPAIGN_LEAD_DAYS = 21;

/** Monday 00:00 of this week and Sunday 23:59:59, as "Y-m-d H:i:s". */
function deals_week_range(): array
{
    $start = strtotime('monday this week', strtotime(date('Y-m-d')));
    return [date('Y-m-d 00:00:00', $start), date('Y-m-d 23:59:59', strtotime('+6 days', $start))];
}

/**
 * Products whose price went down since this week started: today's price is lower than the highest price
 * recorded in the last 7 days. Only real drops from price_history count, not the shop's "was" price.
 */
function deals_price_drops(int $limit = 12): array
{
    $since = date('Y-m-d H:i:s', strtotime('-7 days'));
    return db_all(
        'SELECT * FROM (
            SELECT ' . product_columns() . ',
                   (SELECT MAX(price) FROM price_history h WHERE h.product_id = products.id AND h.recorded_at >= ?) AS week_high
            FROM products WHERE active = 1
         ) WHERE week_high > price + 0.009
         ORDER BY (1 - price / week_high) DESC, sold DESC LIMIT ?',
        [$since, $limit]
    );
}

/** The $perCategory biggest discounts in every category, best sellers first when discounts tie. */
function deals_by_category(int $perCategory = 3): array
{
    $rows = db_all(
        'SELECT * FROM (
            SELECT ' . product_columns() . ',
                   ROW_NUMBER() OVER (PARTITION BY category ORDER BY ' . discount_sql() . ' DESC, sold DESC) AS pick
            FROM products WHERE active = 1 AND category <> \'\'
         ) WHERE pick <= ? ORDER BY category, pick',
        [$perCategory]
    );
    $out = [];
    foreach ($rows as $row) {
        $out[$row['category']][] = $row;
    }
    return $out;
}

/** Campaign data for this year's (or, once it is long over, next year's) shopping day — or null. */
function campaign_get(string $slug)
{
    if (!isset(CAMPAIGNS[$slug])) {
        return null;
    }
    $c = CAMPAIGNS[$slug] + ['slug' => $slug];
    $today = strtotime(date('Y-m-d'));
    $year = (int) date('Y');
    $day = mktime(0, 0, 0, $c['month'], $c['day'], $year);
    if ($day < strtotime('-7 days', $today)) {
        $day = mktime(0, 0, 0, $c['month'], $c['day'], $year + 1);
    }
    $c['date'] = date('Y-m-d', $day);
    $c['year'] = (int) date('Y', $day);
    $c['days_left'] = (int) round(($day - $today) / 86400);
    $c['state'] = $c['days_left'] > 0 ? 'upcoming' : ($c['days_left'] === 0 ? 'live' : 'ended');
    return $c;
}

/** The next shopping day within CAMPAIGN_LEAD_DAYS (or today), to promote around the site — or null. */
function campaign_next()
{
    $best = null;
    foreach (array_keys(CAMPAIGNS) as $slug) {
        $c = campaign_get($slug);
        if ($c['days_left'] >= 0 && $c['days_left'] <= CAMPAIGN_LEAD_DAYS && (!$best || $c['days_left'] < $best['days_left'])) {
            $best = $c;
        }
    }
    return $best;
}

function campaign_link(array $c): string
{
    return url('sale/' . $c['slug']);
}

/** Lowest/highest price in 30 days for each product, keyed by product id (see price_summary). */
function price_summaries(array $products): array
{
    $out = [];
    foreach ($products as $p) {
        $out[(int) $p['id']] = price_summary($p);
    }
    return $out;
}
