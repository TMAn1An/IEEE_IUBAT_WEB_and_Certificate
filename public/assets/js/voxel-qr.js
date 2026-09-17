/* ══════════════════════════════════════════════════════════════════
   voxel-qr.js

   A voxel tree whose leaves are the dark modules of a QR code. Once the
   frame is fully on screen it holds for a beat, then the canopy collapses
   into a scannable code seen from directly above.

   Requires three.js r128+. Also requires qrcode-generator UNLESS you pass
   a server-built grid via data-matrix.

   Mount point:
     <div class="vq" data-voxel-qr data-url="https://example.com"></div>

   Attributes are documented in the README comment at the bottom.
   ══════════════════════════════════════════════════════════════════ */

(function () {
  "use strict";

  var DEFAULTS = {
    url:        "https://www.ieee.org",
    matrix:     null,
    title:      "",
    subtitle:   "",
    overlay:    "",      // caption drawn ON the scene, tree phase only
    overlaySub: "",
    trigger:    "scroll",// "scroll" = wait until fully in view; "load" = at once
    delay:      2600,    // hold on the tree before collapsing
    fall:       2400,
    grow:       1900,
    loop:       0        // 0 = settle on the code and stay
  };

  var ECC   = "H";  // the voxel seams eat into the margin; buy it back here
  var QUIET = 4;    // quiet zone in modules — 4 is the spec minimum
  var RIM   = 3;    // grass border, sits outside the quiet zone

  var PALETTE = {
    // ── flat phase: these have to survive a camera scan ──────────
    // worst dark/light pair here measures 4.0:1. Scanners get flaky under ~3:1,
    // so if you rebrand these, keep the dark entries genuinely dark.
    dark:   [0x24702b, 0x2b7b2f, 0x1f6527, 0x1a5c22],
    light:  [0xf1ebe0, 0xeae3d5, 0xf4efe6, 0xe6dfd0],

    // ── tree phase: never scanned, so it can be as bright as it likes ──
    leafShade: 0x1c5522,   // interior, in shadow
    leafMid:   0x2f8a33,
    leafSun:   0x63c14e,   // outer and upper, catching light
    leafHigh:  0x8ad46b,   // rim highlight
    leafAccent:[0xd8c44a, 0xc9a63f, 0x9fbf45],  // occasional turning leaves
    bark:      [0x6d4c2f, 0x7d5836, 0x5e4028, 0x82603c],

    // ── border ────────────────────────────────────────────────────
    soil:   [0x49803f, 0x3f7337, 0x548c46],
    grass:  [0x5cbb4a, 0x4aa73e, 0x6bcf55, 0x429a38],
    wings:  [0xffd166, 0xfaf4e4, 0xef8354, 0xffe9a8, 0xf7b267],
    bg:     0xf2efe6
  };

  // ════════════════════════════════════════════════════════════════
  // helpers
  // ════════════════════════════════════════════════════════════════
  function mulberry32(a) {
    return function () {
      a |= 0; a = (a + 0x6d2b79f5) | 0;
      var t = Math.imul(a ^ (a >>> 15), 1 | a);
      t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
      return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
  }
  function seedFrom(s) {
    var h = 2166136261;
    for (var i = 0; i < s.length; i++) { h ^= s.charCodeAt(i); h = Math.imul(h, 16777619); }
    return h >>> 0;
  }
  function clamp01(v) { return v < 0 ? 0 : v > 1 ? 1 : v; }
  function lerp(a, b, t) { return a + (b - a) * t; }
  function easeInOut(t) { return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2; }
  function easeIn(t) { return t * t * t; }
  function easeOut(t) { return 1 - Math.pow(1 - t, 3); }
  function pick(arr, rnd) { return arr[(rnd() * arr.length) | 0]; }

  var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  var mixA = new THREE.Color(), mixB = new THREE.Color();
  function mixHex(a, b, t) {
    mixA.setHex(a); mixB.setHex(b);
    return mixA.lerp(mixB, clamp01(t)).getHex();
  }

  // ════════════════════════════════════════════════════════════════
  // QR matrix
  // ════════════════════════════════════════════════════════════════
  function matrixFromString(str) {
    var rows = str.trim().split(/[|\n]+/), grid = [];
    for (var r = 0; r < rows.length; r++) {
      var row = rows[r].trim(), out = [];
      for (var c = 0; c < row.length; c++) out.push(row.charAt(c) === "1");
      grid.push(out);
    }
    return grid.length && grid.length === grid[0].length ? grid : null;
  }

  function matrixFromUrl(text) {
    if (typeof qrcode !== "function") return null;
    for (var type = 1; type <= 40; type++) {
      try {
        var qr = qrcode(type, ECC);
        qr.addData(text);
        qr.make();
        var n = qr.getModuleCount(), grid = [];
        for (var r = 0; r < n; r++) {
          grid[r] = [];
          for (var c = 0; c < n; c++) grid[r][c] = qr.isDark(r, c);
        }
        return grid;
      } catch (e) { /* payload overflowed this type, try the next */ }
    }
    return null;
  }

  // ════════════════════════════════════════════════════════════════
  // one widget instance
  // ════════════════════════════════════════════════════════════════
  function VoxelQR(root) {
    var cfg = Object.assign({}, DEFAULTS);

    [["url", "url"], ["matrix", "matrix"], ["title", "title"],
     ["subtitle", "subtitle"], ["overlay", "overlay"],
     ["overlaySub", "overlay-sub"], ["trigger", "trigger"]].forEach(function (pair) {
      var v = root.getAttribute("data-" + pair[1]);
      if (v !== null) cfg[pair[0]] = v;
    });
    ["delay", "fall", "grow", "loop"].forEach(function (k) {
      var v = root.getAttribute("data-" + k);
      if (v !== null && v !== "" && !isNaN(+v)) cfg[k] = +v;
    });

    // ── DOM ────────────────────────────────────────────────────────
    var h = document.createElement("h2");
    h.className = "vq__title";
    h.textContent = cfg.title || "";

    var sub = document.createElement("p");
    sub.className = "vq__sub";
    sub.textContent = cfg.subtitle || "";

    var stage = document.createElement("div");
    stage.className = "vq__stage";

    var overlay = document.createElement("div");
    overlay.className = "vq__overlay";
    overlay.setAttribute("aria-hidden", "true");
    var oLine = document.createElement("p");
    oLine.className = "vq__overlay-line";
    oLine.textContent = cfg.overlay || "";
    var oSub = document.createElement("p");
    oSub.className = "vq__overlay-sub";
    oSub.textContent = cfg.overlaySub || "";
    overlay.appendChild(oLine);
    overlay.appendChild(oSub);
    var hasOverlay = !!(cfg.overlay || cfg.overlaySub);

    var fallback = document.createElement("div");
    fallback.className = "vq__fallback";
    fallback.textContent = "This code could not be drawn here. Open the link directly instead.";

    if (hasOverlay) stage.appendChild(overlay);
    stage.appendChild(fallback);
    root.appendChild(h);
    root.appendChild(sub);
    root.appendChild(stage);

    // ── matrix ─────────────────────────────────────────────────────
    var grid = cfg.matrix ? matrixFromString(cfg.matrix) : matrixFromUrl(cfg.url);
    if (!grid) { stage.setAttribute("data-failed", "1"); return; }

    var N = grid.length;
    var field = N + QUIET * 2;
    var span = field + RIM * 2;
    var half = (span - 1) / 2;
    var rnd = mulberry32(seedFrom(cfg.matrix || cfg.url));

    // ── three.js ───────────────────────────────────────────────────
    var renderer;
    try { renderer = new THREE.WebGLRenderer({ antialias: true }); }
    catch (e) { stage.setAttribute("data-failed", "1"); return; }
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    stage.appendChild(renderer.domElement);

    var scene = new THREE.Scene();
    scene.background = new THREE.Color(PALETTE.bg);

    var camera = new THREE.OrthographicCamera(-1, 1, 1, -1, -600, 1200);

    // Held just under full brightness on purpose: any brighter and the pale
    // modules clip to flat white, losing both the path texture and the
    // contrast headroom the scan depends on.
    scene.add(new THREE.HemisphereLight(0xffffff, 0xc8ccbe, 0.60));
    var sun = new THREE.DirectionalLight(0xffffff, 0.46);
    sun.position.set(60, 130, 45);
    scene.add(sun);

    var dummy = new THREE.Object3D();
    var cA = new THREE.Color(), cB = new THREE.Color(), cMix = new THREE.Color();

    // ══════════════════════════════════════════════════════════════
    // board: every QR module, plus soil under the grass border
    // ══════════════════════════════════════════════════════════════
    var boardGeo = new THREE.BoxGeometry(1, 1, 1);
    var boardMat = new THREE.MeshLambertMaterial();
    var cells = [], darkCells = [];

    for (var gz = 0; gz < span; gz++) {
      for (var gx = 0; gx < span; gx++) {
        var fx = gx - RIM, fz = gz - RIM;
        var inField = fx >= 0 && fz >= 0 && fx < field && fz < field;
        var mx = fx - QUIET, mz = fz - QUIET;
        var inCode = inField && mx >= 0 && mz >= 0 && mx < N && mz < N;

        var kind, height, finalHex;
        if (!inField)                       { kind = "soil";  height = 0.34; finalHex = pick(PALETTE.soil, rnd); }
        else if (inCode && grid[mz][mx])    { kind = "dark";  height = 0.52; finalHex = pick(PALETTE.dark, rnd); }
        else                                { kind = "light"; height = 0.24; finalHex = pick(PALETTE.light, rnd); }

        var cell = {
          idx: cells.length,
          kind: kind, height: height,
          finalHex: finalHex, startHex: finalHex,
          flat: { x: gx - half, y: height / 2, z: gz - half },
          tree: null, delay: 0, spin: null,
          size: 1, flut: null, part: null
        };
        cells.push(cell);
        if (kind === "dark") darkCells.push(cell);
      }
    }

    var maxTreeY = 1;
    shapeTree(darkCells);

    var board = new THREE.InstancedMesh(boardGeo, boardMat, cells.length);
    board.instanceMatrix.setUsage(THREE.DynamicDrawUsage);
    scene.add(board);
    for (var i = 0; i < cells.length; i++) {
      cA.setHex(cells[i].startHex);
      board.setColorAt(i, cA);
    }

    // ── trunk, branches, clumped canopy ───────────────────────────
    function shapeTree(list) {
      list.sort(function (a, b) {
        return (a.flat.x * a.flat.x + a.flat.z * a.flat.z) -
               (b.flat.x * b.flat.x + b.flat.z * b.flat.z);
      });

      var trunkH  = N * 0.34;
      var canopyR = N * 0.36;
      var canopyY = trunkH + canopyR * 0.46;

      var trunkCount  = Math.max(14, Math.round(list.length * 0.10));
      var branchCount = Math.max(10, Math.round(list.length * 0.06));

      // five overlapping lobes: one clean sphere reads as a lollipop
      var lobes = [];
      for (var l = 0; l < 5; l++) {
        var a = (l / 5) * Math.PI * 2 + rnd() * 0.7;
        var d = l === 0 ? 0 : canopyR * (0.36 + rnd() * 0.26);
        lobes.push({
          x: Math.cos(a) * d,
          y: canopyY + (l === 0 ? canopyR * 0.18 : (rnd() - 0.45) * canopyR * 0.52),
          z: Math.sin(a) * d,
          r: canopyR * (l === 0 ? 0.70 : 0.44 + rnd() * 0.2)
        });
      }

      // Real foliage grows in clusters off twig ends, not as an even mist.
      // Scattering leaves uniformly through the lobes is what made the old
      // canopy read as static — this seeds clump centres first, then hangs
      // leaves off them.
      var clumps = [];
      var clumpCount = Math.max(18, Math.round(list.length * 0.055));
      for (var q = 0; q < clumpCount; q++) {
        var lb = lobes[(rnd() * lobes.length) | 0];
        var cu = rnd() * 2 - 1, cv = rnd() * Math.PI * 2;
        var cs = Math.sqrt(1 - cu * cu);
        var cr = lb.r * (0.52 + 0.48 * Math.pow(rnd(), 0.4));
        clumps.push({
          x: lb.x + Math.cos(cv) * cs * cr,
          y: lb.y + cu * cr * 0.76,
          z: lb.z + Math.sin(cv) * cs * cr,
          r: canopyR * (0.11 + rnd() * 0.11)
        });
      }

      var minLeafY = Infinity, maxLeafY = -Infinity;
      var leaves = [];

      for (var i = 0; i < list.length; i++) {
        var c = list[i], pos, hex = null;

        if (i < trunkCount) {
          var t = i / trunkCount;
          var ang = rnd() * Math.PI * 2;
          var rad = lerp(1.15, 0.44, t) * (0.55 + rnd() * 0.6);   // tapers upward
          var lean = t * t * 0.9;
          pos = { x: Math.cos(ang) * rad + lean * 0.5,
                  y: 0.35 + t * trunkH,
                  z: Math.sin(ang) * rad - lean * 0.3 };
          hex = pick(PALETTE.bark, rnd);
          c.part = "bark";
          c.size = 1.05 + rnd() * 0.3;

        } else if (i < trunkCount + branchCount) {
          // limbs from the trunk top out toward the lobes, so the canopy is
          // attached to something instead of floating
          var bi = (i - trunkCount) % 4;
          var target = lobes[1 + bi] || lobes[0];
          var bt = 0.25 + rnd() * 0.75;
          pos = {
            x: lerp(0, target.x * 0.75, bt) + (rnd() - 0.5) * 0.5,
            y: lerp(trunkH * 0.72, target.y * 0.82, bt) + (rnd() - 0.5) * 0.5,
            z: lerp(0, target.z * 0.75, bt) + (rnd() - 0.5) * 0.5
          };
          hex = pick(PALETTE.bark, rnd);
          c.part = "bark";
          c.size = 0.78 + rnd() * 0.3;

        } else {
          var cl = clumps[(i - trunkCount - branchCount) % clumps.length];
          var u = rnd() * 2 - 1, v = rnd() * Math.PI * 2;
          var s = Math.sqrt(1 - u * u);
          var r = cl.r * (0.35 + 0.65 * Math.pow(rnd(), 0.55));
          pos = { x: cl.x + Math.cos(v) * s * r,
                  y: cl.y + u * r * 0.85,
                  z: cl.z + Math.sin(v) * s * r };
          c.part = "leaf";
          c.size = 0.58 + rnd() * 0.46;     // mixed leaf sizes, not one cube
          leaves.push(c);
          if (pos.y < minLeafY) minLeafY = pos.y;
          if (pos.y > maxLeafY) maxLeafY = pos.y;
        }

        c.tree = pos;
        if (hex !== null) c.startHex = hex;
        c.spin = { x: (rnd() - 0.5) * 2.6, y: (rnd() - 0.5) * 2.6, z: (rnd() - 0.5) * 2.6 };
        c.flut = {
          a: c.part === "leaf" ? 0.10 + rnd() * 0.17 : 0,
          s: 0.7 + rnd() * 0.9,
          p: rnd() * Math.PI * 2
        };
        if (pos.y > maxTreeY) maxTreeY = pos.y;
      }

      // Shade the canopy by exposure. A flat green mass is the single biggest
      // reason voxel foliage looks fake: outer and upper leaves catch light,
      // interior ones sit in shadow.
      var yRange = Math.max(0.001, maxLeafY - minLeafY);
      for (var k = 0; k < leaves.length; k++) {
        var lf = leaves[k], p = lf.tree;
        var hNorm = clamp01((p.y - minLeafY) / yRange);
        var out = clamp01(Math.sqrt(p.x * p.x + p.z * p.z) / canopyR);
        var exposure = clamp01(0.52 * hNorm + 0.48 * out + (rnd() - 0.5) * 0.2);

        var col;
        if (exposure < 0.45)      col = mixHex(PALETTE.leafShade, PALETTE.leafMid, exposure / 0.45);
        else if (exposure < 0.82) col = mixHex(PALETTE.leafMid, PALETTE.leafSun, (exposure - 0.45) / 0.37);
        else                      col = mixHex(PALETTE.leafSun, PALETTE.leafHigh, (exposure - 0.82) / 0.18);

        if (rnd() < 0.035) col = pick(PALETTE.leafAccent, rnd);   // a few turning
        lf.startHex = col;
      }

      for (var j = 0; j < list.length; j++) {
        list[j].delay = 0.36 * clamp01(list[j].tree.y / maxTreeY);  // canopy drains down
      }
    }

    // ══════════════════════════════════════════════════════════════
    // grass: thin blades on the border, pivoting at their base
    // ══════════════════════════════════════════════════════════════
    var bladeGeo = new THREE.BoxGeometry(1, 1, 1);
    bladeGeo.translate(0, 0.5, 0);        // pivot at the ground, so sway looks rooted
    var blades = [];

    for (var bz = 0; bz < span; bz++) {
      for (var bx = 0; bx < span; bx++) {
        var ofx = bx - RIM, ofz = bz - RIM;
        if (ofx >= 0 && ofz >= 0 && ofx < field && ofz < field) continue;
        var count = 2 + ((rnd() * 2) | 0);
        for (var k2 = 0; k2 < count; k2++) {
          blades.push({
            x: bx - half + (rnd() - 0.5) * 0.8,
            z: bz - half + (rnd() - 0.5) * 0.8,
            h: 0.55 + rnd() * 1.25,
            w: 0.14 + rnd() * 0.1,
            hex: pick(PALETTE.grass, rnd),
            phase: rnd() * Math.PI * 2,
            speed: 0.7 + rnd() * 0.7,
            amp: 0.06 + rnd() * 0.09,
            base: 0.3
          });
        }
      }
    }

    var grass = new THREE.InstancedMesh(bladeGeo, boardMat, blades.length);
    grass.instanceMatrix.setUsage(THREE.DynamicDrawUsage);
    scene.add(grass);
    for (var b = 0; b < blades.length; b++) {
      cA.setHex(blades[b].hex);
      grass.setColorAt(b, cA);
    }

    function swayGrass(time) {
      for (var i = 0; i < blades.length; i++) {
        var bl = blades[i];
        var a = Math.sin(time * 0.0011 * bl.speed + bl.phase) * bl.amp;
        dummy.position.set(bl.x, bl.base, bl.z);
        dummy.rotation.set(a * 0.6, 0, a);
        dummy.scale.set(bl.w, bl.h, bl.w);
        dummy.updateMatrix();
        grass.setMatrixAt(i, dummy.matrix);
      }
      grass.instanceMatrix.needsUpdate = true;
    }

    // ══════════════════════════════════════════════════════════════
    // butterflies — cleared before the code settles, since a wing parked
    // over a module would break the scan
    // ══════════════════════════════════════════════════════════════
    var flock = [];
    if (!reduceMotion) buildFlock(6);

    function buildFlock(n) {
      var wingGeo = new THREE.PlaneGeometry(0.62, 0.5);
      wingGeo.translate(0.31, 0, 0);
      var bodyGeo = new THREE.BoxGeometry(0.09, 0.09, 0.42);

      for (var i = 0; i < n; i++) {
        var wingMat = new THREE.MeshLambertMaterial({
          color: pick(PALETTE.wings, rnd), side: THREE.DoubleSide, transparent: true
        });
        var bodyMat = new THREE.MeshLambertMaterial({ color: 0x4a3a2a, transparent: true });

        var g = new THREE.Group();
        var right = new THREE.Mesh(wingGeo, wingMat);
        var left = new THREE.Mesh(wingGeo, wingMat);
        left.scale.x = -1;
        g.add(right, left, new THREE.Mesh(bodyGeo, bodyMat));
        scene.add(g);

        flock.push({
          group: g, right: right, left: left, mats: [wingMat, bodyMat],
          rx: N * (0.30 + rnd() * 0.22),
          rz: N * (0.30 + rnd() * 0.22),
          y:  N * (0.24 + rnd() * 0.34),
          bob: 0.7 + rnd() * 1.3,
          phase: rnd() * Math.PI * 2,
          speed: 0.00022 + rnd() * 0.00020,
          flap: 9 + rnd() * 5
        });
      }
    }

    function flyFlock(time, p) {
      var fade = clamp01(1 - p / 0.55);      // gone well before the modules land
      for (var i = 0; i < flock.length; i++) {
        var f = flock[i];
        if (fade <= 0) { f.group.visible = false; continue; }
        f.group.visible = true;

        var a = time * f.speed + f.phase;
        var escape = (1 - fade) * N * 0.5;
        var x = Math.cos(a) * (f.rx + escape);
        var z = Math.sin(a * 1.3) * (f.rz + escape);
        var y = f.y + Math.sin(a * 2.6) * f.bob + (1 - fade) * N * 0.45;
        var nx = Math.cos(a + 0.05) * (f.rx + escape);
        var nz = Math.sin((a + 0.05) * 1.3) * (f.rz + escape);

        f.group.position.set(x, y, z);
        f.group.rotation.y = Math.atan2(nx - x, nz - z);

        var beat = Math.abs(Math.sin(time * 0.001 * f.flap)) * 1.15 + 0.15;
        f.right.rotation.y = -beat;
        f.left.rotation.y = beat;
        for (var m = 0; m < f.mats.length; m++) f.mats[m].opacity = fade;
      }
    }

    // ══════════════════════════════════════════════════════════════
    // placement. p = 0 is the tree, p = 1 is the flat code
    // ══════════════════════════════════════════════════════════════
    function placeCell(c, p, time) {
      var flatW = c.kind === "dark" ? 0.99 : 0.955;   // dark modules nearly touch
                                                      // so neighbours merge

      if (!c.tree) {
        dummy.position.set(c.flat.x, c.flat.y, c.flat.z);
        dummy.rotation.set(0, 0, 0);
        dummy.scale.set(flatW, c.height, flatW);
      } else {
        var lp = clamp01((p - c.delay) / (1 - c.delay));
        var e = easeOut(lp);
        var alive = 1 - e;                            // how "tree" it still is

        var x = lerp(c.tree.x, c.flat.x, easeInOut(lp));
        var y = lerp(c.tree.y, c.flat.y, easeIn(lp));  // accelerates like a fall
        var z = lerp(c.tree.z, c.flat.z, easeInOut(lp));

        if (alive > 0.001 && c.flut.a > 0) {
          // per-leaf flutter plus a whole-canopy sway that scales with height,
          // so the crown moves more than the lower leaves
          var amp = c.flut.a * alive;
          var t1 = time * 0.001 * c.flut.s + c.flut.p;
          var sway = Math.sin(time * 0.00058) * 0.42 * alive * (c.tree.y / maxTreeY);
          x += Math.sin(t1) * amp + sway;
          y += Math.sin(t1 * 1.31 + 1.7) * amp * 0.65;
          z += Math.cos(t1 * 0.87) * amp + sway * 0.55;
        }

        dummy.position.set(x, y, z);
        var tumble = alive;
        dummy.rotation.set(c.spin.x * tumble, c.spin.y * tumble, c.spin.z * tumble);

        var w = lerp(c.size, flatW, e);
        dummy.scale.set(w, lerp(c.size, c.height, e), w);

        if (c.startHex !== c.finalHex) {
          cA.setHex(c.startHex); cB.setHex(c.finalHex);
          cMix.copy(cA).lerp(cB, e);
          board.setColorAt(c.idx, cMix);
        }
      }

      dummy.updateMatrix();
      board.setMatrixAt(c.idx, dummy.matrix);
    }

    function writeAll(p, time) {
      for (var i = 0; i < cells.length; i++) placeCell(cells[i], p, time);
      board.instanceMatrix.needsUpdate = true;
      if (board.instanceColor) board.instanceColor.needsUpdate = true;
    }

    // while the tree just stands there only the tree voxels are moving, so
    // there's no reason to rewrite the ground every frame
    function writeTree(p, time) {
      for (var i = 0; i < darkCells.length; i++) placeCell(darkCells[i], p, time);
      board.instanceMatrix.needsUpdate = true;
    }

    // ══════════════════════════════════════════════════════════════
    // camera: isometric diorama → dead-on top-down
    // ══════════════════════════════════════════════════════════════
    function placeCamera(p, time) {
      var e = easeInOut(p);
      var drift = Math.sin(time * 0.00026) * 6 * (1 - e);   // idle sway, tree only

      var az = THREE.MathUtils.degToRad(lerp(45 + drift, 0, e));
      var el = THREE.MathUtils.degToRad(lerp(33, 88, e));   // 88, not 90: lookAt
      var r = 320;                                          // breaks parallel to up

      camera.position.set(
        r * Math.cos(el) * Math.sin(az),
        r * Math.sin(el),
        r * Math.cos(el) * Math.cos(az)
      );
      camera.lookAt(0, 0, 0);

      var view = lerp(span * 1.58, span * 1.02, e);
      var aspect = (stage.clientWidth || 1) / (stage.clientHeight || 1);
      camera.left = (-view / 2) * aspect;
      camera.right = (view / 2) * aspect;
      camera.top = view / 2;
      camera.bottom = -view / 2;
      camera.updateProjectionMatrix();
    }

    // ══════════════════════════════════════════════════════════════
    // timeline
    // ══════════════════════════════════════════════════════════════
    var anim = { p: 0, from: 0, to: 0, t0: 0, dur: 0, running: false };
    var timer = null, visible = true, boardDirty = true;

    function animateTo(target, dur) {
      boardDirty = true;
      anim.from = anim.p; anim.to = target;
      anim.t0 = performance.now(); anim.dur = dur;
      anim.running = true;
    }
    function schedule(fn, ms) { clearTimeout(timer); timer = setTimeout(fn, ms); }

    function collapse() {
      animateTo(1, cfg.fall);
      if (cfg.loop > 0) schedule(regrow, cfg.fall + cfg.loop);
    }
    function regrow() {
      animateTo(0, cfg.grow);
      schedule(collapse, cfg.grow + cfg.delay);
    }

    function resize() { renderer.setSize(stage.clientWidth, stage.clientHeight, false); }
    window.addEventListener("resize", resize);

    // rendering pause only — the collapse is gated separately, below
    var tickRaf = null;
    if ("IntersectionObserver" in window) {
      new IntersectionObserver(function (entries) {
        visible = entries[0].isIntersecting;
        if (visible && !tickRaf) tickRaf = requestAnimationFrame(tick);
      }, { threshold: 0.01 }).observe(stage);
    }

    // ── scroll gate ───────────────────────────────────────────────
    // intersectionRatio alone can't express "fully visible": a frame taller
    // than the viewport never reaches 1, so a threshold:1 observer would sit
    // there and never fire.
    //
    // Uses a coverage ratio rather than a fixed pixel pad: at non-integer
    // browser zoom levels getBoundingClientRect() and innerHeight/innerWidth
    // round to CSS pixels independently, and when the frame is taller than
    // the viewport (zoomed in enough that it no longer fits) the pixel
    // window where the old top<=2/bottom>=vh-2 check held true shrank to a
    // handful of px — easy for a single scroll/momentum frame to jump past
    // entirely, so it could sit on the tree forever no matter how you
    // scrolled. A percentage-of-coverage check keeps a wide, scale-invariant
    // trigger window at any zoom level.
    function fullyOnScreen() {
      var r = stage.getBoundingClientRect();
      var vh = window.innerHeight || document.documentElement.clientHeight;
      var vw = window.innerWidth || document.documentElement.clientWidth;
      if (r.width <= 0 || r.height <= 0) return false;

      var visibleH = Math.min(r.bottom, vh) - Math.max(r.top, 0);
      var visibleW = Math.min(r.right, vw) - Math.max(r.left, 0);
      if (visibleH <= 0 || visibleW <= 0) return false;

      var coverage = 0.5;
      return visibleH / Math.min(r.height, vh) >= coverage &&
             visibleW / Math.min(r.width, vw) >= coverage;
    }

    var settled = false;
    function gate() {
      var now = fullyOnScreen();
      if (now === settled) return;
      settled = now;
      clearTimeout(timer);
      if (now) schedule(collapse, cfg.delay);
      else if (anim.p > 0) animateTo(0, Math.max(400, cfg.grow * anim.p));
    }

    var queued = false;
    function queueGate() {
      if (queued) return;
      queued = true;
      requestAnimationFrame(function () { queued = false; gate(); });
    }
    window.addEventListener("scroll", queueGate, { passive: true });
    window.addEventListener("resize", queueGate);

    // ══════════════════════════════════════════════════════════════
    function tick(now) {
      if (!visible) { tickRaf = null; return; }
      tickRaf = requestAnimationFrame(tick);
      if (!visible) return;

      if (anim.running) {
        var k = clamp01((now - anim.t0) / anim.dur);
        anim.p = lerp(anim.from, anim.to, k);
        if (k >= 1) { anim.running = false; boardDirty = true; }
      }

      if (anim.running || boardDirty) { writeAll(anim.p, now); boardDirty = false; }
      else if (anim.p < 1) { writeTree(anim.p, now); }
      // once flat, nothing moves on the board at all

      if (!reduceMotion) swayGrass(now);
      if (flock.length) flyFlock(now, anim.p);
      if (hasOverlay) overlay.style.opacity = easeOut(clamp01(1 - anim.p / 0.3));
      placeCamera(anim.p, now);
      renderer.render(scene, camera);
    }

    resize();
    writeAll(0, 0);
    swayGrass(0);

    if (reduceMotion) {
      anim.p = 1;                      // straight to the code, no growing or flying
      writeAll(1, 0);
      boardDirty = false;
      if (hasOverlay) overlay.style.opacity = 0;
    } else if (cfg.trigger === "load") {
      schedule(collapse, cfg.delay);
    } else {
      queueGate();                     // may already be in view on first paint
    }

    tickRaf = requestAnimationFrame(tick);
  }

  // ════════════════════════════════════════════════════════════════
  function boot() {
    if (typeof THREE === "undefined") return;
    var nodes = document.querySelectorAll("[data-voxel-qr]");
    for (var i = 0; i < nodes.length; i++) VoxelQR(nodes[i]);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();

/* ══════════════════════════════════════════════════════════════════
   ATTRIBUTES

   data-url          link to encode. Printed by your backend; there is no
                     input on the page, so a visitor cannot swap it.
   data-matrix       optional. A grid your backend already built: rows of
                     0/1 joined with "|". Overrides data-url, and lets you
                     drop the qrcode-generator script entirely.
   data-title        heading above the frame. Omit or "" for none.
   data-subtitle     line under that heading. Same.
   data-overlay      caption drawn ON the scene while the tree stands. Fades
                     out as the collapse begins, so it never covers the code.
   data-overlay-sub  second line under the caption.
   data-trigger      "scroll" (default) waits until the whole frame is on
                     screen, holds data-delay, then collapses; scrolling any
                     edge off winds it back to the tree.
                     "load" collapses on page load instead.
   data-delay        ms in view before collapsing. Default 2600.
   data-fall         collapse duration, ms. Default 2400.
   data-grow         regrow duration, ms. Default 1900.
   data-loop         ms to hold the code before regrowing. Omit = stay flat.
   ══════════════════════════════════════════════════════════════════ */
