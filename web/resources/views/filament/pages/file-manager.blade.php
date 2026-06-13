<x-filament-panels::page>
    @php($sites = \App\Models\Site::orderBy('domain')->get())

    @if ($sites->isEmpty())
        <x-filament::section>
            <p class="text-sm text-gray-500">Create a site first — the file manager works per site.</p>
        </x-filament::section>
    @else
    <div
        x-data="{
            ctx: { open: false, x: 0, y: 0, name: '', isArchive: false, isFile: false, isText: false, dl: '', ed: '' },
            up: { active: false, progress: 0 },
            openCtx(e, d) { this.ctx = { ...d, open: true, x: e.clientX, y: e.clientY }; },
        }"
        @click="ctx.open = false"
        x-on:livewire-upload-start.window="up.active = true; up.progress = 0"
        x-on:livewire-upload-progress.window="up.progress = $event.detail.progress"
        x-on:livewire-upload-finish.window="up.active = false"
        x-on:livewire-upload-error.window="up.active = false"
        class="space-y-4"
    >
        {{-- Toolbar: site, breadcrumb, upload, search, sizes ------------------ --}}
        <div class="flex flex-wrap items-center gap-3">
            <select wire:model.live="site"
                    class="fi-input rounded-lg border-gray-300 dark:border-white/10 dark:bg-white/5 text-sm py-1.5">
                @foreach ($sites as $s)
                    <option value="{{ $s->id }}">{{ $s->domain }}</option>
                @endforeach
            </select>

            <nav class="text-sm flex items-center gap-1 flex-wrap">
                <button wire:click="goto('/')" class="text-primary-600 hover:underline">home</button>
                @php($crumb = '')
                @foreach (array_filter(explode('/', $path)) as $part)
                    @php($crumb .= '/' . $part)
                    <span class="text-gray-400">/</span>
                    <button wire:click="goto('{{ $crumb }}')" class="text-primary-600 hover:underline">{{ $part }}</button>
                @endforeach
            </nav>

            <div class="ml-auto flex items-center gap-2">
                <label class="cursor-pointer">
                    <input type="file" multiple wire:model="uploads" class="hidden">
                    <span class="fi-btn fi-btn-size-sm inline-flex items-center gap-1.5 rounded-lg bg-primary-600 hover:bg-primary-500 text-white text-sm font-medium px-3 py-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 7.5L12 3m0 0L7.5 7.5M12 3v13.5"/></svg>
                        Upload
                    </span>
                </label>

                <form wire:submit.prevent="doSearch" class="flex items-center gap-1">
                    <input type="search" wire:model="search" placeholder="Search files…"
                           class="fi-input rounded-lg border-gray-300 dark:border-white/10 dark:bg-white/5 text-sm w-44 py-1.5">
                    <x-filament::button type="submit" size="sm" color="gray" icon="heroicon-o-magnifying-glass" />
                </form>
                <x-filament::button size="sm" color="{{ $showSizes ? 'primary' : 'gray' }}"
                    wire:click="toggleSizes" icon="heroicon-o-calculator">Sizes</x-filament::button>
            </div>
        </div>

        @if ($listError)
            <div class="rounded-lg bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300 px-4 py-3 text-sm">
                {{ $listError }}
            </div>
        @endif

        {{-- Search results --------------------------------------------------- --}}
        @if ($searching)
            <x-filament::section>
                <x-slot name="heading">Search results for “{{ $search }}” ({{ count($searchResults) }})</x-slot>
                <x-slot name="headerEnd">
                    <x-filament::button size="xs" color="gray" wire:click="clearSearch">Clear</x-filament::button>
                </x-slot>
                @if (empty($searchResults))
                    <p class="text-sm text-gray-500">Nothing found.</p>
                @else
                    <ul class="divide-y divide-gray-100 dark:divide-white/5 text-sm">
                        @foreach ($searchResults as $r)
                            <li class="py-2">
                                <button wire:click="openResult('{{ addslashes($r['path']) }}', {{ $r['dir'] ? 'true' : 'false' }})" class="text-left">
                                    <span class="text-primary-600">{{ $r['dir'] ? '📁' : '📄' }} {{ $r['name'] }}</span>
                                    <span class="block text-xs text-gray-500 font-mono">{{ $r['path'] }}</span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-filament::section>
        @else

        {{-- Selection toolbar (uniform sm buttons) --------------------------- --}}
        <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 px-3 py-2 flex flex-wrap items-center gap-2">
            <span class="text-sm text-gray-500 pr-1">Selected: {{ count($selected) }} / {{ count($items) }}</span>

            @if ($this->singleFile())
                <x-filament::button tag="a" size="sm" color="gray" icon="heroicon-m-pencil-square" :href="$this->editUrl($this->singleFile())">Edit</x-filament::button>
                <x-filament::button tag="a" size="sm" color="gray" icon="heroicon-m-arrow-down-tray" :href="$this->downloadUrl($this->singleFile())">Download</x-filament::button>
            @endif

            <x-filament::button size="sm" color="gray" icon="heroicon-m-document-duplicate" wire:click="copySelected" :disabled="empty($selected)">Copy</x-filament::button>
            <x-filament::button size="sm" color="gray" icon="heroicon-m-square-2-stack" wire:click="duplicateSelected" :disabled="empty($selected)">Duplicate</x-filament::button>
            <x-filament::button size="sm" color="gray" icon="heroicon-m-arrows-right-left" wire:click="cutSelected" :disabled="empty($selected)">Move</x-filament::button>
            @if ($this->clipboard())
                <x-filament::button size="sm" color="success" icon="heroicon-m-clipboard" wire:click="paste">Paste ({{ count($this->clipboard()['items']) }})</x-filament::button>
            @endif
            <x-filament::button size="sm" color="gray" icon="heroicon-m-archive-box" wire:click="mountAction('archive')" :disabled="empty($selected)">Archive</x-filament::button>
            <x-filament::button size="sm" color="gray" icon="heroicon-m-pencil" wire:click="mountAction('rename')" :disabled="count($selected) !== 1">Rename</x-filament::button>
            <x-filament::button size="sm" color="gray" icon="heroicon-m-lock-closed" wire:click="mountAction('permissions')" :disabled="empty($selected)">Set Permissions</x-filament::button>
            <x-filament::button size="sm" color="danger" icon="heroicon-m-trash" wire:click="removeSelected" wire:confirm="Remove the selected item(s)? This cannot be undone." :disabled="empty($selected)">Remove</x-filament::button>
        </div>

        {{-- File table ------------------------------------------------------- --}}
        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900">
            <table class="w-full text-sm" wire:key="list-{{ $site }}-{{ $path }}">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-gray-500 bg-gray-50 dark:bg-white/5 border-b border-gray-200 dark:border-white/10">
                        <th class="px-4 py-3 w-10">
                            <input type="checkbox" class="rounded border-gray-300 dark:border-white/20"
                                   @if (count($selected) === count($items) && count($items) > 0) checked @endif
                                   x-on:click="$el.checked ? $wire.selectAll() : $wire.deselectAll()">
                        </th>
                        <th class="px-3 py-3 font-medium">Name</th>
                        <th class="px-3 py-3 font-medium">Size</th>
                        <th class="px-3 py-3 font-medium">Permissions</th>
                        <th class="px-3 py-3 font-medium">Last modified</th>
                        <th class="px-3 py-3 font-medium">Owner</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @if ($path !== '/')
                        <tr class="hover:bg-gray-50 dark:hover:bg-white/5">
                            <td></td>
                            <td class="px-3 py-2.5" colspan="5">
                                <button wire:click="up" class="inline-flex items-center gap-2 text-gray-500 hover:text-primary-600">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/></svg>
                                    Parent directory
                                </button>
                            </td>
                        </tr>
                    @endif
                    @forelse ($items as $item)
                        @php($archive = (bool) preg_match('/\.(zip|tar\.gz|tgz|tar)$/i', $item['name']))
                        @php($text = (bool) preg_match('/\.(php|html?|css|js|json|txt|md|xml|ini|conf|env|htaccess|sql|ya?ml|log)$/i', $item['name']))
                        <tr @class([
                                'group hover:bg-gray-50 dark:hover:bg-white/5',
                                'bg-primary-50 dark:bg-primary-500/10' => in_array($item['name'], $selected, true),
                            ])
                            @contextmenu.prevent="$wire.selectOnly(@js($item['name'])); openCtx($event, {
                                name: @js($item['name']),
                                isArchive: {{ $archive ? 'true' : 'false' }},
                                isFile: {{ $item['dir'] ? 'false' : 'true' }},
                                isText: {{ $text ? 'true' : 'false' }},
                                dl: @js($item['dir'] ? '' : $this->downloadUrl($item['name'])),
                                ed: @js($item['dir'] ? '' : $this->editUrl($item['name'])),
                            })">
                            <td class="px-4 py-2.5">
                                <input type="checkbox" class="rounded border-gray-300 dark:border-white/20"
                                       wire:model.live="selected" value="{{ $item['name'] }}">
                            </td>
                            <td class="px-3 py-2.5">
                                @if ($item['dir'])
                                    <button wire:click="open('{{ addslashes($item['name']) }}')" class="inline-flex items-center gap-2 font-medium text-gray-800 dark:text-gray-100 hover:text-primary-600">
                                        <svg class="w-5 h-5 text-amber-400" viewBox="0 0 20 20" fill="currentColor"><path d="M3.75 3A1.75 1.75 0 002 4.75v10.5c0 .966.784 1.75 1.75 1.75h12.5A1.75 1.75 0 0018 15.25V6.75A1.75 1.75 0 0016.25 5h-5.586a.25.25 0 01-.177-.073L8.823 3.263A.9.9 0 008.186 3H3.75z"/></svg>
                                        {{ $item['name'] }}
                                    </button>
                                @else
                                    <span class="inline-flex items-center gap-2 text-gray-700 dark:text-gray-200">
                                        <svg class="w-5 h-5 {{ $archive ? 'text-emerald-400' : 'text-gray-400' }}" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.5 2A1.5 1.5 0 003 3.5v13A1.5 1.5 0 004.5 18h11a1.5 1.5 0 001.5-1.5V7.621a1.5 1.5 0 00-.44-1.06l-3.122-3.121A1.5 1.5 0 0012.378 3H4.5zm0 0" clip-rule="evenodd"/></svg>
                                        {{ $item['name'] }}
                                        @if (!empty($item['link']))<span class="text-xs text-gray-400">↗ link</span>@endif
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-gray-500 text-xs">{{ ($item['dir'] && !$showSizes) ? '—' : \Illuminate\Support\Number::fileSize($item['size'] ?? 0) }}</td>
                            <td class="px-3 py-2.5 text-gray-500 text-xs font-mono">{{ $item['mode'] }}</td>
                            <td class="px-3 py-2.5 text-gray-500 text-xs">{{ \Illuminate\Support\Carbon::createFromTimestamp($item['mtime'])->format('M j, Y H:i') }}</td>
                            <td class="px-3 py-2.5 text-gray-500 text-xs font-mono">{{ $item['owner'] ?? '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-10 text-center text-gray-500">Empty folder.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-400">Tip: right-click a file or folder for quick actions.</p>
        @endif

        {{-- Right-click context menu ----------------------------------------- --}}
        <div x-show="ctx.open" x-cloak x-transition.opacity
             :style="`position:fixed; left:${ctx.x}px; top:${ctx.y}px; z-index:50;`"
             @click.stop
             class="w-44 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-800 shadow-xl py-1 text-sm">
            <template x-if="ctx.isFile">
                <div>
                    <a :href="ctx.dl" class="block px-3 py-1.5 hover:bg-gray-100 dark:hover:bg-white/10">⬇ Download</a>
                    <template x-if="ctx.isText">
                        <a :href="ctx.ed" class="block px-3 py-1.5 hover:bg-gray-100 dark:hover:bg-white/10">✎ Edit</a>
                    </template>
                </div>
            </template>
            <button @click="$wire.copySelected(); ctx.open=false" class="w-full text-left px-3 py-1.5 hover:bg-gray-100 dark:hover:bg-white/10">Copy</button>
            <button @click="$wire.cutSelected(); ctx.open=false" class="w-full text-left px-3 py-1.5 hover:bg-gray-100 dark:hover:bg-white/10">Move</button>
            <button @click="$wire.duplicateSelected(); ctx.open=false" class="w-full text-left px-3 py-1.5 hover:bg-gray-100 dark:hover:bg-white/10">Duplicate</button>
            <button @click="$wire.paste(); ctx.open=false" class="w-full text-left px-3 py-1.5 hover:bg-gray-100 dark:hover:bg-white/10">Paste</button>
            <div class="my-1 border-t border-gray-100 dark:border-white/10"></div>
            <button @click="$wire.mountAction('rename'); ctx.open=false" class="w-full text-left px-3 py-1.5 hover:bg-gray-100 dark:hover:bg-white/10">Rename</button>
            <button @click="$wire.mountAction('permissions'); ctx.open=false" class="w-full text-left px-3 py-1.5 hover:bg-gray-100 dark:hover:bg-white/10">Set Permissions</button>
            <button @click="$wire.mountAction('archive'); ctx.open=false" class="w-full text-left px-3 py-1.5 hover:bg-gray-100 dark:hover:bg-white/10">Archive</button>
            <template x-if="ctx.isArchive">
                <button @click="$wire.extract(ctx.name); ctx.open=false" class="w-full text-left px-3 py-1.5 hover:bg-gray-100 dark:hover:bg-white/10">Extract</button>
            </template>
            <div class="my-1 border-t border-gray-100 dark:border-white/10"></div>
            <button @click="$wire.removeSelected(); ctx.open=false" class="w-full text-left px-3 py-1.5 text-danger-600 hover:bg-gray-100 dark:hover:bg-white/10">Remove</button>
        </div>

        {{-- Upload progress toast -------------------------------------------- --}}
        <div x-show="up.active" x-cloak
             class="fixed bottom-4 right-4 z-50 w-72 rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-800 shadow-xl p-4">
            <div class="flex items-center justify-between text-sm mb-2">
                <span class="font-medium">Uploading…</span>
                <span class="text-gray-500" x-text="up.progress + '%'"></span>
            </div>
            <div class="h-2 rounded-full bg-gray-200 dark:bg-white/10 overflow-hidden">
                <div class="h-full rounded-full bg-primary-500 transition-all" :style="`width:${up.progress}%`"></div>
            </div>
        </div>
    </div>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
