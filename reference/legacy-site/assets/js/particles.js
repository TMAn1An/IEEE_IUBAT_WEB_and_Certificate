/* ---------------------------------------------------------
   particles.js — lightweight animated background
   Creates a fixed full-screen canvas with drifting dots
   and faint connection lines. No dependencies.
   Respects prefers-reduced-motion. --------------------------------------------------------- */
(function () {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  const COUNT   = 50;
  const CONNECT  = 120;
  const SPEED    = 0.3;
  const DOT_SIZE = 2;
  const COLOR    = [0, 98, 155];          /* IEEE blue RGB */

  const canvas = document.createElement('canvas');
  canvas.id = 'particles-bg';
  canvas.style.cssText = 'position:fixed;inset:0;z-index:-1;pointer-events:none;width:100%;height:100%';
  document.body.prepend(canvas);

  const ctx = canvas.getContext('2d');
  let w, h, particles, rafId = null;

  function resize() {
    w = canvas.width  = window.innerWidth;
    h = canvas.height = window.innerHeight;
  }

  function init() {
    resize();
    particles = [];
    for (let i = 0; i < COUNT; i++) {
      particles.push({
        x:  Math.random() * w,
        y:  Math.random() * h,
        vx: (Math.random() - 0.5) * SPEED,
        vy: (Math.random() - 0.5) * SPEED,
        r:  Math.random() * DOT_SIZE + 0.8
      });
    }
  }

  function draw() {
    ctx.clearRect(0, 0, w, h);

    /* connections */
    ctx.lineWidth = 0.6;
    for (let i = 0; i < particles.length; i++) {
      for (let j = i + 1; j < particles.length; j++) {
        const dx = particles[i].x - particles[j].x;
        const dy = particles[i].y - particles[j].y;
        const d  = Math.sqrt(dx * dx + dy * dy);
        if (d < CONNECT) {
          const alpha = (1 - d / CONNECT) * 0.12;
          ctx.strokeStyle = `rgba(${COLOR[0]},${COLOR[1]},${COLOR[2]},${alpha})`;
          ctx.beginPath();
          ctx.moveTo(particles[i].x, particles[i].y);
          ctx.lineTo(particles[j].x, particles[j].y);
          ctx.stroke();
        }
      }
    }

    /* dots */
    for (const p of particles) {
      ctx.beginPath();
      ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
      ctx.fillStyle = `rgba(${COLOR[0]},${COLOR[1]},${COLOR[2]},0.25)`;
      ctx.fill();
    }
  }

  function tick() {
    for (const p of particles) {
      p.x += p.vx;
      p.y += p.vy;
      if (p.x < 0) p.x = w;
      if (p.x > w) p.x = 0;
      if (p.y < 0) p.y = h;
      if (p.y > h) p.y = 0;
    }
    draw();
    rafId = requestAnimationFrame(tick);
  }

  function start() { if (!rafId) rafId = requestAnimationFrame(tick); }
  function stop()  { if (rafId) { cancelAnimationFrame(rafId); rafId = null; } }

  document.addEventListener('visibilitychange', function () {
    document.hidden ? stop() : start();
  });

  window.addEventListener('resize', resize);
  init();
  start();
})();
