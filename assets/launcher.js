(() => {
  'use strict';
  const launcher = document.querySelector('.ws-launcher');
  if (!launcher) return;

  let messages = {};
  try { messages = JSON.parse(document.getElementById('ws-translations')?.textContent || '{}'); } catch (_) {}
  const text = (key, value) => String(messages[key] || '').replace('%s', () => String(value ?? ''));
  const prefix = `workspace.${launcher.dataset.wsUser}.`;
  const read = (key, fallback) => { try { return localStorage.getItem(prefix + key) || fallback; } catch (_) { return fallback; } };
  const write = (key, value) => { try { localStorage.setItem(prefix + key, value); } catch (_) {} };

  const nativeAdmin = launcher.hasAttribute('data-ws-native');
  const search = launcher.querySelector('[data-ws-search]');
  const cards = [...launcher.querySelectorAll('[data-ws-app]')];
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
    launcher.querySelectorAll('[data-ws-pin]').forEach(button => {
      const active = pinned.has(button.dataset.wsPin);
      button.setAttribute('aria-pressed', String(active));
      if (nativeAdmin) {
        button.querySelector('.ws-star-outline').hidden = active;
        button.querySelector('.ws-star-filled').hidden = !active;
      }
      button.setAttribute('aria-label', text(active ? 'remove' : 'add', button.dataset.wsAppTitle));
      button.title = text(active ? 'removeTitle' : 'addTitle');
    });
    launcher.querySelector('[data-ws-pinned-count]')?.replaceChildren(String(pinned.size));
    const favorites = launcher.querySelector('[data-ws-filter=pinned]');
    if (favorites) {
      favorites.dataset.wsHasFavorites = String(pinned.size > 0);
      const all = launcher.querySelector('[data-ws-filter=all]');
      const first = pinned.size ? favorites : all;
      const toolbar = favorites.parentElement;
      if (first && toolbar.firstElementChild !== first) {
        const focused = document.activeElement;
        toolbar.insertBefore(first, toolbar.firstElementChild);
        if (focused === favorites || focused === all) focused.focus({ preventScroll: true });
      }
    }
    launcher.querySelector('[data-ws-count]')?.replaceChildren(String(messages.counts?.[count] ?? ''));
    const empty = launcher.querySelector('[data-ws-empty]');
    if (empty) empty.hidden = count !== 0 || cards.length === 0;
    const message = launcher.querySelector('[data-ws-empty-message]');
    if (message) message.textContent = text(filter === 'pinned' && pinned.size === 0 ? 'emptyFavorites' : 'emptySearch');
    launcher.querySelectorAll('[data-ws-filter]').forEach(button => {
      const active = button.dataset.wsFilter === filter;
      button.setAttribute('aria-pressed', String(active));
      if (nativeAdmin) button.classList.toggle('uk-active', active);
    });
  };
  search?.addEventListener('input', updateLibrary);
  search?.addEventListener('keydown', event => {
    if (event.key === 'Escape') { search.value = ''; updateLibrary(); }
    if (event.key === 'Enter') {
      const first = cards.find(card => !card.hidden)?.querySelector('.ws-app-launch');
      if (first) { event.preventDefault(); window.location.assign(first.href); }
    }
  });
  launcher.querySelectorAll('[data-ws-filter]').forEach(button => button.addEventListener('click', () => {
    filter = button.dataset.wsFilter === 'pinned' ? 'pinned' : 'all';
    updateLibrary();
  }));
  launcher.querySelectorAll('[data-ws-pin]').forEach(button => button.addEventListener('click', () => {
    const hadFavorites = pinned.size > 0;
    if (pinned.has(button.dataset.wsPin)) pinned.delete(button.dataset.wsPin); else pinned.add(button.dataset.wsPin);
    if (!hadFavorites && pinned.size) filter = 'pinned';
    if (!pinned.size) filter = 'all';
    write('pinned', JSON.stringify([...pinned]));
    updateLibrary();
    const announcement = launcher.querySelector('[data-ws-announcement]');
    if (announcement) announcement.textContent = text(pinned.has(button.dataset.wsPin) ? 'added' : 'removed', button.dataset.wsAppTitle);
  }));
  launcher.querySelector('[data-ws-reset]')?.addEventListener('click', () => { filter = 'all'; if (search) search.value = ''; updateLibrary(); search?.focus(); });
  if (cards.length) updateLibrary();
  document.addEventListener('keydown', event => {
    if (!document.querySelector('#ws-appearance-dialog')?.open && event.key === '/' && search && !event.ctrlKey && !event.metaKey && !event.altKey && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement?.tagName || '') && !document.activeElement?.isContentEditable) {
      event.preventDefault(); search.focus(); search.select();
    }
  });
})();
