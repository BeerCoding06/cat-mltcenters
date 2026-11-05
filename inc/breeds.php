<?php
/**
 * Cat breed pages, filled automatically:
 *  - list of breeds + country of origin: Wikidata (CC0), every "cat breed" item that has a Thai Wikipedia article
 *  - text: that Thai Wikipedia article (CC BY-SA 4.0 — the breed page credits and links it)
 *  - optional traits (energy, affection, shedding...): TheCatAPI, only when config 'thecatapi_key' is set
 * Run bin/fetch-breeds.php (monthly is plenty) or press the button in Admin.
 */

/** Which of our cat illustrations best matches each breed (Wikidata id => preset in assets/pet-cats.js). */
const BREED_LOOKS = [
    'Q42732' => 'siamese', 'Q42604' => 'siamese', 'Q42700' => 'khao-manee', 'Q42691' => 'korat',
    'Q42573' => 'suphalak', 'Q21002394' => 'suphalak', 'Q42566' => 'black', 'Q42654' => 'korat',
    'Q29273' => 'silver-tabby', 'Q42610' => 'cream', 'Q42636' => 'cream', 'Q42583' => 'ginger-tabby',
    'Q7955' => 'ginger-tabby', 'Q7962' => 'silver-tabby', 'Q42712' => 'cream', 'Q42715' => 'ginger-tabby',
    'Q686698' => 'calico', 'Q7960' => 'tuxedo', 'Q7338' => 'silver-tabby',
];

/** Wikipedia sections that are just lists of links/references. */
const BREED_SKIP_SECTIONS = ['อ้างอิง', 'แหล่งข้อมูลอื่น', 'ดูเพิ่ม', 'เชิงอรรถ', 'บรรณานุกรม', 'หนังสืออ่านเพิ่ม', 'อ่านเพิ่ม'];

/** TheCatAPI trait keys we show (1-5 scale) and their Thai labels. */
const BREED_TRAITS = [
    'affection_level' => 'ขี้อ้อน', 'energy_level' => 'พลังงาน / ขี้เล่น', 'child_friendly' => 'เข้ากับเด็ก',
    'dog_friendly' => 'เข้ากับสุนัข', 'grooming' => 'ต้องแปรงขนบ่อย', 'shedding_level' => 'ขนร่วง',
    'intelligence' => 'ฉลาด', 'vocalisation' => 'ช่างร้อง',
];

function breed_link(array $breed): string
{
    return url('breed/' . rawurlencode($breed['slug']));
}

function breeds_all(): array
{
    return db_all('SELECT * FROM breeds ORDER BY name_th');
}

function breed_by_slug(string $slug)
{
    return db_one('SELECT * FROM breeds WHERE slug = ?', [$slug]);
}

/** Where breed photos are kept (in the data volume, so they survive redeploys) and served from (/media/breeds/…). */
const BREED_PHOTO_DIR = APP_ROOT . '/data/photos/breeds';

/** URL of the breed's photo ('' when it has none); $small for cards and lists. */
function breed_photo_url(array $breed, bool $small = false): string
{
    if (($breed['photo'] ?? '') === '') {
        return '';
    }
    $file = $small ? preg_replace('/(\.\w+)$/', '-sm$1', $breed['photo']) : $breed['photo'];
    return url('media/breeds/' . rawurlencode((string) $file));
}

/** ['artist' =>, 'license' =>, 'license_url' =>, 'source' =>] for the caption, or null. */
function breed_photo_credit(array $breed)
{
    $c = json_decode((string) ($breed['photo_credit'] ?? ''), true);
    return is_array($c) ? $c : null;
}

/**
 * Lead photo of a Thai Wikipedia page, from Wikimedia Commons: saves an 800x600 and a 400x300 WebP (warm house tone)
 * into BREED_PHOTO_DIR and returns [file, credit] — or null when there is no usable photo. Skips the download when
 * $current already holds the same Commons file.
 */
function breed_fetch_photo(string $imageName, string $slug, $current)
{
    $info = http_get_json('https://commons.wikimedia.org/w/api.php?' . http_build_query([
        'action' => 'query', 'titles' => 'File:' . $imageName, 'prop' => 'imageinfo', 'iiprop' => 'url|extmetadata',
        'iiurlwidth' => 1000, 'format' => 'json', 'formatversion' => 2,
    ]))['query']['pages'][0]['imageinfo'][0] ?? null;
    if (!$info || empty($info['thumburl'])) {
        return null;
    }
    $meta = $info['extmetadata'] ?? [];
    $plain = function ($key) use ($meta) {
        return trim(html_entity_decode(strip_tags((string) ($meta[$key]['value'] ?? '')), ENT_QUOTES, 'UTF-8'));
    };
    $credit = [
        'file'        => $imageName,
        'artist'      => mb_substr($plain('Artist') ?: 'ไม่ระบุชื่อ', 0, 120),
        'license'     => $plain('LicenseShortName') ?: 'ดูที่หน้าไฟล์',
        'license_url' => $plain('LicenseUrl'),
        'source'      => (string) ($info['descriptionurl'] ?? ''),
    ];
    $file = $slug . '.webp';
    $old = $current ? json_decode((string) $current['photo_credit'], true) : null;
    if ($current && $current['photo'] === $file && ($old['file'] ?? '') === $imageName && is_file(BREED_PHOTO_DIR . '/' . $file)) {
        return [$file, $credit];
    }
    $bytes = http_get($info['thumburl']);
    // Cats are usually in the upper middle of these photos: crop a little above centre.
    if (!photo_make($bytes, BREED_PHOTO_DIR . '/' . $file, 800, 600, 0.45)
        || !photo_make($bytes, BREED_PHOTO_DIR . '/' . $slug . '-sm.webp', 400, 300, 0.45)) {
        return null;
    }
    return [$file, $credit];
}

function breed_look(array $breed): string
{
    return BREED_LOOKS[$breed['qid']] ?? 'ginger-tabby';
}

/** Split a plain-text Wikipedia extract into [intro, [[heading, text], ...]], dropping reference sections. */
function breed_split_extract(string $text): array
{
    $parts = preg_split('/^(={2,})\s*(.+?)\s*\1\s*$/mu', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    $intro = trim((string) array_shift($parts));
    // $parts is now: [ '==', 'heading', 'body', '===', 'sub-heading', 'body', ... ]
    $all = [];
    for ($i = 0; $i + 1 < count($parts); $i += 3) {
        $heading = trim($parts[$i + 1]);
        if ($heading !== '' && !in_array($heading, BREED_SKIP_SECTIONS, true)) {
            $all[] = [$heading, trim($parts[$i + 2] ?? ''), strlen($parts[$i])];
        }
    }
    // Keep sections with text, and empty headings only when sub-sections with text follow them.
    $sections = [];
    foreach ($all as $n => $s) {
        $next = $all[$n + 1] ?? null;
        if ($s[1] !== '' || ($next && $next[2] > $s[2] && $next[1] !== '')) {
            $sections[] = $s;
        }
    }
    return [$intro, $sections];
}

function breed_slug(string $title): string
{
    $name = trim((string) preg_replace('/\s*\((?:พันธุ์)?แมว\)\s*$/u', '', $title));
    return slugify($name);
}

/** Fetch everything and upsert the breeds table. Returns a summary for the admin page / CLI. */
function fetch_breeds(): array
{
    $result = ['found' => 0, 'saved' => 0, 'skipped' => [], 'errors' => [], 'traits' => 0];

    $query = 'SELECT ?item ?thTitle ?enTitle ?originLabel WHERE {'
        . ' ?item wdt:P31 wd:Q43577 .'
        . ' ?th schema:about ?item ; schema:isPartOf <https://th.wikipedia.org/> ; schema:name ?thTitle .'
        . ' OPTIONAL { ?en schema:about ?item ; schema:isPartOf <https://en.wikipedia.org/> ; schema:name ?enTitle . }'
        . ' OPTIONAL { ?item wdt:P495 ?origin . }'
        . ' SERVICE wikibase:label { bd:serviceParam wikibase:language "th,en". } }';
    $rows = http_get_json('https://query.wikidata.org/sparql?format=json&query=' . rawurlencode($query),
        ['Accept: application/sparql-results+json'])['results']['bindings'] ?? [];

    $items = [];
    foreach ($rows as $r) {
        $qid = basename((string) $r['item']['value']);
        if (!isset($items[$qid])) {
            $items[$qid] = ['th' => $r['thTitle']['value'], 'en' => $r['enTitle']['value'] ?? '', 'origins' => []];
        }
        $origin = $r['originLabel']['value'] ?? '';
        if ($origin !== '' && !preg_match('/^Q\d+$/', $origin) && !in_array($origin, $items[$qid]['origins'], true)) {
            $items[$qid]['origins'][] = preg_replace('/^ประเทศ/u', '', $origin);
        }
    }
    $result['found'] = count($items);

    $traits = breed_traits_by_en_title();
    $seenTitles = [];
    $now = now();

    foreach ($items as $qid => $item) {
        $page = null;
        for ($attempt = 1; $attempt <= 4; $attempt++) {
            try {
                usleep(1200000); // be gentle with Wikipedia: about one request a second
                $page = http_get_json('https://th.wikipedia.org/w/api.php?' . http_build_query([
                    'action' => 'query', 'prop' => 'extracts|info|pageimages', 'piprop' => 'name', 'explaintext' => 1, 'exsectionformat' => 'wiki',
                    'inprop' => 'url', 'redirects' => 1, 'format' => 'json', 'formatversion' => 2, 'titles' => $item['th'],
                    'maxlag' => 5,
                ]))['query']['pages'][0] ?? null;
                break;
            } catch (Throwable $ex) {
                if (strpos($ex->getMessage(), 'HTTP 429') === false || $attempt === 4) {
                    $result['errors'][] = $item['th'] . ': ' . $ex->getMessage();
                    continue 2;
                }
                sleep(5 * $attempt); // rate limited: back off and try again
            }
        }
        if (!$page || !empty($page['missing']) || trim($page['extract'] ?? '') === '') {
            $result['skipped'][] = $item['th'] . ' (ไม่มีเนื้อหา)';
            continue;
        }
        if (isset($seenTitles[$page['title']])) {
            $result['skipped'][] = $item['th'] . ' (ซ้ำกับ ' . $page['title'] . ')';
            continue;
        }
        $seenTitles[$page['title']] = true;

        list($intro, $sections) = breed_split_extract((string) $page['extract']);
        $trait = $traits[mb_strtolower($item['en'])] ?? null;
        if ($trait) {
            $result['traits']++;
        }
        $nameTh = trim((string) preg_replace('/\s*\((?:พันธุ์)?แมว\)\s*$/u', '', $page['title']));
        $existing = db_value('SELECT id FROM breeds WHERE qid = ?', [$qid]);
        $slugForPhoto = $existing ? (string) db_value('SELECT slug FROM breeds WHERE id = ?', [$existing]) : breed_slug($page['title']);
        $photo = null;
        for ($attempt = 1; !empty($page['pageimage']) && $attempt <= 4; $attempt++) {
            try {
                usleep(1500000);
                $photo = breed_fetch_photo((string) $page['pageimage'], $slugForPhoto,
                    $existing ? db_one('SELECT photo, photo_credit FROM breeds WHERE id = ?', [$existing]) : null);
                break;
            } catch (Throwable $ex) {
                if (strpos($ex->getMessage(), 'HTTP 429') === false || $attempt === 4) {
                    $result['errors'][] = $item['th'] . ' (รูป): ' . $ex->getMessage();
                    break;
                }
                sleep(5 * $attempt); // rate limited: back off and try again
            }
        }
        if ($photo) {
            $result['photos'] = ($result['photos'] ?? 0) + 1;
        }
        $values = [
            $nameTh, $item['en'], implode(', ', $item['origins']), $page['title'], (string) $page['fullurl'],
            $intro, json_encode($sections, JSON_UNESCAPED_UNICODE), $trait ? json_encode($trait, JSON_UNESCAPED_UNICODE) : null, $now,
        ];
        if ($existing) {
            db_run('UPDATE breeds SET name_th = ?, name_en = ?, origin = ?, wiki_title = ?, wiki_url = ?, intro = ?, sections = ?,
                    traits = ?, fetched_at = ? WHERE id = ?', array_merge($values, [$existing]));
            $breedId = (int) $existing;
        } else {
            $slug = breed_slug($page['title']);
            if (db_value('SELECT 1 FROM breeds WHERE slug = ?', [$slug])) {
                $slug .= '-' . strtolower($qid);
            }
            db_run('INSERT INTO breeds (name_th, name_en, origin, wiki_title, wiki_url, intro, sections, traits, fetched_at, qid, slug)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', array_merge($values, [$qid, $slug]));
            $breedId = (int) db()->lastInsertId();
            if ($photo && $slug !== $slugForPhoto) {  // slug got a suffix: keep the photo under the final name
                foreach (['', '-sm'] as $suffix) {
                    if (is_file(BREED_PHOTO_DIR . '/' . $slugForPhoto . $suffix . '.webp')) {
                        rename(BREED_PHOTO_DIR . '/' . $slugForPhoto . $suffix . '.webp', BREED_PHOTO_DIR . '/' . $slug . $suffix . '.webp');
                    }
                }
                $photo[0] = $slug . '.webp';
            }
        }
        if ($photo) {
            db_run('UPDATE breeds SET photo = ?, photo_credit = ? WHERE id = ?',
                [$photo[0], json_encode($photo[1], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $breedId]);
        }
        $result['saved']++;
    }
    return $result;
}

/** TheCatAPI breed traits keyed by lower-case English Wikipedia title. Empty without an API key. */
function breed_traits_by_en_title(): array
{
    $key = (string) config('thecatapi_key', '');
    if ($key === '') {
        return [];
    }
    try {
        $list = http_get_json('https://api.thecatapi.com/v1/breeds', ['x-api-key: ' . $key]);
    } catch (Throwable $ex) {
        return [];
    }
    $out = [];
    foreach ($list as $b) {
        $wiki = (string) ($b['wikipedia_url'] ?? '');
        if ($wiki === '') {
            continue;
        }
        $title = mb_strtolower(str_replace('_', ' ', rawurldecode(basename(parse_url($wiki, PHP_URL_PATH)))));
        $t = ['life_span' => (string) ($b['life_span'] ?? ''), 'weight_kg' => (string) ($b['weight']['metric'] ?? '')];
        foreach (array_keys(BREED_TRAITS) as $k) {
            if (isset($b[$k])) {
                $t[$k] = max(0, min(5, (int) $b[$k]));
            }
        }
        $out[$title] = $t;
    }
    return $out;
}

/** Paragraphs from Wikipedia plain text, escaped. */
function breed_paragraphs(string $text): string
{
    $html = '';
    foreach (preg_split("/\n+/u", trim($text)) as $p) {
        $p = trim($p);
        if ($p !== '') {
            $html .= '<p>' . e($p) . "</p>\n";
        }
    }
    return $html;
}
