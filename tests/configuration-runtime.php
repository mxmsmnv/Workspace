<?php namespace ProcessWire;
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=realpath((string)getenv('WORKSPACE_SITE_ROOT'));
if(!$root||!is_file($root.'/wire/core/ProcessWire.php'))throw new \RuntimeException('Set WORKSPACE_SITE_ROOT.');
require $root.'/wire/core/ProcessWire.php';
$pw=new ProcessWire($root.'/','https://workspace.test:8443/');
$operator=$pw->users->get(getenv('WORKSPACE_OPERATOR')?:'workspace-demo');
if(!$operator->id||$operator->isSuperuser())throw new \RuntimeException('Operator fixture required.');
$workspace=$pw->modules->get('Workspace');$count=0;$finished=false;
register_shutdown_function(static function()use(&$finished){if(!$finished){fwrite(STDERR,"Settings test exited prematurely.\n");exit(1);}});
$originalUser=$pw->user;$originalPage=$pw->page;$segments=$pw->input->urlSegments();$cookies=$_COOKIE;
try{
 foreach(['AdminThemeUikit','AdminThemeWorkspace'] as $theme){
  $probe=clone $operator;$probe->set('admin_theme',$theme);$pw->users->setCurrentUser($probe);
  $_COOKIE['workspace-admin']='1';$_COOKIE['workspace-settings']='enabled';
  foreach(['mailbox'=>['discover','accounts'],'dispatch'=>['diagnostics','worker'],'tickets'=>['interfaces','automation'],'kontor'=>['components','settings-migration']] as $appId=>$paths){
   $apps=$workspace->apps();$app=array_values(array_filter($apps,static fn($a)=>$a['id']===$appId))[0]??null;
   if(!$app)throw new \RuntimeException('Prepared app unavailable: '.$appId);
   $pw->wire('page',$pw->pages->get($app['pageId']));
   foreach($paths as $section){
    $pw->input->setUrlSegments([$section]);$controller=$pw->wire(new ProcessController());$controller->setProcessName($app['process']);
    $blocked=false;try{$controller->execute();}catch(WirePermissionException $e){$blocked=str_contains($e->getMessage(),'settings are available only');}
    if(!$blocked)throw new \RuntimeException('Configuration must be denied before native execution: '.$appId.'/'.$section);
    $count++;
   }
  }
 }
 // Same rendering hook must preserve administrator HTML unchanged.
 $pw->users->setCurrentUser($pw->users->get($pw->config->superUserPageID));
 $hook=$pw->wire(new HookEvent());$hook->return='<a href="/private-admin/module/edit?name=Mailbox">Settings</a>';
 $workspace->filterOperatorConfigurationLinks($hook);
 if($hook->return!=='<a href="/private-admin/module/edit?name=Mailbox">Settings</a>')throw new \RuntimeException('Administrator navigation changed.');
 $count++;
 echo "Workspace configuration runtime passed: $count checks.\n";
}finally{
 $_COOKIE=$cookies;$pw->input->setUrlSegments($segments);$pw->wire('page',$originalPage);$pw->users->setCurrentUser($originalUser);$finished=true;
}
