<?php namespace ProcessWire;

if (!defined('PROCESSWIRE')) die();

/** Compatibility with site/templates/admin.php files using adminTemplates. */
/** @var Config $config */
require $config->paths->core . 'admin.php';
