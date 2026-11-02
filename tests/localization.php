<?php namespace ProcessWire;

/** Read-only native LanguageSupport integration test. Translations exist only in memory. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = realpath((string)getenv('WORKSPACE_SITE_ROOT'));
if (!$root || !is_file($root . '/wire/core/ProcessWire.php')) { fwrite(STDERR, "Set WORKSPACE_SITE_ROOT to an isolated ProcessWire site.\n"); exit(2); }
if (!defined('PROCESSWIRE')) define('PROCESSWIRE', 303);
require_once $root . '/wire/core/ProcessWire.php';
$config = ProcessWire::buildConfig($root);
$config->internal = false;
$pw = new ProcessWire($config);
$modules = $pw->wire('modules');
if (!$modules->isInstalled('LanguageSupport')) throw new \RuntimeException('Prepare LanguageSupport on the isolated site first.');
$modules->get('LanguageSupport');
require_once $root . '/wire/modules/LanguageSupport/LanguageParser.php';
$language = $pw->wire('languages')->getDefault();
$operator = $pw->wire('users')->get(getenv('WORKSPACE_OPERATOR') ?: 'workspace-operator');
if (!$operator->id) throw new \RuntimeException('Prepared operator required.');
$pw->wire('users')->setCurrentUser($operator);
$operator->language = $language;
$translator = $language->translator();
$checks = 0;
$assert = static function($condition, $message) use (&$checks) {
    if (!$condition) throw new \RuntimeException($message);
    $checks++;
};
$workspace = $modules->get('Workspace');
$phrases = [
    'ProcessWorkspace.module.php' => ['Your workspace', 'Open application', 'Add %s to favorites', '%d application', '%d applications', 'Save interface'],
    'AdminThemeWorkspace/_main.php' => ['Appearance', 'Sign out', 'Display mode', 'Font family', 'Reset settings', 'en'],
    'src/Presentation/WorkspaceClientTranslations.php' => ['Saved automatically in this browser.', '%s added to favorites.', '%d application', '%d applications'],
    'integrations/built-in.php' => ['Files', 'Customers, sales and business operations.'],
    'src/Access/WorkspaceAccessTrait.php' => ['Administrator access is required.'],
    'src/Registry/WorkspaceRegistryTrait.php' => ['Overview'],
    'src/Presentation/WorkspaceShellTrait.php' => ['Kontor connects your customers, companies and day-to-day operations.'],
];
foreach ($phrases as $relative => $expected) {
    $file = Workspace::translationFile($relative);
    $parser = new LanguageParser($translator, $file);
    $found = array_values($parser->getUntranslated());
    foreach ($expected as $text) $assert(in_array($text, $found, true), "Native parser missed $relative: $text");
}
$translate = static function($relative, $source, $target, $context = '') use ($translator) {
    $domain = $translator->filenameToTextdomain(Workspace::translationFile($relative));
    $translator->setTranslation($domain, $source, $target, $context);
};
$translate('ProcessWorkspace.module.php', 'Administrator access is required.', 'Требуется администратор.');
$translate('ProcessWorkspace.module.php', 'Your workspace', 'Ваше пространство');
$translate('ProcessWorkspace.module.php', 'Find an application', 'Поиск «приложения» & "фильтр"');
$translate('ProcessWorkspace.module.php', 'Open application', 'Открыть приложение');
$translate('AdminThemeWorkspace/_main.php', 'Appearance', 'Оформление');
$translate('AdminThemeWorkspace/_main.php', 'Sign out', 'Выйти');
$translate('AdminThemeWorkspace/_main.php', 'en', 'ru', 'HTML language code');
$translate('integrations/built-in.php', 'Files', 'Файлы');
$translate('src/Registry/WorkspaceRegistryTrait.php', 'Overview', 'Обзор');
$translate('src/Access/WorkspaceAccessTrait.php', 'Administrator access is required.', 'Требуется администратор.');
$translate('src/Presentation/WorkspaceClientTranslations.php', '%d application', '%d приложение');
$translate('src/Presentation/WorkspaceClientTranslations.php', '%d applications', 'Приложений: %d');
$translate('src/Presentation/WorkspaceClientTranslations.php', 'Saved automatically in this browser.', 'Сохранено & готово');
$translate('src/Presentation/WorkspaceClientTranslations.php', '%s added to favorites.', 'В избранном: %s');
$apps = $workspace->apps();
$files = array_values(array_filter($apps, static fn($a) => $a['id'] === 'files'))[0];
$assert($files['title'] === 'Файлы', 'Built-in labels must use their native file textdomain.');
$assert($files['sections'][0]['label'] === 'Обзор', 'Traits must use their own native textdomain.');
$homeId = $modules->getModuleID('ProcessWorkspace');
$home = $pw->wire('pages')->get("template=admin, process=$homeId, include=all");
$pw->wire('page', $home);
$process = $modules->get('ProcessWorkspace');
$html = $process->execute();
$assert(str_contains($html, 'Ваше пространство'), 'Launcher did not translate.');
$assert(str_contains($html, 'Открыть приложение'), 'Launcher actions did not translate.');
$assert(str_contains($html, '&quot;фильтр&quot;') && !str_contains($html, '&amp;quot;'), 'Attributes must be escaped once.');
$messages = require dirname(__DIR__) . '/src/Presentation/WorkspaceClientTranslations.php';
$assert($messages['counts'][1] === '1 приложение' && $messages['counts'][2] === 'Приложений: 2', 'Native plural forms did not reach browser catalog.');
$assert($messages['saved'] === 'Сохранено & готово', 'Browser text must be plain text, not HTML entities.');
$assert($messages['added'] === 'В избранном: %s', 'Placeholder order must belong to translation.');
$common = $translator->filenameToTextdomain($root . '/wire/modules/LanguageSupport/LanguageTranslator.php');
$translator->setTranslation($common, '0-plural', '0-singular');
$messages = require dirname(__DIR__) . '/src/Presentation/WorkspaceClientTranslations.php';
$assert($messages['counts'][0] === '0 приложение', 'Browser counts must follow native zero-plural configuration.');
$theme = $modules->get('AdminThemeWorkspace');
$pw->wire('adminTheme', $theme);
ob_start();
$theme->includeFile('_main.php', ['layout'=>'', 'content'=>$html]);
$shell = ob_get_clean();
$assert(str_contains($shell, 'lang="ru"'), 'HTML language code must be translatable.');
$assert(str_contains($shell, 'Оформление') && str_contains($shell, 'Выйти'), 'Shell/account text must translate.');
$assert(str_contains($shell, 'id="ws-translations"'), 'Browser catalog is missing.');
try { $process->executeAppearance(); $assert(false, 'Operator gained administrator access.'); }
catch (WirePermissionException $e) { $assert($e->getMessage() === 'Требуется администратор.', 'Permission errors must translate in trait/module domains.'); }
try { $workspace->setInterfaceTheme('AdminThemeUikit'); $assert(false, 'Operator gained theme administration access.'); }
catch (WirePermissionException $e) { $assert($e->getMessage() === 'Требуется администратор.', 'Trait permission error did not use its source textdomain.'); }
// No saveTranslation/saveTextdomain/user save is called: the installed language and user stay unchanged.
echo "Workspace native localization passed: $checks checks.\n";
