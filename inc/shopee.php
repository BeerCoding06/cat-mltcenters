<?php
/**
 * Shopee Affiliate Open API (GraphQL) — product offers.
 * Credentials come from your Shopee Affiliate account (Open API / App ID + Secret).
 * Request signing: Authorization: SHA256 Credential={appId}, Timestamp={ts}, Signature=sha256(appId . ts . payload . secret)
 */

const SHOPEE_SORT_BY_SALES = 2;

const SHOPEE_OFFER_FIELDS = 'itemId productName priceMin priceMax priceDiscountRate sales ratingStar imageUrl productLink offerLink commissionRate shopName';

function shopee_configured(): bool
{
    $api = (array) config('shopee_api', []);
    return !empty($api['app_id']) && !empty($api['secret']);
}

/**
 * Best-selling offers for a keyword. Returns the list of product nodes.
 * $fixture: path to a saved JSON response, used instead of calling the API (for testing).
 */
function shopee_product_offers(string $keyword, int $limit, ?string $fixture = null): array
{
    $query = 'query { productOfferV2(keyword: ' . json_encode($keyword, JSON_UNESCAPED_UNICODE)
        . ', sortType: ' . SHOPEE_SORT_BY_SALES . ', page: 1, limit: ' . $limit . ') { nodes { ' . SHOPEE_OFFER_FIELDS . ' } } }';

    if ($fixture !== null) {
        $all = json_decode((string) file_get_contents($fixture), true);
        $body = $all[$keyword] ?? ['data' => ['productOfferV2' => ['nodes' => []]]];
    } else {
        $body = shopee_graphql($query);
    }

    if (!empty($body['errors'])) {
        throw new RuntimeException('Shopee API: ' . json_encode($body['errors'], JSON_UNESCAPED_UNICODE));
    }
    return (array) ($body['data']['productOfferV2']['nodes'] ?? []);
}

function shopee_graphql(string $query): array
{
    $api = (array) config('shopee_api', []);
    if (!shopee_configured()) {
        throw new RuntimeException('Shopee API is not configured — set shopee_api.app_id and shopee_api.secret in config.php');
    }
    $payload = json_encode(['query' => $query], JSON_UNESCAPED_UNICODE);
    $timestamp = time();
    $signature = hash('sha256', $api['app_id'] . $timestamp . $payload . $api['secret']);

    $ch = curl_init((string) ($api['endpoint'] ?? 'https://open-api.affiliate.shopee.co.th/graphql'));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            "Authorization: SHA256 Credential={$api['app_id']}, Timestamp={$timestamp}, Signature={$signature}",
            'Content-Type: application/json',
        ],
    ]);
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('Shopee API request failed: ' . $error);
    }
    $body = json_decode((string) $response, true);
    if ($status !== 200 || !is_array($body)) {
        throw new RuntimeException('Shopee API HTTP ' . $status . ': ' . mb_substr((string) $response, 0, 300));
    }
    return $body;
}

/** Maps a productOfferV2 node to our product fields. */
function shopee_node_to_product(array $node, string $category): array
{
    $price = (float) ($node['priceMin'] ?? 0);
    // priceDiscountRate is the discount in percent; rebuild the pre-discount price from it.
    $rate = (float) ($node['priceDiscountRate'] ?? 0);
    $original = ($rate > 0 && $rate < 100) ? round($price / (1 - $rate / 100)) : 0;

    return [
        'platform'       => 'shopee',
        'name'           => (string) ($node['productName'] ?? ''),
        'category'       => $category,
        'product_url'    => (string) ($node['productLink'] ?? ''),
        'affiliate_url'  => (string) ($node['offerLink'] ?? ''),
        'image_url'      => (string) ($node['imageUrl'] ?? ''),
        'price'          => $price,
        'original_price' => $original,
        'rating'         => (float) ($node['ratingStar'] ?? 0),
        'sold'           => (int) ($node['sales'] ?? 0),
    ];
}

/**
 * Pulls best sellers for every configured category, keeps the overall top N and ranks them.
 * Products that have not shown up for `hide_after_days` are hidden so stale prices don't stay online.
 */
function fetch_top_products(?string $fixture = null): array
{
    $cfg = (array) config('daily_top', []);
    $count = (int) ($cfg['count'] ?? 15);
    $keywords = (array) ($cfg['keywords'] ?? []);
    $hideAfter = (int) ($cfg['hide_after_days'] ?? 14);

    $result = ['fetched' => 0, 'created' => 0, 'updated' => 0, 'hidden' => 0, 'errors' => [], 'top' => []];
    $candidates = [];

    foreach ($keywords as $category => $keyword) {
        try {
            foreach (shopee_product_offers((string) $keyword, $count, $fixture) as $node) {
                $id = (string) ($node['itemId'] ?? '');
                if ($id === '') {
                    continue;
                }
                $product = shopee_node_to_product($node, (string) $category);
                if (isset($candidates[$id])) {
                    // Same item found under another keyword: keep its first category, take the freshest sales figure.
                    $candidates[$id]['sold'] = max($candidates[$id]['sold'], $product['sold']);
                    continue;
                }
                // Drop invalid offers before ranking so they don't take a slot in the top N.
                list(, $errors) = product_validate($product);
                if ($errors) {
                    $result['errors'][] = $product['name'] . ': ' . implode(' / ', $errors);
                    continue;
                }
                $candidates[$id] = $product;
            }
        } catch (Throwable $ex) {
            $result['errors'][] = $category . ': ' . $ex->getMessage();
        }
    }
    $result['fetched'] = count($candidates);

    usort($candidates, function ($a, $b) {
        return $b['sold'] - $a['sold'];
    });
    $top = array_slice($candidates, 0, $count);

    if (!$top) {
        // Keep yesterday's ranking rather than emptying the section when the API fails.
        return $result;
    }

    $now = now();
    db_run('UPDATE products SET top_rank = NULL WHERE top_rank IS NOT NULL');
    $rank = 0;
    foreach ($top as $product) {
        list($status, $idOrErrors) = product_upsert($product);
        if ($status === 'error') {
            $result['errors'][] = $product['name'] . ': ' . implode(' / ', $idOrErrors);
            continue;
        }
        $rank++;
        $result[$status]++;
        db_run('UPDATE products SET top_rank = ?, last_synced_at = ?, active = 1 WHERE id = ?', [$rank, $now, $idOrErrors]);
        $result['top'][] = '#' . $rank . ' ' . $product['name'] . ' (' . number_format($product['sold']) . ' sold)';
    }

    $result['hidden'] = db_run(
        'UPDATE products SET active = 0 WHERE active = 1 AND last_synced_at IS NOT NULL AND last_synced_at < ?',
        [date('Y-m-d H:i:s', strtotime("-{$hideAfter} days"))]
    );
    return $result;
}

function fetch_status_file(): string
{
    return dirname((string) config('db_path')) . '/last-fetch.json';
}
