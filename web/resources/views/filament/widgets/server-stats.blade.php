@php($s = $this->getStats())
<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Server</x-slot>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <dl class="text-sm space-y-2">
                <div class="flex justify-between"><dt class="text-gray-500">Host</dt><dd class="font-mono">{{ $s['hostname'] }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">OS</dt><dd>{{ $s['os'] }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Uptime</dt><dd>{{ $s['uptime'] }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Load</dt>
                    <dd class="font-mono">{{ implode(' / ', $s['load']) }} <span class="text-gray-400">· {{ $s['cpu_count'] }} cores</span></dd></div>
            </dl>

            @foreach ([['Memory', $s['memory']['percent'], $s['memory']['used_mb'].' / '.$s['memory']['total_mb'].' MB'], ['Disk', $s['disk']['percent'], $s['disk']['used_gb'].' / '.$s['disk']['total_gb'].' GB']] as [$label, $pct, $detail])
                <div>
                    <div class="flex justify-between text-sm mb-1.5">
                        <span class="text-gray-500">{{ $label }}</span>
                        <span>{{ $detail }}</span>
                    </div>
                    <div class="h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                        <div class="h-full rounded-full {{ $pct > 85 ? 'bg-danger-500' : ($pct > 65 ? 'bg-warning-500' : 'bg-primary-500') }}"
                             style="width: {{ $pct }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
