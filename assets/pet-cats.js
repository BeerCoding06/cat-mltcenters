// Pet cats that live on the page — and a little studio where each visitor makes their own.
//
// Cats roam anywhere on the screen and act on their own: stroll, trot, zoomies, sit and look around, yawn,
// doze off (snot bubble!), chase each other. They ignore the mouse.
// Tap a cat and round command icons pop out around it (walk, run around, sit, sleep, yawn, play, run through the
// screen, come here, design this cat). The 🐾 button (bottom-left) fans out the same icons for every cat, plus
// "แต่งแมว" — a side drawer to create and edit cats (name, preset breed, colours, pattern, collar, size, personality).
// Nothing is modal; the page stays usable.
// Everything is saved in this browser only (localStorage). Not shown to people who prefer reduced motion.
//
// Frames come from assets/cat-sprites.svg (see inc/illustrations.php): cw1-3 walk, cr1-3 run (face right),
// cs1-8 sitting (cs2-3 yawn, cs6-8 dozing). The file is fetched once and inlined, and each cat keeps every
// frame it has used stacked in its own <svg>, so switching frames never reloads anything (no flicker).
(function () {
  var script = document.currentScript;
  var SPRITES = (script && script.getAttribute('data-sprites')) || 'assets/cat-sprites.svg';
  if (!window.matchMedia || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    return;
  }

  var STORE_KEY = 'deal-pet-cats';
  var MAX = 6;
  var BOX_RATIO = 150 / 320; // frame box height / width
  var SIT_SCALE = 0.77;      // sqrt(walk-frame area / sit-frame area), measured from the sprite
  var SVG_NS = 'http://www.w3.org/2000/svg';

  var PATTERNS = { solid: 'สีพื้น', tabby: 'ลายสลิด', socks: 'ขาวสองสี', points: 'แต้มสี', calico: 'สามสี',
    tortie: 'กระดองเต่า', shaded: 'ปลายขนเงิน' };
  var MOODS = { normal: 'ปกติ', sleepy: 'ขี้เซา', playful: 'ซนสุดๆ' };

  // ── Care game (docs/pet-care-game-spec.md, stage 1: adopt, food, water) ──
  // Only adopted cats get hungry and thirsty; the others stay carefree decorations. Values run 0-100 and drop in
  // real time, also while the site is closed (caught up from lastTick on the next visit).
  var CARE = {
    maxAdopted: 3,
    foodPerHour: 100 / 16,   // full to empty in about 16 hours
    waterPerHour: 100 / 12,  // about 12 hours
    feed: 40, drink: 50,     // what one bowl gives
    full: 90,                // above this the cat turns its nose up
    low: 30,                 // below this it asks (icon over its head, red dot on the paw button)
    // Stage 2: falling ill. Checked once per hour of real time, the same hour gives the same result on every reload.
    sickChance: 0.01,        // per hour when fed and watered
    sickChanceLow: 0.05,     // per hour when food or water is under 20
    sickChanceEmpty: 0.15,   // per hour when both are empty
    worseAfter: 24 * 3600000,  // untreated for a day: very sick
    doseGap: 6 * 3600000,      // very sick needs two doses at least 6 hours apart
    // Stage 3: health. Per hour; the losses add up.
    healthEmpty: 2,            // food or water at 0
    healthSick: 1, healthVerySick: 3,
    healthGain: 1,             // food and water over 50 and not sick
    warnAt: 20,                // below this a warning card shows when the page opens
    memorialMax: 20,
    // Stage 4: difficulty multiplies every loss (food, water, health). About how long a neglected cat lasts.
    levels: {
      easy: { label: 'สบายๆ', note: 'ประมาณ 5–6 วัน', rate: 0.5 },
      normal: { label: 'ปกติ', note: 'ประมาณ 2–3 วัน', rate: 1 },
      hard: { label: 'ท้าทาย', note: 'ประมาณ 1.5–2 วัน', rate: 1.5 }
    },
    dealEvery: 5,              // every 5th meal may suggest today's cat-food deals
    dealGap: 7 * 86400000      // at most once a week
  };
  var HOUR = 3600000;
  var PRESETS = {
    'ginger-tabby': { label: 'ส้มลายสลิด', base: '#f6a65a', accent: '#c8692a', pattern: 'tabby', eye: '#8cc63f', collar: '#e2768c' },
    'silver-tabby': { label: 'ซิลเวอร์แท็บบี้', base: '#b7c0c9', accent: '#5d6874', pattern: 'tabby', eye: '#7cc36b', collar: '#6aa6dc' },
    'black': { label: 'โกนจา (ดำ)', base: '#3a3433', accent: '#262120', pattern: 'solid', eye: '#ffd166', collar: '#e2768c' },
    'khao-manee': { label: 'ขาวมณี', base: '#fbf7f1', accent: '#e8ddd0', pattern: 'solid', eye: '#6aa6dc', collar: '#f2c14e' },
    'korat': { label: 'สีสวาด', base: '#8e9aa6', accent: '#6f7b87', pattern: 'solid', eye: '#7cc36b', collar: '#f9c1cc' },
    'suphalak': { label: 'ศุภลักษณ์', base: '#a0623d', accent: '#7f4a2c', pattern: 'solid', eye: '#f2c14e', collar: '#5bb38a' },
    'siamese': { label: 'วิเชียรมาศ', base: '#f3e6d0', accent: '#5a3d30', pattern: 'points', eye: '#6aa6dc', collar: '#e2768c' },
    'calico': { label: 'สามสี', base: '#f0974f', accent: '#3a3433', pattern: 'calico', eye: '#f2b134', collar: '#5bb38a' },
    'tuxedo': { label: 'ขาวดำ', base: '#2f2a2a', accent: '#1f1b1b', pattern: 'socks', eye: '#9bd36a', collar: '#e2768c' },
    'cream': { label: 'ครีม', base: '#f1dcc0', accent: '#d8b48a', pattern: 'solid', eye: '#e8a23b', collar: '#6aa6dc' },
    'ginger-white': { label: 'ส้มขาว', base: '#f2a057', accent: '#c8692a', pattern: 'socks', eye: '#8cc63f', collar: '#6aa6dc' },
    'grey-white': { label: 'เทาขาว', base: '#8c949c', accent: '#5d6874', pattern: 'socks', eye: '#f2b134', collar: '#e2768c' },
    'tortie': { label: 'กระดองเต่า', base: '#3d2b25', accent: '#d9822b', pattern: 'tortie', eye: '#f2b134', collar: '#5bb38a' },
    'british-blue': { label: 'บริติชบลู', base: '#7d8a99', accent: '#64707e', pattern: 'solid', eye: '#e8952e', collar: '#f9c1cc' },
    'chocolate': { label: 'ช็อกโกแลต', base: '#6b4130', accent: '#4f2e21', pattern: 'solid', eye: '#e8b04a', collar: '#f2c14e' },
    'lilac': { label: 'ไลแลค', base: '#c4b5b8', accent: '#a99a9d', pattern: 'solid', eye: '#8cc63f', collar: '#b98bd0' },
    'golden-tabby': { label: 'ทองลายสลิด', base: '#e9b867', accent: '#b07a2e', pattern: 'tabby', eye: '#7cc36b', collar: '#e2768c' },
    'brown-tabby': { label: 'น้ำตาลลายเสือ', base: '#9c7a5b', accent: '#4a3528', pattern: 'tabby', eye: '#c9d23c', collar: '#e05d4b' },
    'flame-point': { label: 'แต้มส้ม', base: '#f8ecdc', accent: '#e0874a', pattern: 'points', eye: '#6aa6dc', collar: '#5bb38a' },
    'chinchilla': { label: 'ชินชิลลา', base: '#d9dde2', accent: '#8f979f', pattern: 'shaded', eye: '#5bb38a', collar: '#e2768c' }
  };
  var NAMES = ['ส้มโอ', 'มะลิ', 'ข้าวปั้น', 'ถั่วแดง', 'โมจิ', 'ทองดี', 'ลูกชิ้น', 'ขนมปัง', 'นมเย็น', 'มะขาม',
    'ชาไทย', 'บัวลอย', 'ข้าวหอม', 'เต้าหู้', 'มะพร้าว', 'ทับทิม', 'ไข่ตุ๋น', 'ลำไย', 'ซูชิ', 'วุ้นเส้น'];
  // Care rewards: days in a row the cat was kept well (health 80+, fed, watered, not sick), counted once a day
  // when the page is open. Each step gives a title, and from 3 days a look the visitor can show off on a share card.
  var TITLES = [
    { min: 30, key: 'legend', label: 'แมวในตำนาน', stars: 5 },
    { min: 14, key: 'crown', label: 'แมวสุขภาพดีเยี่ยม', stars: 4 },
    { min: 7, key: 'shiny', label: 'แมวขนเงาวิ้ง', stars: 3 },
    { min: 3, key: 'happy', label: 'แมวอารมณ์ดี', stars: 2 },
    { min: 1, key: 'new', label: 'ลูกรักมือใหม่', stars: 1 }
  ];
  // [command, label, colour tone] — tones are .tone-* in style.css
  var COMMANDS = [
    ['walk', 'เดินเล่น', 'mint'], ['zoom', 'วิ่งพล่าน', 'sun'], ['stay', 'นั่งนิ่งๆ', 'pink'], ['sleep', 'นอน', 'sky'],
    ['yawn', 'หาว', 'lilac'], ['play', 'เล่นกัน', 'rose'], ['dash', 'วิ่งทะลุจอ', 'orange'], ['come', 'มาตรงกลาง', 'teal'],
    ['hunt', 'ล่าเหยื่อ', 'olive'], ['stretch', 'บิดขี้เกียจ', 'peach']
  ];

  // Icons: Lucide v0.454.0 (ISC licence, lucide.dev; full text in assets/LICENSE-lucide.txt) — footprints, zap, cat, bed, arrow-left-right, map-pin, pencil,
  // eye-off, eye, paw-print, crosshair, sunrise. "yawn" and "play" are drawn here in the same style (24 grid, 2px round strokes).
  var ICONS = {
    hunt: "<circle cx=\"12\" cy=\"12\" r=\"10\" /><line x1=\"22\" x2=\"18\" y1=\"12\" y2=\"12\" /><line x1=\"6\" x2=\"2\" y1=\"12\" y2=\"12\" /><line x1=\"12\" x2=\"12\" y1=\"6\" y2=\"2\" /><line x1=\"12\" x2=\"12\" y1=\"22\" y2=\"18\" />",
    stretch: "<path d=\"M12 2v8\" /><path d=\"m4.93 10.93 1.41 1.41\" /><path d=\"M2 18h2\" /><path d=\"M20 18h2\" /><path d=\"m19.07 10.93-1.41 1.41\" /><path d=\"M22 22H2\" /><path d=\"m8 6 4-4 4 4\" /><path d=\"M16 18a4 4 0 0 0-8 0\" />",
    walk: "<path d=\"M4 16v-2.38C4 11.5 2.97 10.5 3 8c.03-2.72 1.49-6 4.5-6C9.37 2 10 3.8 10 5.5c0 3.11-2 5.66-2 8.68V16a2 2 0 1 1-4 0Z\" /><path d=\"M20 20v-2.38c0-2.12 1.03-3.12 1-5.62-.03-2.72-1.49-6-4.5-6C14.63 6 14 7.8 14 9.5c0 3.11 2 5.66 2 8.68V20a2 2 0 1 0 4 0Z\" /><path d=\"M16 17h4\" /><path d=\"M4 13h4\" />",
    zoom: "<path d=\"M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z\" />",
    stay: "<path d=\"M12 5c.67 0 1.35.09 2 .26 1.78-2 5.03-2.84 6.42-2.26 1.4.58-.42 7-.42 7 .57 1.07 1 2.24 1 3.44C21 17.9 16.97 21 12 21s-9-3-9-7.56c0-1.25.5-2.4 1-3.44 0 0-1.89-6.42-.5-7 1.39-.58 4.72.23 6.5 2.23A9.04 9.04 0 0 1 12 5Z\" /><path d=\"M8 14v.5\" /><path d=\"M16 14v.5\" /><path d=\"M11.25 16.25h1.5L12 17l-.75-.75Z\" />",
    sleep: "<path d=\"M2 4v16\" /><path d=\"M2 8h18a2 2 0 0 1 2 2v10\" /><path d=\"M2 17h20\" /><path d=\"M6 8v9\" />",
    yawn: "<path d=\"M12 5c.67 0 1.35.09 2 .26 1.78-2 5.03-2.84 6.42-2.26 1.4.58-.42 7-.42 7 .57 1.07 1 2.24 1 3.44C21 17.9 16.97 21 12 21s-9-3-9-7.56c0-1.25.5-2.4 1-3.44 0 0-1.89-6.42-.5-7 1.39-.58 4.72.23 6.5 2.23A9.04 9.04 0 0 1 12 5Z\" /><path d=\"M7.5 12.5q1 .9 2 0\"/><path d=\"M14.5 12.5q1 .9 2 0\"/><ellipse cx=\"12\" cy=\"17\" rx=\"1.6\" ry=\"2.1\"/>",
    play: "<circle cx=\"10.5\" cy=\"11.5\" r=\"7\"/><path d=\"M4.6 8.5c3.4.6 6.8 3.6 8.2 9.6\"/><path d=\"M8.2 5c2.7 2 4.7 6.3 4.8 12\"/><path d=\"M15.6 6.8c-3.4 1.4-6.8 5.1-8.3 10\"/><path d=\"M17.5 12.5c2.5.5 3.7 2.7 3 5.5\"/>",
    dash: "<path d=\"M8 3 4 7l4 4\" /><path d=\"M4 7h16\" /><path d=\"m16 21 4-4-4-4\" /><path d=\"M20 17H4\" />",
    come: "<path d=\"M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0\" /><circle cx=\"12\" cy=\"10\" r=\"3\" />",
    edit: "<path d=\"M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z\" /><path d=\"m15 5 4 4\" />",
    hide: "<path d=\"M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49\" /><path d=\"M14.084 14.158a3 3 0 0 1-4.242-4.242\" /><path d=\"M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143\" /><path d=\"m2 2 20 20\" />",
    show: "<path d=\"M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0\" /><circle cx=\"12\" cy=\"12\" r=\"3\" />",
    // Care (docs/pet-care-game-spec.md): bowl and house drawn in the same style, droplet from Lucide.
    feed: "<path d=\"M3 12h18a9 6.5 0 0 1-18 0z\" /><path d=\"M8 21h8\" /><path d=\"M12 18.5V21\" /><path d=\"M8 8c0-1 1.2-1.4 1.2-2.4S8 4.4 8 3.4\" /><path d=\"M12.5 8c0-1 1.2-1.4 1.2-2.4s-1.2-1.2-1.2-2.2\" />",
    drink: "<path d=\"M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7z\" />",
    heart: "<path d=\"M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z\" />",
    star: "<path d=\"M11.525 2.295a.53.53 0 0 1 .95 0l2.31 4.679a2.123 2.123 0 0 0 1.595 1.16l5.166.756a.53.53 0 0 1 .294.904l-3.736 3.638a2.123 2.123 0 0 0-.611 1.878l.882 5.14a.53.53 0 0 1-.771.56l-4.618-2.428a2.122 2.122 0 0 0-1.973 0L6.396 21.01a.53.53 0 0 1-.77-.56l.881-5.139a2.122 2.122 0 0 0-.611-1.879L2.16 9.795a.53.53 0 0 1 .294-.906l5.165-.755a2.122 2.122 0 0 0 1.597-1.16z\" />",
    med: "<path d=\"m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7Z\" /><path d=\"m8.5 8.5 7 7\" />",
    sick: "<path d=\"M14 4v10.54a4 4 0 1 1-4 0V4a2 2 0 0 1 4 0Z\" />",
    adopt: "<path d=\"M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8\" /><path d=\"M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z\" />",
    crown: "<path d=\"M11.562 3.266a.5.5 0 0 1 .876 0L15.39 8.87a1 1 0 0 0 1.516.294L21.183 5.5a.5.5 0 0 1 .798.519l-2.834 10.246a1 1 0 0 1-.956.734H5.81a1 1 0 0 1-.957-.734L2.02 6.02a.5.5 0 0 1 .798-.519l4.276 3.664a1 1 0 0 0 1.516-.294z\" /><path d=\"M5 21h14\" />",
    share: "<circle cx=\"18\" cy=\"5\" r=\"3\" /><circle cx=\"6\" cy=\"12\" r=\"3\" /><circle cx=\"18\" cy=\"19\" r=\"3\" /><line x1=\"8.59\" x2=\"15.42\" y1=\"13.51\" y2=\"17.49\" /><line x1=\"15.41\" x2=\"8.59\" y1=\"6.51\" y2=\"10.49\" />",
    paw: "<circle cx=\"11\" cy=\"4\" r=\"2\" /><circle cx=\"18\" cy=\"8\" r=\"2\" /><circle cx=\"20\" cy=\"16\" r=\"2\" /><path d=\"M9 10a5 5 0 0 1 5 5v3.5a3.5 3.5 0 0 1-6.84 1.045Q6.52 17.48 4.46 16.84A3.5 3.5 0 0 1 5.5 10Z\" />"
  };

  function iconSvg(name) {
    var span = document.createElement('span');
    span.className = 'pet-icon';
    span.setAttribute('aria-hidden', 'true');
    // ICONS are fixed strings defined above, never user input.
    span.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" '
      + 'stroke-linejoin="round">' + ICONS[name] + '</svg>';
    return span;
  }

  // ── Colours ────────────────────────────────
  function rgb(hex) {
    var n = parseInt(hex.slice(1), 16);
    return [n >> 16 & 255, n >> 8 & 255, n & 255];
  }
  function hex(c) {
    return '#' + c.map(function (v) { return ('0' + Math.round(v).toString(16)).slice(-2); }).join('');
  }
  function mix(a, b, t) {
    var x = rgb(a);
    var y = rgb(b);
    return hex([0, 1, 2].map(function (i) { return x[i] + (y[i] - x[i]) * t; }));
  }
  function lighten(c, t) { return mix(c, '#ffffff', t); }
  function darken(c, t) { return mix(c, '#000000', t); }
  function luminance(c) {
    var x = rgb(c);
    return (0.299 * x[0] + 0.587 * x[1] + 0.114 * x[2]) / 255;
  }
  function validHex(v, fallback) {
    return /^#[0-9a-f]{6}$/i.test(v || '') ? v.toLowerCase() : fallback;
  }

  /** The CSS variables that paint the frames, from a cat's colours + pattern. */
  function paint(cfg) {
    var base = cfg.base;
    var accent = cfg.accent;
    var v = { '--ginger': base, '--cat-light': lighten(base, 0.3), '--cat-mid': darken(base, 0.18),
      '--cat-shade': darken(base, 0.12), '--paw': base };
    if (cfg.pattern === 'tabby') {
      v['--cat-mid'] = accent;
      v['--paw'] = lighten(base, 0.6);
    } else if (cfg.pattern === 'socks') {
      v['--cat-light'] = '#fffaf3';
      v['--cat-mid'] = darken(base, 0.2);
      v['--paw'] = '#fffaf3';
    } else if (cfg.pattern === 'points') {
      v['--cat-light'] = lighten(base, 0.4);
      v['--cat-mid'] = accent;
      v['--cat-shade'] = darken(base, 0.1);
      v['--paw'] = accent;
    } else if (cfg.pattern === 'calico') {
      v['--ginger'] = '#fffaf3';
      v['--cat-light'] = base;
      v['--cat-mid'] = accent;
      v['--cat-shade'] = '#ece0d0';
      v['--paw'] = '#fffaf3';
    } else if (cfg.pattern === 'tortie') {
      // Dark coat mottled with orange: the light areas carry the orange, the markings sit between the two.
      v['--cat-light'] = accent;
      v['--cat-mid'] = mix(base, accent, 0.45);
      v['--paw'] = mix(base, accent, 0.3);
    } else if (cfg.pattern === 'shaded') {
      // Pale undercoat, darker tips on the back (chinchilla, silver shaded).
      v['--cat-light'] = lighten(base, 0.65);
      v['--cat-mid'] = accent;
      v['--cat-shade'] = darken(base, 0.08);
      v['--paw'] = lighten(base, 0.5);
    }
    // The silhouette poses (sl1-8) have no drawn pattern, so they switch on matching marking layers instead.
    v['--sl-stripes'] = cfg.pattern === 'tabby' ? 1 : 0;
    v['--sl-points'] = cfg.pattern === 'points' ? 1 : 0;
    v['--sl-patches'] = cfg.pattern === 'calico' || cfg.pattern === 'tortie' ? 1 : 0;
    v['--sl-muzzle'] = cfg.pattern === 'points' ? 0 : 1;
    v['--cat-belly'] = cfg.pattern === 'calico' ? '#fffaf3' : v['--cat-light'];
    // Back highlight of the walk/run frames changes shape every frame, so keep it close to the coat colour or
    // it flickers while walking. Calico keeps it: there it is the orange back patch.
    v['--cat-sheen'] = cfg.pattern === 'calico' ? v['--cat-light'] : lighten(base, 0.1);
    // One tail colour for every frame set: dark for points and calico, the coat colour otherwise.
    v['--cat-tail'] = cfg.pattern === 'points' || cfg.pattern === 'calico' ? accent : base;
    v['--eye'] = cfg.eye;
    v['--collar'] = cfg.collar;
    v['--ink'] = luminance(v['--ginger']) < 0.3 ? '#d8ccc2' : '#4f3b2c';
    return v;
  }

  function applyPaint(el, cfg) {
    var v = paint(cfg);
    for (var k in v) {
      el.style.setProperty(k, v[k]);
    }
  }

  // ── Storage ────────────────────────────────
  function rand(min, max) { return min + Math.random() * (max - min); }
  function pick(list) { return list[Math.floor(Math.random() * list.length)]; }
  function uid() { return Math.random().toString(36).slice(2, 9); }

  function freshName(taken) {
    var free = NAMES.filter(function (n) { return taken.indexOf(n) < 0; });
    return pick(free.length ? free : NAMES);
  }

  function newCatConfig(presetKey, taken) {
    var key = presetKey || pick(Object.keys(PRESETS));
    var p = PRESETS[key];
    return {
      id: uid(), name: freshName(taken || []), preset: key, base: p.base, accent: p.accent, pattern: p.pattern,
      eye: p.eye, collar: p.collar, size: Math.round(rand(110, 140)), mood: 'normal'
    };
  }

  function clean(c) {
    var preset = PRESETS[c.preset] || PRESETS[c.breed] || PRESETS['ginger-tabby'];
    return {
      id: String(c.id || uid()),
      name: String(c.name || pick(NAMES)).slice(0, 20),
      preset: PRESETS[c.preset] ? c.preset : (PRESETS[c.breed] ? c.breed : ''),
      base: validHex(c.base, preset.base),
      accent: validHex(c.accent, preset.accent),
      pattern: PATTERNS[c.pattern] ? c.pattern : preset.pattern,
      eye: validHex(c.eye, preset.eye),
      collar: validHex(c.collar, preset.collar),
      size: Math.max(80, Math.min(180, parseInt(c.size, 10) || 120)),
      mood: MOODS[c.mood] ? c.mood : 'normal',
      x: typeof c.x === 'number' && isFinite(c.x) ? c.x : null,
      y: typeof c.y === 'number' && isFinite(c.y) ? c.y : null,
      care: cleanCare(c.care)
    };
  }

  function clamp100(v, fallback) {
    v = Number(v);
    return isFinite(v) ? Math.max(0, Math.min(100, v)) : fallback;
  }
  function cleanCare(c) {
    if (!c || typeof c !== 'object' || !c.adoptedAt) {
      return null;
    }
    var now = Date.now();
    var stats = c.stats || {};
    return {
      adoptedAt: Number(c.adoptedAt) || now,
      food: clamp100(c.food, 100),
      water: clamp100(c.water, 100),
      health: clamp100(c.health, 100),
      level: CARE.levels[c.level] ? c.level : 'normal',
      diedAt: Number(c.diedAt) || null,
      lastTick: Math.min(now, Number(c.lastTick) || now),
      sick: Math.max(0, Math.min(2, c.sick | 0)),  // 0 well, 1 sick, 2 very sick
      sickSince: Number(c.sickSince) || null,
      doses: c.doses | 0,                           // doses given in this illness
      lastDoseAt: Number(c.lastDoseAt) || null,
      stats: { meals: stats.meals | 0, drinks: stats.drinks | 0, meds: stats.meds | 0 },
      streak: c.streak | 0,                         // good days in a row (see TITLES)
      best: Math.max(c.best | 0, c.streak | 0),
      goodDay: /^\d{4}-\d\d-\d\d$/.test(c.goodDay || '') ? c.goodDay : ''
    };
  }

  function localDay(t) {
    var d = new Date(t);
    return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
  }
  /** Good days in a row as of today: a run stops counting once a whole day was missed. */
  function streakNow(care) {
    if (!care || !care.goodDay) { return 0; }
    var now = Date.now();
    return care.goodDay === localDay(now) || care.goodDay === localDay(now - 86400000) ? care.streak : 0;
  }
  function titleFor(days) {
    for (var i = 0; i < TITLES.length; i++) {
      if (days >= TITLES[i].min) { return TITLES[i]; }
    }
    return null;
  }
  /** Count today as a good day if the cat is doing well; returns the title just reached, if any. */
  function careStreak(care, now) {
    if (care.health < 40 || care.sick === 2) {
      care.streak = 0;
      return null;
    }
    var today = localDay(now);
    if (care.goodDay === today || care.health < 80 || care.food < CARE.low || care.water < CARE.low || care.sick) {
      return null;
    }
    care.streak = care.goodDay === localDay(now - 86400000) ? care.streak + 1 : 1;
    care.goodDay = today;
    care.best = Math.max(care.best, care.streak);
    var t = titleFor(care.streak);
    return t && t.min === care.streak && t.min >= 3 ? t : null;
  }

  /** Repeatable 0-1 number for one cat in one hour, so a reload doesn't re-roll whether it fell ill. */
  function hourRoll(id, hourIndex) {
    var hsh = 2166136261;
    var key = id + ':' + hourIndex;
    for (var i = 0; i < key.length; i++) {
      hsh ^= key.charCodeAt(i);
      hsh = Math.imul(hsh, 16777619);
    }
    return ((hsh >>> 0) % 100000) / 100000;
  }

  /** Move a cat's needs forward to `now` (real time, so hours away count too), one clock hour at a time:
   *  at each full hour it may fall ill (more likely when hungry or thirsty), and an untreated illness gets worse. */
  function careAdvance(cfg, now, live) {
    var care = cfg.care;
    if (care.diedAt) {
      return;
    }
    var rate = (CARE.levels[care.level] || CARE.levels.normal).rate;
    var t = care.lastTick;
    var guard = 24 * 60; // at most 60 days are simulated after a long absence
    while (t < now && guard-- > 0) {
      var next = Math.min(now, (Math.floor(t / HOUR) + 1) * HOUR);
      var hours = (next - t) / HOUR;
      var dh = 0;
      if (care.food === 0 || care.water === 0) { dh -= CARE.healthEmpty; }
      if (care.sick === 1) { dh -= CARE.healthSick; }
      if (care.sick === 2) { dh -= CARE.healthVerySick; }
      if (!dh && care.food > 50 && care.water > 50) { dh = CARE.healthGain; }
      care.health = Math.max(0, Math.min(100, care.health + (dh < 0 ? dh * rate : dh) * hours));
      care.food = Math.max(0, care.food - hours * CARE.foodPerHour * rate);
      care.water = Math.max(0, care.water - hours * CARE.waterPerHour * rate);
      t = next;
      if (care.health === 0) {
        if (live) {
          care.health = 1;   // never let it go while the visitor is looking at the page
        } else {
          care.diedAt = t;
          break;
        }
      }
      if (t % HOUR === 0) {
        if (!care.sick) {
          var chance = care.food === 0 && care.water === 0 ? CARE.sickChanceEmpty
            : care.food < 20 || care.water < 20 ? CARE.sickChanceLow : CARE.sickChance;
          if (hourRoll(cfg.id, t / HOUR) < chance) {
            care.sick = 1;
            care.sickSince = t;
            care.doses = 0;
          }
        } else if (care.sick === 1 && t - care.sickSince >= CARE.worseAfter) {
          care.sick = 2;
        }
      }
    }
    if (t < now && !care.diedAt) {  // guard ran out: just bring the numbers up to date
      care.food = Math.max(0, care.food - (now - t) / HOUR * CARE.foodPerHour * rate);
      care.water = Math.max(0, care.water - (now - t) / HOUR * CARE.waterPerHour * rate);
    }
    care.lastTick = now;
  }
  /** What the cat wants most right now, or null. */
  function careNeed(care) {
    if (!care) { return null; }
    if (care.sick) { return 'sick'; }
    if (care.water < CARE.low && care.water <= care.food) { return 'drink'; }
    if (care.food < CARE.low) { return 'feed'; }
    if (care.water < CARE.low) { return 'drink'; }
    return null;
  }

  function load() {
    var data = {};
    try {
      data = JSON.parse(localStorage.getItem(STORE_KEY)) || {};
    } catch (e) { /* ignore */ }
    var cats = Array.isArray(data.cats) ? data.cats.slice(0, MAX).map(clean) : [];
    if (!cats.length) {
      var count = window.innerWidth < 760 ? 2 : 3;
      var keys = Object.keys(PRESETS).sort(function () { return Math.random() - 0.5; });
      for (var i = 0; i < count; i++) {
        cats.push(newCatConfig(keys[i], cats.map(function (c) { return c.name; })));
      }
    }
    var memorial = Array.isArray(data.memorial) ? data.memorial.slice(0, CARE.memorialMax).map(function (m) {
      return { cat: clean(m.cat || {}), diedAt: Number(m.diedAt) || Date.now(), adoptedAt: Number(m.adoptedAt) || Date.now() };
    }) : [];
    return { on: data.on !== false, cats: cats, memorial: memorial, lastDealCard: Number(data.lastDealCard) || 0 };
  }

  var store = load();
  store.cats.forEach(function (cfg) { if (cfg.care) { careAdvance(cfg, Date.now()); } });
  var pets = {}; // id -> live pet

  function save() {
    store.cats = store.cats.map(function (cfg) {
      var live = pets[cfg.id];
      if (live) {
        cfg.x = live.x / window.innerWidth;
        cfg.y = live.y / window.innerHeight;
      }
      return cfg;
    });
    try {
      localStorage.setItem(STORE_KEY, JSON.stringify({ v: 5, on: store.on, cats: store.cats, memorial: store.memorial, lastDealCard: store.lastDealCard }));
    } catch (e) { /* private mode etc. — cats just won't be remembered */ }
  }

  // ── Frames ─────────────────────────────────
  var inlined = false;
  function href(frame) {
    return (inlined ? '' : SPRITES) + '#' + frame;
  }

  // Fetch the sprite file once and put it in the page, then point every <use> at the local copy.
  function inlineSprites() {
  fetch(SPRITES).then(function (r) { return r.ok ? r.text() : Promise.reject(r.status); }).then(function (text) {
    var holder = document.createElement('div');
    holder.setAttribute('aria-hidden', 'true');
    holder.style.cssText = 'position:absolute;width:0;height:0;overflow:hidden';
    holder.innerHTML = text; // our own same-origin asset
    document.body.insertBefore(holder, document.body.firstChild);
    inlined = true;
    [].forEach.call(document.querySelectorAll('use[data-frame]'), function (u) {
      u.setAttribute('href', href(u.getAttribute('data-frame')));
    });
  }).catch(function () { /* keep using the external file */ });
  }

  function useEl(frame) {
    var use = document.createElementNS(SVG_NS, 'use');
    use.setAttribute('data-frame', frame);
    use.setAttribute('href', href(frame));
    // Explicit size: otherwise the symbol is scaled to the outer viewBox, which breaks cropped previews.
    use.setAttribute('width', '320');
    use.setAttribute('height', '150');
    return use;
  }

  /** A static picture of one frame. viewBox lets previews crop to the sitting cat. */
  function frameSvg(frame, viewBox) {
    var svg = document.createElementNS(SVG_NS, 'svg');
    svg.setAttribute('viewBox', viewBox || '0 0 320 150');
    svg.setAttribute('aria-hidden', 'true');
    svg.appendChild(useEl(frame));
    return svg;
  }

  // ── Pets on the page ───────────────────────
  var looping = false;

  function area(pet) {
    return { minX: -pet.w * 0.15, maxX: window.innerWidth - pet.w * 0.85, minY: 0, maxY: window.innerHeight - pet.h };
  }
  function randomSpot(pet) {
    var a = area(pet);
    return [rand(a.minX, a.maxX), rand(a.minY, a.maxY)];
  }

  // The left and right edges are open, like a loop: a cat that runs past one comes back in from the other.
  function wrapLimits(pet) {
    return { left: -pet.w * 1.2, right: window.innerWidth + pet.w * 0.2 };
  }
  function wrapSpan(pet) {
    var l = wrapLimits(pet);
    return l.right - l.left;
  }
  /** Teleport to the other side once fully past an edge. Returns true if it wrapped. */
  function wrapIfOut(pet) {
    var l = wrapLimits(pet);
    if (pet.x < l.left || pet.x > l.right) {
      pet.x += pet.x < l.left ? wrapSpan(pet) : -wrapSpan(pet);
      place(pet);
      return true;
    }
    return false;
  }
  function onScreen(pet) {
    return pet.x > -pet.w * 0.6 && pet.x < window.innerWidth - pet.w * 0.4;
  }
  /** Signed horizontal distance from a to b the short way round (possibly through an edge). */
  function loopDx(a, b) {
    var d = b.x - a.x;
    var span = wrapSpan(a);
    if (d > span / 2) { d -= span; }
    if (d < -span / 2) { d += span; }
    return d;
  }
  /** A point beyond the left or right edge, mostly ahead of the way the cat faces (to run through the screen). */
  function exitSpot(pet) {
    var right = Math.random() < 0.75 ? pet.face > 0 : pet.face < 0;
    var l = wrapLimits(pet);
    var a = area(pet);
    return [right ? l.right + 80 : l.left - 80, rand(a.minY, a.maxY)];
  }

  function spawn(cfg) {
    var el = document.createElement('div');
    el.className = 'pet-cat';
    el.setAttribute('aria-hidden', 'true');
    el.style.width = cfg.size + 'px';
    var svg = document.createElementNS(SVG_NS, 'svg');
    svg.setAttribute('viewBox', '0 0 320 150');
    el.appendChild(svg);
    applyPaint(el, cfg);
    el.title = cfg.name + ' — กดเพื่อสั่ง';
    document.body.appendChild(el);

    var pet = { cfg: cfg, el: el, svg: svg, uses: {}, frame: null, stride: 0, frameIdx: 0,
      w: cfg.size, h: cfg.size * BOX_RATIO, face: 1 };
    var a = area(pet);
    if (typeof cfg.x === 'number' && typeof cfg.y === 'number' && isFinite(cfg.x) && isFinite(cfg.y)) {
      pet.x = Math.max(a.minX, Math.min(a.maxX, cfg.x * window.innerWidth));
      pet.y = Math.max(a.minY, Math.min(a.maxY, cfg.y * window.innerHeight));
    } else {
      var spot = randomSpot(pet);
      pet.x = spot[0];
      pet.y = spot[1];
    }
    // Stack the frames used most up front so the first steps are already there.
    ['cs1', 'cw1', 'cw2', 'cw3', 'cr1', 'cr2', 'cr3'].forEach(function (f) { addFrame(pet, f); });
    setFrame(pet, 'cs1');
    setFace(pet, Math.random() < 0.5 ? 1 : -1);
    pets[cfg.id] = pet;
    pet.need = document.createElement('span');
    pet.need.className = 'pet-need';
    pet.need.hidden = true;
    el.appendChild(pet.need);
    pet.crown = h('span', { class: 'pet-crown', hidden: true }, [iconSvg('crown')]);
    el.appendChild(pet.crown);
    el.addEventListener('click', function (e) {
      e.stopPropagation();
      openCatRadial(pet);
    });
    act(pet, 'sit', performance.now(), rand(800, 3000));
    place(pet);
    return pet;
  }

  function addFrame(pet, frame) {
    var use = useEl(frame);
    // The sitting set (cs*) is drawn about 1.3x bigger than the walk/run/silhouette sets: shrink it on the
    // ground line so the cat keeps the same size in every pose. Static previews keep the full-size drawing.
    if (frame.indexOf('cs') === 0) {
      use.setAttribute('transform', 'translate(160 146) scale(' + SIT_SCALE + ') translate(-160 -146)');
    }
    use.style.display = 'none';
    pet.svg.appendChild(use);
    pet.uses[frame] = use;
    return use;
  }

  function setFrame(pet, frame) {
    if (pet.frame === frame) {
      return;
    }
    var next = pet.uses[frame] || addFrame(pet, frame);
    next.style.display = '';
    if (pet.frame && pet.uses[pet.frame]) {
      pet.uses[pet.frame].style.display = 'none';
    }
    pet.frame = frame;
  }

  function despawn(id) {
    if (pets[id] && radialFor === pets[id]) {
      closeRadial();
    }
    if (pets[id]) {
      pets[id].el.remove();
      delete pets[id];
    }
  }

  function refreshPet(cfg) {
    var pet = pets[cfg.id];
    if (!pet) {
      return;
    }
    pet.cfg = cfg;
    pet.el.style.width = cfg.size + 'px';
    pet.w = cfg.size;
    pet.h = cfg.size * BOX_RATIO;
    pet.el.title = cfg.name + ' — กดเพื่อสั่ง';
    applyPaint(pet.el, cfg);
  }

  function place(pet) {
    pet.el.style.transform = 'translate3d(' + pet.x.toFixed(1) + 'px,' + pet.y.toFixed(1) + 'px,0)';
  }

  function setFace(pet, dx) {
    if (Math.abs(dx) < 0.5) {
      return;
    }
    var face = dx > 0 ? 1 : -1; // walk/run frames face right
    if (face !== pet.face) {
      pet.face = face;
      pet.el.style.setProperty('--sx', face);
    }
  }

  // ── Behaviour ──────────────────────────────
  // stride: px travelled per frame (at size 120) — legs keep pace with the ground, so feet don't slide.
  var GAITS = {
    walk: { frames: ['cw1', 'cw2', 'cw3'], stride: 15, speed: 1.2 },
    trot: { frames: ['cw1', 'cw2', 'cw3'], stride: 20, speed: 2.5 },
    run: { frames: ['cr1', 'cr2', 'cr3'], stride: 42, speed: 8 },
    stalk: { frames: ['sl5', 'sl7'], stride: 24, speed: 0.9 } // low, sneaky steps
  };
  var SEQUENCES = {
    yawn: [['cs1', 300], ['cs2', 450], ['cs3', 1000], ['cs2', 350], ['cs4', 600]],
    doze: [['cs1', 500], ['cs6', 700], ['cs7', 900]],
    dozeLoop: [['cs8', 1400], ['cs7', 1200]],
    wake: [['cs7', 400], ['cs6', 500], ['sl2', 1500], ['sl4', 500], ['cs1', 300]], // stretch on waking up
    stretch: [['cs6', 400], ['sl2', 1700], ['sl4', 600], ['cs1', 300]],
    curious: [['sl4', 700], ['sl3', 1800], ['sl4', 500], ['sl3', 1200], ['cs1', 300]],    // stands up, looks up at something
    look: [['cs5', 1400], ['cs1', 200], ['cs4', 900]],
    eat: [['sl8', 3200]],                    // crouched, nose in the bowl (the bob comes from .is-eating)
    sick: [['cs6', 2200], ['cs7', 1800]]     // low, droopy, eyes half shut
  };
  var WEIGHTS = {
    normal: { walk: 20, trot: 7, sit: 14, yawn: 5, sleep: 9, zoom: 7, play: 8, dash: 20, hunt: 9, stretch: 3, curious: 6 },
    sleepy: { walk: 16, trot: 3, sit: 20, yawn: 13, sleep: 28, zoom: 3, play: 4, dash: 7, hunt: 2, stretch: 7, curious: 3 },
    playful: { walk: 12, trot: 10, sit: 5, yawn: 2, sleep: 4, zoom: 12, play: 16, dash: 26, hunt: 16, stretch: 2, curious: 5 }
  };

  function pickWeighted(weights) {
    var total = 0;
    var key;
    for (key in weights) { total += weights[key]; }
    var r = Math.random() * total;
    for (key in weights) {
      r -= weights[key];
      if (r <= 0) { return key; }
    }
    return key;
  }

  /** Start doing something. opts: target [x,y], through (target is past an edge), partner, legs (zoomies dashes),
   *  seq (frame sequence), loop. */
  function act(pet, state, now, duration, opts) {
    opts = opts || {};
    pet.state = state;
    pet.until = now + (duration || 0);
    pet.target = opts.target || null;
    pet.partner = opts.partner || null;
    pet.legs = opts.legs || 0;
    pet.through = !!opts.through;
    pet.dashing = !!opts.dash;
    pet.seq = opts.seq || null;
    pet.seqLoop = opts.loop || null;
    pet.seqStart = now;
    pet.el.classList.remove('is-wiggling');
    if (state === 'sit' || state === 'yawn' || state === 'sleep') {
      setFrame(pet, pet.seq ? pet.seq[0][0] : 'cs1');
    }
  }

  function busy(pet) {
    return pet.state === 'chase' || pet.state === 'flee' || pet.state === 'sleep' || pet.state === 'come' || pet.state === 'hunt' ||
      pet.state === 'held' || pet.dashing || pet.through || !onScreen(pet);
  }

  function others(pet) {
    return Object.keys(pets).map(function (k) { return pets[k]; }).filter(function (p) { return p !== pet; });
  }

  function decide(pet, now) {
    var prev = pet.state;
    if (prev === 'sleep') {
      act(pet, 'sit', now, 1200, { seq: SEQUENCES.wake });
      return;
    }
    if (prev === 'chase' || prev === 'flee') {
      act(pet, 'sit', now, rand(1500, 3000));
      if (pet.partner) {
        setFace(pet, pet.partner.x - pet.x);
      }
      return;
    }
    if (pet.cfg.care && pet.cfg.care.sick) {
      // A sick cat has no energy: it lies low and dozes, now and then shuffles a few steps.
      if (Math.random() < 0.15) {
        act(pet, 'walk', now, 8000, { target: [pet.x + rand(-120, 120), pet.y + rand(-30, 30)] });
      } else {
        act(pet, 'yawn', now, rand(8000, 15000), { seq: SEQUENCES.sick, loop: SEQUENCES.sick });
      }
      return;
    }
    var free = others(pet).filter(function (p) { return !busy(p); });
    var w = Object.assign({}, WEIGHTS[pet.cfg.mood] || WEIGHTS.normal);
    if (!free.length) { w.play = 0; }
    if (prev === 'yawn') { w.yawn = 0; }
    command(pet, pickWeighted(w), now, free);
  }

  /** Make one cat do something (used by the random brain and by the command buttons). */
  function command(pet, what, now, playmates) {
    var sleepy = pet.cfg.mood === 'sleepy' ? 1.5 : 1;
    if (pet.cfg.care && pet.cfg.care.sick && what !== 'come' && what !== 'sleep') {
      // Too unwell for running, hunting or playing: it stays down whatever it is asked.
      act(pet, 'yawn', now, rand(8000, 15000), { seq: SEQUENCES.sick, loop: SEQUENCES.sick });
      return;
    }
    if (what === 'walk' || what === 'trot') {
      var stroll = Math.random() < 0.2; // sometimes wander off one side and back in the other
      act(pet, what, now, 25000, { target: stroll ? exitSpot(pet) : randomSpot(pet), through: stroll });
    } else if (what === 'zoom') {
      var out = Math.random() < 0.4;
      act(pet, 'zoom', now, 15000, { target: out ? exitSpot(pet) : randomSpot(pet), through: out, legs: Math.round(rand(3, 5)) });
    } else if (what === 'dash') {
      // Run straight across the screen and through the edge, 2-4 times in a row.
      act(pet, 'zoom', now, 30000, { target: exitSpot(pet), through: true, dash: true, legs: Math.round(rand(2, 4)) });
    } else if (what === 'play') {
      var mates = playmates || others(pet);
      if (!mates.length) {
        command(pet, 'zoom', now);
        return;
      }
      var mate = pick(mates);
      var length = rand(3000, 6000);
      act(pet, 'chase', now, length, { partner: mate });
      act(mate, 'flee', now, length + 200, { partner: pet });
    } else if (what === 'sleep') {
      act(pet, 'sleep', now, rand(20000, 60000) * sleepy, { seq: SEQUENCES.doze, loop: SEQUENCES.dozeLoop });
    } else if (what === 'yawn') {
      act(pet, 'yawn', now, 2800, { seq: SEQUENCES.yawn });
    } else if (what === 'stretch') {
      act(pet, 'yawn', now, 3000, { seq: SEQUENCES.stretch });
    } else if (what === 'curious') {
      act(pet, 'yawn', now, 4500, { seq: SEQUENCES.curious });
    } else if (what === 'hunt') {
      startHunt(pet, now);
    } else if (what === 'come') {
      var x = window.innerWidth / 2 - pet.w / 2 + rand(-pet.w, pet.w);
      var y = window.innerHeight / 2 - pet.h / 2 + rand(-30, 30);
      act(pet, 'come', now, 15000, { target: [x, y] });
    } else if (what === 'stay') {
      act(pet, 'sit', now, 15000, { seq: SEQUENCES.look, loop: SEQUENCES.look });
    } else {
      act(pet, 'sit', now, rand(3000, 8000) * sleepy, Math.random() < 0.5 ? { seq: SEQUENCES.look } : null);
    }
  }

  /** Advance the leg cycle by distance travelled. */
  function stepLegs(pet, gait, moved) {
    var g = GAITS[gait];
    var strideLen = g.stride * pet.w / 120;
    if (g.frames.indexOf(pet.frame) < 0) {
      pet.frameIdx = 0;
      pet.stride = 0;
    } else {
      pet.stride += moved;
      while (pet.stride >= strideLen) {
        pet.stride -= strideLen;
        pet.frameIdx = (pet.frameIdx + 1) % g.frames.length;
      }
    }
    setFrame(pet, g.frames[pet.frameIdx]);
  }

  function playSequence(pet, now) {
    if (!pet.seq) {
      setFrame(pet, 'cs1');
      return;
    }
    var t = now - pet.seqStart;
    var total = 0;
    for (var i = 0; i < pet.seq.length; i++) {
      total += pet.seq[i][1];
      if (t < total) {
        setFrame(pet, pet.seq[i][0]);
        return;
      }
    }
    if (pet.seqLoop) {           // switch to the looping part (e.g. snot bubble in and out)
      pet.seq = pet.seqLoop;
      pet.seqStart = now;
      setFrame(pet, pet.seq[0][0]);
    } else {
      setFrame(pet, pet.seq[pet.seq.length - 1][0]);
    }
  }

  // ── Hunting: sneak up, crouch and wiggle, pounce in an arc, then sniff the catch ──
  function startHunt(pet, now) {
    var a = area(pet);
    var dir = Math.random() < 0.75 ? pet.face : -pet.face;
    var dist = rand(220, 380) * (pet.w / 120);
    var prey = [Math.max(a.minX, Math.min(a.maxX, pet.x + dir * dist)), Math.max(a.minY, Math.min(a.maxY, pet.y + rand(-40, 40)))];
    if (Math.abs(prey[0] - pet.x) < pet.w * 0.8) {          // hemmed in by an edge: hunt the other way
      prey[0] = Math.max(a.minX, Math.min(a.maxX, pet.x - dir * dist));
    }
    var side = prey[0] >= pet.x ? 1 : -1;
    act(pet, 'hunt', now, 20000);
    pet.prey = prey;
    pet.phase = 'stalk';
    pet.target = [prey[0] - side * pet.w * 0.9, prey[1]];   // where it crouches before the pounce
  }

  function stepHunt(pet, now, dt) {
    if (pet.phase === 'stalk') {
      var moved = moveToward(pet, pet.target[0], pet.target[1], GAITS.stalk.speed, dt);
      stepLegs(pet, 'stalk', moved);
      if (!moved || now > pet.until) {
        pet.phase = 'crouch';
        pet.phaseUntil = now + rand(900, 1600);
        setFace(pet, pet.prey[0] - pet.x);
        setFrame(pet, 'sl6');
        pet.el.classList.add('is-wiggling');           // the butt wiggle before a pounce
      }
    } else if (pet.phase === 'crouch') {
      if (now > pet.phaseUntil) {
        pet.el.classList.remove('is-wiggling');
        pet.phase = 'leap';
        pet.leapFrom = [pet.x, pet.y];
        pet.leapStart = now;
        setFrame(pet, 'sl1');
      }
    } else if (pet.phase === 'leap') {
      var t = Math.min(1, (now - pet.leapStart) / 480);
      pet.x = pet.leapFrom[0] + (pet.prey[0] - pet.leapFrom[0]) * t;
      pet.y = pet.leapFrom[1] + (pet.prey[1] - pet.leapFrom[1]) * t;
      var lift = Math.sin(Math.PI * t) * pet.h * 0.9;  // jump arc
      pet.el.style.transform = 'translate3d(' + pet.x.toFixed(1) + 'px,' + (pet.y - lift).toFixed(1) + 'px,0)';
      if (t >= 1) {
        place(pet);
        pet.phase = 'sniff';
        pet.phaseUntil = now + rand(1500, 2500);
        setFrame(pet, 'sl8');
      }
    } else if (now > pet.phaseUntil) {
      act(pet, 'sit', now, rand(2000, 4000), { seq: SEQUENCES.look }); // proud of the catch
    }
  }

  /** Move toward a point; returns the distance moved this tick (0 = already there). */
  function moveToward(pet, tx, ty, speed, dt, offscreenOk) {
    var a = area(pet);
    if (!offscreenOk) {
      tx = Math.max(a.minX, Math.min(a.maxX, tx));
    }
    ty = Math.max(a.minY, Math.min(a.maxY, ty));
    var dx = tx - pet.x;
    var dy = ty - pet.y;
    var dist = Math.sqrt(dx * dx + dy * dy);
    if (dist < 2) {
      return 0;
    }
    var stepLen = Math.min(speed * dt, dist);
    pet.x += (dx / dist) * stepLen;
    pet.y += (dy / dist) * stepLen;
    setFace(pet, dx);
    place(pet);
    return stepLen;
  }

  function step(pet, now, dt) {
    var s = pet.state;
    var moved;
    if (s === 'gone') {          // going to the cat star: the CSS animation does the rest
      return;
    }
    if (s === 'held') {          // its command circle is open: sit still and look around
      playSequence(pet, now);
      return;
    }
    if (s === 'hunt') {
      stepHunt(pet, now, dt);
      return;
    }
    if (s === 'walk' || s === 'trot' || s === 'zoom' || s === 'come') {
      var gait = s === 'walk' ? 'walk' : s === 'trot' ? 'trot' : 'run';
      moved = moveToward(pet, pet.target[0], pet.target[1], GAITS[gait].speed, dt, pet.through);
      stepLegs(pet, gait, moved);
      if (pet.through && wrapIfOut(pet)) {
        if (pet.dashing && --pet.legs > 0) {
          // Keep going the same way, all the way across and out again.
          var l = wrapLimits(pet);
          pet.target = [pet.face > 0 ? l.right + 80 : l.left - 80, randomSpot(pet)[1]];
          return;
        }
        // Came out of the other side: carry on into the screen.
        pet.through = false;
        pet.dashing = false;
        pet.target = randomSpot(pet);
        return;
      }
      if (!moved && s === 'zoom' && --pet.legs > 0) {
        pet.through = Math.random() < 0.45; // zoomies: dash off again, often straight through an edge
        pet.target = pet.through ? exitSpot(pet) : randomSpot(pet);
        return;
      }
      if (!onScreen(pet) && !pet.through) {
        pet.target = randomSpot(pet); // never stop out of sight
        return;
      }
      if (!moved || (now > pet.until && onScreen(pet))) {
        act(pet, 'sit', now, s === 'come' ? rand(6000, 10000) : rand(1500, 5000), s === 'come' ? { seq: SEQUENCES.look } : null);
      }
      return;
    }
    if (s === 'chase' && pet.partner) {
      // Chase the short way round, even if that means through an edge.
      var gap = loopDx(pet, pet.partner);
      moved = moveToward(pet, pet.x + gap - pet.partner.face * pet.w * 0.55, pet.partner.y, 7, dt, true);
      stepLegs(pet, 'run', moved);
      wrapIfOut(pet);
    } else if (s === 'flee' && pet.partner) {
      var a = area(pet);
      var awayX = loopDx(pet, pet.partner) > 0 ? -1 : 1;
      var awayY = pet.y >= pet.partner.y ? 1 : -1;
      if ((awayY > 0 && pet.y >= a.maxY - 2) || (awayY < 0 && pet.y <= a.minY + 2)) {
        awayY = -awayY;
      }
      moved = moveToward(pet, pet.x + awayX * 120, pet.y + awayY * 60, 7.4, dt, true); // straight through the edge if need be
      stepLegs(pet, 'run', moved);
      wrapIfOut(pet);
    } else {
      playSequence(pet, now);
    }
    if (now > pet.until) {
      if (!onScreen(pet)) {
        act(pet, 'trot', now, 20000, { target: randomSpot(pet) }); // finish off-screen? come back in first
        return;
      }
      decide(pet, now);
    }
  }

  var last = 0;
  var lastRaf = 0;
  function tick() {
    // Always read the clock here: rAF timestamps can be older than the backup clock's last tick.
    var now = performance.now();
    var dt = last ? Math.max(0, Math.min(48, now - last)) / 16.7 : 1; // frames' worth of time, capped after tab switches
    last = now;
    for (var id in pets) {
      step(pets[id], now, dt);
    }
  }
  function loop() {
    if (!store.on || !Object.keys(pets).length) {
      looping = false;
      return;
    }
    lastRaf = performance.now();
    tick();
    requestAnimationFrame(loop);
  }
  function start() {
    if (!looping) {
      looping = true;
      last = 0;
      requestAnimationFrame(loop);
    }
  }
  // Backup clock: some in-app browsers and power-saving modes stall requestAnimationFrame on visible pages.
  // When frames stop arriving, drive the cats at ~30 fps with timers instead (still paused in background tabs).
  setInterval(function () {
    if (store.on && Object.keys(pets).length && !document.hidden && performance.now() - lastRaf > 120) {
      tick();
    }
  }, 33);

  function showAll() {
    store.cats.forEach(function (cfg) {
      if (!pets[cfg.id]) {
        spawn(cfg);
      }
    });
    start();
    if (typeof careUpdate === 'function' && fabBtn) {
      careUpdate();
    }
  }
  function hideAll() {
    save();
    Object.keys(pets).forEach(despawn);
  }

  window.addEventListener('resize', function () {
    for (var id in pets) {
      var pet = pets[id];
      var a = area(pet);
      pet.x = Math.max(a.minX, Math.min(a.maxX, pet.x));
      pet.y = Math.max(a.minY, Math.min(a.maxY, pet.y));
      place(pet);
    }
  });
  window.addEventListener('pagehide', save);
  setInterval(function () { if (store.on) { save(); } }, 5000);

  function giveCommand(ids, what) {
    if (!store.on) {
      store.on = true;
      showAll();
    }
    var now = performance.now();
    ids.forEach(function (id) {
      if (pets[id]) {
        command(pets[id], what, now);
      }
    });
    start();
    save();
  }

  // ── Care: adopt, feed, give water ──────────
  function adopted() {
    return store.cats.filter(function (c) { return c.care; });
  }

  function careUpdate() {
    var now = Date.now();
    var anyNeed = false;
    store.cats.forEach(function (cfg) {
      if (!cfg.care || cfg.care.diedAt) { return; }
      careAdvance(cfg, now, true);
      var reached = careStreak(cfg.care, now);
      if (reached) {
        setTimeout(function () { celebrate(cfg, reached); }, 1500);
      }
      var need = careNeed(cfg.care);
      anyNeed = anyNeed || !!need;
      var pet = pets[cfg.id];
      if (pet) {
        pet.el.classList.toggle('is-sick', !!cfg.care.sick);
        pet.el.classList.toggle('is-very-sick', cfg.care.sick === 2);
        showLook(pet);
        pet.need.hidden = !need;
        if (need) {
          pet.need.textContent = '';
          pet.need.appendChild(iconSvg(need));
          pet.need.className = 'pet-need pet-need-' + need;
        }
      }
    });
    fabBtn.classList.toggle('has-need', anyNeed);
  }

  /** The reward look of a well-kept cat: hearts from 3 good days, a sparkle from 7, a crown from 14 (gold at 30). */
  function showLook(pet) {
    var t = pet.cfg.care && !pet.cfg.care.diedAt ? titleFor(streakNow(pet.cfg.care)) : null;
    var stars = t ? t.stars : 0;
    pet.el.classList.toggle('look-happy', stars >= 2);
    pet.el.classList.toggle('look-shiny', stars >= 3);
    pet.el.classList.toggle('look-legend', stars >= 5);
    pet.crown.hidden = stars < 4 || !pet.need.hidden;
  }

  /** A new title: a card with the share button, so the moment is easy to show friends. */
  function celebrate(cfg, title) {
    if (!careCard.hidden) { return; }
    careCard.textContent = '';
    careCard.classList.add('is-celebrate');
    careCard.appendChild(h('h3', {}, [iconSvg('crown'), document.createTextNode(cfg.name + 'ได้ฉายาใหม่!')]));
    careCard.appendChild(h('p', { text: 'ดูแลดีติดต่อกัน ' + title.min + ' วัน ได้เป็น "' + title.label + '" แล้ว ' + '★'.repeat(title.stars)
      + ' อวดเพื่อนใน LINE หรือ Facebook ได้เลย' }));
    careCard.appendChild(h('div', { class: 'pet-care-actions' }, [
      h('button', { type: 'button', class: 'btn btn-deal', text: 'แชร์การ์ดแมว', onclick: function () { closeCareCard(); shareCard(cfg); } }),
      h('button', { type: 'button', class: 'btn btn-ghost', text: 'ไว้ก่อน', onclick: closeCareCard })
    ]));
    careCard.hidden = false;
    save();
  }

  // ── Share card: a picture of the cat with its title, for LINE / Facebook ──
  var CARD_W = 1080, CARD_H = 1350;  // 4:5, shows large in both feeds

  function gameUrl() {
    var nav = document.querySelector('.main-nav a[href$="/game"]');
    return nav ? nav.href : location.origin + '/game';
  }

  /** The sitting cat in its own colours as an image (the sprite symbol drawn into a standalone SVG). */
  function catImage(cfg) {
    var sym = document.getElementById('cs1');
    if (!sym) { return Promise.reject(new Error('sprite not loaded')); }
    var vars = paint(cfg);
    var style = Object.keys(vars).map(function (k) { return k + ':' + vars[k]; }).join(';');
    var svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="88 6 164 144" width="820" height="720" style="' + style + '">'
      + sym.innerHTML + '</svg>';
    return new Promise(function (resolve, reject) {
      var img = new Image();
      img.onload = function () { resolve(img); };
      img.onerror = reject;
      img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
    });
  }

  function roundRect(ctx, x, y, w, hgt, r) {
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + hgt, r);
    ctx.arcTo(x + w, y + hgt, x, y + hgt, r);
    ctx.arcTo(x, y + hgt, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
  }

  /** Draw the card and resolve with a PNG blob. */
  function drawCard(cfg) {
    var care = cfg.care;
    var days = streakNow(care);
    var title = titleFor(days) || { label: 'ลูกรักคนใหม่', stars: 0 };
    var together = daysBetween(care.adoptedAt, Date.now());
    var fonts = document.fonts ? Promise.all(['700 80px Mali', '600 40px Mali', '600 40px "IBM Plex Sans Thai Looped"']
      .map(function (f) { return document.fonts.load(f, 'แมวก'); })).catch(function () {}) : Promise.resolve();
    return Promise.all([catImage(cfg), fonts]).then(function (res) {
      var img = res[0];
      var cv = document.createElement('canvas');
      cv.width = CARD_W;
      cv.height = CARD_H;
      var c = cv.getContext('2d');
      var head = 'Mali, "IBM Plex Sans Thai Looped", sans-serif';
      var body = '"IBM Plex Sans Thai Looped", Mali, sans-serif';
      // Cream page with soft paw dots, like the site.
      c.fillStyle = '#fff8ef';
      c.fillRect(0, 0, CARD_W, CARD_H);
      c.fillStyle = '#fbecd9';
      for (var y = 30; y < CARD_H; y += 90) {
        for (var x = (y / 90) % 2 ? 75 : 30; x < CARD_W; x += 90) {
          c.beginPath(); c.arc(x, y, 9, 0, Math.PI * 2); c.fill();
        }
      }
      c.textAlign = 'center';
      c.fillStyle = '#b04a15';
      c.font = '700 44px ' + head;
      c.fillText('คัดมาเหมียว 🐾 เกมเลี้ยงแมว', CARD_W / 2, 92);
      // White card with the cat.
      roundRect(c, 70, 140, CARD_W - 140, 1060, 56);
      c.fillStyle = '#ffffff';
      c.shadowColor = 'rgba(59,42,36,.15)';
      c.shadowBlur = 40;
      c.shadowOffsetY = 16;
      c.fill();
      c.shadowColor = 'transparent';
      if (title.stars >= 3) {   // a warm glow behind well-kept cats
        var g = c.createRadialGradient(CARD_W / 2, 520, 60, CARD_W / 2, 520, 420);
        g.addColorStop(0, title.stars >= 5 ? 'rgba(255,209,102,.55)' : 'rgba(249,193,204,.55)');
        g.addColorStop(1, 'rgba(255,255,255,0)');
        c.fillStyle = g;
        c.fillRect(110, 170, CARD_W - 220, 700);
      }
      c.drawImage(img, (CARD_W - 760) / 2, 210, 760, 667);
      if (title.stars >= 4) {   // the crown, drawn from the same Lucide outline as the on-page badge
        c.save();
        c.translate(CARD_W / 2 - 70, 170);
        c.scale(6, 6);
        c.fillStyle = title.stars >= 5 ? '#f2b134' : '#ffd166';
        c.strokeStyle = '#b06a10';
        c.lineWidth = 1.2;
        var crown = new Path2D('M11.562 3.266a.5.5 0 0 1 .876 0L15.39 8.87a1 1 0 0 0 1.516.294L21.183 5.5a.5.5 0 0 1 .798.519l-2.834 10.246a1 1 0 0 1-.956.734H5.81a1 1 0 0 1-.957-.734L2.02 6.02a.5.5 0 0 1 .798-.519l4.276 3.664a1 1 0 0 0 1.516-.294z');
        c.fill(crown);
        c.stroke(crown);
        c.restore();
      }
      c.fillStyle = '#3b2a24';
      c.font = '700 96px ' + head;
      c.fillText(cfg.name, CARD_W / 2, 960);
      // Title pill and stars.
      c.font = '700 48px ' + head;
      var tw = c.measureText(title.label).width + 80;
      roundRect(c, (CARD_W - tw) / 2, 995, tw, 76, 38);
      c.fillStyle = '#b04a15';
      c.fill();
      c.fillStyle = '#ffffff';
      c.fillText(title.label, CARD_W / 2, 1050);
      c.font = '700 48px ' + body;
      c.fillStyle = '#f2b134';
      c.fillText('★★★★★'.slice(0, title.stars) + '☆☆☆☆☆'.slice(0, 5 - title.stars), CARD_W / 2, 1135);
      c.fillStyle = '#76594d';
      c.font = '600 36px ' + body;
      c.fillText('อยู่ด้วยกัน ' + together + ' วัน · ดูแลดีติดกัน ' + days + ' วัน · สุขภาพ ' + Math.round(care.health) + '%', CARD_W / 2, 1180);
      c.fillStyle = '#3b2a24';
      c.font = '600 40px ' + body;
      c.fillText('มาเลี้ยงแมวด้วยกัน เล่นฟรีบนเว็บ ไม่ต้องโหลดแอป', CARD_W / 2, 1265);
      c.fillStyle = '#b04a15';
      c.font = '700 40px ' + body;
      c.fillText(gameUrl().replace(/^https?:\/\//, ''), CARD_W / 2, 1318);
      return new Promise(function (resolve) { cv.toBlob(resolve, 'image/png'); });
    });
  }

  function shareText(cfg) {
    var t = titleFor(streakNow(cfg.care));
    return 'น้อง' + cfg.name.replace(/^น้อง/, '') + (t ? ' ได้ฉายา "' + t.label + '"' : '') + ' อยู่ด้วยกันมา '
      + daysBetween(cfg.care.adoptedAt, Date.now()) + ' วันแล้ว 🐱 มาเลี้ยงแมวด้วยกันนะ เล่นฟรีบนเว็บ ' + gameUrl();
  }

  /** Phones: the system share sheet with the picture (LINE, Facebook, …). Elsewhere: preview, save, share links. */
  function shareCard(cfg) {
    drawCard(cfg).then(function (blob) {
      var file = new File([blob], 'cat-' + cfg.name + '.png', { type: 'image/png' });
      if (navigator.canShare && navigator.canShare({ files: [file] })) {
        return navigator.share({ files: [file], text: shareText(cfg) }).catch(function () {});
      }
      var url = URL.createObjectURL(blob);
      careCard.textContent = '';
      careCard.classList.add('is-share');
      careCard.appendChild(h('h3', {}, [iconSvg('share'), document.createTextNode('การ์ดของ' + cfg.name)]));
      careCard.appendChild(h('img', { class: 'pet-share-img', src: url, alt: 'การ์ดแมว ' + cfg.name }));
      careCard.appendChild(h('p', { class: 'small', text: 'บันทึกรูปแล้วโพสต์ใน Facebook หรือส่งใน LINE ได้เลย' }));
      careCard.appendChild(h('div', { class: 'pet-care-actions' }, [
        h('a', { class: 'btn btn-deal', href: url, download: 'cat-' + cfg.name + '.png', text: 'บันทึกรูป' }),
        h('a', { class: 'btn btn-line', href: 'https://line.me/R/share?text=' + encodeURIComponent(shareText(cfg)),
          target: '_blank', rel: 'noopener', text: 'LINE' }),
        h('a', { class: 'btn btn-fb', href: 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(gameUrl()),
          target: '_blank', rel: 'noopener', text: 'Facebook' }),
        h('button', { type: 'button', class: 'btn btn-ghost', text: 'ปิด', onclick: function () { URL.revokeObjectURL(url); closeCareCard(); } })
      ]));
      careCard.hidden = false;
    }).catch(function () {
      window.open('https://line.me/R/share?text=' + encodeURIComponent(shareText(cfg)), '_blank', 'noopener');
    });
  }

  /** A short speech bubble over a cat ("อิ่มแล้วเหมียว"). */
  function say(pet, text) {
    var bubble = h('span', { class: 'pet-say', text: text });
    pet.el.appendChild(bubble);
    setTimeout(function () { bubble.remove(); }, 2200);
  }

  /** A bowl appears on the floor in front of the cat; it eats or drinks, then the bowl fades. */
  function serve(pet, what) {
    var care = pet.cfg.care;
    careAdvance(pet.cfg, Date.now(), true);
    var key = what === 'feed' ? 'food' : 'water';
    if (care[key] > CARE.full) {
      say(pet, what === 'feed' ? 'อิ่มแล้วเหมียว' : 'ยังไม่หิวน้ำเหมียว');
      return;
    }
    care[key] = Math.min(100, care[key] + (what === 'feed' ? CARE.feed : CARE.drink));
    care.stats[what === 'feed' ? 'meals' : 'drinks']++;
    if (what === 'feed') {
      setTimeout(function () { maybeSuggestDeals(care); }, 3600);
    }
    // The bowl sits just in front of the nose of the crouched pose (sl8: nose tip at about x 262 of 320, ground at y 146).
    var size = Math.max(24, pet.w * 0.17);
    var noseX = pet.face > 0 ? 280 / 320 : 40 / 320;
    var bowl = h('span', { class: 'pet-bowl' });
    bowl.innerHTML = BOWL_SVG[what]; // fixed markup defined below, never user input
    bowl.style.width = size + 'px';
    bowl.style.left = (pet.x + pet.w * noseX - size / 2) + 'px';
    bowl.style.top = (pet.y + pet.h * (146 / 150) - size * 0.62) + 'px';
    document.body.appendChild(bowl);
    act(pet, 'sit', performance.now(), 3200, { seq: SEQUENCES.eat });
    pet.el.classList.add(what === 'feed' ? 'is-eating' : 'is-drinking');
    setTimeout(function () {
      pet.el.classList.remove('is-eating', 'is-drinking');
      bowl.classList.add('is-done');
    }, 3100);
    setTimeout(function () { bowl.remove(); }, 3600);
    careUpdate();
    save();
  }

  /** Give a pill. Sick: one dose cures. Very sick: two doses, at least CARE.doseGap apart. */
  function giveMed(pet) {
    var care = pet.cfg.care;
    var now = Date.now();
    careAdvance(pet.cfg, now, true);
    if (!care.sick) {
      say(pet, 'ไม่ได้ป่วยแล้วเหมียว');
      return;
    }
    if (care.sick === 2 && care.doses > 0 && now - care.lastDoseAt < CARE.doseGap) {
      var wait = Math.ceil((CARE.doseGap - (now - care.lastDoseAt)) / HOUR);
      say(pet, 'อีก ' + wait + ' ชม. ค่อยให้ยาเม็ดต่อไปนะ');
      return;
    }
    care.doses++;
    care.lastDoseAt = now;
    care.stats.meds++;
    var cured = care.sick === 1 || care.doses >= 2;
    // The pill drops in front of its mouth, the cat pulls a face (wiggle), then perks up if it is cured.
    var pill = h('span', { class: 'pet-pill' }, [iconSvg('med')]);
    var size = Math.max(18, pet.w * 0.13);
    pill.style.width = size + 'px';
    pill.style.left = (pet.x + pet.w * (pet.face > 0 ? 0.62 : 0.38) - size / 2) + 'px';
    pill.style.top = (pet.y + pet.h * 0.35) + 'px';
    document.body.appendChild(pill);
    act(pet, 'sit', performance.now(), 1600, { seq: SEQUENCES.look });
    pet.el.classList.add('is-wiggling');
    setTimeout(function () { pill.remove(); }, 900);
    setTimeout(function () {
      pet.el.classList.remove('is-wiggling');
      if (cured) {
        care.sick = 0;
        care.sickSince = null;
        care.doses = 0;
        say(pet, 'หายแล้วเหมียว!');
      } else {
        say(pet, 'ดีขึ้นนิดนึง อีก ' + Math.round(CARE.doseGap / HOUR) + ' ชม. ให้ยาอีกเม็ดนะ');
      }
      careUpdate();
      save();
    }, 1000);
    save();
  }

  // Side-view bowls in the site's flat style: a blue dish with kibble, or with water and a highlight.
  var BOWL_SVG = {
    feed: '<svg viewBox="0 0 48 30" aria-hidden="true"><ellipse cx="24" cy="27" rx="19" ry="2.6" fill="rgba(0,0,0,.12)"/>'
      + '<circle cx="15" cy="11" r="4" fill="#a35a2a"/><circle cx="21" cy="9" r="4.2" fill="#c27a3e"/><circle cx="27" cy="10" r="4" fill="#a35a2a"/>'
      + '<circle cx="33" cy="11" r="3.8" fill="#c27a3e"/><circle cx="24" cy="12" r="3.6" fill="#8e4c22"/>'
      + '<path d="M4 12h40l-4.5 13a3 3 0 0 1-2.8 2H11.3a3 3 0 0 1-2.8-2z" fill="#6aa6dc"/>'
      + '<path d="M4 12h40l-1 3H5z" fill="#4f8fca"/><path d="M10 17h4l-1 6h-2z" fill="#fff" opacity=".35"/></svg>',
    drink: '<svg viewBox="0 0 48 30" aria-hidden="true"><ellipse cx="24" cy="27" rx="19" ry="2.6" fill="rgba(0,0,0,.12)"/>'
      + '<ellipse cx="24" cy="12" rx="19" ry="3.4" fill="#cfe9fb"/><ellipse cx="19" cy="11.4" rx="6" ry="1.1" fill="#fff" opacity=".8"/>'
      + '<path d="M4 12a20 3.6 0 0 0 40 0l-4.5 13a3 3 0 0 1-2.8 2H11.3a3 3 0 0 1-2.8-2z" fill="#e2768c"/>'
      + '<path d="M10 17h4l-1 6h-2z" fill="#fff" opacity=".35"/></svg>'
  };

  /** Small bars under the cat (food / water) while its command circle is open. */
  var careBars = h('div', { class: 'pet-care-bars', hidden: true });
  function showCareBars(pet) {
    var care = pet.cfg.care;
    careBars.textContent = '';
    if (!care) {
      careBars.hidden = true;
      return;
    }
    careAdvance(pet.cfg, Date.now(), true);
    [['feed', 'อาหาร', care.food], ['drink', 'น้ำ', care.water], ['heart', 'สุขภาพ', care.health]].forEach(function (b) {
      var fill = h('i');
      fill.style.width = Math.round(b[2]) + '%';
      careBars.appendChild(h('div', { class: 'pet-care-bar pet-care-' + b[0] + (b[2] < CARE.low ? ' is-low' : ''),
        title: b[1] + ' ' + Math.round(b[2]) + '%' }, [iconSvg(b[0]), h('span', { class: 'pet-care-track' }, [fill])]));
    });
    if (care.sick) {
      var note = care.sick === 2 ? 'ป่วยหนัก · ให้ยาแล้ว ' + care.doses + '/2' : 'ป่วย · ให้ยา 1 เม็ดหาย';
      careBars.appendChild(h('div', { class: 'pet-care-bar pet-care-sick' }, [iconSvg('sick'), h('span', { text: note })]));
    }
    careBars.style.left = (pet.x + pet.w * 0.48) + 'px';
    careBars.style.top = (pet.y + pet.h + 6) + 'px';
    careBars.hidden = false;
  }

  /** Card in the corner for adoption (and later stages: warnings, farewells). Not modal. */
  var careCard = h('div', { class: 'pet-care-card', role: 'dialog', 'aria-live': 'polite', hidden: true });
  function closeCareCard() {
    careCard.hidden = true;
    careCard.classList.remove('is-warning', 'is-celebrate', 'is-share');
    careCard.textContent = '';
  }
  function askAdopt(pet) {
    careCard.textContent = '';
    if (adopted().length >= CARE.maxAdopted) {
      careCard.appendChild(h('p', { text: 'รับเลี้ยงได้ครั้งละ ' + CARE.maxAdopted + ' ตัวนะ ดูแลตัวที่มีอยู่ให้ดีก่อนเหมียว' }));
      careCard.appendChild(h('div', { class: 'pet-care-actions' }, [
        h('button', { type: 'button', class: 'btn btn-ghost', text: 'ตกลง', onclick: closeCareCard })]));
    } else {
      careCard.appendChild(h('h3', {}, [iconSvg('adopt'), document.createTextNode('รับ' + pet.cfg.name + 'มาเลี้ยงไหม?')]));
      careCard.appendChild(h('p', { text: 'รับเลี้ยงแล้ว ' + pet.cfg.name + 'จะหิวและกระหายน้ำตามเวลาจริง แม้ตอนปิดเว็บไป '
        + 'แวะมาให้อาหารและน้ำบ่อยๆ นะ (กดที่ตัวแมวเพื่อดูแล)' }));
      careCard.appendChild(levelPicker('normal'));
      careCard.appendChild(h('div', { class: 'pet-care-actions' }, [
        h('button', { type: 'button', class: 'btn btn-deal', text: 'รับเลี้ยง', onclick: function () {
          var picked = careCard.querySelector('input[name="care-level"]:checked');
          pet.cfg.care = cleanCare({ adoptedAt: Date.now(), food: 80, water: 80, lastTick: Date.now(),
            level: picked ? picked.value : 'normal' });
          closeCareCard();
          say(pet, 'เมี้ยว~ ฝากตัวด้วยนะ');
          careUpdate();
          save();
        } }),
        h('button', { type: 'button', class: 'btn btn-ghost', text: 'ยังก่อน', onclick: closeCareCard })
      ]));
    }
    careCard.hidden = false;
    var first = careCard.querySelector('button');
    if (first) { first.focus({ preventScroll: true }); }
  }

  /** Radio buttons for the difficulty (how fast needs drop). */
  function levelPicker(current, onchange) {
    var group = h('div', { class: 'pet-care-levels', role: 'radiogroup', 'aria-label': 'ระดับการเลี้ยง' });
    Object.keys(CARE.levels).forEach(function (key) {
      var lv = CARE.levels[key];
      var input = h('input', { type: 'radio', name: onchange ? 'care-level-' + current.id : 'care-level', value: key,
        checked: key === (onchange ? current.level : current), onchange: onchange ? function () { onchange(key); } : null });
      group.appendChild(h('label', { class: 'pet-care-level' }, [input, h('span', {}, [
        h('b', { text: lv.label }), h('small', { text: 'ทิ้งไว้ได้' + lv.note })])]));
    });
    return group;
  }

  /** Every few meals (at most weekly): a gentle nudge towards real cat-food deals. Never for medicine. */
  var SITE_BASE = SPRITES.split('assets/')[0];
  function maybeSuggestDeals(care) {
    var now = Date.now();
    if (care.stats.meals % CARE.dealEvery !== 0 || now - (store.lastDealCard || 0) < CARE.dealGap || !careCard.hidden) {
      return;
    }
    store.lastDealCard = now;
    careCard.textContent = '';
    careCard.appendChild(h('h3', {}, [iconSvg('feed'), document.createTextNode('น้องแมวที่บ้านกินอะไรอยู่?')]));
    careCard.appendChild(h('p', { text: 'ให้อาหารน้องบนจอครบ ' + care.stats.meals + ' มื้อแล้ว ลองดูดีลอาหารแมวจริงวันนี้ไหม' }));
    careCard.appendChild(h('div', { class: 'pet-care-actions' }, [
      h('a', { class: 'btn btn-deal', href: SITE_BASE + 'c/' + encodeURIComponent('อาหารแมวเม็ด'), text: 'ดูดีลอาหารแมว →' }),
      h('button', { type: 'button', class: 'btn btn-ghost', text: 'ไว้ก่อน', onclick: closeCareCard })
    ]));
    careCard.hidden = false;
    save();
  }

  // ── Stage 3: farewell, warnings, memories ──
  function daysBetween(a, b) {
    return Math.max(1, Math.round((b - a) / 86400000));
  }


  /** A cat whose health ran out: it sits, grows little wings, floats up and fades. Then it moves to the memories. */
  function sayGoodbye(cfg) {
    var pet = pets[cfg.id] || spawn(cfg);
    pet.state = 'gone';
    setFrame(pet, 'cs1');
    pet.need.hidden = true;
    var wings = h('span', { class: 'pet-wings' });
    wings.innerHTML = '<svg viewBox="0 0 60 30" aria-hidden="true"><path d="M30 18c-6-10-18-14-26-10 4 2 6 6 4 9 6-2 12 0 22 1z" fill="#fff" stroke="#d9cfe8"/>'
      + '<path d="M30 18c6-10 18-14 26-10-4 2-6 6-4 9-6-2-12 0-22 1z" fill="#fff" stroke="#d9cfe8"/></svg>';
    pet.el.appendChild(wings);
    pet.el.classList.add('is-departing');
    store.memorial.unshift({ cat: cfg, diedAt: cfg.care.diedAt, adoptedAt: cfg.care.adoptedAt });
    store.memorial = store.memorial.slice(0, CARE.memorialMax);
    store.cats = store.cats.filter(function (c) { return c.id !== cfg.id; });
    save();
    setTimeout(function () {
      despawn(cfg.id);
      showFarewell(cfg);
    }, 4200);
  }

  function showFarewell(cfg) {
    careCard.textContent = '';
    careCard.appendChild(h('h3', {}, [iconSvg('star'), document.createTextNode(cfg.name + 'ไปอยู่ดาวแมวแล้ว')]));
    careCard.appendChild(h('p', { text: 'เลี้ยงกันมา ' + daysBetween(cfg.care.adoptedAt, cfg.care.diedAt) + ' วัน ขอบคุณที่ดูแลนะเหมียว '
      + 'ครั้งหน้าแวะมาให้อาหาร น้ำ และยาบ่อยๆ น้า' }));
    careCard.appendChild(h('div', { class: 'pet-care-actions' }, [
      h('button', { type: 'button', class: 'btn btn-deal', text: 'รับเลี้ยงตัวใหม่', onclick: function () {
        closeCareCard();
        if (store.cats.length >= MAX) {
          openDrawer();
          return;
        }
        var fresh = newCatConfig(null, store.cats.map(function (c) { return c.name; }));
        store.cats.push(fresh);
        var pet = spawn(fresh);
        start();
        save();
        askAdopt(pet);
      } }),
      h('button', { type: 'button', class: 'btn btn-ghost', text: 'ดูความทรงจำ', onclick: function () {
        closeCareCard();
        openDrawer();
        var mem = drawer.querySelector('.studio-memorial');
        if (mem) { mem.scrollIntoView({ block: 'start' }); }
      } })
    ]));
    careCard.hidden = false;
  }

  /** On opening the page: say goodbye to cats that died while away, or warn about one that is close. */
  function careWelcome() {
    var gone = store.cats.filter(function (c) { return c.care && c.care.diedAt; });
    if (gone.length) {
      gone.forEach(function (cfg, i) { setTimeout(function () { sayGoodbye(cfg); }, i * 600); });
      return;
    }
    var weak = store.cats.filter(function (c) { return c.care && c.care.health < CARE.warnAt; })[0];
    if (!weak) {
      return;
    }
    careCard.textContent = '';
    careCard.classList.add('is-warning');
    careCard.appendChild(h('h3', {}, [iconSvg('heart'), document.createTextNode(weak.name + 'อาการไม่ดีเลย')]));
    careCard.appendChild(h('p', { text: weak.care.sick ? 'ป่วยหนักและอ่อนแรงมาก ต้องการยา อาหาร และน้ำด่วน'
      : 'หิวและอ่อนแรงมาก ต้องการอาหารและน้ำด่วน' }));
    careCard.appendChild(h('div', { class: 'pet-care-actions' }, [
      h('button', { type: 'button', class: 'btn btn-deal', text: 'ไปดูแลเลย', onclick: function (e) {
        e.stopPropagation(); // or the outside-click handler shuts the circle straight away
        closeCareCard();
        if (pets[weak.id]) { openCatRadial(pets[weak.id]); }
      } }),
      h('button', { type: 'button', class: 'btn btn-ghost', text: 'ปิด', onclick: closeCareCard })
    ]));
    careCard.hidden = false;
  }

  // ── UI helpers ─────────────────────────────
  function h(tag, attrs, children) {
    var el = document.createElement(tag);
    for (var k in attrs || {}) {
      if (k === 'text') {
        el.textContent = attrs[k];
      } else if (k.slice(0, 2) === 'on') {
        el.addEventListener(k.slice(2), attrs[k]);
      } else if (attrs[k] !== false && attrs[k] !== null && attrs[k] !== undefined) {
        el.setAttribute(k, attrs[k] === true ? '' : attrs[k]);
      }
    }
    (children || []).forEach(function (c) { if (c) { el.appendChild(c); } });
    return el;
  }

  function catPreview(cfg, viewBox) {
    var svg = frameSvg('cs1', viewBox || '88 6 164 144');
    applyPaint(svg, cfg);
    return svg;
  }

  // ── Round command icons (radial menu) ──────
  // Tap a cat: it stops, and round icons pop out in a circle around it. The 🐾 button does the same for all cats.
  var radial = h('div', { class: 'pet-radial', role: 'menu', hidden: true });
  document.body.appendChild(radial);
  var radialFor = null;  // pet the circle is open for, or 'all'

  /** Lay out buttons on rings around (cx, cy). arc = [startDeg, endDeg]; 0deg = right, -90deg = up.
   *  A ring may set `turn` (degrees) to rotate it, so its icons sit between the ones on the ring inside. */
  function openRadial(cx, cy, items, rings, arc, owner) {
    radial.textContent = '';
    radial.style.left = cx + 'px';
    radial.style.top = cy + 'px';
    var placed = 0;
    rings.forEach(function (ring) {
      var n = Math.min(ring.count, items.length - placed);
      for (var k = 0; k < n; k++) {
        var it = items[placed];
        var span = arc[1] - arc[0];
        var full = Math.abs(span) >= 360;
        var angle = (arc[0] + (ring.turn || 0) + span * (full ? k / n : (n === 1 ? 0.5 : k / (n - 1)))) * Math.PI / 180;
        var btn = h('button', { type: 'button', class: 'pet-radial-item tone-' + it.tone, role: 'menuitem', 'aria-label': it.label,
          onclick: (function (fn) { return function (e) { e.stopPropagation(); fn(); closeRadial(); }; })(it.run) }, [
          iconSvg(it.icon),
          h('span', { class: 'pet-radial-label', text: it.short || it.label })
        ]);
        btn.style.setProperty('--tx', (Math.cos(angle) * ring.r).toFixed(1) + 'px');
        btn.style.setProperty('--ty', (Math.sin(angle) * ring.r).toFixed(1) + 'px');
        btn.style.setProperty('--i', placed);
        radial.appendChild(btn);
        placed++;
      }
    });
    radialFor = owner;
    radial.hidden = false;
    radial.classList.remove('is-open');
    void radial.offsetWidth; // restart the pop-out animation
    radial.classList.add('is-open');
    var first = radial.querySelector('button');
    if (first) {
      first.focus({ preventScroll: true });
    }
  }

  function closeRadial() {
    if (radialFor && radialFor !== 'all' && radialFor.state === 'held') {
      decide(radialFor, performance.now()); // let the cat get on with its day
    }
    radialFor = null;
    radial.classList.remove('is-open');
    radial.hidden = true;
    careBars.hidden = true;
    fabBtn.setAttribute('aria-expanded', 'false');
  }

  function commandItems(ids) {
    return COMMANDS.map(function (c) {
      return { icon: c[0], label: c[1], tone: c[2], run: function () { giveCommand(ids, c[0]); } };
    });
  }

  function openCatRadial(pet) {
    if (pet.state === 'gone') {
      return;
    }
    if (radialFor === pet) {
      closeRadial();
      return;
    }
    closeRadial();
    act(pet, 'held', performance.now(), 0, { seq: SEQUENCES.look, loop: SEQUENCES.look });
    var care = pet.cfg.care ? (pet.cfg.care.sick ? [
      { icon: 'med', label: 'ให้ยา ' + pet.cfg.name, short: 'ให้ยา', tone: 'pink', run: function () { giveMed(pet); } }
    ] : []).concat([
      { icon: 'feed', label: 'ให้อาหาร ' + pet.cfg.name, short: 'ให้อาหาร', tone: 'orange', run: function () { serve(pet, 'feed'); } },
      { icon: 'drink', label: 'ให้น้ำ ' + pet.cfg.name, short: 'ให้น้ำ', tone: 'sky', run: function () { serve(pet, 'drink'); } }
    ]) : [
      { icon: 'adopt', label: 'รับ ' + pet.cfg.name + ' มาเลี้ยง', short: 'รับเลี้ยง', tone: 'pink', run: function () { askAdopt(pet); } }
    ];
    var items = care.concat(commandItems([pet.cfg.id])).concat([{ icon: 'edit', label: 'แก้ไข ' + pet.cfg.name, short: 'แก้ไขแมว', tone: 'ink', run: function () {
      openDrawer(pet.cfg.id);
    } }]);
    // One ring of 13-14 icons is too tight for the labels at any width, so use two (care + first commands inside),
    // with the outer ring turned half a step so labels don't stack. Phones also get smaller buttons (style.css).
    var narrow = window.innerWidth < 480;
    var inner = Math.min(6, Math.ceil(items.length / 2));
    var outer = items.length - inner;
    var rings = narrow ? [{ r: 84, count: inner }, { r: 146, count: outer, turn: 180 / outer }]
      : [{ r: 100, count: inner }, { r: 178, count: outer, turn: 180 / outer }];
    var r = rings[rings.length - 1].r;
    var pad = narrow ? 34 : 28; // phones: wide labels on the side icons need the extra room
    // Centre on the sitting cat's body (the frame box leaves room for walking tails on the right).
    var cx = pet.x + pet.w * 0.48;
    var cy = pet.y + pet.h * 0.55;
    cx = Math.max(r + pad, Math.min(window.innerWidth - r - pad, cx));
    cy = Math.max(r + pad, Math.min(window.innerHeight - r - pad - (narrow ? 14 : 0), cy));
    openRadial(cx, cy, items, rings, [-90, 270], pet);
    showCareBars(pet);
    if (!careBars.hidden) {
      // The rings fill the space under the cat, so move the bars under the circle (or over it if the screen ends first).
      var bh = careBars.offsetHeight;
      var below = cy + r + (narrow ? 46 : 52);
      careBars.style.left = cx + 'px';
      careBars.style.top = (below + bh < window.innerHeight - 8 ? below : cy - r - (narrow ? 32 : 36) - bh) + 'px';
    }
  }

  // The 🐾 button: commands for every cat, plus design and show/hide.
  var fabBtn = h('button', { type: 'button', class: 'pet-fab-btn', 'aria-haspopup': 'menu', 'aria-expanded': 'false',
    'aria-label': 'คำสั่งแมวทุกตัว', onclick: function (e) {
      e.stopPropagation();
      if (radialFor === 'all') {
        closeRadial();
        return;
      }
      closeRadial();
      var ids = store.cats.map(function (c) { return c.id; });
      var cmd = {};
      commandItems(ids).forEach(function (it) { cmd[it.icon] = it; });
      var edit = { icon: 'edit', label: 'สร้าง / แก้ไขแมว', short: 'แก้ไขแมว', tone: 'ink', run: function () { openDrawer(); } };
      var toggle = { icon: store.on ? 'hide' : 'show', label: store.on ? 'ซ่อนแมว' : 'เรียกแมวกลับมา', short: store.on ? 'ซ่อนแมว' : 'เรียกแมว',
        tone: 'gray', run: function () {
          store.on = !store.on;
          if (store.on) { showAll(); } else { hideAll(); }
          save();
        } };
      var rect = fabBtn.getBoundingClientRect();
      var cx = rect.left + rect.width / 2;
      var cy = rect.top + rect.height / 2;
      if (!store.on) {
        openRadial(cx, cy, [toggle, edit], [{ r: 110, count: 2 }], [-90, 0], 'all');
      } else {
        // Quarter circle fanning up and to the right of the corner button:
        // inner ring = controls, outer two rings = cat behaviours (far enough apart that labels don't touch).
        openRadial(cx, cy, [cmd.stay, cmd.come, edit, toggle, cmd.walk, cmd.zoom, cmd.dash, cmd.play,
          cmd.hunt, cmd.stretch, cmd.yawn, cmd.sleep],
          [{ r: 118, count: 4 }, { r: 200, count: 4 }, { r: 282, count: 4 }], [-90, 0], 'all');
      }
      fabBtn.setAttribute('aria-expanded', 'true');
    } }, [iconSvg('paw')]);
  document.body.appendChild(fabBtn);
  document.body.appendChild(careBars);
  document.body.appendChild(careCard);

  document.addEventListener('click', function (e) {
    if (radialFor && !radial.contains(e.target) && e.target !== fabBtn) {
      closeRadial();
    }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      closeRadial();
      closeDrawer();
      closeCareCard();
    }
  });
  window.addEventListener('scroll', function () { if (radialFor && radialFor !== 'all') { closeRadial(); } }, { passive: true });

  // ── Drawer: make / edit cats (not modal) ───
  var drawer = h('aside', { class: 'cat-drawer', 'aria-label': 'แต่งแมวของฉัน', hidden: true });
  var drawerBody = h('div', { class: 'studio-body' });
  drawer.appendChild(h('div', { class: 'studio-head' }, [
    h('h2', {}, [iconSvg('edit'), document.createTextNode('สร้าง / แก้ไขแมวของฉัน')]),
    h('button', { type: 'button', class: 'studio-close', 'aria-label': 'ปิด', text: '×', onclick: closeDrawer })
  ]));
  drawer.appendChild(drawerBody);
  document.body.appendChild(drawer);
  var editing = null; // cfg being edited (copy), or null

  function openDrawer(catId) {
    var cfg = catId && store.cats.filter(function (c) { return c.id === catId; })[0];
    editing = cfg ? Object.assign({}, cfg) : null;
    renderDrawer();
    drawer.hidden = false;
    void drawer.offsetWidth; // let the hidden -> shown change land before sliding in
    drawer.classList.add('is-open');
  }
  function closeDrawer() {
    // Closing mid-edit drops the unsaved colours from the live cat.
    if (editing && pets[editing.id]) {
      applyPaint(pets[editing.id].el, pets[editing.id].cfg);
    }
    drawer.classList.remove('is-open');
    setTimeout(function () { if (!drawer.classList.contains('is-open')) { drawer.hidden = true; } }, 250);
  }

  function renderDrawer() {
    drawerBody.textContent = '';
    if (editing) {
      drawerBody.appendChild(renderEditor());
      return;
    }
    var list = h('div', { class: 'studio-cats' });
    store.cats.forEach(function (cfg) {
      list.appendChild(h('div', { class: 'studio-cat' }, [
        h('div', { class: 'studio-cat-top' }, [
          catPreview(cfg),
          h('div', { class: 'studio-cat-info' }, [
            h('div', { class: 'studio-cat-name', text: cfg.name }),
            h('div', { class: 'studio-cat-sub', text: (PRESETS[cfg.preset] ? PRESETS[cfg.preset].label : 'สีของฉันเอง') + ' · ' + PATTERNS[cfg.pattern] + ' · ' + MOODS[cfg.mood] }),
            cfg.care && titleFor(streakNow(cfg.care)) ? h('div', { class: 'studio-cat-title', text: '★'.repeat(titleFor(streakNow(cfg.care)).stars)
              + ' ' + titleFor(streakNow(cfg.care)).label + ' · ดูแลดีติดกัน ' + streakNow(cfg.care) + ' วัน' }) : null,
            cfg.care ? h('div', { class: 'studio-cat-care' }, [
              h('span', { class: 'studio-cat-sub', text: 'รับเลี้ยงแล้ว · ระดับ' }),
              levelPicker({ id: cfg.id, level: cfg.care.level }, function (key) {
                careAdvance(cfg, Date.now(), true);  // the old pace up to now, the new one from here on
                cfg.care.level = key;
                save();
              })
            ]) : null
          ]),
          h('div', { class: 'studio-cat-actions' }, [
            cfg.care && !cfg.care.diedAt ? h('button', { type: 'button', class: 'chip-btn line', text: 'แชร์การ์ดแมว', onclick: function () { shareCard(cfg); } }) : null,
            h('button', { type: 'button', class: 'chip-btn', text: 'แก้ไข', onclick: function () { editing = Object.assign({}, cfg); renderDrawer(); } }),
            h('button', { type: 'button', class: 'chip-btn danger', text: 'ลบ', onclick: function () {
              if (window.confirm('ลบ "' + cfg.name + '" ?')) {
                store.cats = store.cats.filter(function (c) { return c.id !== cfg.id; });
                despawn(cfg.id);
                save();
                renderDrawer();
              }
            } })
          ])
        ])
      ]));
    });
    drawerBody.appendChild(h('div', { class: 'studio-section' }, [
      h('h3', { text: 'แมวของฉัน (' + store.cats.length + '/' + MAX + ')' }),
      store.cats.length ? list : h('p', { class: 'studio-note', text: 'ยังไม่มีแมวเลย สร้างตัวแรกกันเลย!' }),
      h('div', { class: 'studio-row', style: 'margin-top:10px' }, [
        h('button', { type: 'button', class: 'chip-btn primary', text: '+ สร้างแมวตัวใหม่', disabled: store.cats.length >= MAX, onclick: function () {
          editing = newCatConfig(null, store.cats.map(function (c) { return c.name; }));
          editing.isNew = true;
          renderDrawer();
        } })
      ]),
      h('p', { class: 'studio-note', text: 'แมวเก็บไว้ในเบราว์เซอร์นี้เท่านั้น ไม่ได้ส่งไปที่ไหน — ถ้าล้างข้อมูลเว็บไซต์ แมวจะหายไปด้วย' })
    ]));
    if (store.memorial.length) {
      drawerBody.appendChild(h('div', { class: 'studio-section studio-memorial' }, [
        h('h3', {}, [iconSvg('star'), document.createTextNode(' ความทรงจำ')]),
        h('div', { class: 'studio-cats' }, store.memorial.map(function (m) {
          var when = new Date(m.diedAt);
          return h('div', { class: 'studio-cat is-memory' }, [h('div', { class: 'studio-cat-top' }, [
            catPreview(m.cat),
            h('div', { class: 'studio-cat-info' }, [
              h('div', { class: 'studio-cat-name', text: m.cat.name }),
              h('div', { class: 'studio-cat-sub', text: 'เลี้ยงมา ' + daysBetween(m.adoptedAt, m.diedAt) + ' วัน · ไปดาวแมวเมื่อ '
                + when.getDate() + '/' + (when.getMonth() + 1) + '/' + when.getFullYear() })
            ])
          ])]);
        })),
        h('p', { class: 'studio-note', text: 'แมวจริงป่วยหรือไม่กินอาหาร ควรพาไปหาสัตวแพทย์นะ' })
      ]));
    }
  }

  function renderEditor() {
    var cfg = editing;
    var big = frameSvg('cs1', '70 0 200 150');
    // One picture of each frame set (walk, run, sleep, hunt, stretch), so every vector shows the new colours.
    var minis = ['cw2', 'cr2', 'cs8', 'sl5', 'sl2'].map(function (f) { return frameSvg(f); });
    var previewBox = h('div', { class: 'studio-preview' }, [big, h('div', { class: 'studio-preview-frames' }, minis)]);
    // The cat already on screen is repainted live too; Cancel puts its saved look back.
    var live = !cfg.isNew && pets[cfg.id] ? pets[cfg.id] : null;
    function repaint() {
      [big].concat(minis).forEach(function (s) { applyPaint(s, cfg); });
      if (live) { applyPaint(live.el, cfg); }
    }

    function field(label, input, wide) {
      return h('label', { class: wide ? 'wide' : null }, [document.createTextNode(label), input]);
    }
    function colour(key) {
      var input = h('input', { type: 'color', value: cfg[key] });
      input.addEventListener('input', function () { cfg[key] = input.value; cfg.preset = ''; presetSel.value = ''; repaint(); });
      return input;
    }
    function select(options, value, onchange) {
      var sel = h('select', {}, Object.keys(options).map(function (k) {
        return h('option', { value: k, text: options[k], selected: k === value });
      }));
      sel.addEventListener('change', function () { onchange(sel.value); });
      return sel;
    }

    var nameInput = h('input', { type: 'text', value: cfg.name, maxlength: '20', placeholder: 'เช่น ส้มโอ' });
    nameInput.addEventListener('input', function () { cfg.name = nameInput.value; });

    var presetOptions = { '': 'สีของฉันเอง' };
    Object.keys(PRESETS).forEach(function (k) { presetOptions[k] = PRESETS[k].label; });
    var inputs = {};
    var presetSel = select(presetOptions, cfg.preset, function (key) {
      if (!PRESETS[key]) { return; }
      var p = PRESETS[key];
      cfg.preset = key;
      ['base', 'accent', 'pattern', 'eye', 'collar'].forEach(function (k) {
        cfg[k] = p[k];
        if (inputs[k]) { inputs[k].value = p[k]; }
      });
      repaint();
    });
    inputs.pattern = select(PATTERNS, cfg.pattern, function (v) { cfg.pattern = v; cfg.preset = ''; presetSel.value = ''; repaint(); });
    inputs.base = colour('base');
    inputs.accent = colour('accent');
    inputs.eye = colour('eye');
    inputs.collar = colour('collar');
    var size = h('input', { type: 'range', min: '80', max: '180', value: String(cfg.size) });
    size.addEventListener('input', function () { cfg.size = parseInt(size.value, 10); });
    var mood = select(MOODS, cfg.mood, function (v) { cfg.mood = v; });

    repaint();
    var form = h('form', { class: 'studio-form' }, [
      previewBox,
      h('div', { class: 'studio-fields' }, [
        field('ชื่อแมว', nameInput, true),
        field('เริ่มจากสายพันธุ์', presetSel),
        field('ลาย', inputs.pattern),
        field('สีขนหลัก', inputs.base),
        field('สีที่สอง (ลาย/แต้ม)', inputs.accent),
        field('สีตา', inputs.eye),
        field('สีปลอกคอ', inputs.collar),
        field('ขนาด', size),
        field('นิสัย', mood),
        h('div', { class: 'studio-row wide', style: 'margin-top:6px' }, [
          h('button', { type: 'submit', class: 'chip-btn primary', text: cfg.isNew ? 'ปล่อยแมวลงหน้าจอ 🐾' : 'บันทึก' }),
          h('button', { type: 'button', class: 'chip-btn', text: 'ยกเลิก', onclick: function () {
            if (live) { applyPaint(live.el, live.cfg); }
            editing = null;
            renderDrawer();
          } })
        ])
      ])
    ]);
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var isNew = cfg.isNew;
      delete cfg.isNew;
      var saved = clean(Object.assign({}, cfg, { name: (cfg.name || '').trim() || pick(NAMES) }));
      if (isNew) {
        store.cats.push(saved);
        if (store.on) {
          spawn(saved);
          start();
        }
      } else {
        store.cats = store.cats.map(function (c) { return c.id === saved.id ? saved : c; });
        refreshPet(saved);
      }
      save();
      editing = null;
      renderDrawer();
    });

    return h('div', { class: 'studio-section' }, [h('h3', { text: cfg.isNew ? 'สร้างแมวตัวใหม่' : 'แก้ไข ' + cfg.name }), form]);
  }

  // ── Start ──────────────────────────────────
  // Wait until the page has settled: cats walking in straight away keep the screen changing, which slows the
  // first view down (and the page's measured speed). They come out as soon as the visitor scrolls, moves or taps,
  // or after a few seconds on their own.
  var awake = false;
  var WAKE_EVENTS = ['pointermove', 'pointerdown', 'scroll', 'keydown', 'touchstart'];
  function wake() {
    if (awake) {
      return;
    }
    awake = true;
    WAKE_EVENTS.forEach(function (type) { window.removeEventListener(type, wake); });
    document.documentElement.classList.add('anim-on');
    inlineSprites();
    if (store.on) {
      showAll();
    }
    careUpdate();
    careWelcome();
    setInterval(careUpdate, 60000);
  }
  WAKE_EVENTS.forEach(function (type) { window.addEventListener(type, wake, { passive: true }); });
  function wakeLater() {
    setTimeout(wake, 6000);
  }
  if (document.readyState === 'complete') {
    wakeLater();
  } else {
    window.addEventListener('load', wakeLater);
  }
  save();

  // For trying things out from the browser console: petCats.command('zoom'), petCats.command('play', 0)
  window.petCats = {
    presets: PRESETS,
    // The "start playing" button on /game: bring the cats out and offer the first one not yet adopted.
    play: function () {
      wake();
      if (!store.on) {
        store.on = true;
        showAll();
        save();
      }
      var free = Object.keys(pets).map(function (k) { return pets[k]; }).filter(function (p) { return !p.cfg.care; });
      if (free.length) {
        askAdopt(free[0]);
      } else {
        openDrawer();
      }
    },
    // Care testing: petCats.care.fastForward(10) ages every adopted cat 10 hours; set(0, { food: 5 }); status()
    care: {
      fastForward: function (hours) {
        // Move every past time stamp back, as if that many hours had really gone by.
        adopted().forEach(function (c) {
          c.care.lastTick -= hours * HOUR;
          if (c.care.sickSince) { c.care.sickSince -= hours * HOUR; }
          if (c.care.lastDoseAt) { c.care.lastDoseAt -= hours * HOUR; }
          careAdvance(c, Date.now());   // as if the page had been closed all that time: it can die
        });
        careWelcome();
        careUpdate();
        save();
      },
      set: function (index, values) {
        var c = store.cats[index];
        if (c && c.care) {
          Object.assign(c.care, values);
          if (c.care.sick && !c.care.sickSince) { c.care.sickSince = Date.now(); }
          careUpdate();
          save();
        }
      },
      status: function () {
        return adopted().map(function (c) {
          return { name: c.name, food: Math.round(c.care.food), water: Math.round(c.care.water), health: Math.round(c.care.health),
            dead: !!c.care.diedAt, sick: c.care.sick,
            doses: c.care.doses, need: careNeed(c.care) };
        });
      }
    },
    debug: function () {
      return Object.keys(pets).map(function (k) {
        var p = pets[k];
        return { name: p.cfg.name, state: p.state, x: Math.round(p.x), face: p.face, through: p.through, dashing: p.dashing, legs: p.legs,
          target: p.target && [Math.round(p.target[0]), Math.round(p.target[1])] };
      });
    },
    command: function (what, index) {
      var ids = Object.keys(pets);
      giveCommand(index === undefined ? ids : [ids[index]], what);
    },
    reset: function () {
      hideAll();
      try { localStorage.removeItem(STORE_KEY); } catch (e) { /* ignore */ }
      store = load();
      showAll();
      save();
    }
  };
})();
