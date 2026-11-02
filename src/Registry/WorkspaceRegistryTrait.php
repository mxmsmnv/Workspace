<?php namespace ProcessWire;

trait WorkspaceRegistryTrait {
    /**
     * Hook after Workspace::getAppDefinitions to append, replace, remove or reorder descriptors.
     * Called per request/use; register hooks from an autoload module's init() or site/init.php.
     * Returned metadata is validated before native page/permission resolution.
     */
    public function ___getAppDefinitions(): array {
        return require dirname(__DIR__, 2) . '/integrations/built-in.php';
    }

    private function definitions(): array {
        $definitions = $this->getAppDefinitions();
        if (!is_array($definitions)) return [];
        $rows = $ids = $processes = [];
        foreach ($definitions as $definition) {
            if (!is_array($definition)) continue;
            $id = $definition['id'] ?? '';
            $process = $definition['process'] ?? '';
            $module = $definition['module'] ?? $process;
            if (!is_string($id) || !preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $id)
                || !is_string($process) || !preg_match('/^Process[A-Za-z0-9_]+$/D', $process)
                || !is_string($module) || !preg_match('/^[A-Za-z][A-Za-z0-9_]*$/D', $module)) continue;
            if (isset($ids[$id]) || isset($processes[$process])) continue;
            $title = $definition['title'] ?? '';
            if (!is_string($title) || trim($title) === '') continue;
            // Registration is for site applications, never an escape hatch into native core admin.
            $file = $this->wire('modules')->getModuleFile($process);
            $file = $file ? realpath($file) : false;
            $wire = realpath((string)$this->wire('config')->paths->wire);
            if ($file && $wire && str_starts_with($file, $wire . DIRECTORY_SEPARATOR)) continue;
            if (in_array($process, ['ProcessWorkspace', 'ProcessLogin'], true)) continue;
            $app = [
                'id'=>$id, 'process'=>$process, 'module'=>$module, 'title'=>trim($title),
                'description'=>is_string($definition['description'] ?? null) ? $definition['description'] : '',
                'icon'=>is_string($definition['icon'] ?? null) && preg_match('/^[a-z0-9-]+$/D', $definition['icon']) ? $definition['icon'] : 'grid',
                'color'=>is_string($definition['color'] ?? null) && preg_match('/^#[a-fA-F0-9]{6}$/D', $definition['color']) ? $definition['color'] : '#64748b',
                'navigationKey'=>is_string($definition['navigationKey'] ?? null) ? $definition['navigationKey'] : 'nav',
                'navigation'=>is_array($definition['navigation'] ?? null) ? $definition['navigation'] : null,
                'settingsSections'=>$this->sectionNames($definition['settingsSections'] ?? []),
                'allowedSections'=>isset($definition['allowedSections']) ? $this->sectionNames($definition['allowedSections']) : null,
            ];
            $ids[$id] = $processes[$process] = true;
            $rows[] = $app;
        }
        return $rows;
    }

    private function sectionNames($sections): array {
        if (!is_array($sections)) return [];
        return array_values(array_filter($sections, static fn($name): bool => is_string($name)
            && (bool)preg_match('/^[a-zA-Z0-9_-]+$/D', $name)));
    }

    private function isSettingsSection(array $app, string $path): bool {
        return WorkspaceConfigurationPolicy::isSettingsSection($app['id'], $path, $app['settingsSections']);
    }

    /** Descriptors never imply that a business application is installed. */
    public function allApps(): array {
        $modules = $this->wire('modules');
        $rows = [];
        foreach ($this->definitions() as $app) {
            $app['installed'] = $modules->isInstalled($app['module']) && $modules->isInstalled($app['process']);
            $page = $app['installed'] ? $this->processPage($app['process']) : null;
            $info = $app['installed'] ? $modules->getModuleInfo($app['process']) : [];
            $app['available'] = $page !== null && $this->canOpen($page, $info);
            $app['url'] = $app['available'] ? (string) $page->url : '';
            $app['pageId'] = $page ? (int) $page->id : 0;
            $app['sections'] = $app['available'] ? $this->sections($app, $info) : [];
            $rows[] = $app;
        }
        return $rows;
    }

    public function apps(): array {
        return array_values(array_filter($this->allApps(), static fn(array $app): bool => $app['available']));
    }

    private function processPage(string $process): ?Page {
        $modules = $this->wire('modules');
        if (!$modules->isInstalled($process)) return null;
        $id = (int) $modules->getModuleID($process);
        $adminId = (int) $this->wire('config')->adminRootPageID;
        if (!$id || !$adminId) return null;
        $page = $this->wire('pages')->findOne("template=admin, has_parent=$adminId, process=$id, include=all, sort=id");
        if (!$page->id || $page->hasStatus(Page::statusUnpublished) || $page->hasStatus(Page::statusTrash)) return null;
        return $page;
    }

    private function canOpen(Page $page, array $info): bool {
        $user = $this->wire('user');
        if (!$user instanceof User || !$user->isLoggedin()) return false;
        if (!$user->isSuperuser() && !$user->hasPermission('workspace-use')) return false;
        $permission = (string) ($info['permission'] ?? '');
        // An unpermissioned Process is superuser-only in native ProcessController.
        if (!$user->isSuperuser() && ($permission === '' || !$user->hasPermission($permission))) return false;
        return (bool) $page->viewable();
    }

    private function sections(array $app, array $info): array {
        $sections = [['label'=>__('Overview', Workspace::translationFile('src/Registry/WorkspaceRegistryTrait.php')), 'url'=>$app['url']]];
        $user = $this->wire('user');
        foreach ((array) ($app['navigation'] ?? $info[$app['navigationKey']] ?? []) as $item) {
            if (!is_array($item)) continue;
            if (!empty($item['module']) && (!is_string($item['module']) || !$this->wire('modules')->isInstalled($item['module']))) continue;
            if (!is_string($item['url'] ?? null) || !is_string($item['label'] ?? null)
                || (isset($item['permission']) && !is_string($item['permission']))) continue;
            $path = $item['url'];
            if ($this->isOperator() && $this->isSettingsSection($app, $path)) continue;
            if ($app['allowedSections'] !== null && !in_array(trim($path, '/'), $app['allowedSections'], true)) continue;
            // Native relative subsection URLs only; no external targets or traversal.
            if ($path === '' || !preg_match('~^[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*/?$~D', $path)) continue;
            $permission = (string) ($item['permission'] ?? '');
            if ($permission !== '' && !$user->isSuperuser() && !$user->hasPermission($permission)) continue;
            $label = trim((string) ($item['label'] ?? ''));
            if ($label === '') continue;
            $sections[] = ['label'=>($app['navigation'] !== null ? $label : __($label, $this->wire('modules')->getModuleFile($app['process']))), 'url'=>$app['url'] . ltrim($path, '/')];
        }
        return $sections;
    }
}
