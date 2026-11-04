# Registering applications

Workspace is an extensible shell for independently installed ProcessWire **site Process modules**. The six original integrations are compatibility defaults in `integrations/built-in.php`, not a closed registry or required dependencies. Third-party apps can contribute through the native hook **`Workspace::getAppDefinitions`** without changing Workspace files.

## Before copying the example

An empty launcher on a plain ProcessWire site is expected. Workspace does not
install the six business applications, and `ProcessPlanner` in the example is
a placeholder, not a module shipped with Workspace.

To connect an existing application:

1. Install its real site Process module and confirm its native admin page works.
2. Replace `ProcessPlanner` with that module's actual Process class. If its
   provider is a separate module, set `module` to the provider's class name.
3. Confirm a published admin page under your admin root has that Process assigned.
4. Register the hook, then reload Workspace as a superuser first. For operators,
   also grant `workspace-use`, the Process permission and native page access.

Core admin tools, ordinary front-end pages and arbitrary external links are
not supported application registrations. A hook describes an existing site
Process; it does not create one.

## Registration contract

Register an after-hook from an autoload module's `init()` or `site/init.php`. Registration must happen before the admin controller guard runs; `ready()` can be too late. See [the complete registration example](../examples/register-app.php). In an autoload module, use `$this->addHookAfter(...)` instead of `$wire->addHookAfter(...)`.

The hook receives an array of descriptors in `$event->return`. Append your entry, then assign the updated array back. Hooks may replace the array to use only their own apps, remove entries with `array_filter`, or reorder them. For example, setting `$event->return = []` removes all application integrations while retaining Workspace home/login. Hook registration is trusted server-side PHP code, not an API for browser-submitted configuration. Do not call `apps()` or `allApps()` inside this hook, which would recursively resolve the registry.

| Field | Contract |
| --- | --- |
| `id` | Required stable lowercase ID, starting with a letter; letters, digits, `_`, `-`, max64 characters. Also used for Favorites and app-specific CSS. |
| `title` | Required nonempty string; escaped by shell templates. |
| `process` | Required installed site Process class, e.g. `ProcessPlanner`. Plain class names beginning `Process`; namespaced class strings are not supported in this API. |
| `module` | Optional separately installed provider class; defaults to `process`. |
| `description` | Optional string. |
| `icon` | Optional Font Awesome name available in your ProcessWire core (e.g. `calendar`, `briefcase`, `shopping-bag`, `ticket`). Existing Workspace aliases such as `grid`, `mail`, `send`, `folder`, `life-buoy` and `monitor` remain supported. An optional `fa-` prefix is accepted. Invalid names use `th-large`; valid names must exist in the core icon set. No external SVG or file path. |
| `color` | Optional six-digit hex; invalid values use neutral gray. |
| `navigation` | Optional array of `label`, relative `url`, optional native `permission` and required installed `module` per item. Overrides module metadata navigation. Empty array means Overview only. |
| `navigationKey` | Optional Process module-info navigation key, defaults to `nav`; used only when `navigation` is absent. |
| `allowedSections` | Optional list restricting visible navigation to named first-level business sections. Presentation filter only; it does not grant or deny business action permissions. |
| `settingsSections` | Optional first path segments reserved for superuser configuration; e.g. `maintenance` denies `/maintenance/` and its children to operators before execution and hides matching links. Use route segments, not URLs. |

Invalid descriptors are omitted. Duplicate IDs or Process classes use the first valid entry; remove the earlier descriptor when intentionally replacing it. Missing modules stay unavailable and have no launch URL. Availability is recalculated for the current user; hook output is not cached across users.

Workspace ignores caller-supplied URLs, page IDs, availability, installed flags and permission overrides. It resolves a published, non-trashed admin Process page beneath the actual configured admin root; both the native Process permission and Page access must pass. A non-superuser also needs `workspace-use`. Core Process modules under `wire/`, Workspace itself and ProcessLogin cannot be registered as business apps. Registration does not install a module, create its Process page, grant permissions or bypass the module's own CSRF/ownership/action checks.

Generic `settings`, `config` and `configuration` routes remain superuser-only for every integration. Existing built-in technical routes remain protected too. A third-party app must declare its additional technical routes in `settingsSections` and enforce its own native permissions for all actions. Hiding links alone is not authorization; Workspace enforces registered configuration paths independently of the selected theme. Query-string action switches and separate admin pages require the app's own authorization and are not inferred from this list.

The shell supplies launch cards, Favorites, current-app selection, navigation and shared appearance automatically. A new app's custom forms, scripts, embedded documents and editors still need compatibility testing. The provided example registers nothing unless you deliberately copy/use it and install its real Process.

This API has been available since Workspace 1.0.0 (ProcessWire module version 100) and remains available in 1.0.1 (101), licensed under MIT.

## Localization

Translate descriptor titles, descriptions and explicit navigation labels in your provider using native `__()` calls before returning them. Workspace preserves those translations; native Process metadata labels use that Process file’s textdomain. See [localization](localization.md).
