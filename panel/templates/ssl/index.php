<?php use Panel\Support\Csrf; ?>
<div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-800 flex items-center justify-between">
        <h2 class="text-sm font-medium text-white">Certificates (Let's Encrypt)</h2>
        <span class="text-xs text-slate-500">Auto-renewal runs twice daily via certbot's systemd timer</span>
    </div>

    <?php if (empty($certs)): ?>
        <div class="p-10 text-center text-slate-500 text-sm">
            No certificates yet. Issue one from the Sites page ("Issue SSL"), or in dev mode this list is empty.
        </div>
    <?php else: ?>
        <table class="w-full text-sm">
            <thead>
            <tr class="text-left text-xs text-slate-500 border-b border-slate-800">
                <th class="px-5 py-3 font-medium">Certificate</th>
                <th class="px-5 py-3 font-medium">Domains</th>
                <th class="px-5 py-3 font-medium">Expires</th>
                <th class="px-5 py-3 font-medium">Status</th>
                <th class="px-5 py-3 font-medium text-right">Actions</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/70">
            <?php foreach ($certs as $cert): ?>
                <tr x-data="{ confirmDelete: false }">
                    <td class="px-5 py-3 text-slate-200 mono text-xs"><?= e($cert['name']) ?></td>
                    <td class="px-5 py-3 text-slate-400 text-xs"><?= e($cert['domains']) ?></td>
                    <td class="px-5 py-3 text-slate-400 text-xs"><?= e($cert['expiry']) ?></td>
                    <td class="px-5 py-3">
                        <?php $valid = str_contains(strtoupper((string) $cert['status']), 'VALID'); ?>
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs <?= $valid
                            ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400' ?>">
                            <?= e($cert['status']) ?>
                        </span>
                    </td>
                    <td class="px-5 py-3 text-right text-xs whitespace-nowrap">
                        <form method="post" action="/ssl/renew" class="inline">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="domain" value="<?= e($cert['name']) ?>">
                            <button class="text-sky-400 hover:underline mr-3">Renew</button>
                        </form>
                        <button @click="confirmDelete = !confirmDelete"
                                class="text-red-400/80 hover:text-red-300">Delete</button>
                        <form x-show="confirmDelete" x-cloak method="post" action="/ssl/delete"
                              class="mt-2 flex items-center gap-2 justify-end">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="domain" value="<?= e($cert['name']) ?>">
                            <input name="confirm_domain" placeholder="type name to confirm" required
                                   class="rounded bg-slate-950 border border-slate-700 px-2 py-1 text-xs w-44 focus:border-red-500 focus:outline-none">
                            <button class="rounded bg-red-500/80 hover:bg-red-500 text-white text-xs px-2 py-1">Confirm</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php if (!empty($sites)): ?>
    <div class="bg-slate-900/50 border border-slate-800 rounded-xl p-5 text-sm text-slate-400">
        Sites without SSL:
        <?php foreach ($sites as $s): ?>
            <a href="/sites" class="text-sky-400 hover:underline mr-2"><?= e($s['domain']) ?></a>
        <?php endforeach; ?>
        — issue certificates from the Sites page.
    </div>
<?php endif; ?>
