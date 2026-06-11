<x-filament-panels::page>
    <div class="space-y-6">
        <form wire:submit="savePhp">
            {{ $this->phpForm }}
            <div class="mt-4">
                <x-filament::button type="submit">Apply PHP settings</x-filament::button>
            </div>
        </form>

        <form wire:submit="saveCron">
            {{ $this->cronForm }}
            <div class="mt-4">
                <x-filament::button type="submit">Save cron jobs</x-filament::button>
            </div>
        </form>

        <x-filament::section>
            <x-slot name="heading">Site info</x-slot>
            <dl class="grid grid-cols-2 gap-y-2 text-sm">
                <dt class="text-gray-500">Document root</dt>
                <dd class="font-mono">{{ $record->doc_root }}</dd>
                <dt class="text-gray-500">System user</dt>
                <dd class="font-mono">{{ $record->system_user }}</dd>
                <dt class="text-gray-500">SSL</dt>
                <dd>{{ $record->ssl_enabled ? 'Active' : 'Not issued' }}</dd>
                <dt class="text-gray-500">Created</dt>
                <dd>{{ $record->created_at?->format('M j, Y') }}</dd>
            </dl>
        </x-filament::section>
    </div>
</x-filament-panels::page>
