# Workspace

Workspace brings independently installed ProcessWire applications into one focused interface: a searchable launcher, favorite apps, shared navigation and a consistent appearance.

![Workspace — independent applications connected in one shared workspace](assets/Workspace.png)

It is made for sites where people work in business applications without needing the full technical administration interface.

**Version:** 1.0.1 (ProcessWire module version 101)<br>
**Author:** Maxim Semenov<br>
**Website:** [smnv.org](https://smnv.org)<br>
**Email:** [maxim@smnv.org](mailto:maxim@smnv.org)

If this project helps your work, consider supporting future development: [GitHub Sponsors](https://github.com/sponsors/mxmsmnv) or [smnv.org/sponsor](https://smnv.org/sponsor/).

## What Workspace Does

- Shows applications the current user can actually open.
- Provides application search, keyboard shortcuts and browser-local Favorites.
- Opens Favorites first when the user has pinned an available application.
- Adds a shared application switcher with square desktop tiles and a single horizontally scrollable row on phones, plus a compact account menu and app navigation.
- Uses native ProcessWire forms, application handlers and permissions.
- Supports native LanguageSupport translations, including browser messages, using the current user’s language.
- Lets other site Process modules register through `Workspace::getAppDefinitions`.
- Keeps technical administration separate from ordinary application work.

One package contains **Workspace** (registry and access policy), **ProcessWorkspace** (launcher) and **AdminThemeWorkspace** (shared shell based on AdminThemeUikit).

## Applications

Optional compatibility descriptors are included for Kontor, Mercato, Mailbox, Dispatch, Files and Tickets. These modules are not bundled or automatically installed. An app appears only when its provider and Process are installed, an eligible admin Process page exists and the current user's native permissions and page access allow it.

Other applications can register without editing Workspace. Add a hook in `site/init.php` or an autoload module's `init()`:

On a plain ProcessWire installation, the launcher is initially empty: Workspace
does not include business applications. The example below assumes an existing,
separately installed **ProcessPlanner** module with its own admin Process page.
Replace it with your real module class; copying the hook alone does not create
an application or its page.

```php
$wire->addHookAfter('Workspace::getAppDefinitions', function($event) {
    $apps = $event->return;
    $apps[] = [
        'id' => 'planner',
        'title' => 'Planner',
        'process' => 'ProcessPlanner',
        'icon' => 'briefcase',
        'color' => '#2563eb',
        'settingsSections' => ['maintenance'],
    ];
    $event->return = $apps;
});
```

`ProcessPlanner` is an example; install the real application and its Process page separately. See [application registration](docs/application-registration.md) for navigation, permissions, validation and settings-route protection, or use the [complete example](examples/register-app.php).

## Appearance

The account menu opens a compact Appearance dialog with:

- Light, Dark, System and Black display modes;
- nine color palettes with primary, surface and secondary swatches;
- six local font stacks;
- a continuous text-size slider from 85% to 125%;
- Standard, Enhanced and Maximum contrast;
- a reset button that preserves Favorites.

Preferences are scoped to the account in the current browser. They are not synchronized between browsers or devices. Text scale applies to the shell and same-origin native application styles; embedded documents retain their own appearance. Fonts depend on those installed on the device. No external font or icon service is required.

## Localization

Translate Workspace through ProcessWire’s native **Setup → Languages** editor and assign the language to the user. English remains the fallback; no separate language service or browser preference is used. See [native localization](docs/localization.md) for the source files, placeholders and third-party registration guidance. Language packs for individual apps remain independent.

## Requirements

- ProcessWire 3.0.244 or newer.
- PHP 8.2 or newer.
- AdminThemeUikit installed.
- Independently installed applications with their own permissions and admin Process pages.

## Installation

1. Back up your site and database, and test on a development copy first.
2. Copy this folder to `/site/modules/Workspace/`.
3. Refresh modules in ProcessWire Admin and install **Workspace**. Its launcher and theme companions are installed through module metadata.
4. Give an operator role `workspace-use` plus only the permissions needed by its applications.
5. Select **AdminThemeWorkspace** for the intended user and open the Workspace admin page.
6. Keep an independent technical administrator using the standard admin theme.

Installation does not install business applications, create users, grant business permissions or change existing user/default themes automatically. URLs are resolved from actual Process pages, including sites with a renamed admin root.

## Administration and Access

For non-superusers with `workspace-use`, Workspace enforces its application boundary on the server regardless of the selected theme. Registered applications, Workspace home and native login remain accessible according to native permissions. Core technical Processes such as Pages, Modules, Templates, Fields, Users and Roles are excluded.

Generic settings/configuration routes and declared application-specific technical routes are administrator-only. A small key icon in the footer opens administrator access through normal ProcessWire sign-in, preserving password controls, CSRF, throttling and configured second-factor authentication. It does not elevate an account or use a shared password.

Superusers retain technical maintenance access. The interface settings page changes only the signed-in superuser's own theme preference. Keep application-level authorization, CSRF and record-ownership checks in every application; Workspace registration does not replace them.

## Compatibility

Workspace is a shell, not a replacement editor for every module. Its launcher, appearance dialog and six integration home screens have been reviewed in desktop Chrome, including text scale at 125%. Every editor, mobile viewport and third-party action still needs site-specific testing.

Sites with an older `site/templates/admin.php` that loads
`$config->paths->adminTemplates . 'controller.php'` are supported through a
compatibility entry point delegating to the native core admin controller.

Some existing Mercato actions lead to native page editing; operators cannot use those routes until dedicated business editors are integrated. Custom query-string actions and separate admin pages require the application's own authorization. There is no bundled production backend, mail delivery setup, payment configuration or sample business data.

Before uninstalling an active theme, select a surviving default/user theme and verify administrator access. Do not remove the Workspace access policy while relying on it to restrict operators.

## Documentation

- [Application registration](docs/application-registration.md)
- [Native localization](docs/localization.md)
- [Development and verification](docs/development.md)
- [Implementation contract](INTERFACE.md)
- [Changelog](CHANGELOG.md)
- [Sponsorship](SPONSORSHIP.md)

## Author

Maxim Semenov<br>
[smnv.org](https://smnv.org)<br>
[maxim@smnv.org](mailto:maxim@smnv.org)

## License

[MIT](LICENSE). Interface icons use Font Awesome supplied by ProcessWire core. The Font Awesome favicon retains its [upstream notice](assets/icons/README.md).
