<?php namespace ProcessWire;
if(!defined('PROCESSWIRE')) die();
$layout = $adminTheme->isModal ? 'modal' : '';
$content = ($content ?? '') . $adminTheme->renderExtraMarkup('content');
$adminTheme->includeFile('_main.php', ['layout' => $layout, 'content' => &$content]);
