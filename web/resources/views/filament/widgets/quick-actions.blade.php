<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Quick actions</x-slot>

        <div class="grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-9 gap-3">
            @foreach ($this->getActions() as [$label, $icon, $url, $color])
                <a href="{{ $url }}" @if (str_starts_with($url, '/')) target="_blank" @endif
                   class="group flex flex-col items-center gap-2 rounded-xl border border-gray-200 dark:border-white/10 p-3 hover:border-primary-500 hover:bg-primary-50/50 dark:hover:bg-primary-500/10 transition">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-500/10 text-primary-500 group-hover:bg-primary-500 group-hover:text-white transition">
                        <x-filament::icon :icon="$icon" class="h-5 w-5" />
                    </span>
                    <span class="text-xs text-center text-gray-600 dark:text-gray-300 group-hover:text-primary-600">{{ $label }}</span>
                </a>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
