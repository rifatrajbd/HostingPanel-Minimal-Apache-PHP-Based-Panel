<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Delivery queue ({{ count($queue) }})</x-slot>
        @if (empty($queue))
            <p class="text-sm text-gray-500">Queue is empty — all mail delivered (or dev mode).</p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 border-b border-gray-200 dark:border-gray-700">
                        <th class="py-2">ID</th>
                        <th class="py-2">From</th>
                        <th class="py-2">To / reason</th>
                        <th class="py-2 text-right"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($queue as $msg)
                        <tr>
                            <td class="py-2 font-mono text-xs">{{ $msg['queue_id'] ?? '' }}</td>
                            <td class="py-2 text-xs">{{ $msg['sender'] ?? '' }}</td>
                            <td class="py-2 text-xs text-gray-500">
                                @foreach (($msg['recipients'] ?? []) as $rcpt)
                                    <div>{{ $rcpt['address'] ?? '' }}
                                        @if (!empty($rcpt['delay_reason']))
                                            <span class="text-amber-600">— {{ $rcpt['delay_reason'] }}</span>
                                        @endif
                                    </div>
                                @endforeach
                            </td>
                            <td class="py-2 text-right">
                                <x-filament::button size="xs" color="danger"
                                    wire:click="deleteMessage('{{ $msg['queue_id'] }}')"
                                    wire:confirm="Delete this message from the queue?">Delete</x-filament::button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>

    @if ($log !== '')
        <x-filament::section collapsible collapsed>
            <x-slot name="heading">Mail log (latest)</x-slot>
            <pre class="text-xs font-mono overflow-x-auto max-h-80 overflow-y-auto whitespace-pre-wrap">{{ $log }}</pre>
        </x-filament::section>
    @endif
</x-filament-panels::page>
