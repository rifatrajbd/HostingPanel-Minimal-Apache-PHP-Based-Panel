<div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <?php
    $cards = [
        ['Sites', $counts['sites'], '/sites'],
        ['Databases', $counts['databases'], '/databases'],
        ['Mailboxes', $counts['mailboxes'], '/mail'],
    ];
    foreach ($cards as [$label, $n, $href]): ?>
        <a href="<?= e($href) ?>"
           class="bg-slate-900 border border-slate-800 rounded-xl p-5 hover:border-slate-700 transition-colors">
            <div class="text-3xl font-semibold text-white"><?= e($n) ?></div>
            <div class="text-sm text-slate-400 mt-1"><?= e($label) ?></div>
        </a>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <!-- Server stats -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl p-5 space-y-4">
        <h2 class="text-sm font-medium text-white">Server</h2>
        <dl class="text-sm space-y-2">
            <div class="flex justify-between">
                <dt class="text-slate-500">Host</dt>
                <dd class="text-slate-300 mono"><?= e($stats['hostname']) ?></dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-slate-500">OS</dt>
                <dd class="text-slate-300"><?= e($stats['os']) ?></dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-slate-500">Uptime</dt>
                <dd class="text-slate-300"><?= e($stats['uptime']) ?></dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-slate-500">Load (1/5/15m)</dt>
                <dd class="text-slate-300 mono">
                    <?= e(implode(' / ', $stats['load'])) ?>
                    <span class="text-slate-500">· <?= e($stats['cpu_count']) ?> cores</span>
                </dd>
            </div>
        </dl>
    </div>

    <!-- Memory + disk -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl p-5 space-y-5">
        <h2 class="text-sm font-medium text-white">Resources</h2>
        <?php
        $bars = [
            ['Memory', $stats['memory']['percent'],
                $stats['memory']['used_mb'] . ' / ' . $stats['memory']['total_mb'] . ' MB'],
            ['Disk', $stats['disk']['percent'],
                $stats['disk']['used_gb'] . ' / ' . $stats['disk']['total_gb'] . ' GB'],
        ];
        foreach ($bars as [$label, $pct, $detail]): ?>
            <div>
                <div class="flex justify-between text-sm mb-1.5">
                    <span class="text-slate-400"><?= e($label) ?></span>
                    <span class="text-slate-300"><?= e($detail) ?></span>
                </div>
                <div class="h-2 bg-slate-800 rounded-full overflow-hidden">
                    <div class="h-full rounded-full <?= $pct > 85 ? 'bg-red-500' : ($pct > 65 ? 'bg-amber-400' : 'bg-sky-500') ?>"
                         style="width: <?= (int) $pct ?>%"></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Recent activity -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl p-5">
        <h2 class="text-sm font-medium text-white mb-3">Recent activity</h2>
        <?php if (empty($recent)): ?>
            <p class="text-sm text-slate-500">No activity yet.</p>
        <?php else: ?>
            <ul class="text-sm space-y-2">
                <?php foreach ($recent as $row): ?>
                    <li class="flex justify-between gap-2">
                        <span class="text-slate-300 truncate">
                            <span class="text-sky-400"><?= e($row['action']) ?></span>
                            <?= e($row['details']) ?>
                        </span>
                        <span class="text-slate-600 whitespace-nowrap text-xs">
                            <?= e(date('M j H:i', (int) $row['created_at'])) ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
