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

        <form wire:submit="saveAliases">
            {{ $this->aliasForm }}
            <div class="mt-4">
                <x-filament::button type="submit">Save domains</x-filament::button>
            </div>
        </form>

        @php($ips = $this->serverAddresses())
        <x-filament::section>
            <x-slot name="heading">DNS records</x-slot>
            <x-slot name="description">Point your domain here, then issue SSL.</x-slot>
            @php($showA = $record->ip_mode !== 'ipv6')
            @php($showAAAA = $record->ip_mode !== 'ipv4' && $ips['ipv6'])
            <table class="text-sm font-mono">
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @if ($showA)
                        <tr><td class="pr-6 py-1 text-gray-500">A</td><td class="pr-6">{{ $record->domain }}</td><td>{{ $ips['ipv4'] ?? '—' }}</td></tr>
                        <tr><td class="pr-6 py-1 text-gray-500">A</td><td class="pr-6">www.{{ $record->domain }}</td><td>{{ $ips['ipv4'] ?? '—' }}</td></tr>
                    @endif
                    @if ($showAAAA)
                        <tr><td class="pr-6 py-1 text-gray-500">AAAA</td><td class="pr-6">{{ $record->domain }}</td><td>{{ $ips['ipv6'] }}</td></tr>
                        <tr><td class="pr-6 py-1 text-gray-500">AAAA</td><td class="pr-6">www.{{ $record->domain }}</td><td>{{ $ips['ipv6'] }}</td></tr>
                    @endif
                </tbody>
            </table>
            @if ($record->ip_mode !== 'both')
                <p class="text-xs text-amber-600 mt-2">This site is {{ strtoupper($record->ip_mode) }}-only — publish only the records shown above.</p>
            @elseif (!$ips['ipv6'])
                <p class="text-xs text-gray-500 mt-2">This server has no IPv6 address, so no AAAA records are needed.</p>
            @endif
        </x-filament::section>

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
