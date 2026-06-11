<?php use Panel\Support\Csrf; ?>
<div class="flex justify-end">
    <a href="/sites/create"
       class="rounded-lg bg-sky-500 hover:bg-sky-400 text-white text-sm font-medium px-4 py-2 transition-colors">
        + Add site
    </a>
</div>

<?php if (empty($sites)): ?>
    <div class="bg-slate-900 border border-slate-800 rounded-xl p-10 text-center text-slate-500 text-sm">
        No sites yet. Click "Add site" to create your first one.
    </div>
<?php else: ?>
    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden">
        <table class="w-full text-sm">
            <thead>
            <tr class="text-left text-xs text-slate-500 border-b border-slate-800">
                <th class="px-5 py-3 font-medium">Domain</th>
                <th class="px-5 py-3 font-medium">PHP</th>
                <th class="px-5 py-3 font-medium">SSL</th>
                <th class="px-5 py-3 font-medium">Document root</th>
                <th class="px-5 py-3 font-medium text-right">Actions</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/70">
            <?php foreach ($sites as $site): ?>
                <tr x-data="{ confirmDelete: false }">
                    <td class="px-5 py-3">
                        <a href="https://<?= e($site['domain']) ?>" target="_blank" rel="noopener"
                           class="text-sky-400 hover:underline"><?= e($site['domain']) ?></a>
                    </td>
                    <td class="px-5 py-3 text-slate-300"><?= e($site['php_version']) ?></td>
                    <td class="px-5 py-3">
                        <?php if ((int) $site['ssl_enabled'] === 1): ?>
                            <span class="inline-flex items-center rounded-full bg-emerald-500/10 text-emerald-400 px-2 py-0.5 text-xs">Active</span>
                        <?php else: ?>
                            <form method="post" action="/sites/<?= (int) $site['id'] ?>/ssl" class="inline-flex items-center gap-2">
                                <?= Csrf::field() ?>
                                <label class="text-xs text-slate-500 inline-flex items-center gap-1">
                                    <input type="checkbox" name="include_www" value="1" class="accent-sky-500"> www
                                </label>
                                <button class="rounded bg-slate-800 hover:bg-slate-700 text-xs px-2 py-1 text-slate-200">
                                    Issue SSL
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3 text-slate-500 mono text-xs"><?= e($site['doc_root']) ?></td>
                    <td class="px-5 py-3 text-right">
                        <a href="/sites/<?= (int) $site['id'] ?>"
                           class="text-xs text-sky-400 hover:underline mr-3">Manage</a>
                        <button @click="confirmDelete = !confirmDelete"
                                class="text-xs text-red-400/80 hover:text-red-300">Delete</button>
                        <form x-show="confirmDelete" x-cloak method="post"
                              action="/sites/<?= (int) $site['id'] ?>/delete"
                              class="mt-2 flex items-center gap-2 justify-end">
                            <?= Csrf::field() ?>
                            <input name="confirm_domain" placeholder="type domain to confirm" required
                                   class="rounded bg-slate-950 border border-slate-700 px-2 py-1 text-xs w-44 focus:border-red-500 focus:outline-none">
                            <button class="rounded bg-red-500/80 hover:bg-red-500 text-white text-xs px-2 py-1">
                                Confirm
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
