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
                    'action' => 'query', 'prop' => 'extracts|info', 'explaintext' => 1, 'exsectionformat' => 'wiki',
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
        $values = [
            $nameTh, $item['en'], implode(', ', $item['origins']), $page['title'], (string) $page['fullurl'],
            $intro, json_encode($sections, JSON_UNESCAPED_UNICODE), $trait ? json_encode($trait, JSON_UNESCAPED_UNICODE) : null, $now,
        ];
        if ($existing) {
            db_run('UPDATE breeds SET name_th = ?, name_en = ?, origin = ?, wiki_title = ?, wiki_url = ?, intro = ?, sections = ?,
                    traits = ?, fetched_at = ? WHERE id = ?', array_merge($values, [$existing]));
        } else {
            $slug = breed_slug($page['title']);
            if (db_value('SELECT 1 FROM breeds WHERE slug = ?', [$slug])) {
                $slug .= '-' . strtolower($qid);
            }
            db_run('INSERT INTO breeds (name_th, name_en, origin, wiki_title, wiki_url, intro, sections, traits, fetched_at, qid, slug)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', array_merge($values, [$qid, $slug]));
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
