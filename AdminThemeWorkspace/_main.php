<?php namespace ProcessWire;
if(!defined('PROCESSWIRE')) die();
/** @var AdminThemeWorkspace $adminTheme */
/** @var Workspace $workspace */
$workspace = $modules->get('Workspace');
$apps = $user->isLoggedin() ? $workspace->apps() : [];
$current = $user->isLoggedin() ? $workspace->currentApp() : null;
$e = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8', false);
$themeInfo = $adminTheme->getThemeInfo('default');
if(!empty($themeInfo['path']) && is_file($themeInfo['path'] . 'ready.php')) include $themeInfo['path'] . 'ready.php';
$adminTheme->renderExtraMarkup('x');
$name = (string)($user->get('title|name'));
$title = $current['title'] ?? $workspace->title();
$home = $workspace->homeUrl();
$shell = $user->isLoggedin() && $layout !== 'modal';
$loginScreen = !$user->isLoggedin() && (string)$page->process === 'ProcessLogin';
$assetVersion = (string)max(filemtime(dirname(__DIR__).'/assets/workspace.css'), filemtime(dirname(__DIR__).'/assets/appearance.css'), filemtime(dirname(__DIR__).'/assets/workspace.js'), filemtime(dirname(__DIR__).'/assets/administration.css'), filemtime(dirname(__DIR__).'/assets/menus.css'));
?><!DOCTYPE html>
<html class="pw" lang="<?= $e(
    _x('en', 'HTML language code', Workspace::translationFile('AdminThemeWorkspace/_main.php'))) ?>">
<head>
<?php $adminTheme->includeFile('_head.php', ['layout' => $layout]); echo $adminTheme->renderExtraMarkup('head'); ?>
<?php if($loginScreen): ?><link rel="stylesheet" href="<?= $e($workspace->assetUrl('administration.css')) ?>?v=<?= $e($assetVersion) ?>"><?php endif ?>
<link rel="icon" type="image/svg+xml" href="<?= $e($workspace->assetUrl('workspace.svg')) ?>">
<link rel="stylesheet" href="<?= $e($workspace->assetUrl('workspace.css')) ?>?v=<?= $e($assetVersion) ?>">
<link rel="stylesheet" href="<?= $e($workspace->assetUrl('appearance.css')) ?>?v=<?= $e($assetVersion) ?>">
<link rel="stylesheet" href="<?= $e($workspace->assetUrl('menus.css')) ?>?v=<?= $e($assetVersion) ?>">
<script>
(function(){var r=document.documentElement,k='workspace.<?= (int)$user->id ?>.',m='system',a='blue',f='system',z='standard',c='standard';try{m=localStorage.getItem(k+'mode')||localStorage.getItem('workspace-theme')||m;a=localStorage.getItem(k+'accent')||a;f=localStorage.getItem(k+'font')||f;z=localStorage.getItem(k+'size')||z;c=localStorage.getItem(k+'contrast')||c;}catch(e){}if(!['light','dark','system','black'].includes(m))m='system';if(!['blue','violet','teal','forest','amber','rose','graphite','ocean','clay'].includes(a))a='blue';r.dataset.wsMode=m;r.dataset.wsTheme=m==='black'?'dark':m==='system'?(matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'):m;r.dataset.wsAccent=a;r.dataset.wsFont=['system','humanist','classic','rounded','serif','mono'].includes(f)?f:'system';r.dataset.wsSize=['compact','standard','large'].includes(z)?z:'standard';var n=100;try{var v=localStorage.getItem(k+'textScale');n=v===null?({compact:93,standard:100,large:114}[z]||100):Number(v);}catch(e){}if(!Number.isFinite(n)||n<85||n>125)n=100;r.dataset.wsScale=String(n);r.style.setProperty('--ws-scale',String(n/100));r.style.fontSize=(16*n/100)+'px';r.dataset.wsContrast=['standard','enhanced','maximum'].includes(c)?c:'standard';})();
</script>
</head>
<body data-ws-user="<?= (int)$user->id ?>" class="<?= $e($adminTheme->getBodyClass()) ?><?= !$shell ? ' ws-simple' : '' ?><?= $loginScreen ? ' ws-login-screen' : '' ?>">
<script>document.body.classList.remove('light-theme','dark-theme','auto-theme');document.body.classList.add(document.documentElement.dataset.wsTheme+'-theme');</script>
<a class="ws-skip" href="#main"><?= $e(
    __('Skip to content', Workspace::translationFile('AdminThemeWorkspace/_main.php'))) ?></a>
<?php if($shell): ?>
<header class="ws-header">
    <a class="ws-brand" href="<?= $e($home) ?>" aria-label="<?= $e(
    __('Workspace home', Workspace::translationFile('AdminThemeWorkspace/_main.php'))) ?>"><span class="ws-mark"><?= AdminThemeWorkspace::icon('workspace') ?></span><span>Workspace</span></a>
    <span class="ws-header-divider" aria-hidden="true"></span>
    <details class="ws-switcher ws-popover">
        <summary aria-label="<?= $e(
    __('Switch application', Workspace::translationFile('AdminThemeWorkspace/_main.php'))) ?>"><span class="ws-current-icon" style="--app-color:<?= $e($current['color'] ?? 'var(--ws-accent)') ?>"><?= AdminThemeWorkspace::icon($current['icon'] ?? 'grid') ?></span><span><?= $e($current['title'] ??
    __('All applications', Workspace::translationFile('AdminThemeWorkspace/_main.php'))) ?></span><?= AdminThemeWorkspace::icon('chevron') ?></summary>
        <nav class="ws-switcher-panel" aria-label="<?= $e(
    __('Applications', Workspace::translationFile('AdminThemeWorkspace/_main.php'))) ?>">
            <a class="ws-switcher-home" href="<?= $e($home) ?>"<?= !$current ? ' aria-current="page"' : '' ?>><span class="ws-mini-icon"><?= AdminThemeWorkspace::icon('grid') ?></span><span><?= $e(
    __('All applications', Workspace::translationFile('AdminThemeWorkspace/_main.php'))) ?></span><span class="ws-menu-count"><?= count($apps) ?></span></a>
            <div class="ws-switcher-grid"><?php foreach($apps as $app): ?><a class="ws-switcher-app<?= ($current['id'] ?? '') === $app['id'] ? ' is-active' : '' ?>" href="<?= $e($app['url']) ?>"<?= ($current['id'] ?? '') === $app['id'] ? ' aria-current="page"' : '' ?>><span class="ws-mini-icon" style="--app-color:<?= $e($app['color']) ?>"><?= AdminThemeWorkspace::icon($app['icon']) ?></span><span><?= $e($app['title']) ?></span></a><?php endforeach ?></div>
        </nav>
    </details>
    <div class="ws-header-end">

    <details class="ws-account ws-popover"><summary aria-label="<?= $e(
    __('Your account', Workspace::translationFile('AdminThemeWorkspace/_main.php'))) ?>"><span class="ws-avatar" aria-hidden="true"><?= AdminThemeWorkspace::icon('user') ?></span><span class="ws-account-name"><?= $e($name) ?></span><?= AdminThemeWorkspace::icon('chevron') ?></summary><div class="ws-account-panel"><div class="ws-account-identity"><span class="ws-avatar" aria-hidden="true"><?= AdminThemeWorkspace::icon('user') ?></span><div><strong><?= $e($name) ?></strong><?php if($user->email): ?><span class="ws-account-email"><?= $e($user->email) ?></span><?php endif ?></div></div><button class="ws-appearance-trigger" type="button" data-ws-appearance-open aria-haspopup="dialog"><?= AdminThemeWorkspace::icon('palette') ?><span class="ws-action-label"><?= $e(
    __('Appearance', Workspace::translationFile('AdminThemeWorkspace/_main.php'))) ?></span></button><?php if($user->isSuperuser()): ?><a href="<?= $e($home . 'appearance/') ?>"><?= AdminThemeWorkspace::icon('grid') ?><span><?= $e(
    __('Interface settings', Workspace::translationFile('AdminThemeWorkspace/_main.php'))) ?></span></a><?php endif ?><a class="ws-signout" href="<?= $e($config->urls->admin . 'login/logout/') ?>"><?= AdminThemeWorkspace::icon('logout') ?><span><?= $e(
    __('Sign out', Workspace::translationFile('AdminThemeWorkspace/_main.php'))) ?><small><?= $e(
    __('End this session', Workspace::translationFile('AdminThemeWorkspace/_main.php'))) ?></small></span></a></div></details></div>
</header>
<?php if($current && !empty($current['sections'])): ?><nav class="ws-app-nav" aria-label="<?= $e(sprintf(
    __('%s navigation', Workspace::translationFile('AdminThemeWorkspace/_main.php')), $title)) ?>"><a class="ws-app-overview" href="<?= $e($current['url']) ?>"><?= $e($title) ?></a><?php foreach($current['sections'] as $section): ?><a href="<?= $e($section['url']) ?>"><?= $e($section['label']) ?></a><?php endforeach ?></nav><?php endif ?>
<?php endif ?>
<?php if($shell): ?>
<dialog class="ws-appearance-dialog" id="ws-appearance-dialog" aria-labelledby="ws-appearance-title">
<div class="ws-preferences-page"><div class="ws-preferences-body"><div class="ws-preferences-titlebar"><div><h1 id="ws-appearance-title"><?= $e(
    __('Appearance', Workspace::translationFile('AdminThemeWorkspace/_main.php'))) ?></h1><p><?= $e(
    __('Make yourself comfortable.', Workspace::translationFile('AdminThemeWorkspace/_main.php'))) ?></p></div><div class="ws-preferences-actions"><button class="ws-dialog-close" type="button" data-ws-appearance-close aria-label="<?= $e(
    __('Close appearance', Workspace::translationFile('AdminThemeWorkspace/_main.php'))) ?>"><span aria-hidden="true">×</span></button></div></div>
<div class="ws-preferences-grid"><div class="ws-preferences-controls">
<section><h2><?= $e(
    __('Display mode', Workspace::translationFile('AdminThemeWorkspace/_main.php'))) ?></h2><div class="ws-mode-options" aria-label="<?= $e(
    __('Display mode', Workspace::translationFile('AdminThemeWorkspace/_main.php'))) ?>"><?php foreach(['light'=>['sun',
    __('Light', Workspace::translationFile('AdminThemeWorkspace/_main.php'))],'dark'=>['moon',
    __('Dark', Workspace::translationFile('AdminThemeWorkspace/_main.php'))],'system'=>['monitor',
    __('System', Workspace::translationFile('AdminThemeWorkspace/_main.php'))],'black'=>['moon',
    __('Black', Workspace::translationFile('AdminThemeWorkspace/_main.php'))]] as $mode=>$option): ?><button type="button" data-ws-mode="<?= $e($mode) ?>" aria-pressed="false"><?= AdminThemeWorkspace::icon($option[0]) ?><span><?= $e($option[1]) ?></span></button><?php endforeach ?></div></section>
<section><h2><?= $e(
    __('Color palette', Workspace::translationFile('AdminThemeWorkspace/_main.php'))) ?></h2><p class="ws-palette-legend"><?= $e(
    __('Primary · Background · Secondary', Workspace::translationFile('AdminThemeWorkspace/_main.php'))) ?></p><div class="ws-accent-options" aria-label="<?= $e(
    __('Color palette', Workspace::translationFile('AdminThemeWorkspace/_main.php'))) ?>"><?php foreach(['blue'=>
    __('Indigo', Workspace::translationFile('AdminThemeWorkspace/_main.php')),'violet'=>
    __('Violet', Workspace::translationFile('AdminThemeWorkspace/_main.php')),'teal'=>
    __('Teal', Workspace::translationFile('AdminThemeWorkspace/_main.php')),'forest'=>
    __('Emerald', Workspace::translationFile('AdminThemeWorkspace/_main.php')),'amber'=>
    __('Amber', Workspace::translationFile('AdminThemeWorkspace/_main.php')),'rose'=>
    __('Rose', Workspace::translationFile('AdminThemeWorkspace/_main.php')),'graphite'=>
    __('Mono', Workspace::translationFile('AdminThemeWorkspace/_main.php')),'ocean'=>
    __('Sky', Workspace::translationFile('AdminThemeWorkspace/_main.php')),'clay'=>
    __('Coffee', Workspace::translationFile('AdminThemeWorkspace/_main.php'))] as $accent=>$label): ?><button type="button" class="ws-palette-preset" data-ws-accent="<?= $e($accent) ?>" aria-pressed="false" title="<?= $e($label) ?>"><span class="ws-palette-colors" aria-hidden="true"><i></i><i></i><i></i></span><span><?= $e($label) ?></span><span class="ws-choice-check" aria-hidden="true">✓</span></button><?php endforeach ?></div></section>
<section><h2><?= $e(
    __('Typography', Workspace::translationFile('AdminThemeWorkspace/_main.php'))) ?></h2><div class="ws-type-controls"><label><?= $e(
    __('Font family', Workspace::translationFile('AdminThemeWorkspace/_main.php'))) ?><select data-ws-font><option value="system"><?= $e(
    __('System sans', Workspace::translationFile('AdminThemeWorkspace/_main.php'))) ?></option><option value="humanist"><?= $e(
    __('Humanist sans', Workspace::translationFile('AdminThemeWorkspace/_main.php'))) ?></option><option value="classic"><?= $e(
    __('Classic sans', Workspace::translationFile('AdminThemeWorkspace/_main.php'))) ?></option><option value="rounded"><?= $e(
    __('Rounded sans', Workspace::translationFile('AdminThemeWorkspace/_main.php'))) ?></option><option value="serif"><?= $e(
    __('Editorial serif', Workspace::translationFile('AdminThemeWorkspace/_main.php'))) ?></option><option value="mono"><?= $e(
    __('Monospace', Workspace::translationFile('AdminThemeWorkspace/_main.php'))) ?></option></select></label><div class="ws-size-control"><label for="ws-text-scale"><?= $e(
    __('Text size', Workspace::translationFile('AdminThemeWorkspace/_main.php'))) ?> <output for="ws-text-scale" data-ws-scale-output>100%</output></label><input id="ws-text-scale" type="range" min="85" max="125" step="1" value="100" data-ws-scale aria-describedby="ws-scale-hint"><span id="ws-scale-hint"><?= $e(
    __('Drag to adjust text size', Workspace::translationFile('AdminThemeWorkspace/_main.php'))) ?></span></div></div></section>
<section><h2><?= $e(
    __('Contrast', Workspace::translationFile('AdminThemeWorkspace/_main.php'))) ?></h2><div class="ws-contrast-options" aria-label="<?= $e(
    __('Contrast', Workspace::translationFile('AdminThemeWorkspace/_main.php'))) ?>"><?php foreach(['standard'=>
    __('Standard', Workspace::translationFile('AdminThemeWorkspace/_main.php')),'enhanced'=>
    __('Enhanced', Workspace::translationFile('AdminThemeWorkspace/_main.php')),'maximum'=>
    __('Maximum', Workspace::translationFile('AdminThemeWorkspace/_main.php'))] as $contrast=>$label): ?><button type="button" data-ws-contrast="<?= $e($contrast) ?>" aria-pressed="false"><?= $e($label) ?></button><?php endforeach ?></div></section>
<div class="ws-preferences-footer"><p class="ws-preferences-note" data-ws-preferences-status role="status"><?= $e(
    __('Saved automatically in this browser.', Workspace::translationFile('AdminThemeWorkspace/_main.php'))) ?></p><button type="button" data-ws-appearance-reset><?= $e(
    __('Reset settings', Workspace::translationFile('AdminThemeWorkspace/_main.php'))) ?></button></div></div>
</div></div></div>
</dialog>
<?php endif ?>
<main id="main" class="<?= $loginScreen ? 'ws-login-main' : 'ws-main pw-container' ?>" tabindex="-1"><div <?= $loginScreen ? 'class="ws-login-card"' : 'id="content" class="pw-content"' ?>>
<?= $adminTheme->renderNotices($notices) ?>
<?php if($loginScreen): ?><div class="ws-access-heading"><span class="ws-access-symbol"><?= AdminThemeWorkspace::icon('key') ?></span><p class="ws-eyebrow"><?= $e(
    __('WORKSPACE', Workspace::translationFile('AdminThemeWorkspace/_main.php'))) ?></p><h1><?= $e(
    __('Welcome to Workspace', Workspace::translationFile('AdminThemeWorkspace/_main.php'))) ?></h1><p><?= $e(
    __('Your applications. One place to sign in.', Workspace::translationFile('AdminThemeWorkspace/_main.php'))) ?></p></div><?php endif ?>
<?php if($current || (!$shell && !$loginScreen)): ?><header id="pw-content-head"><div id="pw-content-head-buttons"></div><h1 id="pw-content-title"><?= $e($adminTheme->getHeadline()) ?></h1></header><?php endif ?>
<div id="pw-content-body"><?= $content ?? '' ?></div>
</div></main>
<?php if($shell): ?><footer class="ws-footer"><span>Workspace</span><div class="ws-footer-end"><span><?= $e(
    __('Your tools. One workspace.', Workspace::translationFile('AdminThemeWorkspace/_main.php'))) ?></span><a class="ws-admin-entry" href="<?= $e($home . ($user->isSuperuser() ? 'appearance/' : 'administration/')) ?>" aria-label="<?= $e(
    __('Administrator access', Workspace::translationFile('AdminThemeWorkspace/_main.php'))) ?>" title="<?= $e(
    __('Administrator access', Workspace::translationFile('AdminThemeWorkspace/_main.php'))) ?>"><?= AdminThemeWorkspace::icon('key') ?></a></div></footer><?php endif ?>
<?= $adminTheme->renderExtraMarkup('body') ?>
<?php $adminTheme->includeFile('_body-scripts.php', ['layout' => $layout]); ?>
<script type="application/json" id="ws-translations"><?= json_encode(require dirname(__DIR__) . '/src/Presentation/WorkspaceClientTranslations.php', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?></script>
<script src="<?= $e($workspace->assetUrl('workspace.js')) ?>?v=<?= $e($assetVersion) ?>" defer></script>
</body></html>
