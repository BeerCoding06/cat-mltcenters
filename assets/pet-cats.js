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

  var PATTERNS = { solid: 'สีพื้น', tabby: 'ลายสลิด', socks: 'ถุงเท้าขาว', points: 'แต้มสี', calico: 'สามสี' };
  var MOODS = { normal: 'ปกติ', sleepy: 'ขี้เซา', playful: 'ซนสุดๆ' };
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
    'cream': { label: 'ครีม', base: '#f1dcc0', accent: '#d8b48a', pattern: 'solid', eye: '#e8a23b', collar: '#6aa6dc' }
  };
  var NAMES = ['ส้มโอ', 'มะลิ', 'ข้าวปั้น', 'ถั่วแดง', 'โมจิ', 'ทองดี', 'ลูกชิ้น', 'ขนมปัง', 'นมเย็น', 'มะขาม'];
  // [command, label, colour tone] — tones are .tone-* in style.css
  var COMMANDS = [
    ['walk', 'เดินเล่น', 'mint'], ['zoom', 'วิ่งพล่าน', 'sun'], ['stay', 'นั่งนิ่งๆ', 'pink'], ['sleep', 'นอน', 'sky'],
    ['yawn', 'หาว', 'lilac'], ['play', 'เล่นกัน', 'rose'], ['dash', 'วิ่งทะลุจอ', 'orange'], ['come', 'มาตรงกลาง', 'teal'],
    ['hunt', 'ล่าเหยื่อ', 'olive'], ['stretch', 'บิดขี้เกียจ', 'peach']
  ];

  // Icons: Lucide v0.454.0 (ISC licence, lucide.dev) — footprints, zap, cat, bed, arrow-left-right, map-pin, pencil,
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
    }
    // The silhouette poses (sl1-8) have no drawn pattern, so they switch on matching marking layers instead.
    v['--sl-stripes'] = cfg.pattern === 'tabby' ? 1 : 0;
    v['--sl-points'] = cfg.pattern === 'points' ? 1 : 0;
    v['--sl-patches'] = cfg.pattern === 'calico' ? 1 : 0;
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
      y: typeof c.y === 'number' && isFinite(c.y) ? c.y : null
    };
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
    return { on: data.on !== false, cats: cats };
  }

  var store = load();
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
      localStorage.setItem(STORE_KEY, JSON.stringify({ v: 4, on: store.on, cats: store.cats }));
    } catch (e) { /* private mode etc. — cats just won't be remembered */ }
  }

  // ── Frames ─────────────────────────────────
  var inlined = false;
  function href(frame) {
    return (inlined ? '' : SPRITES) + '#' + frame;
  }

  // Fetch the sprite file once and put it in the page, then point every <use> at the local copy.
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
    look: [['cs5', 1400], ['cs1', 200], ['cs4', 900]]
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
    var free = others(pet).filter(function (p) { return !busy(p); });
    var w = Object.assign({}, WEIGHTS[pet.cfg.mood] || WEIGHTS.normal);
    if (!free.length) { w.play = 0; }
    if (prev === 'yawn') { w.yawn = 0; }
    command(pet, pickWeighted(w), now, free);
  }

  /** Make one cat do something (used by the random brain and by the command buttons). */
  function command(pet, what, now, playmates) {
    var sleepy = pet.cfg.mood === 'sleepy' ? 1.5 : 1;
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

  /** Lay out buttons on rings around (cx, cy). arc = [startDeg, endDeg]; 0deg = right, -90deg = up. */
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
        var angle = (arc[0] + span * (full ? k / n : (n === 1 ? 0.5 : k / (n - 1)))) * Math.PI / 180;
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
    fabBtn.setAttribute('aria-expanded', 'false');
  }

  function commandItems(ids) {
    return COMMANDS.map(function (c) {
      return { icon: c[0], label: c[1], tone: c[2], run: function () { giveCommand(ids, c[0]); } };
    });
  }

  function openCatRadial(pet) {
    if (radialFor === pet) {
      closeRadial();
      return;
    }
    closeRadial();
    act(pet, 'held', performance.now(), 0, { seq: SEQUENCES.look, loop: SEQUENCES.look });
    var items = commandItems([pet.cfg.id]).concat([{ icon: 'edit', label: 'แก้ไข ' + pet.cfg.name, short: 'แก้ไขแมว', tone: 'ink', run: function () {
      openDrawer(pet.cfg.id);
    } }]);
    var r = Math.max(118, pet.w * 0.72); // room for 11 icons with labels
    // Centre on the sitting cat's body (the frame box leaves room for walking tails on the right).
    var cx = pet.x + pet.w * 0.48;
    var cy = pet.y + pet.h * 0.55;
    cx = Math.max(r + 28, Math.min(window.innerWidth - r - 28, cx));
    cy = Math.max(r + 28, Math.min(window.innerHeight - r - 28, cy));
    openRadial(cx, cy, items, [{ r: r, count: items.length }], [-90, 270], pet);
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

  document.addEventListener('click', function (e) {
    if (radialFor && !radial.contains(e.target) && e.target !== fabBtn) {
      closeRadial();
    }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      closeRadial();
      closeDrawer();
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
            h('div', { class: 'studio-cat-sub', text: (PRESETS[cfg.preset] ? PRESETS[cfg.preset].label : 'สีของฉันเอง') + ' · ' + PATTERNS[cfg.pattern] + ' · ' + MOODS[cfg.mood] })
          ]),
          h('div', { class: 'studio-cat-actions' }, [
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
  if (store.on) {
    showAll();
  }
  save();

  // For trying things out from the browser console: petCats.command('zoom'), petCats.command('play', 0)
  window.petCats = {
    presets: PRESETS,
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
