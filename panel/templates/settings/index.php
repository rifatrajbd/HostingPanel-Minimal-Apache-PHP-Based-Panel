<?php use Panel\Support\Csrf; ?>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <!-- Panel domain -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 space-y-4">
        <h2 class="text-sm font-medium text-white">Panel domain</h2>
        <?php if ($panelDomain !== ''): ?>
            <p class="text-sm text-emerald-400">
                ✓ Panel served at <span class="mono">https://<?= e($panelDomain) ?>:8443</span> with a trusted certificate.
            </p>
        <?php else: ?>
            <p class="text-sm text-slate-400 leading-relaxed">
                Give the panel its own domain (e.g. <span class="mono">panel.example.com</span>) and a real
                Let's Encrypt certificate, instead of the IP + self-signed cert.
                <strong class="text-slate-300">First</strong> point the domain's A record at this server.
            </p>
        <?php endif; ?>
        <form method="post" action="/settings/panel-domain" class="flex gap-2">
            <?= Csrf::field() ?>
            <input name="domain" required placeholder="panel.example.com" pattern="[a-zA-Z0-9.-]+"
                   value="<?= e($panelDomain) ?>"
                   class="flex-1 rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
            <button class="rounded-lg bg-sky-500 hover:bg-sky-400 text-white text-sm font-medium px-4">
                <?= $panelDomain !== '' ? 'Update' : 'Set up' ?>
            </button>
        </form>

        <div class="pt-4 border-t border-slate-800 space-y-3">
            <h2 class="text-sm font-medium text-white">Panel update</h2>
            <p class="text-sm text-slate-400">
                Pulls the latest code from your GitHub repo and redeploys the panel.
                Sites, mail and data are not touched.
            </p>
            <form method="post" action="/settings/self-update" x-data
                  @submit="if (!confirm('Update the panel to the latest version from the repo?')) $event.preventDefault()">
                <?= Csrf::field() ?>
                <button class="rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-sm px-5 py-2.5">
                    Update panel now
                </button>
            </form>
        </div>
    </div>

    <!-- Backup status / actions -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 space-y-4">
        <h2 class="text-sm font-medium text-white">Backup actions</h2>
        <p class="text-sm text-slate-400">
            <?php if ($backup['type'] !== ''): ?>
                Remote: <span class="text-slate-200"><?= e($backup['type'] === 'ftp' ? 'FTP — ' . $backup['host'] : 'Google Drive') ?></span>
                · path <span class="mono text-slate-300"><?= e($backup['path']) ?></span>
                · keep <?= e($backup['retention']) ?> · schedule <span class="text-slate-200"><?= e($backup['schedule']) ?></span>
            <?php else: ?>
                No backup remote configured yet — set one up below.
            <?php endif; ?>
        </p>
        <div class="flex gap-2">
            <form method="post" action="/settings/backup/test">
                <?= Csrf::field() ?>
                <button class="rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-sm px-4 py-2">Test connection</button>
            </form>
            <form method="post" action="/settings/backup/run">
                <?= Csrf::field() ?>
                <button class="rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-sm px-4 py-2">Run backup now</button>
            </form>
        </div>
        <?php if ($backupLog !== ''): ?>
            <div>
                <div class="text-xs text-slate-500 mb-1">Backup log (latest)</div>
                <pre class="bg-slate-950 border border-slate-800 rounded-lg p-3 text-xs text-slate-400 overflow-x-auto max-h-48 overflow-y-auto mono"><?= e($backupLog) ?></pre>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Backup configuration -->
<div class="bg-slate-900 border border-slate-800 rounded-xl p-6 space-y-4"
     x-data="{ type: '<?= e($backup['type'] !== '' ? $backup['type'] : 'ftp') ?>' }">
    <h2 class="text-sm font-medium text-white">Automatic backups
        <span class="text-slate-500 font-normal">— site files + all MySQL databases + panel data</span></h2>

    <form method="post" action="/settings/backup" class="space-y-4">
        <?= Csrf::field() ?>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label class="block text-xs font-medium text-slate-400 mb-1.5">Destination</label>
                <select name="type" x-model="type"
                        class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                    <option value="ftp">FTP server</option>
                    <option value="drive">Google Drive</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-400 mb-1.5">Schedule</label>
                <select name="schedule"
                        class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                    <?php foreach (['daily' => 'Daily 03:00', 'twice-daily' => 'Twice daily', 'weekly' => 'Weekly (Sun)', 'disabled' => 'Disabled'] as $value => $label): ?>
                        <option value="<?= $value ?>" <?= $backup['schedule'] === $value ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-400 mb-1.5">Keep last N backups</label>
                <input name="retention" type="number" min="1" max="365" value="<?= e($backup['retention']) ?>"
                       class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-400 mb-1.5">Remote folder</label>
                <input name="path" value="<?= e($backup['path']) ?>"
                       class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm mono focus:border-sky-500 focus:outline-none">
            </div>
        </div>

        <!-- FTP fields -->
        <div x-show="type === 'ftp'" class="grid grid-cols-3 gap-3">
            <input name="host" placeholder="ftp.example.com" value="<?= e($backup['host']) ?>"
                   class="rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
            <input name="user" placeholder="ftp username" value="<?= e($backup['user']) ?>"
                   class="rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
            <input name="pass" type="password" placeholder="ftp password (stored on server only)"
                   autocomplete="new-password"
                   class="rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
        </div>

        <!-- Google Drive fields -->
        <div x-show="type === 'drive'" x-cloak class="space-y-2">
            <textarea name="token" rows="3"
                      placeholder='Paste the token JSON here — on your own PC run:  rclone authorize "drive"'
                      class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-xs mono focus:border-sky-500 focus:outline-none"></textarea>
            <p class="text-xs text-slate-600">
                Install rclone on your PC (rclone.org/downloads), run
                <code class="text-slate-500">rclone authorize "drive"</code>, sign in to Google in the
                browser window, then paste the JSON it prints ({"access_token": …}).
            </p>
        </div>

        <button class="rounded-lg bg-sky-500 hover:bg-sky-400 text-white text-sm font-medium px-5 py-2.5">
            Save backup settings
        </button>
    </form>
</div>
