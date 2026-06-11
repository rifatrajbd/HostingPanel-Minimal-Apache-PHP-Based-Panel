<x-filament-panels::page>
    <form wire:submit="submit">
        {{ $this->form }}
        <div class="mt-4">
            <x-filament::button type="submit">Apply</x-filament::button>
        </div>
    </form>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach ($versions as $version => $exts)
            <x-filament::section>
                <x-slot name="heading">PHP {{ $version }}</x-slot>
                @if (empty($exts))
                    <p class="text-sm text-gray-500">Extension list unavailable (dev mode).</p>
                @else
                    <p class="text-xs text-gray-500 mb-2">{{ count($exts) }} loaded</p>
                    <div class="flex flex-wrap gap-1">
                        @foreach ($exts as $ext)
                            <span class="text-xs font-mono rounded bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5">{{ $ext }}</span>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
