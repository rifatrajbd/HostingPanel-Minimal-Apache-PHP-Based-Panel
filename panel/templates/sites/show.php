<?php

use Panel\Support\Csrf;

$iniValue = static fn (string $key, string $default): string => (string) ($ini[$key] ?? $default);
?>
<div class="flex items-center gap-3 text-sm">
    <a href="/sites" class="text-slate-400 hover:text-sky-400">← All sites</a>
    <span class="text-slate-600">/</span>
    <a href="https://<?= e($site['domain']) ?>" target="_blank" rel="noopener"
       class="text-sky-400 hover:underline"><?= e($site['domain']) ?></a>
    <a href="/files?site=<?= (int) $site['id'] ?>"
       class="ml-auto rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs px-3 py-2">Open in File Manager</a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <!-- PHP version + ini -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 space-y-4">
        <h2 class="text-sm font-medium text-white">PHP settings</h2>
        <form method="post" action="/sites/<?= (int) $site['id'] ?>/php" class="space-y-3">
            <?= Csrf::field() ?>
            <div>
                <label class="block text-xs font-medium text-slate-400 mb-1.5">PHP version</label>
                <select name="php_version"
                        class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                    <?php foreach ($phpVersions as $version): ?>
                        <option value="<?= e($version) ?>" <?= $version === $site['php_version'] ? 'selected' : '' ?>>
                            PHP <?= e($version) ?><?= $version === '7.4' ? ' (end-of-life)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <?php
                $fields = [
                    'memory_limit' => ['Memory limit', '256M'],
                    'upload_max_filesize' => ['Upload max size', '64M'],
                    'post_max_size' => ['POST max size', '64M'],
                    'max_execution_time' => ['Max execution (s)', '120'],
                ];
                foreach ($fields as $key => [$label, $default]): ?>
                    <div>
                        <label class="block text-xs font-medium text-slate-400 mb-1.5"><?= e($label) ?></label>
                        <input name="<?= e($key) ?>" value="<?= e($iniValue($key, $default)) ?>"
                               class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm mono focus:border-sky-500 focus:outline-none">
                    </div>
                <?php endforeach; ?>
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-400">
                <input type="checkbox" name="display_errors" value="on"
                       <?= $iniValue('display_errors', 'off') === 'on' ? 'checked' : '' ?> class="accent-sky-500">
                display_errors (debugging only — never leave on in production)
            </label>
            <button class="rounded-lg bg-sky-500 hover:bg-sky-400 text-white text-sm font-medium px-5 py-2.5">
                Apply PHP settings
            </button>
        </form>
    </div>

    <!-- Cloudflare-only access -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 space-y-4">
        <h2 class="text-sm font-medium text-white">Cloudflare-only access</h2>
        <?php if ((int) $site['cf_only'] === 1): ?>
            <p class="text-sm text-emerald-400">✓ Enabled — only Cloudflare's IP ranges can reach this site.</p>
            <form method="post" action="/sites/<?= (int) $site['id'] ?>/cfonly">
                <?= Csrf::field() ?>
                <input type="hidden" name="enable" value="0">
                <button class="rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-sm px-5 py-2.5">
                    Disable
                </button>
            </form>
        <?php else: ?>
            <p class="text-sm text-slate-400 leading-relaxed">
                Blocks direct-to-server requests so visitors can only reach this site through
                Cloudflare. <strong class="text-slate-300">Requirements:</strong> the domain must be
                on Cloudflare with the proxy (orange cloud) enabled — otherwise the site goes down.
                The real visitor IP is restored from <code>CF-Connecting-IP</code>.
            </p>
            <form method="post" action="/sites/<?= (int) $site['id'] ?>/cfonly">
                <?= Csrf::field() ?>
                <input type="hidden" name="enable" value="1">
                <button class="rounded-lg bg-amber-500 hover:bg-amber-400 text-white text-sm font-medium px-5 py-2.5">
                    Enable Cloudflare-only
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Cron jobs -->
<div class="bg-slate-900 border border-slate-800 rounded-xl p-6 space-y-4">
    <h2 class="text-sm font-medium text-white">Cron jobs <span class="text-slate-500 font-normal">— run as this site's user</span></h2>

    <form method="post" action="/sites/<?= (int) $site['id'] ?>/cron" class="flex flex-wrap gap-2">
        <?= Csrf::field() ?>
        <input name="schedule" required placeholder="*/15 * * * *"
               class="w-40 rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm mono focus:border-sky-500 focus:outline-none">
        <input name="command" required
               placeholder="php <?= e($site['doc_root']) ?>/cron.php"
               class="flex-1 min-w-64 rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm mono focus:border-sky-500 focus:outline-none">
        <button class="rounded-lg bg-sky-500 hover:bg-sky-400 text-white text-sm font-medium px-4">Add job</button>
    </form>
    <p class="text-xs text-slate-600">
        Examples: <code class="text-slate-500">*/5 * * * *</code> every 5 min ·
        <code class="text-slate-500">0 3 * * *</code> daily at 03:00 ·
        <code class="text-slate-500">0 */6 * * *</code> every 6 hours.
        MyBB tip: <code class="text-slate-500">php <?= e($site['doc_root']) ?>/task.php</code>
    </p>

    <?php if (empty($cronJobs)): ?>
        <p class="text-sm text-slate-500">No cron jobs yet.</p>
    <?php else: ?>
        <table class="w-full text-sm">
            <tbody class="divide-y divide-slate-800/70">
            <?php foreach ($cronJobs as $job): ?>
                <tr>
                    <td class="py-2.5 pr-4 mono text-sky-300 text-xs whitespace-nowrap"><?= e($job['schedule']) ?></td>
                    <td class="py-2.5 mono text-slate-300 text-xs"><?= e($job['command']) ?></td>
                    <td class="py-2.5 text-right">
                        <form method="post"
                              action="/sites/<?= (int) $site['id'] ?>/cron/<?= (int) $job['id'] ?>/delete"
                              class="inline" x-data
                              @submit="if (!confirm('Remove this cron job?')) $event.preventDefault()">
                            <?= Csrf::field() ?>
                            <button class="text-xs text-red-400/80 hover:text-red-300">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="bg-slate-900/50 border border-slate-800 rounded-xl p-5 text-xs text-slate-500 leading-relaxed">
    <strong class="text-slate-400">Site info:</strong>
    document root <code class="text-slate-400"><?= e($site['doc_root']) ?></code> ·
    system user <code class="text-slate-400"><?= e($site['system_user']) ?></code> ·
    SSL <?= (int) $site['ssl_enabled'] === 1 ? 'active (manage on the SSL page)' : 'not issued yet (Sites page → Issue SSL)' ?> ·
    created <?= e(date('M j, Y', (int) $site['created_at'])) ?>
</div>
