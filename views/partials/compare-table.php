<?php /** @var array $products  same item on different platforms, cheapest first */ ?>
<table class="compare">
  <thead><tr><th>แพลตฟอร์ม</th><th>สินค้า</th><th>ราคา</th><th></th></tr></thead>
  <tbody>
    <?php foreach ($products as $i => $c): ?>
    <tr<?= $i === 0 && count($products) > 1 ? ' class="current"' : '' ?>>
      <td><span class="platform platform-<?= e($c['platform']) ?>"><?= e(PLATFORMS[$c['platform']]) ?></span></td>
      <td><a href="<?= e(product_link($c)) ?>"><?= e($c['name']) ?></a><?= $i === 0 && count($products) > 1 ? ' <small>(ถูกสุด)</small>' : '' ?></td>
      <td class="num"><?= e(baht($c['price'])) ?></td>
      <td><a class="btn btn-small" href="<?= e(product_go_link($c, 'compare')) ?>" rel="sponsored nofollow noopener" target="_blank">ดูดีล</a></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
