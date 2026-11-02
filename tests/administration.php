<?php namespace ProcessWire;
/** Real-site authorization, CSRF and current-account-only preference checks. Restores saved preference. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = realpath((string)getenv('WORKSPACE_SITE_ROOT'));
if (!$root || !is_file($root.'/wire/core/ProcessWire.php')) { fwrite(STDERR,"Set WORKSPACE_SITE_ROOT.\n"); exit(2); }
require $root.'/wire/core/ProcessWire.php';
$pw = new ProcessWire($root.'/', 'https://workspace.test:8443/');
$originalUser = $pw->user;
$admin = $pw->users->get($pw->config->superUserPageID);
$operator = $pw->users->get(getenv('WORKSPACE_OPERATOR') ?: 'workspace-demo');
if (!$operator->id || $operator->isSuperuser()) throw new \RuntimeException('A non-superuser operator fixture is required.');
$originalTheme = (string)$admin->admin_theme;
$operatorTheme = (string)$operator->admin_theme;
$originalDefault = (string)$pw->config->defaultAdminTheme;
$checks = 0; $finished = false;
register_shutdown_function(static function() use (&$finished) { if (!$finished) { fwrite(STDERR,"Administration test exited prematurely.\n"); exit(1); } });
$assert = static function(bool $ok, string $message) use (&$checks) { if (!$ok) throw new \RuntimeException($message); $checks++; };
$workspace = $pw->modules->get('Workspace');
$pw->users->setCurrentUser($admin);
$process = $pw->modules->get('ProcessWorkspace');
$method = $_SERVER['REQUEST_METHOD'] ?? null;
$post = $pw->input->post->getArray();
$redirectHook = null;
try {
    $pw->users->setCurrentUser($operator);
    try { $workspace->setInterfaceTheme('AdminThemeUikit'); $denied=false; } catch (WirePermissionException $e) { $denied=true; }
    $assert($denied, 'Operator must not change an interface using the service.');
    try { $process->___executeAppearance(); $denied=false; } catch (WirePermissionException $e) { $denied=true; }
    $assert($denied, 'Operator must not open the privileged settings handler.');
    $_SERVER['REQUEST_METHOD']='POST';
    $pw->input->post->setArray([]);
    try { $process->___executeAdministration(); $denied=false; } catch (WireCSRFException $e) { $denied=true; }
    $assert($denied && $pw->user->id === $operator->id, 'Missing CSRF must not sign out the operator.');
    $pw->users->setCurrentUser($admin);
    try { $process->___executeAppearance(); $denied=false; } catch (WireCSRFException $e) { $denied=true; }
    $assert($denied, 'A superuser POST still requires CSRF.');
    try { $workspace->setInterfaceTheme('NotAnInstalledTheme'); $denied=false; } catch (WireException $e) { $denied=true; }
    $assert($denied && (string)$admin->admin_theme === $originalTheme, 'Unknown theme must not alter the preference.');
    foreach (['AdminThemeUikit', 'AdminThemeWorkspace'] as $theme) {
        $workspace->setInterfaceTheme($theme);
        $stored = $pw->database->prepare('SELECT data FROM field_admin_theme WHERE pages_id=:id');
        $stored->execute([':id'=>(int)$admin->id]);
        $assert((int)$stored->fetchColumn() === (int)$pw->modules->getModuleID($theme), 'The selected theme must persist for this administrator.');
    }
    $assert((string)$operator->admin_theme === $operatorTheme, 'Operator theme must remain unchanged.');
    $assert((string)$pw->config->defaultAdminTheme === $originalDefault, 'Global default must remain unchanged.');
    $pw->session->setFor($workspace,'administratorLogin',time());
    $login=$pw->modules->get('ProcessLogin');
    $assert($login->afterLoginURL('/fallback/') === $workspace->homeUrl().'appearance/', 'Native administrator login should return to settings.');
    $assert(!$pw->session->getFor($workspace,'administratorLogin'), 'Login intent must be consumed.');
    $pw->users->setCurrentUser($operator);
    $pw->session->setFor($workspace,'administratorLogin',time());
    $assert($login->afterLoginURL('/fallback/') === $workspace->homeUrl().'administration/', 'An ordinary login must not grant settings access.');
    $pw->session->setFor($workspace,'administratorLogin',time()-901);
    $assert($login->afterLoginURL('/fallback/') !== $workspace->homeUrl().'appearance/', 'Expired intent must not grant a settings redirect.');
    $_SERVER['REQUEST_METHOD']='GET';
    $pw->users->setCurrentUser($admin);
    $html=$process->___executeAppearance();
    $assert(str_contains($html,'Standard administration') && str_contains($html,'Application workspace'), 'Settings must offer both installed interfaces.');
    $assert(str_contains($html,'workspace_theme'), 'Settings must contain an explicit selected preference.');
    echo "Workspace administration checks passed: $checks checks.\n";
} finally {
    $pw->users->setCurrentUser($admin);
    $admin->of(false); $admin->admin_theme=$originalTheme; $admin->save('admin_theme');
    $pw->session->removeFor($workspace,'administratorLogin');
    $pw->input->post->setArray($post);
    if ($method === null) unset($_SERVER['REQUEST_METHOD']); else $_SERVER['REQUEST_METHOD']=$method;
    $pw->users->setCurrentUser($originalUser);
    $finished=true;
}
