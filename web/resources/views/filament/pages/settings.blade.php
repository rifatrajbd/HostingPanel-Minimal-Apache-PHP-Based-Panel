<x-filament-panels::page>
    <form wire:submit="saveDomain">
        {{ $this->domainForm }}
        <div class="mt-4">
            <x-filament::button type="submit">Save panel domain</x-filament::button>
        </div>
    </form>

    <form wire:submit="saveBackup">
        {{ $this->backupForm }}
        <div class="mt-4">
            <x-filament::button type="submit">Save backup settings</x-filament::button>
        </div>
    </form>

    @if ($backupLog !== '')
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Backup log (latest)</x-slot>
            <pre class="text-xs font-mono overflow-x-auto max-h-64 overflow-y-auto whitespace-pre-wrap">{{ $backupLog }}</pre>
        </x-filament::section>
    @endif
</x-filament-panels::page>
