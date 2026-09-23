/* =============================================================================
   Gilgal Reward Outreach — front-end behaviour

   Everything here is progressive: with JS disabled the site still reads,
   navigates, filters (server-side) and submits every form.
   ============================================================================= */
(function () {
  'use strict';

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* --- Mobile navigation --------------------------------------------------- */
  function initNav() {
    var toggle = document.querySelector('.nav-toggle');
    var nav = document.getElementById('mainNav');
    if (!toggle || !nav) return;

    toggle.addEventListener('click', function () {
      var open = nav.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    // Close the menu when a link is chosen, or on Escape.
    nav.addEventListener('click', function (e) {
      if (e.target.closest('a') && nav.classList.contains('is-open')) {
        nav.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && nav.classList.contains('is-open')) {
        nav.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.focus();
      }
    });
  }

  /* --- Sticky header shadow ------------------------------------------------ */
  function initHeader() {
    var header = document.querySelector('.site-header');
    if (!header) return;

    var onScroll = function () {
      header.classList.toggle('is-stuck', window.scrollY > 12);
    };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  /* --- Scroll reveal ------------------------------------------------------- */
  function initReveal() {
    var items = document.querySelectorAll('.reveal');
    if (!items.length) return;

    if (reduceMotion || !('IntersectionObserver' in window)) {
      items.forEach(function (el) { el.classList.add('is-in'); });
      return;
    }

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-in');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px' });

    items.forEach(function (el) { observer.observe(el); });
  }

  /* --- Count-up on impact numbers ------------------------------------------ */
  function initCounters() {
    var counters = document.querySelectorAll('[data-count-to]');
    if (!counters.length) return;

    var run = function (el) {
      var target = parseInt(el.getAttribute('data-count-to'), 10) || 0;
      if (reduceMotion) { el.textContent = target.toLocaleString(); return; }

      var duration = 1400;
      var started = null;

      var step = function (now) {
        if (started === null) started = now;
        var progress = Math.min((now - started) / duration, 1);
        // ease-out so the number settles rather than stopping dead
        var eased = 1 - Math.pow(1 - progress, 3);
        el.textContent = Math.round(target * eased).toLocaleString();
        if (progress < 1) requestAnimationFrame(step);
      };
      requestAnimationFrame(step);
    };

    if (!('IntersectionObserver' in window)) {
      counters.forEach(run);
      return;
    }

    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          run(entry.target);
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.5 });

    counters.forEach(function (el) { observer.observe(el); });
  }

  /* --- Gallery filtering + lightbox ---------------------------------------- */
  function initGallery() {
    var grid = document.getElementById('galleryGrid');

    /* Gallery filtering moved to real server-side links so a filtered view is
       shareable and works without JS. Only <button> filters (used elsewhere)
       still get the client-side handler -- attaching it to an anchor would
       fight the navigation. */
    var buttons = document.querySelectorAll('button.filter-btn');

    if (grid && buttons.length) {
      var empty = document.getElementById('galleryEmpty');

      buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
          var filter = btn.getAttribute('data-filter');

          buttons.forEach(function (b) {
            b.classList.toggle('is-active', b === btn);
            b.setAttribute('aria-pressed', b === btn ? 'true' : 'false');
          });

          var shown = 0;
          grid.querySelectorAll('.gallery-item').forEach(function (item) {
            var match = filter === 'all' || item.getAttribute('data-category') === filter;
            item.hidden = !match;
            if (match) shown++;
          });

          if (empty) empty.hidden = shown !== 0;
        });
      });
    }

    /* Lightbox */
    var box = document.getElementById('lightbox');
    if (!box) return;

    var img = box.querySelector('img');
    var capTitle = box.querySelector('.lightbox-cap strong');
    var capMeta = box.querySelector('.lightbox-cap span');
    var lastFocus = null;
    var items = [];
    var index = 0;

    var visibleItems = function () {
      return Array.prototype.filter.call(
        document.querySelectorAll('.gallery-item'),
        function (el) { return !el.hidden; }
      );
    };

    var show = function (i) {
      items = visibleItems();
      if (!items.length) return;
      index = (i + items.length) % items.length;

      var el = items[index];
      var source = el.querySelector('img');
      img.src = source.getAttribute('src');
      img.alt = source.getAttribute('alt') || '';
      capTitle.textContent = el.getAttribute('data-title') || '';
      capMeta.textContent = el.getAttribute('data-meta') || '';
    };

    var open = function (el) {
      lastFocus = el;
      items = visibleItems();
      show(items.indexOf(el));
      box.classList.add('is-open');
      document.body.style.overflow = 'hidden';
      box.querySelector('.lightbox-close').focus();
    };

    /* Keep Tab inside the dialog while it is open. */
    box.addEventListener('keydown', function (e) {
      if (e.key !== 'Tab' || !box.classList.contains('is-open')) return;

      var focusable = box.querySelectorAll('button, [href]');
      if (!focusable.length) return;

      var first = focusable[0];
      var last = focusable[focusable.length - 1];

      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    });

    var close = function () {
      box.classList.remove('is-open');
      document.body.style.overflow = '';
      if (lastFocus) lastFocus.focus();
    };

    document.addEventListener('click', function (e) {
      var trigger = e.target.closest('.gallery-item');
      if (trigger) { e.preventDefault(); open(trigger); }
    });

    box.querySelector('.lightbox-close').addEventListener('click', close);
    box.querySelector('.lightbox-prev').addEventListener('click', function () { show(index - 1); });
    box.querySelector('.lightbox-next').addEventListener('click', function () { show(index + 1); });

    box.addEventListener('click', function (e) {
      if (e.target === box) close();
    });

    document.addEventListener('keydown', function (e) {
      if (!box.classList.contains('is-open')) return;
      if (e.key === 'Escape') close();
      if (e.key === 'ArrowLeft') show(index - 1);
      if (e.key === 'ArrowRight') show(index + 1);
    });
  }

  /* --- Back to top --------------------------------------------------------- */
  function initToTop() {
    var btn = document.querySelector('.to-top');
    if (!btn) return;

    var onScroll = function () {
      btn.classList.toggle('is-visible', window.scrollY > 600);
    };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });

    btn.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
    });
  }

  /* --- Form helpers -------------------------------------------------------- *
   * Applies to every form on the page rather than being wired per-template:
   * required markers, friendly inline errors from the native Constraint
   * Validation API, textarea counters, and a submit guard against double posts.
   * ------------------------------------------------------------------------ */
  function initForms() {
    document.querySelectorAll('form[data-enhance]').forEach(function (form) {

      // Required-field markers, unless the label already carries one.
      form.querySelectorAll('[required]').forEach(function (field) {
        var group = field.closest('.form-group');
        var label = group && group.querySelector('label');
        if (label && !label.querySelector('.req')) {
          var star = document.createElement('span');
          star.className = 'req';
          star.setAttribute('aria-hidden', 'true');
          star.textContent = '*';
          label.appendChild(star);
        }
      });

      // Live character counters on any length-capped textarea.
      form.querySelectorAll('textarea[maxlength]').forEach(function (area) {
        var max = parseInt(area.getAttribute('maxlength'), 10);
        var counter = document.createElement('p');
        counter.className = 'form-hint';
        counter.setAttribute('aria-live', 'polite');
        area.insertAdjacentElement('afterend', counter);

        var update = function () {
          var left = max - area.value.length;
          counter.textContent = left + ' characters remaining';
          counter.style.color = left < 60 ? 'var(--coral)' : '';
        };
        area.addEventListener('input', update);
        update();
      });

      var friendlyMessage = function (field) {
        var name = (field.closest('.form-group') || {}).querySelector
          ? field.closest('.form-group').querySelector('label')
          : null;
        var label = name ? name.textContent.replace('*', '').trim() : 'This field';

        if (field.validity.valueMissing) return label + ' is required.';
        if (field.validity.typeMismatch && field.type === 'email') return 'Please enter a valid email address.';
        if (field.validity.typeMismatch && field.type === 'tel') return 'Please enter a valid phone number.';
        if (field.validity.tooShort) return label + ' is too short.';
        if (field.validity.patternMismatch) return 'Please check the format of ' + label.toLowerCase() + '.';
        return 'Please check ' + label.toLowerCase() + '.';
      };

      var clearError = function (field) {
        var group = field.closest('.form-group');
        if (!group) return;
        group.classList.remove('has-error');
        var msg = group.querySelector('.field-error.js-error');
        if (msg) msg.remove();
        field.removeAttribute('aria-invalid');
      };

      var showError = function (field) {
        var group = field.closest('.form-group');
        if (!group) return;
        clearError(field);
        group.classList.add('has-error');
        field.setAttribute('aria-invalid', 'true');
        var msg = document.createElement('span');
        msg.className = 'field-error js-error';
        msg.textContent = friendlyMessage(field);
        group.appendChild(msg);
      };

      form.addEventListener('input', function (e) {
        if (e.target.willValidate && e.target.checkValidity()) clearError(e.target);
      });

      form.addEventListener('submit', function (e) {
        var invalid = [];
        form.querySelectorAll('input, select, textarea').forEach(function (field) {
          if (!field.willValidate) return;
          if (!field.checkValidity()) { showError(field); invalid.push(field); }
          else clearError(field);
        });

        if (invalid.length) {
          e.preventDefault();
          invalid[0].focus();
          invalid[0].scrollIntoView({ block: 'center', behavior: reduceMotion ? 'auto' : 'smooth' });
          return;
        }

        // Guard against a double submission creating two records.
        var submit = form.querySelector('[type="submit"]');
        if (submit) {
          submit.disabled = true;
          submit.dataset.original = submit.textContent;
          submit.textContent = submit.getAttribute('data-busy') || 'Sending...';
          // If the browser restores the page from cache, re-enable the button.
          window.setTimeout(function () {
            submit.disabled = false;
            submit.textContent = submit.dataset.original;
          }, 8000);
        }
      });

      // Suppress the browser's own bubbles; we show our own messages.
      form.setAttribute('novalidate', 'novalidate');
    });
  }


  /* --- Theme toggle -------------------------------------------------------- *
   * The initial theme is applied by a tiny inline script in the page head so
   * there is no flash of the wrong theme. This only handles the switching.
   * ------------------------------------------------------------------------ */
  function initTheme() {
    var toggle = document.getElementById('themeToggle');
    if (!toggle) return;

    var systemDark = window.matchMedia('(prefers-color-scheme: dark)');

    var currentTheme = function () {
      var explicit = document.documentElement.getAttribute('data-theme');
      if (explicit) return explicit;
      return systemDark.matches ? 'dark' : 'light';
    };

    var sync = function () {
      toggle.setAttribute('aria-pressed', currentTheme() === 'dark' ? 'true' : 'false');
    };

    toggle.addEventListener('click', function () {
      var next = currentTheme() === 'dark' ? 'light' : 'dark';
      document.documentElement.setAttribute('data-theme', next);
      try { localStorage.setItem('theme', next); } catch (e) { /* private mode */ }
      sync();
    });

    // Follow the system if the visitor has never chosen explicitly.
    systemDark.addEventListener('change', function () {
      if (!document.documentElement.getAttribute('data-theme')) sync();
    });

    sync();
  }

  /* --- Navigation dropdowns ------------------------------------------------ *
   * Desktop opens them on hover via CSS. This adds the click behaviour that
   * touch and keyboard need, and closes them on Escape or an outside click.
   * ------------------------------------------------------------------------ */
  function initNavGroups() {
    var toggles = document.querySelectorAll('.nav-group-toggle');
    if (!toggles.length) return;

    var closeAll = function (except) {
      toggles.forEach(function (t) {
        if (t === except) return;
        t.setAttribute('aria-expanded', 'false');
        var panel = document.getElementById(t.getAttribute('aria-controls'));
        if (panel) panel.classList.remove('is-open');
      });
    };

    toggles.forEach(function (toggle) {
      toggle.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var panel = document.getElementById(toggle.getAttribute('aria-controls'));
        if (!panel) return;

        var open = toggle.getAttribute('aria-expanded') === 'true';
        closeAll(toggle);
        toggle.setAttribute('aria-expanded', open ? 'false' : 'true');
        panel.classList.toggle('is-open', !open);
      });
    });

    document.addEventListener('click', function (e) {
      if (!e.target.closest('.nav-group')) closeAll(null);
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeAll(null);
    });
  }

  /* --- Scroll progress ----------------------------------------------------- */
  function initProgress() {
    var bar = document.querySelector('.scroll-progress span');
    if (!bar) return;

    var update = function () {
      var doc = document.documentElement;
      var scrollable = doc.scrollHeight - doc.clientHeight;
      var pct = scrollable > 0 ? (doc.scrollTop / scrollable) * 100 : 0;
      bar.style.width = Math.min(100, Math.max(0, pct)) + '%';
    };

    update();
    window.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
  }

  /* --- Accordion ----------------------------------------------------------- *
   * Progressive: the panels are rendered open, and this closes all but the
   * first once JS confirms the toggles work. With JS off every answer is
   * simply visible.
   * ------------------------------------------------------------------------ */
  function initAccordion() {
    var triggers = document.querySelectorAll('.accordion-trigger');
    if (!triggers.length) return;

    triggers.forEach(function (trigger, index) {
      var panel = document.getElementById(trigger.getAttribute('aria-controls'));
      if (!panel) return;

      var open = index === 0;
      trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
      panel.hidden = !open;

      trigger.addEventListener('click', function () {
        var isOpen = trigger.getAttribute('aria-expanded') === 'true';
        trigger.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
        panel.hidden = isOpen;
      });
    });
  }

  /* --- Cookie notice ------------------------------------------------------- */
  function initCookieNote() {
    var note = document.querySelector('.cookie-note');
    if (!note) return;

    var stored;
    try { stored = localStorage.getItem('cookie-notice'); } catch (e) { stored = 'seen'; }

    if (stored === 'seen') {
      note.remove();
      return;
    }

    window.setTimeout(function () { note.classList.add('is-shown'); }, 900);

    note.querySelectorAll('[data-dismiss]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        note.classList.remove('is-shown');
        try { localStorage.setItem('cookie-notice', 'seen'); } catch (e) { /* ignore */ }
        window.setTimeout(function () { note.remove(); }, 400);
      });
    });
  }

  /* --- Share buttons ------------------------------------------------------- *
   * Uses the native share sheet where the browser offers one, and falls back
   * to copying the link. The anchors remain real links either way.
   * ------------------------------------------------------------------------ */
  function initShare() {
    var nativeBtn = document.querySelector('[data-share-native]');

    if (nativeBtn) {
      if (navigator.share) {
        nativeBtn.hidden = false;
        nativeBtn.addEventListener('click', function () {
          navigator.share({
            title: document.title,
            url: window.location.href
          }).catch(function () { /* the visitor cancelled */ });
        });
      } else {
        nativeBtn.remove();
      }
    }

    var copyBtn = document.querySelector('[data-copy-link]');
    if (copyBtn && navigator.clipboard) {
      copyBtn.hidden = false;
      copyBtn.addEventListener('click', function () {
        navigator.clipboard.writeText(window.location.href).then(function () {
          var original = copyBtn.getAttribute('aria-label');
          copyBtn.setAttribute('aria-label', 'Link copied');
          copyBtn.classList.add('is-copied');
          window.setTimeout(function () {
            copyBtn.setAttribute('aria-label', original);
            copyBtn.classList.remove('is-copied');
          }, 2000);
        });
      });
    } else if (copyBtn) {
      copyBtn.remove();
    }
  }

  /* --- Boot ---------------------------------------------------------------- */
  function boot() {
    initTheme();
    initNav();
    initNavGroups();
    initHeader();
    initProgress();
    initReveal();
    initCounters();
    initGallery();
    initAccordion();
    initCookieNote();
    initShare();
    initToTop();
    initForms();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
