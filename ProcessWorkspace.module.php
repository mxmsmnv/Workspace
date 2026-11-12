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
        $title = __('Your workspace', Workspace::translationFile('ProcessWorkspace.module.php'));
        $this->headline($title);
        $theme = $this->wire('adminTheme');
        $standardAdmin = $theme instanceof AdminThemeUikit && !$theme instanceof AdminThemeWorkspace;
        $workspace = $this->wire('modules')->get('Workspace');
        $apps = $workspace->apps();
        $esc = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
        $icon = static fn($name): string => AdminThemeWorkspace::icon($name);
        if ($standardAdmin && !$theme->isModal) {
            $adminUrl = $esc($this->wire('config')->urls->admin);
            $adminLabel = $esc(__('Admin', Workspace::translationFile('ProcessWorkspace.module.php')));
            $currentLabel = $esc(__('Workspace', Workspace::translationFile('ProcessWorkspace.module.php')));
            $navLabel = $esc(__('Breadcrumb', Workspace::translationFile('ProcessWorkspace.module.php')));
            $theme->addHookAfter('renderBreadcrumbs', static function (HookEvent $event) use ($adminUrl, $adminLabel, $currentLabel, $navLabel): void {
                $event->return = '<nav aria-label="' . $navLabel . '"><ol class="uk-breadcrumb">'
                    . '<li><a href="' . $adminUrl . '">' . $adminLabel . '</a></li>'
                    . '<li><span aria-current="page">' . $currentLabel . '</span></li></ol></nav>';
            });
        }

        $native = static fn(string $classes): string => $standardAdmin ? ' ' . $classes : '';
        $userId = (int) $this->wire('user')->id;
        $out = '<section class="ws-launcher"' . ($standardAdmin ? ' data-ws-native' : '') . ' data-ws-user="' . $userId . '"><div class="ws-launcher-heading' . $native('uk-flex uk-flex-middle uk-flex-between uk-flex-wrap uk-margin-small-bottom') . '"><div>';
        if (!$standardAdmin) {
            $out .= '<p class="ws-eyebrow">' . $esc(__('APPLICATIONS', Workspace::translationFile('ProcessWorkspace.module.php')))
                . '</p><h1>' . $esc($title) . '</h1>';
        }
        $out .= '<p class="ws-lead' . $native('uk-margin-small-bottom') . '">' . $esc(__('The tools you use, together in one place.', Workspace::translationFile('ProcessWorkspace.module.php'))) . '</p></div>';
        $out .= '<label class="ws-search' . $native('uk-inline uk-width-medium') . '"><span class="ws-sr-only' . $native('uk-hidden-visually') . '">' . $esc(
            __('Find an application', Workspace::translationFile('ProcessWorkspace.module.php'))) . '</span>'.($standardAdmin ? '<span class="uk-form-icon">'.$icon('search').'</span>' : $icon('search')).'<input' . ($standardAdmin ? ' class="uk-input"' : '') . ' type="search" id="ws-app-search" data-ws-search placeholder="' . $esc(
            __('Find an application', Workspace::translationFile('ProcessWorkspace.module.php'))) . '" autocomplete="off">' . ($standardAdmin ? '' : '<kbd>/</kbd>') . '</label></div>';
        if ($this->wire('user')->isSuperuser()) {
            $out .= '<p class="ws-admin-shortcut' . $native('uk-margin-small') . '"><a class="uk-button uk-button-default" href="'.$esc($workspace->homeUrl().'appearance/').'">' . $esc(
            __('Interface settings', Workspace::translationFile('ProcessWorkspace.module.php'))) . '</a></p>';
        }
        $out .= '<div class="ws-library-toolbar' . $native('uk-flex uk-flex-middle uk-flex-between uk-flex-wrap uk-margin') . '"><div class="ws-library-filters' . $native('uk-flex uk-flex-wrap') . '" aria-label="' . $esc(
            __('Application filter', Workspace::translationFile('ProcessWorkspace.module.php'))) . '"><button' . ($standardAdmin ? ' class="uk-button uk-button-default uk-active uk-margin-small-right"' : '') . ' type="button" data-ws-filter="all" aria-pressed="true">' . $esc(
            __('All applications', Workspace::translationFile('ProcessWorkspace.module.php'))) . ' <span>'.count($apps).'</span></button><button' . ($standardAdmin ? ' class="uk-button uk-button-default"' : '') . ' type="button" data-ws-filter="pinned" aria-pressed="false">' . $esc(
            __('Favorites', Workspace::translationFile('ProcessWorkspace.module.php'))) . ' <span data-ws-pinned-count>0</span></button></div><span class="ws-result-count' . $native('uk-text-meta') . '" data-ws-count aria-live="polite">'.$esc(sprintf(
            _n('%d application', '%d applications', count($apps), Workspace::translationFile('ProcessWorkspace.module.php')), count($apps))).'</span></div><div class="ws-app-grid' . $native('uk-flex uk-flex-wrap') . '">';
        foreach ($apps as $app) {
            $out .= '<article class="ws-app-card' . $native('uk-card uk-card-default uk-card-small uk-card-body uk-position-relative uk-width-large uk-margin-small-right uk-margin-small-bottom') . '" data-ws-app data-ws-app-id="'.$esc($app['id']).'" data-search="'.$esc(strtolower($app['title'].' '.$app['description'])).'" style="--app-color:'.$esc($app['color']).'">';
            $out .= '<a class="ws-app-launch' . $native('uk-display-block uk-link-reset') . '" href="'.$esc($app['url']).'"><span class="ws-app-icon">'.$icon($app['icon']).'</span><div class="ws-app-copy' . $native('uk-margin-small') . '"><h2' . ($standardAdmin ? ' class="uk-card-title uk-margin-remove"' : '') . '>'.$esc($app['title']).'</h2><p' . ($standardAdmin ? ' class="uk-margin-small-top uk-margin-remove-bottom"' : '') . '>'.$esc($app['description']).'</p></div><span class="ws-app-open' . $native('uk-link') . '">' . $esc(
            __('Open application', Workspace::translationFile('ProcessWorkspace.module.php'))) . ' '.$icon('arrow').'</span></a>';
            $out .= '<button class="ws-pin' . $native('uk-button uk-button-default uk-button-small uk-position-top-right uk-position-small') . '" type="button" data-ws-pin="'.$esc($app['id']).'" data-ws-app-title="'.$esc($app['title']).'" aria-pressed="false" aria-label="'.$esc(sprintf(
            __('Add %s to favorites', Workspace::translationFile('ProcessWorkspace.module.php')), $app['title'])).'" title="'.$esc(
            __('Add to favorites', Workspace::translationFile('ProcessWorkspace.module.php'))).'"><span class="ws-star-outline">'.$icon('star').'</span><span class="ws-star-filled"' . ($standardAdmin ? ' hidden' : '') . '>'.$icon('star-filled').'</span></button></article>';
        }
        $out .= '</div><div class="ws-empty' . $native('uk-alert uk-margin') . '" data-ws-empty hidden><h2>' . $esc(
            __('No applications found', Workspace::translationFile('ProcessWorkspace.module.php'))) . '</h2><p data-ws-empty-message>' . $esc(
            __('Try another name or clear the search.', Workspace::translationFile('ProcessWorkspace.module.php'))) . '</p><button class="ws-empty-action' . $native('uk-button uk-button-default') . '" type="button" data-ws-reset>' . $esc(
            __('Show all applications', Workspace::translationFile('ProcessWorkspace.module.php'))) . '</button></div>';
        if (!$apps) $out .= '<div class="ws-empty' . $native('uk-alert uk-margin') . '"><h2>' . $esc(
            __('Your workspace is ready', Workspace::translationFile('ProcessWorkspace.module.php'))) . '</h2><p>' . $esc(
            __('Applications appear here when they are connected to your account.', Workspace::translationFile('ProcessWorkspace.module.php'))) . '</p></div>';
        $out .= '<p class="ws-home-note' . $native('uk-text-meta') . '">' . sprintf($esc(
            __('Use %s to search. Pin the applications you use most.', Workspace::translationFile('ProcessWorkspace.module.php'))), '<kbd>/</kbd>') . '</p><span class="ws-sr-only' . $native('uk-hidden-visually') . '" data-ws-announcement role="status"></span></section>';
        if ($standardAdmin) {
            $messages = json_encode(require __DIR__ . '/src/Presentation/WorkspaceClientTranslations.php', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
            $scriptUrl = $esc($workspace->assetUrl('launcher.js') . '?v=' . filemtime(__DIR__ . '/assets/launcher.js'));
            $out .= '<script type="application/json" id="ws-translations">' . $messages . '</script>'
                . '<script src="' . $scriptUrl . '" defer></script>';
        }
        return $out;
    }

    public function ___executeAccount(): string {
        $this->headline(__('Your account', Workspace::translationFile('ProcessWorkspace.module.php')));
        $user = $this->wire('user');
        $workspace = $this->wire('modules')->get('Workspace');
        $esc = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
        $passwordLink = '<a class="uk-button uk-button-default" href="' . $esc($workspace->homeUrl() . 'password/') . '">' . $esc(
            __('Change password', Workspace::translationFile('ProcessWorkspace.module.php'))) . '</a>';
        return '<section class="ws-account"><p class="ws-eyebrow">' . $esc(
            __('WORKSPACE', Workspace::translationFile('ProcessWorkspace.module.php'))) . '</p><h1>' . $esc(
            __('Your account', Workspace::translationFile('ProcessWorkspace.module.php'))) . '</h1><dl><dt>' . $esc(
            __('Name', Workspace::translationFile('ProcessWorkspace.module.php'))) . '</dt><dd>'.$esc($user->name).'</dd><dt>' . $esc(
            __('Email', Workspace::translationFile('ProcessWorkspace.module.php'))) . '</dt><dd>'.$esc($user->email).'</dd></dl><p>' . $esc(
            __('Contact your workspace administrator to change your account details.', Workspace::translationFile('ProcessWorkspace.module.php'))) . '</p><p>' . $passwordLink . '</p></section>';
    }

    public function ___executePassword(): string {
        $user = $this->wire('user');
        if (!$user->isLoggedin()) {
            throw new WirePermissionException(__('Sign in to change your password.'));
        }

        $title = __('Change password', Workspace::translationFile('ProcessWorkspace.module.php'));
        $this->headline($title);
        $input = $this->wire('input');
        $session = $this->wire('session');
        $modules = $this->wire('modules');
        $workspace = $modules->get('Workspace');
        $url = $workspace->homeUrl() . 'password/';

        $password = $user->getField('pass')->getInputfield($user);
        $password->attr('name', 'pass');
        $password->set('requireOld', InputfieldPassword::requireOldYes);
        $password->required = true;
        $password->collapsed = Inputfield::collapsedNo;

        $form = $modules->get('InputfieldForm');
        $form->attr('method', 'post');
        $form->attr('action', $url);
        $form->add($password);
        $submit = $modules->get('InputfieldSubmit');
        $submit->attr('name', 'save_password');
        $submit->attr('value', $title);
        $form->add($submit);

        if ($input->requestMethod('POST')) {
            $session->CSRF->validate();
            $post = $input->post;
            $complete = true;
            foreach (['pass', '_pass', '_old_pass'] as $name) {
                $value = $post->get($name);
                if (!is_string($value) || $value === '') $complete = false;
            }
            if (!$complete) {
                $password->error(__('Enter your current password and confirm your new password.', Workspace::translationFile('ProcessWorkspace.module.php')));
            } else {
                $password->processInput($post);
                $newPassword = $password->val();
                if ($password->getErrors() === [] && is_string($newPassword) && $newPassword !== '') {
                    $formatted = $user->of(false);
                    try {
                        $user->set('pass', $newPassword);
                        if (!$user->save('pass')) throw new WireException(__('Unable to save the password.'));
                    } finally {
                        $user->of($formatted);
                    }
                    $session->message(__('Your password was changed.', Workspace::translationFile('ProcessWorkspace.module.php')));
                    $session->redirect($url, false);
                }
            }
            $password->attr('value', '');
        }

        $label = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<section class="ws-account"><h1>' . $label . '</h1>' . $form->render() . '</section>';
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
