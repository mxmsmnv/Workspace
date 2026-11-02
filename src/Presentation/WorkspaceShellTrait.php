<?php namespace ProcessWire;

trait WorkspaceShellTrait {
    public function currentApp(): ?array {
        $page = $this->wire('page');
        if (!$page instanceof Page) return null;
        foreach ($this->apps() as $app) {
            if ((int) $page->id === $app['pageId']) return $app;
        }
        return null;
    }

    public function homeUrl(): string {
        $page = $this->processPage('ProcessWorkspace');
        return $page ? (string) $page->url : rtrim((string) $this->wire('config')->urls->admin, '/') . '/workspace/';
    }

    public function assetUrl(string $file): string {
        if (!preg_match('~^[a-zA-Z0-9_-]+(?:\.[a-zA-Z0-9_-]+)+$~D', $file)) {
            throw new WireException('Invalid Workspace asset name.');
        }
        return $this->wire('config')->urls->get('Workspace') . 'assets/' . $file;
    }

    public function isShellPage(): bool {
        $page = $this->wire('page');
        if (!$page instanceof Page || !$page->template || $page->template->name !== 'admin') return false;
        if ($this->currentApp() !== null) return true;
        $home = $this->processPage('ProcessWorkspace');
        return $home !== null && (int) $home->id === (int) $page->id;
    }

    public function title(): string {
        return $this->currentApp()['title'] ?? __('Workspace', Workspace::translationFile('src/Presentation/WorkspaceShellTrait.php'));
    }

    /** Explicit copy adapter; never rewrite script identifiers or arbitrary application data. */
    public function adaptKontorOverview(HookEvent $event): void {
        if (!$this->isOperator() || !is_string($event->return)) return;
        $event->return = str_replace(
            'Kontor connects customer data, companies and operational components inside ProcessWire.',
            __('Kontor connects your customers, companies and day-to-day operations.', Workspace::translationFile('src/Presentation/WorkspaceShellTrait.php')),
            $event->return
        );
    }
}
