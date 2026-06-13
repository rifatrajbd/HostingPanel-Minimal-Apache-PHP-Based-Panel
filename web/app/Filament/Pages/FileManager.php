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
    public array $selected = [];
    public bool $showSizes = false;
    public ?string $listError = null;

    /** Bound to the upload <input>; files auto-import the moment they're chosen. */
    public $uploads = [];

    public string $search = '';
    public bool $searching = false;
    public array $searchResults = [];

    public function mount(): void
    {
        $this->site ??= Site::min('id');
        $this->load();
    }

    public function updatedSite(): void
    {
        $this->path = '/';
        $this->clearSearch();
        $this->load();
    }

    public function load(): void
    {
        $this->items = [];
        $this->selected = [];
        $this->listError = null;
        $site = $this->currentSite();
        if (!$site) {
            return;
        }
        $flags = ['domain' => $site->domain, 'path' => $this->path];
        if ($this->showSizes) {
            $flags['sizes'] = '1';
        }
        $result = app(PanelCtl::class)->run('fs:list', $flags);
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

    // --- navigation --------------------------------------------------------

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

    public function toggleSizes(): void
    {
        $this->showSizes = ! $this->showSizes;
        $this->load();
    }

    public function selectAll(): void
    {
        $this->selected = array_column($this->items, 'name');
    }

    public function deselectAll(): void
    {
        $this->selected = [];
    }

    /** Used by the right-click menu: operate on just this item. */
    public function selectOnly(string $name): void
    {
        $this->selected = [$name];
    }

    // --- search ------------------------------------------------------------

    public function doSearch(): void
    {
        $site = $this->currentSite();
        if (!$site || strlen(trim($this->search)) < 2) {
            return;
        }
        $result = app(PanelCtl::class)->run('fs:search', ['domain' => $site->domain, 'query' => trim($this->search)]);
        $this->searchResults = $result->ok() ? (json_decode($result->stdout, true) ?: []) : [];
        $this->searching = true;
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->searching = false;
        $this->searchResults = [];
    }

    public function openResult(string $path, bool $dir): void
    {
        $this->path = $dir ? $this->clean($path) : $this->clean(dirname($path));
        $this->clearSearch();
        $this->load();
    }

    // --- single-item helpers (links) ---------------------------------------

    public function downloadUrl(string $name): string
    {
        return route('files.download', ['site' => $this->site, 'path' => $this->join($this->path, $name)]);
    }

    public function editUrl(string $name): string
    {
        return route('files.edit', ['site' => $this->site, 'path' => $this->join($this->path, $name)]);
    }

    /** The single selected file name, or null (used to enable Download/Edit). */
    public function singleFile(): ?string
    {
        if (count($this->selected) !== 1) {
            return null;
        }
        $name = $this->selected[array_key_first($this->selected)];
        foreach ($this->items as $item) {
            if ($item['name'] === $name && empty($item['dir'])) {
                return $name;
            }
        }
        return null;
    }

    // --- clipboard / batch -------------------------------------------------

    public function copySelected(): void
    {
        $this->setClipboard('copy');
    }

    public function cutSelected(): void
    {
        $this->setClipboard('cut');
    }

    private function setClipboard(string $mode): void
    {
        if (empty($this->selected)) {
            return;
        }
        session(['fm_clipboard' => [
            'mode' => $mode, 'site' => $this->site, 'base' => $this->path, 'items' => array_values($this->selected),
        ]]);
        Notification::make()->title(count($this->selected) . ' item(s) ' . ($mode === 'cut' ? 'cut' : 'copied')
            . ' — open a folder and Paste.')->success()->send();
    }

    public function clipboard(): ?array
    {
        return session('fm_clipboard');
    }

    public function paste(): void
    {
        $clip = session('fm_clipboard');
        $site = $this->currentSite();
        if (!$clip || !$site || (int) $clip['site'] !== (int) $this->site) {
            Notification::make()->title('Clipboard is empty (paste works within the same site).')->warning()->send();
            return;
        }
        $command = $clip['mode'] === 'cut' ? 'fs:rename' : 'fs:copy';
        $ok = 0;
        foreach ($clip['items'] as $item) {
            $result = app(PanelCtl::class)->run($command, [
                'domain' => $site->domain,
                'from' => $this->clean(rtrim($clip['base'], '/') . '/' . $item),
                'to' => $this->join($this->path, $item),
            ]);
            $result->ok() ? $ok++ : Notification::make()->title("{$item}: " . $result->output())->danger()->send();
        }
        if ($clip['mode'] === 'cut') {
            session()->forget('fm_clipboard');
        }
        AuditLog::record('fs.paste', $site->domain . ':' . $this->path);
        Notification::make()->title("{$ok} item(s) pasted")->success()->send();
        $this->load();
    }

    public function duplicateSelected(): void
    {
        $site = $this->currentSite();
        if (!$site || empty($this->selected)) {
            return;
        }
        foreach ($this->selected as $name) {
            app(PanelCtl::class)->run('fs:copy', [
                'domain' => $site->domain,
                'from' => $this->join($this->path, $name),
                'to' => $this->join($this->path, $this->copyName($name)),
            ]);
        }
        AuditLog::record('fs.duplicate', $site->domain . ':' . $this->path);
        Notification::make()->title('Duplicated')->success()->send();
        $this->load();
    }

    public function removeSelected(): void
    {
        $site = $this->currentSite();
        if (!$site || empty($this->selected)) {
            return;
        }
        foreach ($this->selected as $name) {
            app(PanelCtl::class)->run('fs:delete', ['domain' => $site->domain, 'path' => $this->join($this->path, $name)]);
        }
        AuditLog::record('fs.delete', $site->domain . ':' . $this->path);
        Notification::make()->title('Removed')->success()->send();
        $this->load();
    }

    public function extract(string $name): void
    {
        $this->run('fs:extract', ['path' => $this->join($this->path, $name), 'dest' => $this->path], 'fs.extract');
    }

    // --- actions with modals ----------------------------------------------

    /** Fired by Livewire as soon as files are chosen — imports them at once. */
    public function updatedUploads(): void
    {
        $site = $this->currentSite();
        if (!$site || empty($this->uploads)) {
            return;
        }
        $stage = config('hostingpanel.uploads');
        if (!is_dir($stage)) {
            @mkdir($stage, 0750, true);
        }
        $count = 0;
        foreach ((array) $this->uploads as $file) {
            $name = basename($file->getClientOriginalName());
            $tmp = rtrim($stage, '/') . '/' . bin2hex(random_bytes(8)) . '-' . $name;
            if (@copy($file->getRealPath(), $tmp)) {
                $result = app(PanelCtl::class)->run('fs:import', [
                    'domain' => $site->domain, 'path' => $this->join($this->path, $name), 'src' => $tmp,
                ]);
                @unlink($tmp);
                $result->ok() ? $count++ : Notification::make()->title("Upload of {$name} failed")
                    ->body($result->output())->danger()->send();
            }
        }
        $this->uploads = [];
        if ($count > 0) {
            AuditLog::record('fs.upload', $site->domain . ':' . $this->path);
            Notification::make()->title("{$count} file(s) uploaded")->success()->send();
        }
        $this->load();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newFolder')
                ->label('New Folder')->icon('heroicon-o-folder-plus')
                ->form([Forms\Components\TextInput::make('name')->required()->regex('/^[^\/\\\\]{1,255}$/')])
                ->action(fn (array $data) => $this->run('fs:mkdir', ['path' => $this->join($this->path, $data['name'])], 'fs.mkdir')),

            Action::make('newFile')
                ->label('New File')->icon('heroicon-o-document-plus')
                ->form([Forms\Components\TextInput::make('name')->required()->regex('/^[^\/\\\\]{1,255}$/')])
                ->action(fn (array $data) => $this->run('fs:write', ['path' => $this->join($this->path, $data['name'])], 'fs.newfile', '')),
        ];
    }

    public function renameAction(): Action
    {
        return Action::make('rename')->label('Rename')->icon('heroicon-o-pencil')->color('gray')
            ->disabled(fn () => count($this->selected) !== 1)
            ->fillForm(fn () => ['name' => $this->selected[array_key_first($this->selected)] ?? ''])
            ->form([Forms\Components\TextInput::make('name')->required()->regex('/^[^\/\\\\]{1,255}$/')])
            ->action(function (array $data) {
                $from = $this->selected[array_key_first($this->selected)];
                $this->run('fs:rename', [
                    'from' => $this->join($this->path, $from),
                    'to' => $this->join($this->path, $data['name']),
                ], 'fs.rename');
            });
    }

    public function archiveAction(): Action
    {
        return Action::make('archive')->label('Archive')->icon('heroicon-o-archive-box')->color('gray')
            ->disabled(fn () => empty($this->selected))
            ->form([
                Forms\Components\TextInput::make('name')->label('Archive name')
                    ->default('archive.zip')->required()
                    ->regex('/^[^\/\\\\]+\.(zip|tar\.gz)$/')->helperText('End with .zip or .tar.gz'),
            ])
            ->action(function (array $data) {
                $site = $this->currentSite();
                $paths = array_map(fn ($n) => $this->join($this->path, $n), array_values($this->selected));
                $result = app(PanelCtl::class)->run('fs:compress', [
                    'domain' => $site->domain,
                    'path' => $this->path,
                    'dest' => $this->join($this->path, $data['name']),
                ], json_encode($paths));
                $this->report($result, 'fs.compress');
            });
    }

    public function permissionsAction(): Action
    {
        return Action::make('permissions')->label('Set Permissions')->icon('heroicon-o-lock-closed')->color('gray')
            ->disabled(fn () => empty($this->selected))
            ->form([
                Forms\Components\TextInput::make('mode')->label('Permissions (octal)')
                    ->default('644')->required()->regex('/^[0-7]{3}$/')->helperText('e.g. 644 for files, 755 for folders'),
            ])
            ->action(function (array $data) {
                $site = $this->currentSite();
                foreach ($this->selected as $name) {
                    app(PanelCtl::class)->run('fs:chmod', [
                        'domain' => $site->domain, 'path' => $this->join($this->path, $name), 'mode' => $data['mode'],
                    ]);
                }
                AuditLog::record('fs.chmod', $site->domain . ':' . $this->path);
                Notification::make()->title('Permissions updated')->success()->send();
                $this->load();
            });
    }

    // --- internals ---------------------------------------------------------

    private function run(string $command, array $flags, string $audit, ?string $stdin = null): void
    {
        $site = $this->currentSite();
        if (!$site) {
            return;
        }
        $result = app(PanelCtl::class)->run($command, ['domain' => $site->domain] + $flags, $stdin);
        $this->report($result, $audit);
    }

    private function report(\App\Services\CtlResult $result, string $audit): void
    {
        if ($result->ok()) {
            AuditLog::record($audit, $this->currentSite()?->domain . ':' . $this->path);
        }
        Notification::make()->title($result->ok() ? $result->output() : 'Failed')
            ->body($result->ok() ? null : $result->output())
            ->{$result->ok() ? 'success' : 'danger'}()->send();
        $this->load();
    }

    private function copyName(string $name): string
    {
        if (str_contains($name, '.') && !str_starts_with($name, '.')) {
            $dot = strrpos($name, '.');
            return substr($name, 0, $dot) . ' copy' . substr($name, $dot);
        }
        return $name . ' copy';
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
