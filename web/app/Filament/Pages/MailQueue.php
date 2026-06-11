<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Services\PanelCtl;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class MailQueue extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';
    protected static ?string $navigationGroup = 'Mail';
    protected static ?int $navigationSort = 2;
    protected static ?string $title = 'Mail Queue';

    protected static string $view = 'filament.pages.mail-queue';

    public array $queue = [];
    public string $log = '';

    public function mount(): void
    {
        $this->refreshQueue();
    }

    public function refreshQueue(): void
    {
        $ctl = app(PanelCtl::class);

        $this->queue = [];
        $result = $ctl->run('mail:queue');
        if ($result->ok()) {
            foreach (explode("\n", trim($result->stdout)) as $line) {
                $entry = json_decode($line, true);
                if (is_array($entry) && isset($entry['queue_id'])) {
                    $this->queue[] = $entry;
                }
            }
        }

        $logResult = $ctl->run('mail:log');
        $this->log = $logResult->ok() ? trim($logResult->stdout) : '';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('flush')
                ->label('Retry all now')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    $result = app(PanelCtl::class)->run('mail:queue:flush');
                    AuditLog::record('mail.queue.flush');
                    Notification::make()->title($result->output())
                        ->{$result->ok() ? 'success' : 'danger'}()->send();
                    $this->refreshQueue();
                }),
        ];
    }

    public function deleteMessage(string $id): void
    {
        $result = app(PanelCtl::class)->run('mail:queue:delete', ['id' => $id]);
        AuditLog::record('mail.queue.delete', $id);
        Notification::make()->title($result->output())
            ->{$result->ok() ? 'success' : 'danger'}()->send();
        $this->refreshQueue();
    }
}
