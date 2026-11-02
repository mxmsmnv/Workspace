/* Appearance is per account/browser; native app authorization and scripts stay in charge. */
(() => {
  'use strict';
  let messages = {};
  try { messages = JSON.parse(document.getElementById('ws-translations')?.textContent || '{}'); } catch (_) {}
  const text = (key, value) => String(messages[key] || '').replace('%s', () => String(value ?? ''));

  const root = document.documentElement;
  document.addEventListener('keydown', event => { if (event.key === 'Tab') root.dataset.wsKeyboard = 'true'; });
  document.addEventListener('pointerdown', () => { delete root.dataset.wsKeyboard; }, true);
  const prefix = `workspace.${document.body.dataset.wsUser || 'guest'}.`;
  const modes = ['light', 'dark', 'system', 'black'];
  const accents = ['blue', 'violet', 'teal', 'forest', 'amber', 'rose', 'graphite', 'ocean', 'clay'];
  const fonts = ['system', 'humanist', 'classic', 'rounded', 'serif', 'mono'];
  const contrasts = ['standard', 'enhanced', 'maximum'];
  let contrast = readPreference('contrast', 'standard', contrasts);
  function readScale() {
    try {
      const stored = localStorage.getItem(prefix + 'textScale');
      const value = stored === null ? ({ compact: 93, standard: 100, large: 114 }[localStorage.getItem(prefix + 'size')] || 100) : Number(stored);
      return Number.isFinite(value) && value >= 85 && value <= 125 ? value : 100;
    } catch (_) { return 100; }
  }
  let font = readPreference('font', 'system', fonts);
  let scale = readScale();
  function readPreference(key, fallback, allowed) { try { const value = localStorage.getItem(prefix + key); return allowed.includes(value) ? value : fallback; } catch (_) { return fallback; } }
  const system = matchMedia('(prefers-color-scheme: dark)');
  const read = (key, fallback) => { try { return localStorage.getItem(prefix + key) || fallback; } catch (_) { return fallback; } };
  const write = (key, value) => { try { localStorage.setItem(prefix + key, value); } catch (_) {} };
  let mode = read('mode', root.dataset.wsMode || 'system');
  let accent = read('accent', root.dataset.wsAccent || 'blue');
  if (!modes.includes(mode)) mode = 'system';
  if (!accents.includes(accent)) accent = 'blue';
  const normalizedStyleSheets = new WeakSet();
  const normalizeTextUnits = value => value.replace(/(-?\d*\.?\d+)px\b/g, (_, number) => `${Number(number) / 16}rem`).replace(/(-?\d*\.?\d+)(vw|vh)\b/g, (_, number, unit) => `calc(${number}${unit} * var(--ws-scale, 1))`);
  const normalizeTypography = () => {
    const visitRules = rules => {
      for (const rule of rules) {
        if (rule.style) for (const property of ['font-size', 'line-height']) {
          const original = rule.style.getPropertyValue(property);
          if (/px\b/.test(original)) rule.style.setProperty(property, normalizeTextUnits(original), rule.style.getPropertyPriority(property));
        }
        if (rule.cssRules) visitRules(rule.cssRules);
      }
    };
    // Normalize fixed text units in native/app styles, without copying or changing their source files.
    // Only the current admin document's same-origin external stylesheets are touched; embedded documents are separate.
    for (const sheet of document.styleSheets) {
      if (!sheet.href || normalizedStyleSheets.has(sheet)) continue;
      try {
        if (new URL(sheet.href, location.href).origin !== location.origin) continue;
        visitRules(sheet.cssRules);
        normalizedStyleSheets.add(sheet);
      } catch (_) { /* Cross-origin or not-yet-loaded styles remain owned by their provider. */ }
    }
  };
  document.addEventListener('load', event => { if (event.target.matches?.('link[rel~=stylesheet]')) normalizeTypography(); }, true);
  // Legacy application initializers may enhance body-wide controls after this deferred script.
  // Observe only our own controls, never native application forms or the whole document.
  const shellControls = [...document.querySelectorAll('.ws-header button,#ws-appearance-dialog button,#ws-appearance-dialog select,.ws-pin,.ws-library-filters button,[data-ws-reset]')];
  const foreignControlClasses = ['uk-button', 'uk-button-default', 'uk-button-primary', 'uk-button-secondary', 'uk-button-small', 'uk-button-text', 'uk-select', 'ui-button', 'ui-widget', 'ui-state-default', 'ui-state-hover', 'ui-state-active', 'ui-state-focus', 'ui-corner-all', 'ui-button-text-only', 'ui-button-icon-only', 'ui-button-icons-only'];
  const isolateShellControl = control => {
    const foreign = foreignControlClasses.filter(name => control.classList.contains(name));
    if (foreign.length) control.classList.remove(...foreign);
  };
  const controlObserver = new MutationObserver(records => {
    new Set(records.map(record => record.target)).forEach(isolateShellControl);
  });
  shellControls.forEach(control => {
    isolateShellControl(control);
    controlObserver.observe(control, { attributes: true, attributeFilter: ['class'] });
  });
  const applyAppearance = () => {
    normalizeTypography();
    shellControls.forEach(isolateShellControl);
    document.querySelector('[data-ws-preferences-status]')?.replaceChildren(text('saved'));
    const theme = mode === 'black' ? 'dark' : mode === 'system' ? (system.matches ? 'dark' : 'light') : mode;
    root.dataset.wsMode = mode;
    root.dataset.wsTheme = theme;
    root.dataset.wsAccent = accent;
    root.dataset.wsFont = font;
    root.dataset.wsSize = scale === 93 ? 'compact' : scale === 114 ? 'large' : scale === 100 ? 'standard' : 'custom';
    root.dataset.wsScale = String(scale);
    root.style.setProperty('--ws-scale', String(scale / 100));
    root.style.fontSize = `${16 * scale / 100}px`;
    root.dataset.wsContrast = contrast;
    document.querySelectorAll('select[data-ws-font]').forEach(select => { select.value = font; });
    document.querySelectorAll('input[data-ws-scale]').forEach(input => { input.value = String(scale); input.setAttribute('aria-valuetext', `${scale}%`); });
    document.querySelector('[data-ws-scale-output]')?.replaceChildren(`${scale}%`);
    document.body.classList.remove('light-theme', 'dark-theme', 'auto-theme', 'main-color-red', 'main-color-blue', 'main-color-green');
    document.body.classList.add(`${theme}-theme`, 'main-color-custom');
    // Sync the native state and iframe editors, without its admin-root POST/save path.
    if (window.AdminDarkMode?.isInit) window.AdminDarkMode.set(theme === 'dark' ? 1 : 0);
    document.querySelectorAll('button[data-ws-mode]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.wsMode === mode)));
    document.querySelectorAll('button[data-ws-accent]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.wsAccent === accent)));
    document.querySelectorAll('button[data-ws-contrast]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.wsContrast === contrast)));
    document.dispatchEvent(new CustomEvent('workspace:appearancechange', { detail: { mode, theme, accent, font, scale, contrast } }));
    if (window.jQuery) window.jQuery(document).trigger('admin-color-change');
  };
  applyAppearance();
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', applyAppearance, { once: true });
  else if (window.jQuery) window.jQuery(applyAppearance);
  system.addEventListener('change', () => { if (mode === 'system') applyAppearance(); });
  document.querySelectorAll('button[data-ws-mode]').forEach(button => button.addEventListener('click', () => {
    if (!modes.includes(button.dataset.wsMode)) return;
    mode = button.dataset.wsMode;
    write('mode', mode);
    applyAppearance();
  }));
  document.querySelectorAll('button[data-ws-accent]').forEach(button => button.addEventListener('click', () => {
    if (!accents.includes(button.dataset.wsAccent)) return;
    accent = button.dataset.wsAccent;
    write('accent', accent);
    applyAppearance();
  }));
  window.addEventListener('storage', event => {
    if (!event.key?.startsWith(prefix)) return;
    const nextMode = read('mode', 'system');
    const nextAccent = read('accent', 'blue');
    mode = modes.includes(nextMode) ? nextMode : 'system';
    accent = accents.includes(nextAccent) ? nextAccent : 'blue';
    font = readPreference('font', 'system', fonts);
    scale = readScale();
    contrast = readPreference('contrast', 'standard', contrasts);
    applyAppearance();
  });
  document.querySelectorAll('select[data-ws-font]').forEach(select => select.addEventListener('change', () => {
    if (select.hasAttribute('data-ws-font') && fonts.includes(select.value)) { font = select.value; write('font', font); }
    applyAppearance();
  }));
  document.querySelector('input[data-ws-scale]')?.addEventListener('input', event => {
    const value = Number(event.target.value);
    if (!Number.isFinite(value) || value < 85 || value > 125) return;
    scale = value; write('textScale', String(scale)); applyAppearance();
  });
  document.querySelectorAll('button[data-ws-contrast]').forEach(button => button.addEventListener('click', () => {
    if (!contrasts.includes(button.dataset.wsContrast)) return;
    contrast = button.dataset.wsContrast; write('contrast', contrast); applyAppearance();
  }));
  document.querySelector('[data-ws-appearance-reset]')?.addEventListener('click', () => {
    mode = 'system'; accent = 'blue'; font = 'system'; scale = 100; contrast = 'standard';
    for (const key of ['mode', 'accent', 'font', 'size', 'textScale', 'contrast']) { try { localStorage.removeItem(prefix + key); } catch (_) {} }
    // An explicit default wins over legacy mode storage on the next navigation.
    write('mode', mode); applyAppearance();
    document.querySelector('[data-ws-preferences-status]')?.replaceChildren(text('reset'));
  });
  const appearance = document.querySelector('#ws-appearance-dialog');
  const appearanceTrigger = document.querySelector('[data-ws-appearance-open]');
  appearanceTrigger?.addEventListener('click', () => {
    if (!appearance || appearance.open) return;
    document.querySelectorAll('.ws-popover[open]').forEach(popover => { popover.open = false; });
    appearance.showModal();
    document.body.classList.add('ws-modal-open');
    appearance.querySelector('[data-ws-appearance-close]')?.focus();
  });
  document.querySelector('[data-ws-appearance-close]')?.addEventListener('click', () => appearance.close());
  appearance?.addEventListener('close', () => { document.body.classList.remove('ws-modal-open'); (appearanceTrigger?.closest('.ws-popover')?.querySelector('summary') || appearanceTrigger)?.focus(); });

  const popovers = [...document.querySelectorAll('.ws-popover')];
  const positionPopovers = () => {
    const header = document.querySelector('.ws-header');
    if (header) root.style.setProperty('--ws-menu-top', `${Math.ceil(header.getBoundingClientRect().bottom) + 8}px`);
  };
  window.addEventListener('resize', positionPopovers);
  popovers.forEach(popover => popover.addEventListener('toggle', () => {
    if (popover.open) {
      positionPopovers();
      popovers.forEach(other => { if (other !== popover) other.open = false; });
    }
  }));
  document.addEventListener('click', event => popovers.forEach(popover => {
    if (popover.open && !popover.contains(event.target)) popover.open = false;
  }));
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') popovers.forEach(popover => {
      if (popover.open) { popover.open = false; popover.querySelector('summary')?.focus(); }
    });
  });

  const search = document.querySelector('[data-ws-search]');
  const cards = [...document.querySelectorAll('[data-ws-app]')];
  let filter = 'all';
  let pinned;
  try { pinned = JSON.parse(read('pinned', '[]')); } catch (_) { pinned = []; }
  if (!Array.isArray(pinned)) pinned = [];
  const appIds = cards.map(card => card.dataset.wsAppId);
  pinned = new Set(pinned.filter(id => typeof id === 'string' && appIds.includes(id)));
  filter = pinned.size ? 'pinned' : 'all';
  const updateLibrary = () => {
    const query = (search?.value || '').trim().toLocaleLowerCase();
    let count = 0;
    cards.forEach(card => {
      const match = (!query || card.dataset.search.toLocaleLowerCase().includes(query)) && (filter !== 'pinned' || pinned.has(card.dataset.wsAppId));
      card.hidden = !match;
      if (match) count++;
    });
    document.querySelectorAll('[data-ws-pin]').forEach(button => {
      const active = pinned.has(button.dataset.wsPin);
      button.setAttribute('aria-pressed', String(active));
      button.setAttribute('aria-label', text(active ? 'remove' : 'add', button.dataset.wsAppTitle));
      button.title = text(active ? 'removeTitle' : 'addTitle');
    });
    document.querySelector('[data-ws-pinned-count]')?.replaceChildren(String(pinned.size));
    const favorites = document.querySelector('[data-ws-filter=pinned]');
    if (favorites) {
      favorites.dataset.wsHasFavorites = String(pinned.size > 0);
      const all = document.querySelector('[data-ws-filter=all]');
      const first = pinned.size ? favorites : all;
      const toolbar = favorites.parentElement;
      if (first && toolbar.firstElementChild !== first) {
        const focused = document.activeElement;
        toolbar.insertBefore(first, toolbar.firstElementChild);
        if (focused === favorites || focused === all) focused.focus({ preventScroll: true });
      }
    }
    document.querySelector('[data-ws-count]')?.replaceChildren(String(messages.counts?.[count] ?? ''));
    const empty = document.querySelector('[data-ws-empty]');
    if (empty) empty.hidden = count !== 0 || cards.length === 0;
    const message = document.querySelector('[data-ws-empty-message]');
    if (message) message.textContent = text(filter === 'pinned' && pinned.size === 0 ? 'emptyFavorites' : 'emptySearch');
    document.querySelectorAll('[data-ws-filter]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.wsFilter === filter)));
  };
  search?.addEventListener('input', updateLibrary);
  search?.addEventListener('keydown', event => {
    if (event.key === 'Escape') { search.value = ''; updateLibrary(); }
    if (event.key === 'Enter') {
      const first = cards.find(card => !card.hidden)?.querySelector('.ws-app-launch');
      if (first) { event.preventDefault(); window.location.assign(first.href); }
    }
  });
  document.querySelectorAll('[data-ws-filter]').forEach(button => button.addEventListener('click', () => {
    filter = button.dataset.wsFilter === 'pinned' ? 'pinned' : 'all';
    updateLibrary();
  }));
  document.querySelectorAll('[data-ws-pin]').forEach(button => button.addEventListener('click', () => {
    const hadFavorites = pinned.size > 0;
    if (pinned.has(button.dataset.wsPin)) pinned.delete(button.dataset.wsPin); else pinned.add(button.dataset.wsPin);
    if (!hadFavorites && pinned.size) filter = 'pinned';
    if (!pinned.size) filter = 'all';
    write('pinned', JSON.stringify([...pinned]));
    updateLibrary();
    const announcement = document.querySelector('[data-ws-announcement]');
    if (announcement) announcement.textContent = text(pinned.has(button.dataset.wsPin) ? 'added' : 'removed', button.dataset.wsAppTitle);
  }));
  document.querySelector('[data-ws-reset]')?.addEventListener('click', () => { filter = 'all'; if (search) search.value = ''; updateLibrary(); search?.focus(); });
  if (cards.length) updateLibrary();
  document.addEventListener('keydown', event => {
    if (!appearance?.open && event.key === '/' && search && !event.ctrlKey && !event.metaKey && !event.altKey && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement?.tagName || '') && !document.activeElement?.isContentEditable) {
      event.preventDefault(); search.focus(); search.select();
    }
  });
})();
