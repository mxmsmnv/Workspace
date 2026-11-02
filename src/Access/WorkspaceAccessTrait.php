<?php namespace ProcessWire;

trait WorkspaceAccessTrait {
    /** The native login owns passwords, throttling and any configured second factor. */
    public function afterAdministratorLogin(HookEvent $event): void {
        $session = $this->wire('session');
        $requestedAt = (int) $session->getFor($this, 'administratorLogin');
        if (!$requestedAt) return;
        $session->removeFor($this, 'administratorLogin');
        if ($requestedAt < time() - 900) return;
        $event->return = $this->homeUrl() . ($this->wire('user')->isSuperuser() ? 'appearance/' : 'administration/');
    }

    /** Change only the signed-in superuser's preference; never elevate a role. */
    public function setInterfaceTheme(string $theme): void {
        $user = $this->wire('user');
        if (!$user->isSuperuser()) throw new WirePermissionException(__('Administrator access is required.', Workspace::translationFile('src/Access/WorkspaceAccessTrait.php')));
        if (!in_array($theme, ['AdminThemeWorkspace', 'AdminThemeUikit'], true)
            || !$this->wire('modules')->isInstalled($theme)) {
            throw new WireException(__('Choose an available interface.', Workspace::translationFile('src/Access/WorkspaceAccessTrait.php')));
        }
        $user->of(false);
        $user->set('admin_theme', $theme);
        $user->save('admin_theme');
    }

    public function isOperator(): bool {
        $user = $this->wire('user');
        return $user instanceof User && $user->isLoggedin()
            && !$user->isSuperuser() && $user->hasPermission('workspace-use');
    }

    private function configurationPolicy(): WorkspaceConfigurationPolicy {
        $config = $this->wire('config');
        $paths = $settings = [];
        foreach ($this->apps() as $app) {
            $paths[$app['id']] = $app['url'];
            $settings[$app['id']] = $app['settingsSections'];
        }
        return new WorkspaceConfigurationPolicy(
            (string)$config->urls->admin,
            (string)($_SERVER['REQUEST_URI'] ?? $this->wire('page')->url),
            ($config->https ? 'https://' : 'http://') . $config->httpHost,
            $paths, $settings
        );
    }

    public function filterOperatorConfigurationLinks(HookEvent $event): void {
        if (!$this->isOperator() || !is_string($event->return) || !$this->currentApp()) return;
        $event->return = $this->configurationPolicy()->filterMarkup($event->return);
    }

    /** Runs regardless of the user's selected admin theme. Native app ACL remains active. */
    public function guardProcess(HookEvent $event): void {
        if (!$this->isOperator()) return;
        $page = $this->wire('page');
        $config = $this->wire('config');
        if (!$page instanceof Page || !$page->template || $page->template->name !== 'admin') return;
        if ((int) $page->id === (int) $config->adminRootPageID) {
            // A write to the administrative root must never become an unrelated action.
            if (!$this->wire('input')->requestMethod('GET')) throw new WirePermissionException(__('This action is not available in Workspace.', Workspace::translationFile('src/Access/WorkspaceAccessTrait.php')));
            $this->wire('session')->redirect($this->homeUrl());
            return;
        }
        // Reject technical pages before Process init can redirect or perform setup work.
        $allowedPageIds = array_column($this->apps(), 'pageId');
        $homePage = $this->processPage('ProcessWorkspace');
        $loginPage = $this->processPage('ProcessLogin');
        if ($homePage) $allowedPageIds[] = (int) $homePage->id;
        if ($loginPage) $allowedPageIds[] = (int) $loginPage->id;
        if (!in_array((int) $page->id, $allowedPageIds, true)) {
            throw new WirePermissionException(__('This area is not available in Workspace.', Workspace::translationFile('src/Access/WorkspaceAccessTrait.php')));
        }
        foreach ($this->apps() as $app) {
            if ((int)$page->id === $app['pageId']
                && $this->isSettingsSection($app, (string)$this->wire('input')->urlSegment1)) {
                throw new WirePermissionException(__('Application settings are available only to an administrator.', Workspace::translationFile('src/Access/WorkspaceAccessTrait.php')));
            }
        }
        // getProcess() preserves native process permissions and inspects the actual controller target.
        $process = $event->object->getProcess();
        $name = $process instanceof Process ? $process->className() : '';
        if ($name === 'ProcessLogin') return;
        $home = $this->processPage('ProcessWorkspace');
        if ($name === 'ProcessWorkspace' && $home && (int) $home->id === (int) $page->id) return;
        foreach ($this->apps() as $app) {
            if ($name === $app['process'] && (int) $page->id === $app['pageId']) return;
        }
        throw new WirePermissionException(__('This area is not available in Workspace.', Workspace::translationFile('src/Access/WorkspaceAccessTrait.php')));
    }
}
