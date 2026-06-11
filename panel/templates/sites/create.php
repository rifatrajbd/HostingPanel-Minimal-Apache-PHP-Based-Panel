<?php use Panel\Support\Csrf; ?>
<div class="max-w-lg">
    <form method="post" action="/sites" class="bg-slate-900 border border-slate-800 rounded-xl p-6 space-y-5">
        <?= Csrf::field() ?>
        <div>
            <label class="block text-xs font-medium text-slate-400 mb-1.5" for="domain">Domain</label>
            <input id="domain" name="domain" type="text" required autofocus placeholder="forum.example.com"
                   pattern="[a-zA-Z0-9.-]+"
                   class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
            <p class="text-xs text-slate-600 mt-1.5">
                Point this domain's A record to this server's IP before issuing SSL.
            </p>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-400 mb-1.5" for="php_version">PHP version</label>
            <select id="php_version" name="php_version"
                    class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                <?php foreach ($phpVersions as $version): ?>
                    <option value="<?= e($version) ?>" <?= $version === '8.1' ? 'selected' : '' ?>>
                        PHP <?= e($version) ?><?= $version === '7.4' ? ' (end-of-life — use only if required)' : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex gap-3">
            <button class="rounded-lg bg-sky-500 hover:bg-sky-400 text-white text-sm font-medium px-5 py-2.5 transition-colors">
                Create site
            </button>
            <a href="/sites" class="rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm font-medium px-5 py-2.5 transition-colors">
                Cancel
            </a>
        </div>
    </form>
</div>
