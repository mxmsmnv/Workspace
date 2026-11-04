<?php namespace ProcessWire;

/** Application-only home; no business records are duplicated here. */
class ProcessWorkspace extends Process {
    public static function getModuleInfo(): array {
        return [
            'title' => 'Workspace', 'version' => 101, 'author' => 'Maxim Semenov',
            'summary' => 'A focused home for your business applications.',
            'requires' => ['Workspace'], 'permission' => 'workspace-use',
            'icon' => 'th-large', 'page' => ['name' => 'workspace', 'title' => 'Workspace'],
        ];
    }

    public function init() {
        parent::init();
        if (in_array((string)$this->wire('input')->urlSegment1, ['appearance', 'administration'], true)) {
            $this->wire('config')->styles->add($this->wire('modules')->get('Workspace')->assetUrl('administration.css') . '?v=' . filemtime(__DIR__.'/assets/administration.css'));
        }
    }

    public function ___execute(): string {
        $this->headline(__('Your workspace', Workspace::translationFile('ProcessWorkspace.module.php')));
        $workspace = $this->wire('modules')->get('Workspace');
        $apps = $workspace->apps();
        $esc = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
        $icon = static fn($name): string => AdminThemeWorkspace::icon($name);
        $out = '<section class="ws-launcher"><div class="ws-launcher-heading"><div><p class="ws-eyebrow">' . $esc(
            __('APPLICATIONS', Workspace::translationFile('ProcessWorkspace.module.php'))) . '</p><h1>' . $esc(
            __('Your workspace', Workspace::translationFile('ProcessWorkspace.module.php'))) . '</h1><p class="ws-lead">' . $esc(
            __('The tools you use, together in one place.', Workspace::translationFile('ProcessWorkspace.module.php'))) . '</p></div>';
        $out .= '<label class="ws-search"><span class="ws-sr-only">' . $esc(
            __('Find an application', Workspace::translationFile('ProcessWorkspace.module.php'))) . '</span>'.$icon('search').'<input type="search" id="ws-app-search" data-ws-search placeholder="' . $esc(
            __('Find an application', Workspace::translationFile('ProcessWorkspace.module.php'))) . '" autocomplete="off"><kbd>/</kbd></label></div>';
        if ($this->wire('user')->isSuperuser()) {
            $out .= '<p class="ws-admin-shortcut"><a class="uk-button uk-button-default" href="'.$esc($workspace->homeUrl().'appearance/').'">' . $esc(
            __('Interface settings', Workspace::translationFile('ProcessWorkspace.module.php'))) . '</a></p>';
        }
        $out .= '<div class="ws-library-toolbar"><div class="ws-library-filters" aria-label="' . $esc(
            __('Application filter', Workspace::translationFile('ProcessWorkspace.module.php'))) . '"><button type="button" data-ws-filter="all" aria-pressed="true">' . $esc(
            __('All applications', Workspace::translationFile('ProcessWorkspace.module.php'))) . ' <span>'.count($apps).'</span></button><button type="button" data-ws-filter="pinned" aria-pressed="false">' . $esc(
            __('Favorites', Workspace::translationFile('ProcessWorkspace.module.php'))) . ' <span data-ws-pinned-count>0</span></button></div><span class="ws-result-count" data-ws-count aria-live="polite">'.$esc(sprintf(
            _n('%d application', '%d applications', count($apps), Workspace::translationFile('ProcessWorkspace.module.php')), count($apps))).'</span></div><div class="ws-app-grid">';
        foreach ($apps as $app) {
            $out .= '<article class="ws-app-card" data-ws-app data-ws-app-id="'.$esc($app['id']).'" data-search="'.$esc(strtolower($app['title'].' '.$app['description'])).'" style="--app-color:'.$esc($app['color']).'">';
            $out .= '<a class="ws-app-launch" href="'.$esc($app['url']).'"><span class="ws-app-icon">'.$icon($app['icon']).'</span><div class="ws-app-copy"><h2>'.$esc($app['title']).'</h2><p>'.$esc($app['description']).'</p></div><span class="ws-app-open">' . $esc(
            __('Open application', Workspace::translationFile('ProcessWorkspace.module.php'))) . ' '.$icon('arrow').'</span></a>';
            $out .= '<button class="ws-pin" type="button" data-ws-pin="'.$esc($app['id']).'" data-ws-app-title="'.$esc($app['title']).'" aria-pressed="false" aria-label="'.$esc(sprintf(
            __('Add %s to favorites', Workspace::translationFile('ProcessWorkspace.module.php')), $app['title'])).'" title="'.$esc(
            __('Add to favorites', Workspace::translationFile('ProcessWorkspace.module.php'))).'"><span class="ws-star-outline">'.$icon('star').'</span><span class="ws-star-filled">'.$icon('star-filled').'</span></button></article>';
        }
        $out .= '</div><div class="ws-empty" data-ws-empty hidden><h2>' . $esc(
            __('No applications found', Workspace::translationFile('ProcessWorkspace.module.php'))) . '</h2><p data-ws-empty-message>' . $esc(
            __('Try another name or clear the search.', Workspace::translationFile('ProcessWorkspace.module.php'))) . '</p><button class="ws-empty-action" type="button" data-ws-reset>' . $esc(
            __('Show all applications', Workspace::translationFile('ProcessWorkspace.module.php'))) . '</button></div>';
        if (!$apps) $out .= '<div class="ws-empty"><h2>' . $esc(
            __('Your workspace is ready', Workspace::translationFile('ProcessWorkspace.module.php'))) . '</h2><p>' . $esc(
            __('Applications appear here when they are connected to your account.', Workspace::translationFile('ProcessWorkspace.module.php'))) . '</p></div>';
        return $out.'<p class="ws-home-note">' . sprintf($esc(
            __('Use %s to search. Pin the applications you use most.', Workspace::translationFile('ProcessWorkspace.module.php'))), '<kbd>/</kbd>') . '</p><span class="ws-sr-only" data-ws-announcement role="status"></span></section>';
    }

    public function ___executeAccount(): string {
        $this->headline(__('Your account', Workspace::translationFile('ProcessWorkspace.module.php')));
        $user = $this->wire('user');
        $esc = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
        return '<section class="ws-account"><p class="ws-eyebrow">' . $esc(
            __('WORKSPACE', Workspace::translationFile('ProcessWorkspace.module.php'))) . '</p><h1>' . $esc(
            __('Your account', Workspace::translationFile('ProcessWorkspace.module.php'))) . '</h1><dl><dt>' . $esc(
            __('Name', Workspace::translationFile('ProcessWorkspace.module.php'))) . '</dt><dd>'.$esc($user->name).'</dd><dt>' . $esc(
            __('Email', Workspace::translationFile('ProcessWorkspace.module.php'))) . '</dt><dd>'.$esc($user->email).'</dd></dl><p>' . $esc(
            __('Contact your workspace administrator to change your account details.', Workspace::translationFile('ProcessWorkspace.module.php'))) . '</p></section>';
    }

    public function ___executeAdministration(): string {
        $workspace = $this->wire('modules')->get('Workspace');
        $session = $this->wire('session');
        if ($this->wire('user')->isSuperuser()) {
            $session->redirect($workspace->homeUrl() . 'appearance/', false);
        }
        $this->headline(__('Administrator access', Workspace::translationFile('ProcessWorkspace.module.php')));
        if ($this->wire('input')->requestMethod('POST')) {
            $session->CSRF->validate();
            // Switching accounts uses the regular login, never a shared password or forceLogin.
            $session->logout();
            $session->setFor($workspace, 'administratorLogin', time());
            $session->redirect($this->wire('config')->urls->admin . 'login/', false);
        }
        $esc = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
        return '<section class="ws-access"><a class="ws-access-back" href="'.$esc($workspace->homeUrl()).'">' . $esc(
            __('← Back to applications', Workspace::translationFile('ProcessWorkspace.module.php'))) . '</a><div class="ws-access-card"><div class="ws-access-heading"><span class="ws-access-symbol">'.AdminThemeWorkspace::icon('key').'</span><p class="ws-eyebrow">' . $esc(
            __('WORKSPACE ADMINISTRATION', Workspace::translationFile('ProcessWorkspace.module.php'))) . '</p><h1>' . $esc(
            __('Administrator access', Workspace::translationFile('ProcessWorkspace.module.php'))) . '</h1><p>' . $esc(
            __('Manage your workspace and system settings.', Workspace::translationFile('ProcessWorkspace.module.php'))) . '</p></div><div class="ws-access-account"><span>' . $esc(
            __('Current account', Workspace::translationFile('ProcessWorkspace.module.php'))) . '</span><strong>'.$esc($this->wire('user')->name).'</strong></div><p class="ws-access-explanation">' . $esc(
            __('Continue to sign in with your administrator account. This will sign out your current account.', Workspace::translationFile('ProcessWorkspace.module.php'))) . '</p><form method="post">'
            . $session->CSRF->renderInput()
            . '<button class="uk-button uk-button-primary" type="submit">' . $esc(
            __('Continue to sign in', Workspace::translationFile('ProcessWorkspace.module.php'))) . ' '.AdminThemeWorkspace::icon('arrow').'</button></form><p class="ws-access-footnote">' . $esc(
            __('Administrator account required', Workspace::translationFile('ProcessWorkspace.module.php'))) . '</p></div></section>';

    }

    public function ___executeAppearance(): string {
        if (!$this->wire('user')->isSuperuser()) {
            throw new WirePermissionException(__('Administrator access is required.', Workspace::translationFile('ProcessWorkspace.module.php')));
        }
        $workspace = $this->wire('modules')->get('Workspace');
        $session = $this->wire('session');
        $this->headline(__('Interface settings', Workspace::translationFile('ProcessWorkspace.module.php')));
        if ($this->wire('input')->requestMethod('POST')) {
            $session->CSRF->validate();
            $theme = $this->wire('input')->post('workspace_theme');
            if (!is_string($theme)) throw new WireException(__('Choose an available interface.', Workspace::translationFile('ProcessWorkspace.module.php')));
            $workspace->setInterfaceTheme($theme);
            $session->message(__('Your interface preference was saved.', Workspace::translationFile('ProcessWorkspace.module.php')));
            $session->redirect($workspace->homeUrl() . 'appearance/', false);
        }
        $esc = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
        $selected = (string)($this->wire('user')->admin_theme ?: $this->wire('config')->defaultAdminTheme);
        $out = '<section class="ws-settings"><p class="ws-eyebrow">' . $esc(
            __('ADMINISTRATION', Workspace::translationFile('ProcessWorkspace.module.php'))) . '</p><h1>' . $esc(
            __('Choose your interface', Workspace::translationFile('ProcessWorkspace.module.php'))) . '</h1><p>' . $esc(
            __('This preference applies to your administrator account. Other users keep their current interface and permissions.', Workspace::translationFile('ProcessWorkspace.module.php'))) . '</p><form method="post" class="uk-form-stacked">'.$session->CSRF->renderInput();
        foreach (['AdminThemeWorkspace' => [
            __('Application workspace', Workspace::translationFile('ProcessWorkspace.module.php')),
            __('A focused launcher and shared navigation for your applications.', Workspace::translationFile('ProcessWorkspace.module.php'))], 'AdminThemeUikit' => [
            __('Standard administration', Workspace::translationFile('ProcessWorkspace.module.php')),
            __('The full administration interface for maintaining the system.', Workspace::translationFile('ProcessWorkspace.module.php'))]] as $theme => $details) {
            if (!$this->wire('modules')->isInstalled($theme)) continue;
            $out .= '<label class="ws-interface-option"><input class="uk-radio" type="radio" name="workspace_theme" value="'.$esc($theme).'"'.($selected === $theme ? ' checked' : '').'><span><strong>'.$esc($details[0]).'</strong><span>'.$esc($details[1]).'</span></span></label>';
        }
        return $out.'<div class="ws-settings-actions"><button class="uk-button uk-button-primary" type="submit">' . $esc(
            __('Save interface', Workspace::translationFile('ProcessWorkspace.module.php'))) . '</button><a class="uk-button uk-button-default" href="'.$esc($workspace->homeUrl()).'">' . $esc(
            __('Back to applications', Workspace::translationFile('ProcessWorkspace.module.php'))) . '</a></div></form></section>';
    }
}
