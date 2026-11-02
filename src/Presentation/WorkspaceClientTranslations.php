<?php namespace ProcessWire;

/** Native textdomain for dynamic browser messages. Keep whole phrases and placeholders. */
$counts = [];
for ($count = 0; $count <= count($apps); $count++) {
    $counts[$count] = sprintf(
    _n('%d application', '%d applications', $count, Workspace::translationFile('src/Presentation/WorkspaceClientTranslations.php')), $count);
}
$messages = [
    'counts' => $counts,
    'reset' => __('Appearance reset to defaults. Favorites kept.', Workspace::translationFile('src/Presentation/WorkspaceClientTranslations.php')),
    'saved' =>
    __('Saved automatically in this browser.', Workspace::translationFile('src/Presentation/WorkspaceClientTranslations.php')),
    'add' =>
    __('Add %s to favorites', Workspace::translationFile('src/Presentation/WorkspaceClientTranslations.php')),
    'remove' =>
    __('Remove %s from favorites', Workspace::translationFile('src/Presentation/WorkspaceClientTranslations.php')),
    'addTitle' =>
    __('Add to favorites', Workspace::translationFile('src/Presentation/WorkspaceClientTranslations.php')),
    'removeTitle' =>
    __('Remove from favorites', Workspace::translationFile('src/Presentation/WorkspaceClientTranslations.php')),
    'added' =>
    __('%s added to favorites.', Workspace::translationFile('src/Presentation/WorkspaceClientTranslations.php')),
    'removed' =>
    __('%s removed from favorites.', Workspace::translationFile('src/Presentation/WorkspaceClientTranslations.php')),
    'emptyFavorites' =>
    __('Add a star to an application to keep it in your favorites.', Workspace::translationFile('src/Presentation/WorkspaceClientTranslations.php')),
    'emptySearch' =>
    __('Try another name or clear the search.', Workspace::translationFile('src/Presentation/WorkspaceClientTranslations.php')),
];

// Native translations may contain encoded entities; DOM textContent needs plain text.
foreach ($messages as &$message) {
    $decode = static fn($value) => html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $message = is_array($message) ? array_map($decode, $message) : $decode($message);
}
unset($message);
return $messages;
