<?php namespace ProcessWire;
/** URL classification and byte-preserving server-render filter. No site or credentials required. */
require_once __DIR__.'/../src/WorkspaceConfigurationPolicy.php';
$policy = new WorkspaceConfigurationPolicy('/private-admin/', '/private-admin/setup/mailbox/', 'https://workspace.test:8443', [
    'mailbox'=>'/private-admin/setup/mailbox/', 'mercato'=>'/private-admin/setup/mercato/',
    'kontor'=>'/private-admin/kontor/', 'dispatch'=>'/private-admin/setup/dispatch/',
    'files'=>'/private-admin/setup/files/', 'tickets'=>'/private-admin/setup/tickets/',
]);
$count=0;
$assert=static function(bool $ok,string $message) use (&$count){if(!$ok)throw new \RuntimeException($message);$count++;};
foreach([
 '/private-admin/module/edit?name=Mailbox&collapse_info=1','/private-admin/module/','/private-admin/modules/',
 '../../module/edit?name=Mailbox','/private-admin/%6dodule/edit?name=Mailbox',
 'https://workspace.test:8443/private-admin/module/edit?name=Files',
 '//workspace.test:8443/private-admin/module/edit?name=Tickets',
 '/private-admin/setup/mailbox/discover/','/private-admin/setup/mailbox/accounts/',
 '/private-admin/kontor/settings-apply/','/private-admin/setup/dispatch/diagnostics/',
 '/private-admin/setup/tickets/interfaces/','/private-admin/setup/mercato/webhooks/',
] as $url)$assert($policy->isSettingsUrl($url),'Expected settings: '.$url);
foreach([
 '/private-admin/setup/mailbox/?account=1','/private-admin/setup/mailbox/message/?uid=3',
 '/private-admin/setup/files/create-folder/','/private-admin/setup/dispatch/templates/',
 '/private-admin/setup/tickets/templates/','/private-admin/kontor/contacts/',
 '/private-admin/module-notes/','https://example.org/private-admin/module/edit?name=Mailbox',
 'https://workspace.test:9443/private-admin/module/edit?name=Mailbox','#settings','?account=1',
] as $url)$assert(!$policy->isSettingsUrl($url),'Must retain business or external link: '.$url);
$script='<script>const template = "<a href=\'/private-admin/module/edit?name=Mailbox\'>Settings</a>";</script>';
$html=$script.'<ul><li><a href="/private-admin/setup/mailbox/discover/">Discover settings</a></li><li><a href="/private-admin/setup/mailbox/?account=1">Inbox</a></li></ul><a class="gear" href="/private-admin/module/edit?name=Mailbox&amp;collapse_info=1"><svg><path d="M1 2"/></svg></a><form data-note="Keep > values"><input name="x" value="Привет &amp; café"></form>';
$out=$policy->filterMarkup($html);
$assert(str_contains($out,$script),'Scripts must stay byte-identical.');
$assert(!str_contains($out,'Discover settings'),'Discovery tab must be removed.');
$assert(!str_contains($out,'class="gear"'),'Gear anchor must be removed.');
$assert(str_contains($out,'<li><a href="/private-admin/setup/mailbox/?account=1">Inbox</a></li>'),'Business link must stay unchanged.');
$assert(str_contains($out,'<form data-note="Keep > values"><input name="x" value="Привет &amp; café"></form>'),'Forms and Unicode must stay byte-identical.');
$assert(!str_contains($out,'<li></li>'),'Removed tab must not leave an empty slot.');
$json='{"html":"<a href=\'/private-admin/module/edit\'>Settings</a>"}';
$assert($policy->filterMarkup($json)===$json,'JSON must not be rewritten.');
$tricky='<a title="href=\'/private-admin/module/edit\'" href="/private-admin/kontor/contacts/">Contacts</a>';
$assert($policy->filterMarkup($tricky)===$tricky,'Attribute contents must not be mistaken for href.');
$custom = new WorkspaceConfigurationPolicy('/private-admin/', '/private-admin/custom/', 'https://workspace.test:8443', ['custom'=>'/private-admin/custom/'], ['custom'=>['maintenance-tools']]);
$assert($custom->isSettingsUrl('/private-admin/custom/maintenance-tools/run/'), 'Custom registered settings section must be classified.');
$assert($custom->isSettingsUrl('/private-admin/custom/settings/'), 'Generic settings remain protected for third-party apps.');
$assert(!$custom->isSettingsUrl('/private-admin/custom/records/'), 'Custom business paths stay available.');
$assert(!str_contains($custom->filterMarkup('<a href="/private-admin/custom/maintenance-tools/">Maintenance</a><a href="/private-admin/custom/records/">Records</a>'), 'Maintenance'), 'Custom settings links must be hidden.');
echo "Workspace configuration policy passed: $count checks.\n";
