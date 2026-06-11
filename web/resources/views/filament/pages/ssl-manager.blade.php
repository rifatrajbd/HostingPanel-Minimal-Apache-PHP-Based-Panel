<x-filament-panels::page>
    @if (empty($certs))
        <x-filament::section>
            <p class="text-sm text-gray-500">
                No certificates yet. Issue one with the button above, or from a site's manage page.
                (In dev mode this list is always empty.)
            </p>
        </x-filament::section>
    @else
        <x-filament::section>
            <x-slot name="heading">Let's Encrypt certificates</x-slot>
            <x-slot name="description">Auto-renewal runs twice daily via certbot's systemd timer.</x-slot>

            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 border-b border-gray-200 dark:border-gray-700">
                        <th class="py-2">Name</th>
                        <th class="py-2">Domains</th>
                        <th class="py-2">Expires</th>
                        <th class="py-2">Status</th>
                        <th class="py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($certs as $cert)
                        <tr>
                            <td class="py-2 font-mono">{{ $cert['name'] ?? '' }}</td>
                            <td class="py-2 text-gray-500 text-xs">{{ $cert['domains'] ?? '' }}</td>
                            <td class="py-2 text-gray-500 text-xs">{{ $cert['expiry'] ?? '' }}</td>
                            <td class="py-2 text-xs">{{ $cert['status'] ?? '' }}</td>
                            <td class="py-2 text-right space-x-2">
                                <x-filament::button size="xs" color="gray"
                                    wire:click="renew('{{ $cert['name'] }}')">Renew</x-filament::button>
                                <x-filament::button size="xs" color="danger"
                                    wire:click="deleteCert('{{ $cert['name'] }}')"
                                    wire:confirm="Delete the certificate for {{ $cert['name'] }}?">Delete</x-filament::button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-filament::section>
    @endif
</x-filament-panels::page>
