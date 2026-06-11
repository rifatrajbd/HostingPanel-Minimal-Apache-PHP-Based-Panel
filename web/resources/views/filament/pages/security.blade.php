<x-filament-panels::page>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <form wire:submit="changePassword">
            {{ $this->passwordForm }}
            <div class="mt-4">
                <x-filament::button type="submit">Update password</x-filament::button>
            </div>
        </form>

        <x-filament::section>
            <x-slot name="heading">Two-factor authentication</x-slot>

            @if (auth()->user()->totp_enabled)
                <p class="text-sm text-success-600 mb-3">✓ 2FA is enabled on this account.</p>
                <div class="flex items-end gap-2">
                    <input type="password" wire:model="disablePassword" placeholder="Account password"
                           class="fi-input rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-sm flex-1">
                    <x-filament::button color="danger" wire:click="disable2fa">Disable</x-filament::button>
                </div>
            @elseif ($enrollSecret)
                <p class="text-sm text-gray-500 mb-2">Add this secret to your authenticator app, then enter the 6-digit code.</p>
                <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-3 mb-3">
                    <div class="text-xs text-gray-500">Secret</div>
                    <div class="font-mono text-sm break-all">{{ $enrollSecret }}</div>
                    <div class="text-xs text-gray-500 mt-2">otpauth URI</div>
                    <div class="font-mono text-xs break-all text-gray-400">{{ $enrollUri }}</div>
                </div>
                <div class="flex items-end gap-2">
                    <input type="text" wire:model="confirmCode" inputmode="numeric" maxlength="6" placeholder="000000"
                           class="fi-input rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-sm flex-1 text-center tracking-widest">
                    <x-filament::button color="success" wire:click="confirmEnroll">Confirm &amp; enable</x-filament::button>
                </div>
            @else
                <p class="text-sm text-gray-500 mb-3">
                    Protect your panel login with a time-based one-time code. Strongly recommended.
                </p>
                <x-filament::button wire:click="startEnroll">Set up 2FA</x-filament::button>
            @endif
        </x-filament::section>
    </div>

    <x-filament::section>
        <x-slot name="heading">Audit log</x-slot>
        @if ($this->auditLogs->isEmpty())
            <p class="text-sm text-gray-500">No entries yet.</p>
        @else
            <table class="w-full text-sm">
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($this->auditLogs as $row)
                        <tr>
                            <td class="py-1.5 text-primary-600 text-xs font-mono">{{ $row->action }}</td>
                            <td class="py-1.5 text-gray-500">{{ $row->details }}</td>
                            <td class="py-1.5 text-gray-400 text-xs font-mono">{{ $row->ip }}</td>
                            <td class="py-1.5 text-gray-400 text-xs text-right">{{ $row->created_at?->format('M j, H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>
</x-filament-panels::page>
