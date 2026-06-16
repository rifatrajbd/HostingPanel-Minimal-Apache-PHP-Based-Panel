<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Check mail DNS</x-slot>
        <x-slot name="description">
            Verify the live MX, A/AAAA, SPF, DKIM, DMARC and reverse-DNS (PTR) records for a mail
            domain. Each record is looked up with <code>dig</code> on the server.
        </x-slot>

        <form wire:submit="check" class="flex items-end gap-3">
            <div class="flex-1">
                {{ $this->form }}
            </div>
            <x-filament::button type="submit" icon="heroicon-o-magnifying-glass" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="check">Run check</span>
                <span wire:loading wire:target="check">Checking…</span>
            </x-filament::button>
        </form>
    </x-filament::section>

    @if ($checked)
        <x-filament::section>
            <x-slot name="heading">Results for {{ $domain }}</x-slot>

            @if (empty($rows))
                <p class="text-sm text-gray-500">
                    No results. DNS lookups only run on the live server (this is dev/dry-run mode).
                </p>
            @else
                @php
                    $okCount = collect($rows)->where('status', 'ok')->count();
                    $total = count($rows);
                @endphp
                <p class="mb-4 text-sm">
                    <span @class([
                        'font-semibold',
                        'text-success-600 dark:text-success-400' => $okCount === $total,
                        'text-warning-600 dark:text-warning-400' => $okCount < $total,
                    ])>{{ $okCount }} / {{ $total }}</span>
                    records OK.
                </p>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 border-b border-gray-200 dark:border-gray-700">
                            <th class="py-2 pr-4">Record</th>
                            <th class="py-2 pr-4">Host</th>
                            <th class="py-2 pr-4">Expected</th>
                            <th class="py-2 pr-4">Found</th>
                            <th class="py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($rows as $row)
                            <tr>
                                <td class="py-2 pr-4 font-semibold">{{ $row['type'] ?? '' }}</td>
                                <td class="py-2 pr-4 font-mono text-xs break-all">{{ $row['host'] ?? '' }}</td>
                                <td class="py-2 pr-4 font-mono text-xs text-gray-500 break-all">{{ $row['expected'] ?? '' }}</td>
                                <td class="py-2 pr-4 font-mono text-xs break-all">{{ $row['found'] ?? '' }}</td>
                                <td class="py-2">
                                    @php $status = $row['status'] ?? 'miss'; @endphp
                                    @if ($status === 'ok')
                                        <x-filament::badge color="success" icon="heroicon-m-check-circle">OK</x-filament::badge>
                                    @elseif ($status === 'warn')
                                        <x-filament::badge color="warning" icon="heroicon-m-exclamation-triangle">Warning</x-filament::badge>
                                    @else
                                        <x-filament::badge color="danger" icon="heroicon-m-x-circle">Missing</x-filament::badge>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
