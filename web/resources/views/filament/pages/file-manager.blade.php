<x-filament-panels::page>
    @php($sites = \App\Models\Site::orderBy('domain')->get())

    @if ($sites->isEmpty())
        <x-filament::section>
            <p class="text-sm text-gray-500">Create a site first — the file manager works per site.</p>
        </x-filament::section>
    @else
        {{-- Toolbar: site, breadcrumb, search, directory sizes ----------------- --}}
        <div class="flex flex-wrap items-center gap-3">
            <select wire:model.live="site"
                    class="fi-input rounded-lg border-gray-300 dark:border-white/10 dark:bg-white/5 text-sm">
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
                            <li class="py-2 flex items-center justify-between gap-3">
                                <button wire:click="openResult('{{ addslashes($r['path']) }}', {{ $r['dir'] ? 'true' : 'false' }})"
                                        class="text-left">
                                    <span class="text-primary-600">{{ $r['dir'] ? '📁' : '📄' }} {{ $r['name'] }}</span>
                                    <span class="block text-xs text-gray-500 font-mono">{{ $r['path'] }}</span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-filament::section>
        @else

        {{-- Selection toolbar ------------------------------------------------- --}}
        <div class="rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 px-3 py-2
                    flex flex-wrap items-center gap-2 text-sm sticky top-0 z-10">
            <span class="text-gray-500 pr-2">Selected: {{ count($selected) }} / {{ count($items) }}</span>

            @if ($this->singleFile())
                <x-filament::button tag="a" size="xs" color="gray" icon="heroicon-o-pencil-square"
                    :href="$this->editUrl($this->singleFile())">Edit</x-filament::button>
                <x-filament::button tag="a" size="xs" color="gray" icon="heroicon-o-arrow-down-tray"
                    :href="$this->downloadUrl($this->singleFile())">Download</x-filament::button>
            @endif

            <x-filament::button size="xs" color="gray" icon="heroicon-o-document-duplicate"
                wire:click="copySelected" :disabled="empty($selected)">Copy</x-filament::button>
            <x-filament::button size="xs" color="gray" icon="heroicon-o-square-2-stack"
                wire:click="duplicateSelected" :disabled="empty($selected)">Duplicate</x-filament::button>
            <x-filament::button size="xs" color="gray" icon="heroicon-o-arrows-right-left"
                wire:click="cutSelected" :disabled="empty($selected)">Move</x-filament::button>
            @if ($this->clipboard())
                <x-filament::button size="xs" color="success" icon="heroicon-o-clipboard"
                    wire:click="paste">Paste ({{ count($this->clipboard()['items']) }})</x-filament::button>
            @endif
            {{ $this->archiveAction }}
            {{ $this->renameAction }}
            {{ $this->permissionsAction }}
            <x-filament::button size="xs" color="danger" icon="heroicon-o-trash"
                wire:click="removeSelected" wire:confirm="Remove the selected item(s)? This cannot be undone."
                :disabled="empty($selected)">Remove</x-filament::button>
        </div>

        {{-- File table ------------------------------------------------------- --}}
        <x-filament::section class="!p-0">
            <table class="w-full text-sm" wire:key="list-{{ $site }}-{{ $path }}">
                <thead>
                    <tr class="text-left text-gray-500 border-b border-gray-200 dark:border-white/10">
                        <th class="px-4 py-2.5 w-8">
                            <input type="checkbox"
                                   @if (count($selected) === count($items) && count($items) > 0) checked @endif
                                   x-on:click="$el.checked ? $wire.selectAll() : $wire.deselectAll()">
                        </th>
                        <th class="px-3 py-2.5">Name</th>
                        <th class="px-3 py-2.5">Size</th>
                        <th class="px-3 py-2.5">Permissions</th>
                        <th class="px-3 py-2.5">Last modified</th>
                        <th class="px-3 py-2.5">Owner</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @if ($path !== '/')
                        <tr>
                            <td></td>
                            <td class="px-3 py-2" colspan="5">
                                <button wire:click="up" class="text-gray-500 hover:text-primary-600">↩ ..</button>
                            </td>
                        </tr>
                    @endif
                    @forelse ($items as $item)
                        @php($archive = preg_match('/\.(zip|tar\.gz|tgz|tar)$/i', $item['name']))
                        <tr @class(['bg-primary-50/50 dark:bg-primary-500/10' => in_array($item['name'], $selected, true)])>
                            <td class="px-4 py-2">
                                <input type="checkbox" wire:model.live="selected" value="{{ $item['name'] }}">
                            </td>
                            <td class="px-3 py-2">
                                @if ($item['dir'])
                                    <button wire:click="open('{{ addslashes($item['name']) }}')" class="text-primary-600 hover:underline">📁 {{ $item['name'] }}</button>
                                @else
                                    <span>{{ !empty($item['link']) ? '🔗' : '📄' }} {{ $item['name'] }}</span>
                                    @if ($archive)
                                        <button wire:click="extract('{{ addslashes($item['name']) }}')" class="ml-2 text-xs text-gray-400 hover:text-emerald-600">extract</button>
                                    @endif
                                @endif
                            </td>
                            <td class="px-3 py-2 text-gray-500 text-xs">
                                {{ ($item['dir'] && !$showSizes) ? '—' : \Illuminate\Support\Number::fileSize($item['size'] ?? 0) }}
                            </td>
                            <td class="px-3 py-2 text-gray-500 text-xs font-mono">{{ $item['mode'] }}</td>
                            <td class="px-3 py-2 text-gray-500 text-xs">{{ \Illuminate\Support\Carbon::createFromTimestamp($item['mtime'])->format('M j, Y H:i') }}</td>
                            <td class="px-3 py-2 text-gray-500 text-xs font-mono">{{ $item['owner'] ?? '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">Empty folder.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-filament::section>
        @endif
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
