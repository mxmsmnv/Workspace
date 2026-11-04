<?php namespace ProcessWire;

require_once __DIR__ . '/src/WorkspaceConfigurationPolicy.php';
require_once __DIR__ . '/src/Registry/WorkspaceRegistryTrait.php';
require_once __DIR__ . '/src/Access/WorkspaceAccessTrait.php';
require_once __DIR__ . '/src/Presentation/WorkspaceShellTrait.php';

/**
 * Application registry and server-side boundary for Workspace operators.
 * @method array getAppDefinitions() Hookable descriptor contribution point.
 */
class Workspace extends WireData implements Module {
    use WorkspaceRegistryTrait;
    use WorkspaceAccessTrait;
    use WorkspaceShellTrait;

    public static function getModuleInfo(): array {
        return [
            'title' => 'Workspace',
            'summary' => 'Extensible, permission-aware application workspace.',
            'version' => 101,
            'author' => 'Maxim Semenov',
            'icon' => 'th-large',
            'singular' => true,
            'autoload' => true,
            'requires' => ['ProcessWire>=3.0.244', 'PHP>=8.2', 'AdminThemeUikit'],
            'installs' => ['ProcessWorkspace', 'AdminThemeWorkspace'],
            'permissions' => ['workspace-use' => 'Use the business application workspace'],
        ];
    }

    /** @internal Use the installed path so native textdomains also work in symlinked development copies. */
    public static function translationFile(string $relative): string {
        return wire('config')->paths->get('Workspace') . $relative;
    }

    public function init(): void {
        $this->addHookBefore('ProcessController::execute', $this, 'guardProcess');
        $this->addHookAfter('ProcessController::execute', $this, 'filterOperatorConfigurationLinks');
        $this->addHookAfter('ProcessKontor::execute', $this, 'adaptKontorOverview');
        $this->addHookAfter('ProcessLogin::afterLoginURL', $this, 'afterAdministratorLogin');
    }
}
