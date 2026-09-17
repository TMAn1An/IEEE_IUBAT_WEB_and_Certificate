/* =========================================================================
   IEEE IUBAT Student Branch — main.js
   Plain vanilla JavaScript. No dependencies, no build step, no cookies,
   no third-party trackers; one localStorage flag for the dismissible alert.
   ---------------------------------------------------------------------
   EVENT DATES AND THE EVENT-REGISTRATION FORM URL ARE IN
   includes/config.php. The footer server-renders them into
   window.IEEE_EVENT; main.js falls back to the values below so the page
   still works if it is opened as a static file.
   ========================================================================= */
const EVENT = window.IEEE_EVENT || {
  earlyBirdDeadline: '2026-08-28T23:59:59+06:00',
  regularDeadline:   '2026-09-01T23:59:59+06:00',
  eventStart:        '2026-09-04T00:00:00+06:00',
  eventEnd:          '2026-09-05T23:59:59+06:00',
  formUrl:           'https://forms.gle/AkKmzLBX8t84c4GE8'
};

/* ------------------------------------------------------- 1. Main navigation */
(function nav() {
  const burger = document.querySelector('.burger');
  const menu = document.getElementById('site-nav');
  if (!menu) return;

  const items = [...menu.querySelectorAll('.nav__item.has-panel')];
  const isDesktop = () => window.matchMedia('(min-width: 901px)').matches;

  const closeAllPanels = except => {
    items.forEach(item => {
      if (item === except) return;
      item.classList.remove('is-open');
      item.querySelector('.nav__link').setAttribute('aria-expanded', 'false');
    });
  };

  items.forEach(item => {
    const trigger = item.querySelector('.nav__link');

    // Click (and Enter/Space, which fire click on a button) is the only way to
    // open a panel. Hover-to-open was removed: it fought the click toggle and
    // opened menus the user only meant to move the pointer past.
    trigger.addEventListener('click', e => {
      e.preventDefault();
      const open = !item.classList.contains('is-open');
      closeAllPanels(item);
      item.classList.toggle('is-open', open);
      trigger.setAttribute('aria-expanded', String(open));
    });
  });

  // close the panel when focus leaves it entirely
  document.addEventListener('focusin', e => {
    if (!isDesktop()) return;
    const inside = items.find(i => i.contains(e.target));
    closeAllPanels(inside);
  });

  document.addEventListener('click', e => {
    if (!menu.contains(e.target) && (!burger || !burger.contains(e.target))) closeAllPanels(null);
  });

  document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    closeAllPanels(null);
    if (burger && menu.classList.contains('is-open')) {
      menu.classList.remove('is-open');
      burger.setAttribute('aria-expanded', 'false');
      burger.focus();
    }
  });

  if (burger) {
    burger.addEventListener('click', () => {
      const open = menu.classList.toggle('is-open');
      burger.setAttribute('aria-expanded', String(open));
      if (!open) closeAllPanels(null);
    });
    // navigating away closes the drawer
    menu.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
      menu.classList.remove('is-open');
      burger.setAttribute('aria-expanded', 'false');
    }));
    window.addEventListener('resize', () => {
      if (isDesktop()) {
        menu.classList.remove('is-open');
        burger.setAttribute('aria-expanded', 'false');
        closeAllPanels(null);
      }
    });
  }
})();

/* --------------------------------------------------- 2. Header scroll state */
(function stickyHeader() {
  const header = document.querySelector('.header');
  if (!header) return;
  const onScroll = () => header.classList.toggle('is-stuck', window.scrollY > 8);
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });
})();

/* -------------------------------------------- 3. Event sub-nav highlighting */
(function subnavSpy() {
  const links = [...document.querySelectorAll('.subnav__links a[href^="#"]')];
  if (!links.length || !('IntersectionObserver' in window)) return;

  const map = new Map();
  links.forEach(a => {
    const section = document.querySelector(a.getAttribute('href'));
    if (section) map.set(section, a);
  });

  const io = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      links.forEach(a => a.classList.remove('is-active'));
      const active = map.get(entry.target);
      if (active) active.classList.add('is-active');
    });
  }, { rootMargin: '-45% 0px -50% 0px' });

  map.forEach((_, section) => io.observe(section));
})();

/* ---------------------------------------------------------- 4. Alert banner */
(function alertBar() {
  const bar = document.querySelector('.alert');
  if (!bar) return;
  if (localStorage.getItem('alert-hta26-earlybird') === '1') { bar.remove(); return; }
  const close = bar.querySelector('.alert__x');
  if (close) close.addEventListener('click', () => {
    localStorage.setItem('alert-hta26-earlybird', '1');
    bar.remove();
  });
})();

/* ------------------------------------------------------------- 5. Countdown */
/* Self-updating across the whole registration window so the site needs no
   manual edit between now and the event: while it counts, the clock shows the
   active pricing window (early-bird, then regular) and, once regular
   registration ends, a "closed" message. Any twin title/date elements marked
   `data-clock-title` / `data-clock-date` are updated to match the active phase. */
(function countdown() {
  const boxes = [...document.querySelectorAll('[data-clock]')];
  if (!boxes.length) return;

  const titles = [...document.querySelectorAll('[data-clock-title]')];
  const dates = [...document.querySelectorAll('[data-clock-date]')];
  const pad = n => String(n).padStart(2, '0');

  const eb = new Date(EVENT.earlyBirdDeadline).getTime();
  const reg = new Date(EVENT.regularDeadline).getTime();
  const start = new Date(EVENT.eventStart).getTime();
  const end = new Date(EVENT.eventEnd).getTime();
  // Render the deadline's calendar date in Bangladesh Standard Time (Asia/Dhaka),
  // not the visitor's local zone, so the label always shows the Dhaka date the
  // window closes. The countdown math itself is timezone-agnostic (explicit
  // +06:00 offsets vs Date.now(), both absolute instants).
  const longDate = ts => new Intl.DateTimeFormat('en-GB', { timeZone: 'Asia/Dhaka', day: 'numeric', month: 'long', year: 'numeric' }).format(ts);

  // Mirrors eventPhase() in includes/components.php — same five phases, same
  // thresholds, so the ticking clock and the server-rendered CTAs/banner never
  // disagree. Kept in sync by hand, like every other value this file falls
  // back to from window.IEEE_EVENT.
  const phase = now => {
    if (now < eb) return 'early';
    if (now <= reg) return 'regular';
    if (now < start) return 'closed';
    if (now <= end) return 'live';
    return 'ended';
  };

  const render = () => {
    const now = Date.now();
    const p = phase(now);

    if (p === 'closed' || p === 'live' || p === 'ended') {
      const messages = {
        closed: 'Registration for this event is now closed.',
        live: 'The event is happening now &mdash; see the schedule below.',
        ended: 'This event has concluded. Thank you to everyone who took part.'
      };
      const titleTexts = {
        closed: 'Registration closed',
        live: 'Happening now',
        ended: 'Event concluded'
      };
      boxes.forEach(b => { b.innerHTML = `<p class="clock--over">${messages[p]}</p>`; });
      titles.forEach(t => { t.textContent = titleTexts[p]; });
      dates.forEach(d => { d.textContent = ''; });
      return true;
    }

    const target = p === 'early' ? eb : reg;
    const when = longDate(target);
    const label = p === 'early' ? 'Early-bird registration closes' : 'Regular registration closes';

    titles.forEach(t => { t.textContent = label; });
    dates.forEach(d => { d.textContent = when; });

    const gap = target - now;
    const html = [
      [Math.floor(gap / 86400000), 'Days'],
      [pad(Math.floor(gap / 3600000) % 24), 'Hours'],
      [pad(Math.floor(gap / 60000) % 60), 'Minutes'],
      [pad(Math.floor(gap / 1000) % 60), 'Seconds']
    ].map(([n, l]) => `<div class="clock__u"><div class="clock__n">${n}</div><div class="clock__l">${l}</div></div>`).join('');

    boxes.forEach(b => { b.innerHTML = html; });
    return false;
  };

  if (!render()) {
    const timer = setInterval(() => { if (render()) clearInterval(timer); }, 1000);
  }
})();

/* --------------------------------------------------------- 6. Scroll reveal */
(function reveal() {
  const items = document.querySelectorAll('.rv');
  if (!items.length) return;

  if (!('IntersectionObserver' in window) ||
      window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    items.forEach(el => el.classList.add('in'));
    return;
  }

  const io = new IntersectionObserver((entries, obs) => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      entry.target.classList.add('in');
      obs.unobserve(entry.target);
    });
  }, { threshold: 0.1, rootMargin: '0px 0px -50px' });

  items.forEach((el, i) => {
    el.style.transitionDelay = (i % 4) * 60 + 'ms';
    io.observe(el);
  });
})();

/* ------------------------------------------------------- 7. Poster lightbox */
(function lightbox() {
  const lb = document.getElementById('lightbox');
  if (!lb) return;

  const img = lb.querySelector('img');
  const closeBtn = lb.querySelector('.lb__x');
  let lastFocus = null;

  const open = src => {
    lastFocus = document.activeElement;
    img.src = src;
    lb.classList.add('is-open');
    document.body.style.overflow = 'hidden';
    closeBtn.focus();
  };

  const close = () => {
    lb.classList.remove('is-open');
    document.body.style.overflow = '';
    img.src = '';
    if (lastFocus) lastFocus.focus();
  };

  document.querySelectorAll('[data-zoom]').forEach(el => {
    el.setAttribute('tabindex', '0');
    el.setAttribute('role', 'button');
    el.addEventListener('click', () => open(el.dataset.zoom));
    el.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(el.dataset.zoom); }
    });
  });

  closeBtn.addEventListener('click', close);
  lb.addEventListener('click', e => { if (e.target === lb) close(); });
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && lb.classList.contains('is-open')) close();
  });
})();

/* ------------------------------------------------ 7b. Poster carousel */
(function posterCarousel() {
  const stage = document.querySelector('.poster-carousel');
  if (!stage) return;

  const cards = [...stage.children].filter(el => el.classList.contains('poster-card'));
  if (cards.length < 2) return;

  // On phones (≤560px) the posters are a static vertical stack (all three
  // readable, DOM order) — no rotating fan, no dots. Only run the sliding fan
  // above that width.
  const phone = window.matchMedia('(max-width: 560px)');
  if (phone.matches) return;

  const POS = ['poster-card--left', 'poster-card--center', 'poster-card--right'];
  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)');

  // order[i] = index of the card currently sitting in slot i (left, center, right)
  const order = cards.map((_, i) => i);

  const apply = () => {
    cards.forEach(card => POS.forEach(p => card.classList.remove(p)));
    order.forEach((cardIdx, slot) => cards[cardIdx].classList.add(POS[slot]));
  };

  // Slide the whole strip left one slot: right -> center -> left, front wraps back.
  const advance = () => { order.unshift(order.pop()); apply(); };

  apply();
  if (reduce.matches) return;

  let interval = null;
  let visible = true;

  // Swap continuously while the posters are on screen; no hover pausing and
  // no width gate, so the carousel rotates at any viewport size.
  const sync = () => {
    clearInterval(interval);
    interval = null;
    if (visible) interval = setInterval(advance, 4000);
  };

  window.addEventListener('resize', sync);

  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver(entries => {
      entries.forEach(e => { visible = e.isIntersecting; });
      sync();
    }, { threshold: 0 });
    io.observe(stage);
  }

  sync();
})();

/* --------------------------------------------------- 8. Copy bKash number */
(function copyNumber() {
  document.querySelectorAll('.copy').forEach(btn => {
    btn.addEventListener('click', async () => {
      const value = btn.dataset.copy || '';
      const done = () => {
        const original = btn.textContent;
        btn.textContent = 'Copied';
        setTimeout(() => { btn.textContent = original; }, 1600);
      };
      try {
        await navigator.clipboard.writeText(value);
        done();
      } catch (err) {
        const tmp = document.createElement('input');
        tmp.value = value;
        document.body.appendChild(tmp);
        tmp.select();
        document.execCommand('copy');
        tmp.remove();
        done();
      }
    });
  });
})();

/* ------------------------------------------------------- 9. Count-up stats */
(function counters() {
  const nums = document.querySelectorAll('[data-count]');
  if (!nums.length || !('IntersectionObserver' in window)) return;

  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  const io = new IntersectionObserver((entries, obs) => {
    entries.forEach(entry => {
      if (!entry.isIntersecting) return;
      const el = entry.target;
      const end = parseInt(el.dataset.count, 10);
      const suffix = el.dataset.suffix || '';
      obs.unobserve(el);

      if (reduce) { el.textContent = end + suffix; return; }

      const start = performance.now();
      const tick = now => {
        const p = Math.min((now - start) / 1000, 1);
        el.textContent = Math.round(end * (1 - Math.pow(1 - p, 3))) + suffix;
        if (p < 1) requestAnimationFrame(tick);
      };
      requestAnimationFrame(tick);
    });
  }, { threshold: 0.5 });

  nums.forEach(n => io.observe(n));
})();

/* ------------------------------------------------- 10. Footer copyright year */
(function year() {
  document.querySelectorAll('[data-year]').forEach(el => {
    el.textContent = new Date().getFullYear();
  });
})();

/* -------------------------------------------- 11. Contact form -> mailto: */
(function contactMailto() {
  // Any form carrying data-mailto="you@example.com" opens the visitor's mail
  // app on submit, with subject/body pre-filled as query parameters.
  document.querySelectorAll('form[data-mailto]').forEach(form => {
    form.addEventListener('submit', e => {
      e.preventDefault();
      const data = new FormData(form);
      const subject = (data.get('subject') || '').toString().trim();
      const message = (data.get('message') || '').toString().trim();

      // mailto:...?...&subject=&body= — like HTTP query params, so the mail
      // app opens with the message pre-filled for the visitor.
      window.location.href = 'mailto:' + form.dataset.mailto
        + '?subject=' + encodeURIComponent(subject)
        + '&body=' + encodeURIComponent(message);
    });
  });
})();
