<?php namespace ProcessWire;

/** Application-only chrome with the native ProcessWire form runtime. */
class AdminThemeWorkspace extends AdminThemeUikit {
    public static function getModuleInfo() {
        return [
            'title' => 'Workspace', 'version' => 101,
            'summary' => 'An application workspace with a shared launcher and native forms.',
            'author' => 'Maxim Semenov', 'icon' => 'th-large',
            'autoload' => 'template=admin',
            'requires' => ['ProcessWire>=3.0.244', 'AdminThemeUikit', 'Workspace'],
        ];
    }

    public function init() {
        parent::init();
        if(!$this->isCurrent()) return;
        $this->layout = '';
        $this->themeName = 'default';
        $this->defaultMainColor = 'blue';
        $this->addBodyClass('ws-shell');
    }

    /** The style catalog is the core catalog, not a copied themes directory. */
    public function getThemeInfo($themeName = '') {
        $themeName = $themeName ?: ($this->themeName ?: 'default');
        if($themeName === 'original') return [];
        $path = parent::path() . 'themes/default/';
        $info = ['name'=>'default', 'path'=>$path, 'url'=>parent::url() . 'themes/default/'];
        if(is_file($path . 'info.php')) $info = array_merge(include $path . 'info.php', $info);
        return $themeName === 'all' ? ['default'=>$info] : $info;
    }

    /** The native CSS compiler keys paths by module class, not by path(). */
    public function ___getUikitCSS() {
        return $this->wire()->modules->get('AdminThemeUikit')->getUikitCSS();
    }

    public function getBrowserTitle() {
        $title = $this->browserTitle ?: $this->wire('processBrowserTitle');
        if(!$title) $title = $this->getHeadline();
        $title = trim(strip_tags((string)$title));
        $title = preg_replace('/<icon-[^>]+>\s*/', '', $title);
        if(!$title || $title === 'Workspace') $title = 'Workspace';
        else if(!$this->isModal) $title .= ' · Workspace';
        return $this->wire()->sanitizer->entities($title);
    }

    /** Keep application errors; leave core diagnostics in the original collection. */
    public function renderNotices($notices, array $options = []) {
        if($notices instanceof Notices) {
            $visible = clone $notices;
            foreach($visible as $notice) {
                if($notice instanceof NoticeError) continue;
                $source = ltrim((string)$notice->class, '\\');
                $maintenance = preg_match('/(?:^|\\\\)(?:SystemUpdater|SystemUpdaterChecks)$/', $source);
                if(($notice->flags & Notice::debug) || $maintenance) $visible->remove($notice);
            }
            return parent::renderNotices($visible, $options);
        }
        return parent::renderNotices($notices, $options);
    }

    /** Uikit's path/url intentionally keep referring to the core asset runtime. */
    public function includeFile($basename, array $vars = []) {
        if(is_file(__DIR__ . '/' . $basename)) {
            parent::includeFile($basename, $vars);
            return;
        }
        if(!preg_match('/^[_a-zA-Z0-9.-]+\.php$/', $basename)) throw new WireException('Invalid theme template.');
        $file = parent::path() . $basename;
        $vars = array_merge($this->wire()->fuel->getArray(), $vars);
        unset($vars['file'], $vars['vars']);
        extract($vars);
        include $file;
    }

    /** Native ProcessWire Font Awesome markup; aliases preserve stored app descriptors. */
    public static function icon(string $name): string {
        $map = [
            'workspace'=>'th-large', 'grid'=>'th-large',
            'kontor'=>'briefcase', 'mercato'=>'shopping-bag',
            'mailbox'=>'envelope-o', 'mail'=>'envelope-o', 'envelope'=>'envelope-o',
            'dispatch'=>'paper-plane-o', 'send'=>'paper-plane-o',
            'files'=>'folder-o', 'folder'=>'folder-o',
            'tickets'=>'ticket', 'life-buoy'=>'ticket',
            'moon'=>'moon-o', 'sun'=>'sun-o', 'chevron'=>'chevron-down',
            'logout'=>'sign-out', 'arrow'=>'arrow-up-right', 'palette'=>'paint-brush',
            'star'=>'star-o', 'star-filled'=>'star', 'monitor'=>'desktop',
        ];
        $name = strtolower($name);
        $name = $map[$name] ?? preg_replace('/^fa-/', '', $name);
        if (!preg_match('/^[a-z0-9-]+$/D', $name)) $name = 'th-large';
        // external-link is available in both core FA4 and the FA6 compatibility map.
        if ($name === 'arrow-up-right') $name = 'external-link';
        return wireIconMarkup($name, 'ws-icon aria-hidden="true"');
    }
}
