<?php namespace ProcessWire;

// Copy this registration into site/init.php, or register the same hook in an
// autoload module's init(). Do not edit Workspace's built-in descriptors.
// ProcessPlanner is a placeholder: install your real Process and its admin
// page first, then replace this class name. This hook creates neither.
$wire->addHookAfter('Workspace::getAppDefinitions', static function(HookEvent $event): void {
    $apps = $event->return;
    $apps[] = [
        'id' => 'planner',
        'title' => __('Planner', __FILE__),
        'description' => __('Projects and schedules.', __FILE__),
        'process' => 'ProcessPlanner',
        // 'module' => 'Planner', // Optional provider class; never translate class names.
        'icon' => 'briefcase',
        'color' => '#2563eb',
        'navigation' => [
            ['label' => __('Projects', __FILE__), 'url' => 'projects/', 'permission' => 'planner-view'],
            ['label' => __('Maintenance', __FILE__), 'url' => 'maintenance/'],
        ],
        'settingsSections' => ['maintenance'],
    ];
    $event->return = $apps;
});
