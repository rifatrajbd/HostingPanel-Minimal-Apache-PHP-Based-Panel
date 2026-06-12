<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\Site;
use App\Services\PanelCtl;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;

class FileManager extends Page
{
    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-folder';
    protected static ?string $navigationGroup = 'Hosting';
    protected static ?int $navigationSort = 4;
    protected static ?string $title = 'File Manager';

    protected static string $view = 'filament.pages.file-manager';

    #[Url]
    public ?int $site = null;

    #[Url]
    public string $path = '/';

    public array $items = [];
    public ?string $listError = null;

    public function mount(): void
    {
        $this->site ??= Site::min('id');
        $this->load();
    }

    public function updatedSite(): void
    {
        $this->path = '/';
        $this->load();
    }

    public function load(): void
    {
        $this->items = [];
        $this->listError = null;
        $site = $this->currentSite();
        if (!$site) {
            return;
        }
        $result = app(PanelCtl::class)->run('fs:list', [
            'domain' => $site->domain,
            'path' => $this->path,
        ]);
        if ($result->ok()) {
            $decoded = json_decode($result->stdout, true);
            if (is_array($decoded)) {
                $this->items = $decoded;
            } else {
                $this->listError = config('hostingpanel.dev')
                    ? 'Dev mode: file operations are dry-run only on this machine.'
                    : 'Unexpected fs:list output.';
            }
        } else {
            $this->listError = $result->output();
        }
    }

    public function currentSite(): ?Site
    {
        return $this->site ? Site::find($this->site) : null;
    }

    public function open(string $name): void
    {
        $this->path = $this->join($this->path, $name);
        $this->load();
    }

    public function up(): void
    {
        $this->path = $this->clean(dirname($this->path));
        $this->load();
    }

    public function goto(string $path): void
    {
        $this->path = $this->clean($path);
        $this->load();
    }

    // --- mutations ---------------------------------------------------------

    public function delete(string $name): void
    {
        $this->ctl('fs:delete', ['path' => $this->join($this->path, $name)], 'fs.delete');
    }

    public function extract(string $name): void
    {
        $this->ctl('fs:extract', [
            'path' => $this->join($this->path, $name),
            'dest' => $this->path,
        ], 'fs.extract');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('upload')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    Forms\Components\FileUpload::make('files')
                        ->multiple()
                        ->disk('local')
                        ->directory('fm-uploads')
                        ->preserveFilenames()
                        ->maxSize(262144) // 256 MB
                        ->required(),
                ])
                ->action(fn (array $data) => $this->handleUpload($data['files'] ?? [])),

            Action::make('newFolder')
                ->icon('heroicon-o-folder-plus')
                ->form([Forms\Components\TextInput::make('name')->required()->regex('/^[^\/\\\\]{1,255}$/')])
                ->action(fn (array $data) => $this->ctl('fs:mkdir', ['path' => $this->join($this->path, $data['name'])], 'fs.mkdir')),

            Action::make('newFile')
                ->icon('heroicon-o-document-plus')
                ->form([Forms\Components\TextInput::make('name')->required()->regex('/^[^\/\\\\]{1,255}$/')])
                ->action(fn (array $data) => $this->ctl('fs:write', ['path' => $this->join($this->path, $data['name'])], 'fs.newfile', '')),
        ];
    }

    public function rename(string $name): void
    {
        // handled via per-row action in blade through renameTo()
    }

    public function renameTo(string $from, string $to): void
    {
        if ($to === '' || str_contains($to, '/')) {
            return;
        }
        $this->ctl('fs:rename', [
            'from' => $this->join($this->path, $from),
            'to' => $this->join($this->path, $to),
        ], 'fs.rename');
    }

    public function chmod(string $name, string $mode): void
    {
        if (!preg_match('/^[0-7]{3}$/', $mode)) {
            Notification::make()->title('Mode must be 3 octal digits')->danger()->send();
            return;
        }
        $this->ctl('fs:chmod', ['path' => $this->join($this->path, $name), 'mode' => $mode], 'fs.chmod');
    }

    private function handleUpload(array $files): void
    {
        $site = $this->currentSite();
        if (!$site) {
            return;
        }
        $stage = config('hostingpanel.uploads');
        if (!is_dir($stage)) {
            @mkdir($stage, 0750, true);
        }
        $count = 0;
        foreach ((array) $files as $stored) {
            // $stored is the path within the 'local' disk
            $full = storage_path('app/' . $stored);
            $name = basename($full);
            $tmp = rtrim($stage, '/') . '/' . bin2hex(random_bytes(8)) . '-' . $name;
            if (@copy($full, $tmp)) {
                $result = app(PanelCtl::class)->run('fs:import', [
                    'domain' => $site->domain,
                    'path' => $this->join($this->path, $name),
                    'src' => $tmp,
                ]);
                @unlink($tmp);
                @unlink($full);
                $result->ok() ? $count++ : Notification::make()->title("Upload of {$name} failed")
                    ->body($result->output())->danger()->send();
            }
        }
        if ($count > 0) {
            AuditLog::record('fs.upload', $site->domain . ':' . $this->path);
            Notification::make()->title("{$count} file(s) uploaded")->success()->send();
        }
        $this->load();
    }

    private function ctl(string $command, array $flags, string $audit, ?string $stdin = null): void
    {
        $site = $this->currentSite();
        if (!$site) {
            return;
        }
        $result = app(PanelCtl::class)->run($command, ['domain' => $site->domain] + $flags, $stdin);
        if ($result->ok()) {
            AuditLog::record($audit, $site->domain . ':' . ($flags['path'] ?? $this->path));
        }
        Notification::make()->title($result->ok() ? $result->output() : 'Failed')
            ->body($result->ok() ? null : $result->output())
            ->{$result->ok() ? 'success' : 'danger'}()->send();
        $this->load();
    }

    public function downloadUrl(string $name): string
    {
        return route('files.download', [
            'site' => $this->site,
            'path' => $this->join($this->path, $name),
        ]);
    }

    public function editUrl(string $name): string
    {
        return route('files.edit', [
            'site' => $this->site,
            'path' => $this->join($this->path, $name),
        ]);
    }

    private function join(string $base, string $name): string
    {
        return $this->clean(rtrim($base, '/') . '/' . $name);
    }

    private function clean(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        if (str_contains($path, '..')) {
            return '/';
        }
        $path = '/' . trim($path, '/');
        return $path === '//' ? '/' : $path;
    }
}
