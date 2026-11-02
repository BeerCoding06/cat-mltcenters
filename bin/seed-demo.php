<?php
// Adds clearly-marked demo products with 60 days of fake price history, so you can see the site working.
//
//   php bin/seed-demo.php            add demo products
//   php bin/seed-demo.php --remove   delete them again (before going live)

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

require dirname(__DIR__) . '/inc/bootstrap.php';

const DEMO_PREFIX = '[DEMO] ';

if (in_array('--remove', $argv, true)) {
    $n = db_run('DELETE FROM products WHERE name LIKE ?', [DEMO_PREFIX . '%']);
    echo "Removed $n demo products\n";
    exit(0);
}

// platform, name, category, price, original price, rating, sold, group_key
$demo = [
    ['lazada', 'อาหารแมวเม็ด สูตรแมวโต รสปลาทูน่า 7 กก.', 'อาหารแมวเม็ด', 799, 1150, 4.8, 9800, 'demo-dry-tuna-7kg'],
    ['shopee', 'อาหารแมวเม็ด สูตรแมวโต รสปลาทูน่า 7 กก.', 'อาหารแมวเม็ด', 759, 1150, 4.9, 15200, 'demo-dry-tuna-7kg'],
    ['lazada', 'อาหารแมวเม็ด เกรดพรีเมียม เกรนฟรี สูตรลูกแมว 1.5 กก.', 'อาหารแมวเม็ด', 459, 690, 4.7, 2300, ''],
    ['shopee', 'อาหารแมวเปียก ซอง 70 กรัม รวมรส (แพ็ก 12)', 'อาหารแมวเปียก', 199, 300, 4.8, 21000, ''],
    ['lazada', 'อาหารแมวเปียก กระป๋อง เนื้อปลาแท้ในเยลลี่ 400 กรัม (6 กระป๋อง)', 'อาหารแมวเปียก', 289, 420, 4.6, 4100, ''],
    ['shopee', 'ทรายแมวเต้าหู้ กลิ่นชาเขียว 6 ลิตร', 'ทรายแมว', 109, 199, 4.8, 48000, 'demo-tofu-litter-6l'],
    ['lazada', 'ทรายแมวเต้าหู้ กลิ่นชาเขียว 6 ลิตร', 'ทรายแมว', 119, 199, 4.7, 26000, 'demo-tofu-litter-6l'],
    ['shopee', 'ทรายแมวภูเขาไฟ จับตัวเร็ว ฝุ่นน้อย 10 ลิตร', 'ทรายแมว', 159, 259, 4.6, 12500, ''],
    ['lazada', 'ขนมแมวเลีย ครีมทูน่า 15 กรัม (แพ็ก 50 ซอง)', 'ขนมแมว', 249, 450, 4.9, 33000, ''],
    ['shopee', 'คอนโดแมว 4 ชั้น พร้อมเสาลับเล็บและบ้านนอน', 'คอนโดแมว & ของเล่น', 890, 1590, 4.6, 1800, ''],
    ['lazada', 'ไม้ตกแมว ขนนก พร้อมกระดิ่ง (ชุด 5 ชิ้น)', 'คอนโดแมว & ของเล่น', 69, 150, 4.5, 7600, ''],
    ['shopee', 'น้ำพุแมว กรองน้ำ 2.5 ลิตร เสียงเงียบ', 'อุปกรณ์แมว', 349, 690, 4.7, 5400, ''],
    ['lazada', 'ห้องน้ำแมว ทรงโดม มีฝาปิด ลดกลิ่น', 'อุปกรณ์แมว', 399, 790, 4.6, 3100, ''],
];

$count = 0;
foreach ($demo as $i => $row) {
    list($platform, $name, $category, $price, $original, $rating, $sold, $group) = $row;
    $host = $platform === 'lazada' ? 'https://www.lazada.co.th/' : 'https://shopee.co.th/';
    list($data, $errors) = product_validate([
        'platform'       => $platform,
        'name'           => DEMO_PREFIX . $name,
        'category'       => $category,
        'group_key'      => $group,
        'affiliate_url'  => $host . '?demo=' . ($i + 1),
        'product_url'    => $host . '?demo=' . ($i + 1),
        'price'          => $price,
        'original_price' => $original,
        'rating'         => $rating,
        'sold'           => $sold,
        'description'    => 'สินค้าตัวอย่างสำหรับทดสอบระบบ ลบได้ด้วยคำสั่ง php bin/seed-demo.php --remove',
    ]);
    if ($errors || product_find_existing($data) !== null) {
        continue;
    }
    $id = product_save($data);

    // Replace the single starting price point with a fake 60-day history that ends at today's price.
    db_run('DELETE FROM price_history WHERE product_id = ?', [$id]);
    $level = $original * 0.85;
    for ($day = 60; $day >= 1; $day -= mt_rand(4, 9)) {
        $level = max($price * 0.92, min($original, $level * (mt_rand(88, 108) / 100)));
        db_run('INSERT INTO price_history (product_id, price, recorded_at) VALUES (?, ?, ?)', [
            $id, round($level), date('Y-m-d 09:00:00', strtotime("-{$day} days")),
        ]);
    }
    db_run('INSERT INTO price_history (product_id, price, recorded_at) VALUES (?, ?, ?)', [$id, $price, now()]);
    $count++;
}
echo "Added $count demo products\n";
