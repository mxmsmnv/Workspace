# Native localization

Workspace uses ProcessWire's **LanguageSupport**, the signed-in user's
`$user->language`, and native `__()`, `_x()` and `_n()` translations. English
source strings are the fallback. LanguageSupport is optional: single-language
sites do not need to install it just to use Workspace.

## Add a translation

1. Using the technical administrator and standard ProcessWire administration,
   install **LanguageSupport** if the site does not already use it.
2. Open **Setup → Languages**, select or add the target language, and use the
   native translation editor to add the following files from the installed
   Workspace directory (normally `site/modules/Workspace/`).
3. Translate the phrases and save them through ProcessWire. Assign that language
   to the intended user in their native user profile, then reload Workspace.

| File | Copy |
| --- | --- |
| `ProcessWorkspace.module.php` | Launcher, account, administrator sign-in and interface settings |
| `AdminThemeWorkspace/_main.php` | Shell, menus, appearance dialog, login introduction and HTML language code |
| `src/Presentation/WorkspaceClientTranslations.php` | Dynamic search, Favorites, counts and preference status |
| `integrations/built-in.php` | Optional built-in application names and descriptions |
| `src/Registry/WorkspaceRegistryTrait.php` | Shared Overview navigation |
| `src/Access/WorkspaceAccessTrait.php` | Access and configuration notices |
| `src/Presentation/WorkspaceShellTrait.php` | Workspace title and compatibility introduction |

For the `en` phrase with context **HTML language code**, use the target BCP47
code, for example `ru` or `de`. Keep `%s` and `%d` placeholders; their position
can change in the translated phrase. Native `_n()` determines count messages,
including the site's native zero-form setting. Languages that need more complex
plural grammar can use a neutral phrase such as “Applications: %d”.

ProcessWire stores and exports translations in its native language files.
Workspace does not create a separate locale database, change user languages,
install language packs or use browser-local language preferences. Translation
support is included; a complete language pack is not bundled.

## JavaScript and application registrations

Server-translated messages reach JavaScript through an escaped JSON block.
Favorites, filtering and appearance updates preserve the user's language.
Messages are inserted as text, and saved appearance/Favorites keys remain
language-independent.

Applications own their own translations. A hook provider should translate
`title`, `description` and explicit `navigation` labels in its own file/module,
for example `'title' => __('Planner', __FILE__)`. Workspace displays those labels
without translating them again under its own textdomain. Native Process
metadata navigation uses the provider Process file's textdomain.

Workspace's installed module path is used for its own textdomains so that
symlinked development copies and ordinary installations resolve the same native
translation files. Use the registered `site/modules/Workspace/…` path in the
translation editor rather than the external symlink target.

Business application screens and native ProcessLogin fields retain their own
module/core translations. Translate or install their language packs separately.
The Workspace operator policy does not grant access to language administration.
