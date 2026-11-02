<?php namespace ProcessWire;

/**
 * Read-only integration smoke test against a prepared, isolated real PW site.
 * WORKSPACE_SITE_ROOT=/path/to/site WORKSPACE_OPERATOR=workspace-operator php tests/registry.php
 * No users, roles, themes, business data or module configuration are saved.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = realpath((string) getenv('WORKSPACE_SITE_ROOT'));
if (!$root || !is_file($root . '/index.php') || !is_file($root . '/wire/core/ProcessWire.php')) {
    fwrite(STDERR, "Set WORKSPACE_SITE_ROOT to an isolated, prepared ProcessWire installation.\n");
    exit(2);
}
$completed = false;
register_shutdown_function(static function() use (&$completed): void {
    if (!$completed) {
        fwrite(STDERR, "Workspace smoke test terminated before completing assertions.\n");
        exit(1);
    }
});
// Bootstrap the API directly: index.php also executes ProcessPageView and may
// terminate for an external script located inside a symlinked site directory.
if (!defined('PROCESSWIRE')) define('PROCESSWIRE', 303);
if (is_file($root . '/vendor/autoload.php')) require_once $root . '/vendor/autoload.php';
require_once $root . '/wire/core/ProcessWire.php';
$configuration = ProcessWire::buildConfig($root);
$configuration->internal = false;
$pw = new ProcessWire($configuration);
$redirectHook = $pw->wire('session')->addHookBefore('redirect', static function(HookEvent $event): void {
    throw new \RuntimeException('Unexpected redirect during smoke test: ' . (string) $event->arguments(0));
});
$savedUser = $pw->wire('user');
$savedPage = $pw->wire('page');
$savedProcess = $pw->wire('process');
$savedTheme = $pw->wire('adminTheme');
$savedSegments = $pw->wire('input')->urlSegments();
$checks = 0;
$assert = static function(bool $condition, string $message) use (&$checks): void {
    if (!$condition) throw new \RuntimeException($message);
    $checks++;
};
$pageFor = static function(string $process) use ($pw): Page {
    $id = (int) $pw->wire('modules')->getModuleID($process);
    $admin = (int) $pw->wire('config')->adminRootPageID;
    if (!$id) return $pw->wire('pages')->newNullPage();
    return $pw->wire('pages')->findOne("template=admin, has_parent=$admin, process=$id, include=all, sort=id");
};

try {
    $modules = $pw->wire('modules');
    $assert($modules->isInstalled('Workspace'), 'Install Workspace before running this test.');
    $assert($modules->isInstalled('ProcessWorkspace'), 'Install ProcessWorkspace before running this test.');
    $workspace = $modules->get('Workspace');
    $operatorName = (string) (getenv('WORKSPACE_OPERATOR') ?: 'workspace-operator');
    $operator = $pw->wire('users')->get($operatorName);
    $assert($operator instanceof User && (int) $operator->id > 0, 'Prepared operator fixture not found; set WORKSPACE_OPERATOR.');
    $assert(!$operator->isSuperuser() && $operator->hasPermission('workspace-use'), 'Fixture must be a non-superuser with workspace-use.');
    $pw->wire('users')->setCurrentUser($operator);
    $assert($workspace->isOperator(), 'Operator detection failed.');
    $all = $workspace->allApps();
    $assert(array_column($all, 'id') === ['kontor','mercato','mailbox','dispatch','files','tickets'], 'Default compatibility descriptors must remain the existing six apps.');
    $adminPage = $pw->wire('pages')->get((int) $pw->wire('config')->adminRootPageID);
    $assert((int) $adminPage->id > 0, 'Configured admin root must exist.');
    $home = $pageFor('ProcessWorkspace');
    $assert((int) $home->id > 0 && $workspace->homeUrl() === (string) $home->url, 'Home URL must resolve the real Process page.');
    $assert(str_starts_with($workspace->homeUrl(), (string) $adminPage->url), 'Home must use the configured admin root.');
    $launched = array_column($workspace->apps(), 'id');
    foreach ($all as $app) {
        $installed = $modules->isInstalled($app['module']) && $modules->isInstalled($app['process']);
        $assert($app['installed'] === $installed, $app['id'] . ': installed state must be factual.');
        if (!$installed) {
            $assert(!$app['available'] && $app['url'] === '' && !in_array($app['id'], $launched, true), $app['id'] . ': absent application must not have a fabricated launch target.');
        }
        if ($app['available']) {
            $actual = $pageFor($app['process']);
            $assert($app['url'] === (string) $actual->url && (int) $app['pageId'] === (int) $actual->id, $app['id'] . ': target must be its actual Process page.');
            $assert($actual->viewable(), $app['id'] . ': launcher bypassed native Page access.');
            $permission = (string) ($modules->getModuleInfo($app['process'])['permission'] ?? '');
            $assert($permission !== '' && $operator->hasPermission($permission), $app['id'] . ': launcher bypassed native Process permission.');
            $assert(str_starts_with($app['url'], (string) $adminPage->url), $app['id'] . ': wrong admin root.');
        }
    }

    // A third-party descriptor uses an existing fixture Process under a new app ID.
    // No fixture modules/pages or business records are installed or saved.
    $registration = $workspace->addHookAfter('getAppDefinitions', static function(HookEvent $event): void {
        $event->return = [
            ['id'=>'third-party', 'title'=>'Third-party library', 'process'=>'ProcessFiles',
                'color'=>'red; background:url(https://example.org)', 'icon'=>'../../private',
                'settingsSections'=>['maintenance-tools'], 'navigation'=>[
                    ['url'=>'records/', 'label'=>'Records'],
                    ['url'=>'maintenance-tools/', 'label'=>'Maintenance'],
                    ['url'=>'https://example.org/', 'label'=>'External'],
                    ['url'=>'../module/', 'label'=>'Traversal'],
                    ['url'=>[], 'label'=>[]],
                    ['url'=>'secret/', 'label'=>'Secret', 'permission'=>'superuser'],
                ], 'url'=>'https://example.org/forged', 'pageId'=>1, 'available'=>true, 'permission'=>'page-edit'],
            ['id'=>'third-party', 'title'=>'Duplicate ID', 'process'=>'ProcessDispatch'],
            ['id'=>'duplicate-process', 'title'=>'Duplicate Process', 'process'=>'ProcessFiles'],
            ['id'=>'missing-app', 'title'=>'Missing app', 'process'=>'ProcessWorkspaceAbsentFixture'],
            ['id'=>'native-edit', 'title'=>'Native editor', 'process'=>'ProcessPageEdit'],
            ['id'=>'native-modules', 'title'=>'Native modules', 'process'=>'ProcessModule'],
            ['id'=>'launcher', 'title'=>'Launcher', 'process'=>'ProcessWorkspace'],
            ['id'=>'../bad', 'title'=>'Bad ID', 'process'=>'ProcessAnything'],
            ['id'=>'bad-process', 'title'=>'Bad Process', 'process'=>'ProcessFiles, id>0'],
            ['id'=>'bad-title', 'title'=>[], 'process'=>'ProcessAnything'],
            'malformed',
        ];
    });
    try {
        $custom = $workspace->allApps();
        $assert(array_column($custom, 'id') === ['third-party','missing-app'], 'Hook descriptors must validate/deduplicate and exclude core admin.');
        $assert($custom[0]['color'] === '#64748b' && $custom[0]['icon'] === 'grid', 'Unsafe color and icon paths must fall back to safe defaults.');
        $assert($custom[0]['module'] === 'ProcessFiles', 'Process can be its own provider module.');
        $actualFiles = $pageFor('ProcessFiles');
        $assert($custom[0]['available'] && $custom[0]['pageId'] === (int)$actualFiles->id && $custom[0]['url'] === $actualFiles->url, 'Hook metadata must not forge availability, URL or page ID.');
        $assert(!$custom[1]['installed'] && !$custom[1]['available'] && $custom[1]['url'] === '', 'Uninstalled hook apps must not produce launch targets.');
        $assert(array_column($custom[0]['sections'], 'label') === ['Overview','Records'], 'Hook navigation must filter settings, unsafe paths, invalid metadata and missing permissions.');
        $pw->wire('page', $actualFiles);
        $pw->wire('input')->setUrlSegments([]);
        $controller = $pw->wire(new ProcessController());
        $controller->setProcessName('ProcessFiles');
        $event = $pw->wire(new HookEvent());
        $event->object = $controller;
        $workspace->guardProcess($event);
        $assert(true, 'Registered app passes the server guard with native permission.');
        $assert($workspace->currentApp()['id'] === 'third-party' && $workspace->isShellPage(), 'A hook app must participate in shell/current app resolution.');
        foreach (['AdminThemeWorkspace','AdminThemeUikit'] as $themeName) {
            $themeProbe = clone $operator;
            $themeProbe->set('admin_theme', $themeName);
            $pw->wire('users')->setCurrentUser($themeProbe);
            $pw->wire('input')->setUrlSegments(['maintenance-tools']);
            $controller = $pw->wire(new ProcessController());
            $controller->setProcessName('ProcessFiles');
            $blocked = false;
            try { $controller->execute(); } catch (WirePermissionException $error) {
                $blocked = str_contains($error->getMessage(), 'settings are available only');
            }
            $assert($blocked, 'Custom settings paths must be denied before execution.');
        }
        $pw->wire('users')->setCurrentUser($operator);
        $pw->wire('input')->setUrlSegments([]);
        $permissionProbe = clone $operator;
        $probeRoles = $pw->wire(new PageArray());
        $nativePermission = $modules->getModuleInfo('ProcessFiles')['permission'];
        foreach ($operator->roles as $originalRole) {
            $role = clone $originalRole;
            $role->set('permissions', clone $originalRole->permissions);
            $role->removePermission($nativePermission);
            $probeRoles->add($role);
        }
        $permissionProbe->set('roles', $probeRoles);
        $pw->wire('users')->setCurrentUser($permissionProbe);
        $assert($permissionProbe->hasPermission('workspace-use') && !$permissionProbe->hasPermission($nativePermission), 'Prepare permission probe without modifying stored roles.');
        $assert($workspace->apps() === [], 'Hook registration must never bypass native Process permission.');
        $pw->wire('users')->setCurrentUser($operator);
    } finally {
        $workspace->removeHook($registration);
        $pw->wire('users')->setCurrentUser($operator);
        $pw->wire('input')->setUrlSegments($savedSegments);
        $pw->wire('page', $savedPage);
    }
    $assert(count($workspace->allApps()) === 6, 'Removing the hook must restore compatibility definitions.');

    // Use real User/Role/Permission objects cloned only in memory. Give a probe
    // the native core permission so a denial cannot be credited to native ACL alone.
    $probe = clone $operator;
    $roles = $pw->wire(new PageArray());
    foreach ($operator->roles as $originalRole) {
        if ((int) $originalRole->id === (int) $pw->wire('config')->guestUserRolePageID) continue;
        $role = clone $originalRole;
        $role->set('permissions', clone $originalRole->permissions);
        $role->addPermission('page-edit');
        $roles->add($role);
        break;
    }
    $assert(count($roles) > 0, 'Operator needs a non-guest role for the in-memory permission probe.');
    $probe->set('roles', $roles);
    $pw->wire('users')->setCurrentUser($probe);
    $assert($probe->hasPermission('page-edit') && !$probe->isSuperuser(), 'Native-permission probe preparation failed.');
    $technicalPage = $pageFor('ProcessPageEdit');
    $assert((int) $technicalPage->id > 0, 'Prepared site must include native ProcessPageEdit.');
    foreach (['AdminThemeUikit', 'AdminThemeWorkspace'] as $themeName) {
        // Theme choice changes only the cloned User field, never stored profile/default.
        $probe->set('admin_theme', $themeName);
        $pw->wire('page', $technicalPage);
        // Native PageEdit can initialise without an edit target in this
        // read-only metadata route; its execute must still never run.
        $pw->wire('input')->setUrlSegments(['navJSON']);
        $controller = $pw->wire(new ProcessController());
        $controller->setProcessName('ProcessPageEdit');
        $assert($controller->getProcess() instanceof Process, 'Native permission must pass before checking Workspace denial.');
        $blocked = false;
        try { $controller->execute(); }
        catch (WirePermissionException $e) {
            $blocked = str_contains($e->getMessage(), 'not available in Workspace');
        }
        $assert($blocked, 'Workspace must deny native PageEdit even with page-edit and theme ' . $themeName . '.');
    }
    // Returning to the fixture must not have altered its persistent role or theme.
    $pw->wire('users')->setCurrentUser($operator);
    $assert($workspace->isOperator(), 'Original operator must remain valid after the probes.');
    echo "Workspace real-runtime registry smoke test passed: $checks checks.\n";
} catch (\Throwable $error) {
    fwrite(STDERR, 'Workspace smoke test failed: ' . $error->getMessage() . "\n");
    $exitCode = 1;
} finally {
    $pw->wire('users')->setCurrentUser($savedUser);
    $pw->wire('page', $savedPage);
    $pw->wire('process', $savedProcess);
    $pw->wire('adminTheme', $savedTheme);
    $pw->wire('input')->setUrlSegments($savedSegments);
    $pw->wire('session')->removeHook($redirectHook);
}
$completed = true;
exit($exitCode ?? 0);
