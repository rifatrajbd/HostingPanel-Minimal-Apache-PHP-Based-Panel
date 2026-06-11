<?php use Panel\Support\Csrf; ?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <!-- Create form -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl p-6">
        <h2 class="text-sm font-medium text-white mb-4">Create database</h2>
        <form method="post" action="/databases" class="space-y-4">
            <?= Csrf::field() ?>
            <div>
                <label class="block text-xs font-medium text-slate-400 mb-1.5" for="name">Database name</label>
                <input id="name" name="name" type="text" required placeholder="mybb"
                       pattern="[a-z][a-z0-9_]{2,31}"
                       class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-400 mb-1.5" for="db_user">Database user</label>
                <input id="db_user" name="db_user" type="text" required placeholder="mybb_user"
                       pattern="[a-z][a-z0-9_]{2,31}"
                       class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
            </div>
            <p class="text-xs text-slate-600">A strong password is generated automatically and shown once.</p>
            <button class="w-full rounded-lg bg-sky-500 hover:bg-sky-400 text-white text-sm font-medium py-2.5 transition-colors">
                Create
            </button>
        </form>
    </div>

    <!-- List -->
    <div class="lg:col-span-2 bg-slate-900 border border-slate-800 rounded-xl overflow-hidden">
        <?php if (empty($databases)): ?>
            <div class="p-10 text-center text-slate-500 text-sm">No databases yet.</div>
        <?php else: ?>
            <table class="w-full text-sm">
                <thead>
                <tr class="text-left text-xs text-slate-500 border-b border-slate-800">
                    <th class="px-5 py-3 font-medium">Database</th>
                    <th class="px-5 py-3 font-medium">User</th>
                    <th class="px-5 py-3 font-medium">Created</th>
                    <th class="px-5 py-3 font-medium text-right">Actions</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/70">
                <?php foreach ($databases as $row): ?>
                    <tr x-data="{ confirmDelete: false }">
                        <td class="px-5 py-3 text-slate-200 mono"><?= e($row['name']) ?></td>
                        <td class="px-5 py-3 text-slate-400 mono"><?= e($row['db_user']) ?></td>
                        <td class="px-5 py-3 text-slate-500 text-xs"><?= e(date('M j, Y', (int) $row['created_at'])) ?></td>
                        <td class="px-5 py-3 text-right">
                            <button @click="confirmDelete = !confirmDelete"
                                    class="text-xs text-red-400/80 hover:text-red-300">Delete</button>
                            <form x-show="confirmDelete" x-cloak method="post"
                                  action="/databases/<?= (int) $row['id'] ?>/delete"
                                  class="mt-2 flex items-center gap-2 justify-end">
                                <?= Csrf::field() ?>
                                <input name="confirm_name" placeholder="type name to confirm" required
                                       class="rounded bg-slate-950 border border-slate-700 px-2 py-1 text-xs w-40 focus:border-red-500 focus:outline-none">
                                <button class="rounded bg-red-500/80 hover:bg-red-500 text-white text-xs px-2 py-1">
                                    Confirm
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div class="bg-slate-900/50 border border-slate-800 rounded-xl p-5 text-xs text-slate-500 leading-relaxed">
    Connect from your site with host <code class="text-slate-400">localhost</code>, port
    <code class="text-slate-400">3306</code>. Database users are restricted to
    <code class="text-slate-400">localhost</code> — they are not reachable from the internet.
    Browse and edit data with <a href="/phpmyadmin/" target="_blank" rel="noopener"
        class="text-sky-400 hover:underline">phpMyAdmin</a> — log in with the database user and password.
</div>
