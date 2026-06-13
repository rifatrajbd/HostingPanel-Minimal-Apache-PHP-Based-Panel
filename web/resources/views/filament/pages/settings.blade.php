<x-filament-panels::page>
    <div x-data="{ tab: 'domain' }" class="space-y-6">
        <x-filament::tabs>
            <x-filament::tabs.item ::alpine-active="tab === 'domain'" x-on:click="tab = 'domain'" icon="heroicon-m-globe-alt">
                Panel domain
            </x-filament::tabs.item>
            <x-filament::tabs.item ::alpine-active="tab === 'access'" x-on:click="tab = 'access'" icon="heroicon-m-lock-closed">
                Access
            </x-filament::tabs.item>
            <x-filament::tabs.item ::alpine-active="tab === 'backups'" x-on:click="tab = 'backups'" icon="heroicon-m-archive-box">
                Backups
            </x-filament::tabs.item>
        </x-filament::tabs>

        {{-- Panel domain ----------------------------------------------------- --}}
        <div x-show="tab === 'domain'">
            <form wire:submit="saveDomain">
                {{ $this->domainForm }}
                <div class="mt-4">
                    <x-filament::button type="submit">Save panel domain</x-filament::button>
                </div>
            </form>
        </div>

        {{-- Access ----------------------------------------------------------- --}}
        <div x-show="tab === 'access'" x-cloak>
            <form wire:submit="saveAccess">
                {{ $this->accessForm }}
                <div class="mt-4 flex gap-2">
                    <x-filament::button type="submit">Restrict access</x-filament::button>
                    <x-filament::button type="button" color="gray" wire:click="openAccess">Open to all</x-filament::button>
                </div>
            </form>
        </div>

        {{-- Backups ---------------------------------------------------------- --}}
        <div x-show="tab === 'backups'" x-cloak class="space-y-6">
            <form wire:submit="saveBackup">
                {{ $this->backupForm }}
                <div class="mt-4 flex flex-wrap gap-2">
                    <x-filament::button type="submit">Save backup settings</x-filament::button>
                    <x-filament::button type="button" color="gray" wire:click="testBackup">Test connection</x-filament::button>
                    <x-filament::button type="button" color="success" wire:click="runBackup">Run backup now</x-filament::button>
                </div>
            </form>

            <x-filament::section>
                <x-slot name="heading">Download &amp; restore</x-slot>
                <x-slot name="description">Download a one-off backup now, or restore everything from a full-backup archive.</x-slot>

                <div class="flex flex-wrap items-end gap-3">
                    <x-filament::button tag="a" href="{{ route('backup.download', ['type' => 'full']) }}"
                        icon="heroicon-m-arrow-down-tray" color="gray">Download full backup</x-filament::button>

                    <div x-data="{ domain: '' }" class="flex items-end gap-2">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Single site</label>
                            <select x-model="domain" class="fi-input rounded-lg border-gray-300 dark:border-white/10 dark:bg-white/5 text-sm py-1.5">
                                <option value="">Choose a site…</option>
                                @foreach ($this->sites as $domain)
                                    <option value="{{ $domain }}">{{ $domain }}</option>
                                @endforeach
                            </select>
                        </div>
                        <x-filament::button color="gray" icon="heroicon-m-arrow-down-tray"
                            x-bind:disabled="!domain"
                            x-on:click="if (domain) window.location.href = '{{ route('backup.download', ['type' => 'site']) }}&domain=' + encodeURIComponent(domain)">
                            Download site
                        </x-filament::button>
                    </div>

                    {{ $this->restoreAction }}
                </div>
                <p class="text-xs text-gray-500 mt-3">
                    The full backup contains every database, every site's files, and the panel database. Restore replaces existing
                    databases and site files. Uploads over 256&nbsp;MB: copy the archive via SFTP and run
                    <code>panelctl backup:restore --src /path/to/backup.tar.gz</code> over SSH.
                </p>
            </x-filament::section>

            @if ($backupLog !== '')
                <x-filament::section collapsible collapsed>
                    <x-slot name="heading">Backup log (latest)</x-slot>
                    <pre class="text-xs font-mono overflow-x-auto max-h-64 overflow-y-auto whitespace-pre-wrap">{{ $backupLog }}</pre>
                </x-filament::section>
            @endif
        </div>

        <x-filament-actions::modals />
    </div>
</x-filament-panels::page>
