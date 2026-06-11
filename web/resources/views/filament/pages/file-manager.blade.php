<x-filament-panels::page>
    @php($sites = \App\Models\Site::orderBy('domain')->get())

    @if ($sites->isEmpty())
        <x-filament::section>
            <p class="text-sm text-gray-500">Create a site first — the file manager works per site.</p>
        </x-filament::section>
    @else
        <div class="flex flex-wrap items-center gap-3">
            <select wire:model.live="site"
                    class="fi-input rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-sm">
                @foreach ($sites as $s)
                    <option value="{{ $s->id }}">{{ $s->domain }}</option>
                @endforeach
            </select>

            <nav class="text-sm flex items-center gap-1 flex-wrap">
                <button wire:click="goto('/')" class="text-primary-600 hover:underline">root</button>
                @php($crumb = '')
                @foreach (array_filter(explode('/', $path)) as $part)
                    @php($crumb .= '/' . $part)
                    <span class="text-gray-400">/</span>
                    <button wire:click="goto('{{ $crumb }}')" class="text-primary-600 hover:underline">{{ $part }}</button>
                @endforeach
            </nav>
        </div>

        @if ($listError)
            <div class="rounded-lg bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300 px-4 py-3 text-sm">
                {{ $listError }}
            </div>
        @endif

        <x-filament::section>
            <table class="w-full text-sm" wire:key="list-{{ $site }}-{{ $path }}">
                <thead>
                    <tr class="text-left text-gray-500 border-b border-gray-200 dark:border-gray-700">
                        <th class="py-2">Name</th>
                        <th class="py-2">Size</th>
                        <th class="py-2">Perms</th>
                        <th class="py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @if ($path !== '/')
                        <tr><td colspan="4" class="py-2">
                            <button wire:click="up" class="text-gray-500 hover:text-primary-600">↩ ..</button>
                        </td></tr>
                    @endif
                    @forelse ($items as $item)
                        @php($isArchive = preg_match('/\.(zip|tar\.gz|tgz|tar)$/i', $item['name']))
                        @php($isText = preg_match('/\.(php|html?|css|js|json|txt|md|xml|ini|conf|env|htaccess|sql|ya?ml|log)$/i', $item['name']))
                        <tr x-data="{ act: '' }">
                            <td class="py-2">
                                @if ($item['dir'])
                                    <button wire:click="open('{{ addslashes($item['name']) }}')" class="text-primary-600 hover:underline">📁 {{ $item['name'] }}</button>
                                @else
                                    <span>📄 {{ $item['name'] }}</span>
                                @endif
                            </td>
                            <td class="py-2 text-gray-500 text-xs">{{ $item['dir'] ? '—' : \Illuminate\Support\Number::fileSize($item['size']) }}</td>
                            <td class="py-2 text-gray-500 text-xs font-mono">{{ $item['mode'] }}</td>
                            <td class="py-2 text-right text-xs whitespace-nowrap space-x-2">
                                @unless ($item['dir'])
                                    <a href="{{ $this->downloadUrl($item['name']) }}" class="text-gray-500 hover:text-primary-600">Download</a>
                                    @if ($isText)
                                        <a href="{{ $this->editUrl($item['name']) }}" class="text-gray-500 hover:text-primary-600">Edit</a>
                                    @endif
                                    @if ($isArchive)
                                        <button wire:click="extract('{{ addslashes($item['name']) }}')" class="text-gray-500 hover:text-emerald-600">Extract</button>
                                    @endif
                                @endunless
                                <button @click="act = act === 'rename' ? '' : 'rename'" class="text-gray-500 hover:text-primary-600">Rename</button>
                                <button @click="act = act === 'chmod' ? '' : 'chmod'" class="text-gray-500 hover:text-primary-600">Chmod</button>
                                <button wire:click="delete('{{ addslashes($item['name']) }}')" wire:confirm="Delete {{ $item['name'] }}?" class="text-danger-600 hover:underline">Delete</button>

                                <div x-show="act === 'rename'" x-cloak class="mt-1" x-data="{ v: '{{ addslashes($item['name']) }}' }">
                                    <input x-model="v" class="fi-input rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900 px-2 py-1 text-xs w-40">
                                    <button @click="$wire.renameTo('{{ addslashes($item['name']) }}', v); act=''" class="text-primary-600">OK</button>
                                </div>
                                <div x-show="act === 'chmod'" x-cloak class="mt-1" x-data="{ m: '{{ $item['mode'] }}' }">
                                    <input x-model="m" maxlength="3" class="fi-input rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900 px-2 py-1 text-xs w-16 font-mono">
                                    <button @click="$wire.chmod('{{ addslashes($item['name']) }}', m); act=''" class="text-primary-600">OK</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-6 text-center text-gray-500">Empty folder.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-filament::section>
    @endif
</x-filament-panels::page>
