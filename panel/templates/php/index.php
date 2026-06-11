<?php use Panel\Support\Csrf; ?>
<?php if (!empty($dev)): ?>
    <div class="rounded-lg px-4 py-3 text-sm border bg-amber-500/10 border-amber-500/30 text-amber-300">
        Dev mode: extension lists are only available on the server.
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <?php foreach ($versions as $version => $info): ?>
        <div class="bg-slate-900 border border-slate-800 rounded-xl p-5 space-y-4"
             x-data="{ showAll: false }">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-medium text-white">PHP <?= e($version) ?></h2>
                <?php if ($version === '7.4'): ?>
                    <span class="text-xs rounded-full bg-red-500/10 text-red-400 px-2 py-0.5">end-of-life</span>
                <?php endif; ?>
            </div>

            <?php if (!$info['ok']): ?>
                <p class="text-sm text-slate-500">Extension list unavailable.</p>
            <?php else: ?>
                <div class="text-xs text-slate-400">
                    <span class="text-slate-500"><?= count($info['extensions']) ?> loaded extensions</span>
                    <button @click="showAll = !showAll" class="text-sky-400 hover:underline ml-2"
                            x-text="showAll ? 'hide' : 'show'"></button>
                </div>
                <div x-show="showAll" x-cloak class="flex flex-wrap gap-1.5">
                    <?php foreach ($info['extensions'] as $ext): ?>
                        <span class="rounded bg-slate-800 text-slate-300 px-2 py-0.5 text-xs mono"><?= e($ext) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Install / enable / disable -->
            <form method="post" action="/php/ext" class="space-y-2">
                <?= Csrf::field() ?>
                <input type="hidden" name="php" value="<?= e($version) ?>">
                <div class="flex gap-2">
                    <input name="name" required list="ext-suggestions" placeholder="extension, e.g. imagick"
                           pattern="[a-z0-9_]{2,30}"
                           class="flex-1 rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-xs mono focus:border-sky-500 focus:outline-none">
                    <select name="action"
                            class="rounded-lg bg-slate-950 border border-slate-700 px-2 py-2 text-xs focus:border-sky-500 focus:outline-none">
                        <option value="install">install</option>
                        <option value="enable">enable</option>
                        <option value="disable">disable</option>
                    </select>
                    <button class="rounded-lg bg-sky-500 hover:bg-sky-400 text-white text-xs px-3">Go</button>
                </div>
            </form>
        </div>
    <?php endforeach; ?>
</div>

<datalist id="ext-suggestions">
    <?php foreach ($suggested as $ext): ?>
        <option value="<?= e($ext) ?>"></option>
    <?php endforeach; ?>
</datalist>

<div class="bg-slate-900/50 border border-slate-800 rounded-xl p-5 text-xs text-slate-500 leading-relaxed">
    <strong class="text-slate-400">install</strong> fetches the apt package (php<em>X.Y</em>-<em>name</em>)
    and enables it · <strong class="text-slate-400">enable/disable</strong> toggle an already-installed
    extension. The matching PHP-FPM service restarts automatically. Per-site PHP version and ini limits
    are on each site's detail page (Sites → domain).
</div>
