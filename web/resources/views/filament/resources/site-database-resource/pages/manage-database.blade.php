<x-filament-panels::page>
    {{-- Overview ------------------------------------------------------------ --}}
    <x-filament::section>
        <x-slot name="heading">Overview</x-slot>
        <x-slot name="description">Detailed information about the database.</x-slot>

        @php
            $cells = [
                ['Database name', $record->name, 'heroicon-o-circle-stack'],
                ['Default charset', $info['charset'] ?? '—', 'heroicon-o-language'],
                ['Default collation', $info['collation'] ?? '—', 'heroicon-o-language'],
                ['Size', isset($info['size_mb']) ? $info['size_mb'] . ' MB' : '—', 'heroicon-o-document'],
                ['Users', $this->users->count(), 'heroicon-o-users'],
                ['Tables', $info['tables'] ?? 0, 'heroicon-o-table-cells'],
                ['Views', $info['views'] ?? 0, 'heroicon-o-eye'],
                ['Events', $info['events'] ?? 0, 'heroicon-o-clock'],
                ['Triggers', $info['triggers'] ?? 0, 'heroicon-o-arrows-right-left'],
                ['Routines', $info['routines'] ?? 0, 'heroicon-o-code-bracket'],
            ];
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach ($cells as [$label, $value, $icon])
                <div class="flex items-center gap-3 rounded-lg border border-gray-200 dark:border-white/10 p-3">
                    <x-filament::icon :icon="$icon" class="h-5 w-5 text-gray-400" />
                    <div>
                        <div class="text-xs text-gray-500">{{ $label }}</div>
                        <div class="text-sm font-medium">{{ $value }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    {{-- User Access --------------------------------------------------------- --}}
    <x-filament::section>
        <x-slot name="heading">User access</x-slot>
        <x-slot name="description">Users that can access this database and their privilege level.</x-slot>
        <x-slot name="headerEnd">{{ $this->grantUserAction }}</x-slot>

        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-gray-500 border-b border-gray-200 dark:border-white/10">
                    <th class="py-2">User name</th>
                    <th class="py-2">Privileges</th>
                    <th class="py-2 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($this->users as $u)
                    <tr>
                        <td class="py-2.5 font-mono">{{ $u->username }}
                            @if ($u->primary)<span class="text-xs text-gray-400">(primary)</span>@endif
                        </td>
                        <td class="py-2.5">
                            <x-filament::badge :color="$u->privileges === 'readonly' ? 'gray' : 'success'" class="inline-flex">
                                {{ $u->privileges === 'readonly' ? 'Read-only' : 'Full access' }}
                            </x-filament::badge>
                        </td>
                        <td class="py-2.5 text-right space-x-2">
                            @unless ($u->primary)
                                @if ($u->privileges === 'readonly')
                                    <x-filament::button size="xs" color="gray"
                                        wire:click="setPrivilege({{ $u->id }}, 'all')">Make full</x-filament::button>
                                @else
                                    <x-filament::button size="xs" color="gray"
                                        wire:click="setPrivilege({{ $u->id }}, 'readonly')">Make read-only</x-filament::button>
                                @endif
                                <x-filament::button size="xs" color="danger"
                                    wire:click="revokeUser({{ $u->id }})"
                                    wire:confirm="Revoke {{ $u->username }}'s access? The user is dropped.">Revoke</x-filament::button>
                            @else
                                {{ $this->resetPasswordAction }}
                            @endunless
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-filament::section>

    {{-- Database Operations -------------------------------------------------- --}}
    <x-filament::section>
        <x-slot name="heading">Database operations</x-slot>
        <x-slot name="description">Import a backup, export, or check / repair / optimize the database.</x-slot>

        <div class="grid grid-cols-2 lg:grid-cols-3 gap-3">
            {{ $this->importAction }}
            <x-filament::button tag="a" :href="$this->exportUrl('sql')" color="gray" icon="heroicon-o-arrow-down-tray">
                Export as SQL
            </x-filament::button>
            <x-filament::button tag="a" :href="$this->exportUrl('gz')" color="gray" icon="heroicon-o-archive-box">
                Export as GZ
            </x-filament::button>
            {{ $this->checkAction }}
            {{ $this->repairAction }}
            {{ $this->optimizeAction }}
        </div>
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-panels::page>
