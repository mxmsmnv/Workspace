# Development and verification

Prepare an isolated real ProcessWire site, install Workspace and its application
fixtures, and create a non-superuser operator with `workspace-use` and the
application permissions needed by the checks. The registry integration test
expects all six compatibility descriptors and an available Files Process.
Never point fixture tests at a production site.

```sh
export WORKSPACE_SITE_ROOT=/path/to/isolated/site
export WORKSPACE_OPERATOR=workspace-operator
php tests/registry.php
php tests/configuration.php
php tests/configuration-runtime.php
php tests/administration.php
# With native LanguageSupport already installed in the isolated site:
php tests/localization.php
```

Registry checks use temporary hooks and in-memory users/roles; they do not
install third-party modules or save business records. The administration test
changes the prepared superuser's theme preference and restores it in `finally`.
Use a disposable site and keep a backup.

For frontend checks, install jsdom26 and css-tree3 as development dependencies
in a separate temporary directory. Save an authenticated launcher HTML response
from the isolated site without placing session cookies or credentials in Git.

```sh
NODE_PATH=/path/to/test-dependencies/node_modules \
WORKSPACE_HTML=/path/to/launcher.html \
node --test tests/appearance.cjs
```

These checks cover appearance/storage, Favorites, dialog behavior, late body-wide
application button enhancement without touching business controls, color
contrast, CSS parsing and typography normalization. They supplement real-browser
review. The localization test uses the real native parser and in-memory translations,
checks source discovery, PHP/JSON output, placeholders, native count forms and escaping,
and never saves a language/user/translation. Frontend checks also exercise translated
messages after interactions. They do not prove every module's editor, embedded content or mobile UI.

The optional development router runs an already prepared site on loopback:

```sh
WORKSPACE_SITE_ROOT=/path/to/isolated/site bash dev/serve
```

It defaults to port8191; set `WORKSPACE_PORT` to select another port. It does not
install a site, configure a reverse proxy, create users or enable automatic
startup. Do not start a duplicate listener. The router is for local development,
not public hosting.

## Local login E2E

`python3 tests/login-e2e.py` exercises the real local HTTP stack, native CSRF
fields, cookies and redirects. Set `WORKSPACE_E2E_URL` to an isolated loopback
site with the prepared `/demo/` fixture. Optionally set
`WORKSPACE_E2E_ADMIN_PATH` (default `/workspace-admin/`) and supply
`WORKSPACE_E2E_USER` / `WORKSPACE_E2E_PASSWORD` through a private environment
provider to include native administrator login. Never commit or print these
values. Missing credentials explicitly skip that check.

The demo endpoint belongs to the isolated site, not the distributed module.
It must be loopback-only, send `Cache-Control: no-store` and redirect with 302;
a permanent 301 can be cached by the browser and bypass later demo logins.
Use a fresh query string once if an old permanent redirect is already cached.
Do not disable session fingerprints or CSRF to make the tests pass.

The checks cover login persistence, application home pages and refresh,
logout revocation and demo re-entry. They do not automate a browser, JavaScript,
second-factor authentication or every application workflow; native browser
verification remains a separate check.

## Source layout

- `Workspace.module.php`: module metadata and hook wiring.
- `src/Registry/WorkspaceRegistryTrait.php`: hookable definitions, validation,
  actual Process page discovery, permissions and navigation resolution.
- `src/Access/WorkspaceAccessTrait.php`: operator boundary, settings filtering,
  administrator sign-in continuation and privileged interface preference.
- `src/Presentation/WorkspaceShellTrait.php`: current app, shell page/title/URLs
  and compatibility copy adaptation.
- `src/WorkspaceConfigurationPolicy.php`: configuration-route classification
  and byte-preserving link filtering.
- `integrations/built-in.php`: optional default application descriptors.
- `ProcessWorkspace.module.php`: launcher and account/interface screens.
- `AdminThemeWorkspace/`: native AdminThemeUikit integration and shell templates.
- `assets/`: CSS, JS, Font Awesome favicon and attribution, and README illustration. Interface icons use ProcessWire’s core font.

Business operations stay in independently installed applications. Keep native
permissions, CSRF and ownership enforcement; a descriptor never supplies a
trusted launch URL or a permission override.
