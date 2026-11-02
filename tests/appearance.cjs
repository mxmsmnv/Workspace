const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');
const cssTree = require('css-tree');
const fixture = process.env.WORKSPACE_HTML;
if (!fixture) throw new Error('Set WORKSPACE_HTML to an authenticated launcher HTML response from the isolated site.');
const html = fs.readFileSync(fixture, 'utf8');
const source = fs.readFileSync(path.join(__dirname, '../assets/workspace.js'), 'utf8');
async function boot(t, values = {}, user = null, unavailableStorage = false, prepare = null) {
  const dom = new JSDOM(html, { url: 'https://workspace.test:8443/workspace-admin/workspace/', runScripts: 'outside-only' });
  t.after(() => dom.window.close());
  const w = dom.window;
  if (user) w.document.body.dataset.wsUser = user;
  const prefix = `workspace.${w.document.body.dataset.wsUser}.`;
  const change = [];
  const media = { matches: false, addEventListener: (name, fn) => change.push(fn) };
  w.matchMedia = () => media;
  w.HTMLDialogElement.prototype.showModal = function () { this.open = true; };
  w.HTMLDialogElement.prototype.close = function () { this.open = false; this.dispatchEvent(new w.Event('close')); };
  w.AdminDarkMode = { isInit: true, mode: 0, calls: 0, saves: 0, set(mode) { this.mode = mode; this.calls++; }, save() { this.saves++; } };
  for (const [key, value] of Object.entries(values)) w.localStorage.setItem(key, value);
  if (unavailableStorage) Object.defineProperty(w, 'localStorage', { get() { throw new Error('Storage disabled'); } });
  if (prepare) prepare(w);
  w.eval(source);
  await new Promise(resolve => w.setTimeout(resolve, 0));
  return { w, doc: w.document, prefix, media, change };
}
test('mode changes synchronize native state without permission-sensitive POST/save', async t => {
  const { w, doc, prefix } = await boot(t);
  doc.querySelector('button[data-ws-mode=dark]').click();
  assert.equal(doc.documentElement.dataset.wsTheme, 'dark');
  assert(doc.body.classList.contains('dark-theme'));
  assert(!doc.body.classList.contains('light-theme'));
  assert.equal(w.AdminDarkMode.mode, 1);
  assert.equal(w.AdminDarkMode.saves, 0);
  assert.equal(w.localStorage.getItem(prefix + 'mode'), 'dark');
  assert.equal(doc.documentElement.getAttribute('aria-pressed'), null);
  doc.querySelector('[data-ws-mode=light]').click();
  assert.equal(w.AdminDarkMode.mode, 0);
});
test('system preference follows system changes without overriding explicit choice', async t => {
  const { doc, media, change } = await boot(t);
  doc.querySelector('button[data-ws-mode=system]').click();
  media.matches = true; change.forEach(fn => fn());
  assert.equal(doc.documentElement.dataset.wsTheme, 'dark');
  doc.querySelector('button[data-ws-mode=light]').click();
  change.forEach(fn => fn());
  assert.equal(doc.documentElement.dataset.wsTheme, 'light');
});
test('all nine Cookie-inspired palettes work; preference is scoped to account and survives reload', async t => {
  const { w, doc, prefix } = await boot(t);
  const names = ['blue', 'violet', 'teal', 'forest', 'amber', 'rose', 'graphite', 'ocean', 'clay'];
  for (const name of names) {
    doc.querySelector(`button[data-ws-accent=${name}]`).click();
    assert.equal(doc.documentElement.dataset.wsAccent, name);
    assert.equal(doc.querySelectorAll('button[data-ws-accent][aria-pressed=true]').length, 1);
  }
  const values = { [prefix + 'accent']: w.localStorage.getItem(prefix + 'accent'), [prefix + 'mode']: 'dark' };
  const reload = await boot(t, values);
  assert.equal(reload.doc.documentElement.dataset.wsAccent, 'clay');
  assert.equal(reload.doc.documentElement.dataset.wsTheme, 'dark');
  const other = await boot(t, values, 'another-account');
  assert.equal(other.doc.documentElement.dataset.wsAccent, 'blue');
});
test('favorites, search, empty state and reset use only available rendered apps', async t => {
  const { w, doc, prefix } = await boot(t);
  assert.equal(doc.querySelectorAll('[data-ws-app]').length, 6);
  doc.querySelector('[data-ws-pin=mercato]').click();
  assert.deepEqual(JSON.parse(w.localStorage.getItem(prefix + 'pinned')), ['mercato']);
  doc.querySelector('[data-ws-filter=pinned]').click();
  assert.deepEqual([...doc.querySelectorAll('[data-ws-app]')].filter(card => !card.hidden).map(card => card.dataset.wsAppId), ['mercato']);
  const reload = await boot(t, { [prefix + 'pinned']: w.localStorage.getItem(prefix + 'pinned') });
  assert.equal(reload.doc.querySelector('[data-ws-pin=mercato]').getAttribute('aria-pressed'), 'true');
  doc.querySelector('[data-ws-pin=mercato]').click();
  assert.equal(doc.querySelector('[data-ws-empty]').hidden, true);
  assert.equal(doc.querySelector('[data-ws-filter=all]').getAttribute('aria-pressed'), 'true');
  doc.querySelector('[data-ws-reset]').click();
  const search = doc.querySelector('[data-ws-search]');
  search.value = 'mail'; search.dispatchEvent(new w.Event('input'));
  assert.deepEqual([...doc.querySelectorAll('[data-ws-app]')].filter(card => !card.hidden).map(card => card.dataset.wsAppId), ['mailbox']);
  search.dispatchEvent(new w.KeyboardEvent('keydown', { key: 'Escape' }));
  assert.equal([...doc.querySelectorAll('[data-ws-app]')].filter(card => !card.hidden).length, 6);
});
test('appearance and launcher remain usable when browser storage is unavailable', async t => {
  const { doc } = await boot(t, {}, null, true);
  doc.querySelector('button[data-ws-mode=dark]').click();
  doc.querySelector('button[data-ws-accent=teal]').click();
  assert.equal(doc.documentElement.dataset.wsTheme, 'dark');
  assert.equal(doc.documentElement.dataset.wsAccent, 'teal');
});
test('styles parse; palette text and primary buttons meet 4.5:1 contrast', () => {
  for (const file of ['workspace.css', 'appearance.css', 'administration.css', 'menus.css']) cssTree.parse(fs.readFileSync(path.join(__dirname, '../assets', file), 'utf8'));
  const css = fs.readFileSync(path.join(__dirname, '../assets/appearance.css'), 'utf8');
  const variables = block => Object.fromEntries([...block.matchAll(/(--[a-z-]+):([^;]+);/g)].map(m => [m[1], m[2].trim()]));
  const base = variables(css.match(/html \{([^}]+)\}/)[1]);
  const linear = hex => {
    const value = hex.length === 4 ? hex.slice(1).split('').map(x => x+x).join('') : hex.slice(1);
    const rgb = [0,2,4].map(i => parseInt(value.slice(i,i+2),16)/255).map(c => c <= .04045 ? c/12.92 : ((c+.055)/1.055)**2.4);
    return rgb[0]*.2126 + rgb[1]*.7152 + rgb[2]*.0722;
  };
  const contrast = (a,b) => (Math.max(linear(a),linear(b))+.05)/(Math.min(linear(a),linear(b))+.05);
  for (const name of ['blue','violet','teal','forest','amber','rose','graphite','ocean','clay']) {
    const match = css.match(new RegExp(`html\\[data-ws-accent=${name}\\] \\{([^}]+)\\}`));
    const palette = { ...base, ...(match ? variables(match[1]) : {}) };
    for (const mode of ['light','dark','black']) {
      const effective = mode === 'black' ? 'dark' : mode;
      const colors = variables(css.match(new RegExp(`html\\[data-ws-theme=${effective}\\] \\{([^}]+)\\}`))[1]);
      if (mode === 'black') Object.assign(colors, variables(css.match(/html\[data-ws-mode=black\]\s*\{([^}]+)\}/)[1]));
      const surface = mode === 'black' ? colors['--ws-surface'] : mode === 'light' ? '#fff' : palette['--ws-surface-dark'];
      for (const level of ['enhanced','maximum']) {
        const overrides = variables(css.match(new RegExp(`html\\[data-ws-theme=${effective}\\]\\[data-ws-contrast=${level}\\]\\s*\\{([^}]+)\\}`))[1]);
        for (const color of ['--ws-text','--ws-muted']) assert(contrast(overrides[color],surface)>=4.5, `${name}/${mode}/${level}/${color}`);
      }
      for (const color of ['--ws-text','--ws-muted']) assert(contrast(colors[color],surface)>=4.5, `${name}/${mode}/${color}`);
      assert(contrast(colors['--ws-on-accent'],palette[`--ws-accent-${effective}`])>=4.5, `${name}/${mode}/button`);
      assert(contrast(palette[`--ws-secondary-${effective}`],surface)>=4.5, `${name}/${mode}/companion`);
      const secondarySurface = mode === 'black' ? '#282828' : palette[`--ws-secondary-surface-${effective}`];
      assert(contrast(palette[`--ws-secondary-${effective}`], secondarySurface)>=4.5, `${name}/${mode}/secondary-button`);
      if (mode === 'black') {
        assert(contrast(colors['--ws-line'], surface)>=4.5, 'Black mode boundaries must remain visible');
        assert(contrast(colors['--ws-selection-bg'], surface)>=3, 'Selected controls must stand out against black');
        assert(contrast(colors['--ws-selection-text'], colors['--ws-selection-bg'])>=4.5, 'Selected control text must remain readable');
      }
    }
  }
});

test('font and continuous text scale update on input, persist, migrate presets and ignore invalid values', async t => {
  const { w, doc, prefix } = await boot(t);
  const font = doc.querySelector('select[data-ws-font]');
  const slider = doc.querySelector('input[data-ws-scale]');
  font.value = 'humanist'; font.dispatchEvent(new w.Event('change'));
  let updates = 0; doc.addEventListener('workspace:appearancechange', () => updates++);
  slider.value = '117'; slider.dispatchEvent(new w.Event('input'));
  assert.equal(doc.documentElement.dataset.wsFont, 'humanist');
  assert.equal(doc.documentElement.dataset.wsScale, '117');
  assert.equal(doc.querySelector('[data-ws-scale-output]').textContent, '117%');
  assert.equal(doc.documentElement.style.fontSize, '18.72px');
  slider.value = '119'; slider.dispatchEvent(new w.Event('input'));
  assert.equal(updates, 2, 'Each drag update must apply once, without duplicate input handlers');
  const values = { [prefix+'font']:w.localStorage.getItem(prefix+'font'), [prefix+'textScale']:w.localStorage.getItem(prefix+'textScale') };
  const reload = await boot(t, values);
  assert.equal(reload.doc.querySelector('select[data-ws-font]').value, 'humanist');
  assert.equal(reload.doc.documentElement.dataset.wsScale, '119');
  const other = await boot(t, values, 'different');
  assert.equal(other.doc.documentElement.dataset.wsFont, 'system');
  assert.equal(other.doc.documentElement.dataset.wsScale, '100');
  const invalid = await boot(t, { [prefix+'font']:'invalid', [prefix+'textScale']:'999' });
  assert.equal(invalid.doc.documentElement.dataset.wsScale, '100');
  const legacy = await boot(t, { [prefix+'size']:'large' });
  assert.equal(legacy.doc.documentElement.dataset.wsScale, '114');
  const unavailable = await boot(t, {}, null, true);
  unavailable.doc.querySelector('input[data-ws-scale]').value='110';
  unavailable.doc.querySelector('input[data-ws-scale]').dispatchEvent(new unavailable.w.Event('input'));
  assert.equal(unavailable.doc.documentElement.dataset.wsScale,'110');
});
test('appearance opens a dialog, locks page scroll and restores focus on close', async t => {
  const { doc } = await boot(t);
  const trigger = doc.querySelector('[data-ws-appearance-open]');
  const dialog = doc.querySelector('#ws-appearance-dialog');
  trigger.click();
  assert(dialog.open);
  assert(doc.body.classList.contains('ws-modal-open'));
  assert.equal(doc.activeElement, dialog.querySelector('[data-ws-appearance-close]'));
  dialog.querySelector('[data-ws-appearance-close]').click();
  assert(!dialog.open);
  assert(!doc.body.classList.contains('ws-modal-open'));
  assert.equal(doc.activeElement, trigger.closest('.ws-account').querySelector('summary'));
  assert(!trigger.closest('.ws-account').open);
  assert.equal(doc.querySelector('.ws-theme-toggle'), null);
});

test('black remains a settings choice and preserves native dark mode without a quick toggle', async t => {
  const { w, doc, prefix } = await boot(t);
  doc.querySelector('[data-ws-mode=black]').click();
  assert.equal(doc.documentElement.dataset.wsMode, 'black');
  assert.equal(doc.documentElement.dataset.wsTheme, 'dark');
  assert.equal(w.AdminDarkMode.mode, 1);
  assert(doc.body.classList.contains('dark-theme'));
  const reload = await boot(t, { [prefix+'mode']:w.localStorage.getItem(prefix+'mode') });
  assert.equal(reload.doc.documentElement.dataset.wsMode, 'black');
  assert.equal(doc.querySelector('.ws-theme-toggle'), null);
  doc.querySelector('[data-ws-mode=light]').click();
  assert.equal(doc.documentElement.dataset.wsMode, 'light');
});
test('six local fonts and contrast choices persist; reset changes appearance but preserves favorites', async t => {
  const { w, doc, prefix } = await boot(t);
  const font = doc.querySelector('select[data-ws-font]');
  assert.equal(font.options.length, 6);
  for (const value of ['system','humanist','classic','rounded','serif','mono']) {
    font.value=value; font.dispatchEvent(new w.Event('change'));
    assert.equal(doc.documentElement.dataset.wsFont,value);
  }
  doc.querySelector('[data-ws-pin=mailbox]').click();
  assert.equal(doc.querySelector('[data-ws-filter=pinned]').dataset.wsHasFavorites, 'true');
  doc.querySelector('[data-ws-contrast=maximum]').click();
  const reload = await boot(t, { [prefix+'contrast']:w.localStorage.getItem(prefix+'contrast') });
  assert.equal(reload.doc.documentElement.dataset.wsContrast,'maximum');
  doc.querySelector('[data-ws-mode=black]').click();
  doc.querySelector('[data-ws-appearance-reset]').click();
  assert.equal(doc.documentElement.dataset.wsMode,'system');
  assert.equal(doc.documentElement.dataset.wsFont,'system');
  assert.equal(doc.documentElement.dataset.wsScale,'100');
  assert.equal(doc.documentElement.dataset.wsContrast,'standard');
  assert.equal(doc.documentElement.dataset.wsAccent,'blue');
  assert.deepEqual(JSON.parse(w.localStorage.getItem(prefix+'pinned')),['mailbox']);
  doc.querySelector('[data-ws-filter=pinned]').click();
  assert.equal(doc.querySelector('[data-ws-filter=pinned]').getAttribute('aria-pressed'),'true');
  assert.deepEqual([...doc.querySelectorAll('[data-ws-app]')].filter(card=>!card.hidden).map(card=>card.dataset.wsAppId),['mailbox']);
  doc.querySelector('[data-ws-pin=mailbox]').click();
  assert.equal(doc.querySelector('[data-ws-filter=pinned]').dataset.wsHasFavorites,'false');
});

test('app-wide native button enhancement cannot restyle Workspace controls', async t => {
  const { w, doc } = await boot(t);
  const controls = [...doc.querySelectorAll('.ws-header button,#ws-appearance-dialog button,.ws-pin,.ws-library-filters button')];
  controls.forEach(button => button.classList.add('uk-button','uk-button-default'));
  // A delayed body-wide app initializer must not require opening/changing appearance to recover.
  const native = doc.createElement('button'); native.className = 'uk-button uk-button-default';
  doc.querySelector('#pw-content-body').append(native);
  await new Promise(resolve => w.setTimeout(resolve, 0));
  assert(native.classList.contains('uk-button'), 'Business controls must keep their native enhancement');
  assert(doc.querySelector('[data-ws-appearance-open] .ws-action-label').textContent.trim());
  assert(controls.every(button => !button.classList.contains('uk-button')));
  assert(doc.querySelector('.ws-pin').classList.contains('ws-pin'));
});

test('favorites become the first and default view on first pin, reload and return; last removal restores all', async t => {
  const { w, doc, prefix } = await boot(t);
  const order = d => [...d.querySelectorAll('.ws-library-filters [data-ws-filter]')].map(button => button.dataset.wsFilter);
  const visible = d => [...d.querySelectorAll('[data-ws-app]')].filter(card=>!card.hidden).map(card=>card.dataset.wsAppId);
  assert.deepEqual(order(doc), ['all','pinned']);
  doc.querySelector('[data-ws-pin=mercato]').click();
  assert.deepEqual(order(doc), ['pinned','all']);
  assert.equal(doc.querySelector('[data-ws-filter=pinned]').getAttribute('aria-pressed'),'true');
  assert.deepEqual(visible(doc), ['mercato']);
  doc.querySelector('[data-ws-filter=all]').click();
  assert.equal(visible(doc).length,6);
  assert.deepEqual(order(doc),['pinned','all']);
  const values = { [prefix+'pinned']:w.localStorage.getItem(prefix+'pinned') };
  const returning = await boot(t,values);
  assert.deepEqual(order(returning.doc), ['pinned','all']);
  assert.deepEqual(visible(returning.doc), ['mercato']);
  const unavailable = await boot(t,{ [prefix+'pinned']:JSON.stringify(['not-installed']) });
  assert.deepEqual(order(unavailable.doc), ['all','pinned']);
  assert.equal(visible(unavailable.doc).length,6);
  const other = await boot(t,values,'another-user');
  assert.deepEqual(order(other.doc), ['all','pinned']);
  doc.querySelector('[data-ws-pin=mercato]').click();
  assert.deepEqual(order(doc), ['all','pinned']);
  assert.equal(visible(doc).length,6);
});


test('native fixed text sizes follow the root scale once, including nested rules and late stylesheets', async t => {
  let sheet;
  const { w, doc } = await boot(t, {}, null, false, w => {
    const style = w.document.createElement('style');
    style.textContent = '.native-label {font-size:14px!important;line-height:20px} @media(min-width:600px){.native-title{font-size:32px}}';
    w.document.head.append(style);
    sheet = style.sheet;
    Object.defineProperty(sheet, 'href', {value: w.location.origin + '/site/modules/native.css'});
  });
  assert.equal(sheet.cssRules[0].style.getPropertyValue('font-size'), '0.875rem');
  assert.equal(sheet.cssRules[0].style.getPropertyPriority('font-size'), 'important');
  assert.equal(sheet.cssRules[0].style.getPropertyValue('line-height'), '1.25rem');
  assert.equal(sheet.cssRules[1].cssRules[0].style.getPropertyValue('font-size'), '2rem');
  const slider = doc.querySelector('input[data-ws-scale]');
  slider.value = '125'; slider.dispatchEvent(new w.Event('input', {bubbles:true}));
  assert.equal(doc.documentElement.style.fontSize, '20px');
  assert.equal(sheet.cssRules[0].style.getPropertyValue('font-size'), '0.875rem');
  const late = doc.createElement('style'); late.textContent = '.late{font-size:18px}'; doc.head.append(late);
  Object.defineProperty(late.sheet, 'href', {value:w.location.origin+'/late.css'});
  const link = doc.createElement('link'); link.rel='stylesheet'; doc.head.append(link);
  link.dispatchEvent(new w.Event('load'));
  assert.equal(late.sheet.cssRules[0].style.getPropertyValue('font-size'), '1.125rem');
});

test('native translated messages stay translated after search, favorites and appearance updates', async t => {
  const { w, doc } = await boot(t, {}, null, false, w => {
    const element = w.document.getElementById('ws-translations');
    const messages = JSON.parse(element.textContent);
    messages.saved = 'Сохранено & готово';
    messages.reset = 'Сброшено; избранное сохранено';
    messages.add = 'В избранное: %s';
    messages.remove = 'Убрать: %s';
    messages.added = 'Добавлено: %s';
    messages.removed = 'Удалено: %s';
    messages.emptySearch = 'Ничего не найдено';
    messages.emptyFavorites = 'Пока нет избранного';
    messages.counts = Array.from({ length: 7 }, (_, n) => `Найдено: ${n}`);
    element.textContent = JSON.stringify(messages);
  });
  const pin = doc.querySelector('[data-ws-pin]');
  const name = pin.dataset.wsAppTitle;
  assert.equal(pin.getAttribute('aria-label'), `В избранное: ${name}`);
  pin.click();
  assert.equal(pin.getAttribute('aria-label'), `Убрать: ${name}`);
  assert.equal(doc.querySelector('[data-ws-announcement]').textContent, `Добавлено: ${name}`);
  assert.equal(doc.querySelector('[data-ws-count]').textContent, 'Найдено: 1');
  const search = doc.querySelector('[data-ws-search]');
  search.value = 'missing'; search.dispatchEvent(new w.Event('input'));
  assert.equal(doc.querySelector('[data-ws-count]').textContent, 'Найдено: 0');
  assert.equal(doc.querySelector('[data-ws-empty-message]').textContent, 'Ничего не найдено');
  doc.querySelector('[data-ws-mode=dark]').click();
  assert.equal(doc.querySelector('[data-ws-preferences-status]').textContent, 'Сохранено & готово');
  pin.click();
  assert.equal(doc.querySelector('[data-ws-announcement]').textContent, `Удалено: ${name}`);
  doc.querySelector('[data-ws-appearance-reset]').click();
  assert.equal(doc.querySelector('[data-ws-preferences-status]').textContent, 'Сброшено; избранное сохранено');
});
