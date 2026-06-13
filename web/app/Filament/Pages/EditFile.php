<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\Site;
use App\Services\PanelCtl;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class EditFile extends Page
{
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $slug = 'file-edit';
    protected static string $view = 'filament.pages.edit-file';

    #[Url]
    public ?int $site = null;

    #[Url]
    public string $path = '';

    public string $content = '';
    public ?string $error = null;

    public function mount(): void
    {
        $site = $this->currentSite();
        if (!$site) {
            $this->error = 'Unknown site.';
            return;
        }
        $result = app(PanelCtl::class)->run('fs:read', ['domain' => $site->domain, 'path' => $this->path]);
        if (!$result->ok()) {
            $this->error = $result->output();
            return;
        }
        $staged = trim($result->stdout);
        if ($staged === '' || !is_file($staged)) {
            $this->error = 'Could not read the file.';
            return;
        }
        $this->content = (string) file_get_contents($staged);
        @unlink($staged);
    }

    public function save(): void
    {
        $site = $this->currentSite();
        if (!$site) {
            return;
        }
        $content = str_replace("\r\n", "\n", $this->content);
        $result = app(PanelCtl::class)->run('fs:write', ['domain' => $site->domain, 'path' => $this->path], $content);

        if ($result->ok()) {
            AuditLog::record('fs.write', $site->domain . ':' . $this->path);
            Notification::make()->title('Saved ' . basename($this->path))->success()->send();
        } else {
            Notification::make()->title('Save failed')->body($result->output())->danger()->persistent()->send();
        }
    }

    public function currentSite(): ?Site
    {
        return $this->site ? Site::find($this->site) : null;
    }

    public function getTitle(): string
    {
        return 'Edit: ' . basename($this->path);
    }

    public function backUrl(): string
    {
        return FileManager::getUrl(['site' => $this->site, 'path' => $this->cleanDir()]);
    }

    /** File extension → CodeMirror mode. */
    public function editorMode(): string
    {
        return match (strtolower(pathinfo($this->path, PATHINFO_EXTENSION))) {
            'php' => 'application/x-httpd-php',
            'js', 'json' => 'application/json',
            'css' => 'text/css',
            'html', 'htm' => 'htmlmixed',
            'xml' => 'application/xml',
            'sql' => 'text/x-sql',
            'yml', 'yaml' => 'text/x-yaml',
            'md' => 'text/x-markdown',
            'sh' => 'text/x-sh',
            default => 'text/plain',
        };
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')->label('Save')->icon('heroicon-m-check')->action('save')
                ->keyBindings(['mod+s']),
            Action::make('back')->label('Back to files')->icon('heroicon-m-arrow-uturn-left')
                ->color('gray')->url(fn () => $this->backUrl()),
        ];
    }

    private function cleanDir(): string
    {
        $dir = str_replace('\\', '/', dirname($this->path));
        return '/' . trim($dir === '.' ? '' : $dir, '/');
    }
}
