<?php namespace ProcessWire;

/** Presentation/route policy for technical configuration, not for business records. */
final class WorkspaceConfigurationPolicy {
    public function __construct(
        private string $adminPath,
        private string $currentPath,
        private string $origin,
        private array $appPaths,
        private array $appSettings = []
    ) {}

    public static function isSettingsSection(string $app, string $section, array $additional = []): bool {
        $section = strtolower(str_replace(['-', '_'], '', explode('/', trim($section, '/'))[0]));
        if (in_array($section, ['settings', 'config', 'configuration'], true)) return true;
        $sections = [
            'mailbox' => ['discover', 'accounts', 'runtime'],
            'dispatch' => ['diagnostics', 'worker'],
            'tickets' => ['interfaces', 'automation'],
            'kontor' => ['settingsmigration', 'settingsapply', 'settingsexport', 'settingspreview', 'components', 'componentsync', 'health', 'backups', 'backupcreate', 'backupdownload', 'organization'],
            'mercato' => ['webhooks', 'launch', 'launchtools'],
            'files' => [],
        ];
        foreach ($additional as $name) {
            if (is_string($name) && $section === strtolower(str_replace(['-', '_'], '', $name))) return true;
        }
        return in_array($section, $sections[$app] ?? [], true);
    }

    public function isSettingsUrl(string $url): bool {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($url === '' || $url[0] === '#' || $url[0] === '?') return false;
        $parts = parse_url($url);
        if (!is_array($parts)) return false;
        if (isset($parts['scheme']) && !in_array(strtolower($parts['scheme']), ['http', 'https'], true)) return false;
        if (isset($parts['host'])) {
            $origin = parse_url($this->origin);
            if (strtolower($parts['host']) !== strtolower((string)($origin['host'] ?? ''))) return false;
            $port = $parts['port'] ?? ((($parts['scheme'] ?? $origin['scheme'] ?? 'https') === 'http') ? 80 : 443);
            $ownPort = $origin['port'] ?? (($origin['scheme'] ?? 'https') === 'http' ? 80 : 443);
            if ($port !== $ownPort) return false;
        }
        $path = rawurldecode((string)($parts['path'] ?? ''));
        if ($path === '') return false;
        if ($path[0] !== '/') {
            $base = parse_url($this->currentPath, PHP_URL_PATH) ?: '/';
            $path = substr($base, 0, (int)strrpos($base, '/') + 1) . $path;
        }
        $path = $this->normalize($path);
        $admin = rtrim($this->normalize($this->adminPath), '/') . '/';
        if (!str_starts_with($path, $admin)) return false;
        $relative = substr($path, strlen($admin));
        $first = strtolower(explode('/', trim($relative, '/'))[0]);
        if (in_array($first, ['module', 'modules'], true)) return true;
        foreach ($this->appPaths as $app => $appPath) {
            $appPath = rtrim($this->normalize($appPath), '/') . '/';
            if (str_starts_with($path, $appPath)) {
                return self::isSettingsSection($app, substr($path, strlen($appPath)), $this->appSettings[$app] ?? []);
            }
        }
        return false;
    }

    private function normalize(string $path): string {
        $result = [];
        foreach (explode('/', str_replace('\\', '/', $path)) as $part) {
            if ($part === '' || $part === '.') continue;
            if ($part === '..') array_pop($result); else $result[] = $part;
        }
        return '/' . implode('/', $result) . '/';
    }

    /** Remove only matched anchors; keep scripts, forms, attributes and encoding byte-for-byte. */
    public function filterMarkup(string $html): string {
        if (preg_match('/^\s*[\[{]/', $html)) return $html; // Never rewrite JSON payloads.
        $pattern = <<<'REGEX'
~<(?<protected>script|style|textarea|pre|code)\b(?:(?:"[^"]*"|'[^']*'|[^'">])*)>.*?</\k<protected>\s*>(*SKIP)(*F)|<a\b(?<attributes>(?:"[^"]*"|'[^']*'|[^'">])*)>.*?</a\s*>~is
REGEX;
        $marker = '<!--workspace-configuration-link-removed-->';
        $filtered = preg_replace_callback($pattern, function(array $match) use ($marker): string {
            preg_match_all('~([^\s=/>]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+)))?~', $match['attributes'], $attributes, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);
            foreach ($attributes as $attribute) {
                if (strtolower($attribute[1]) !== 'href') continue;
                $url = $attribute[2] ?? $attribute[3] ?? $attribute[4] ?? '';
                return $this->isSettingsUrl($url) ? $marker : $match[0];
            }
            return $match[0];
        }, $html);
        if (!is_string($filtered)) throw new \RuntimeException('Could not apply Workspace configuration policy.');
        // Do not leave empty navigation slots when a settings tab was removed.
        $filtered = preg_replace('~<li\b[^>]*>\s*'.preg_quote($marker, '~').'\s*</li>~i', '', $filtered);
        return str_replace($marker, '', $filtered);
    }
}
