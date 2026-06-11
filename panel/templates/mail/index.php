<?php use Panel\Support\Csrf; ?>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <!-- Mail domains -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 space-y-4">
        <h2 class="text-sm font-medium text-white">Mail domains</h2>

        <form method="post" action="/mail/domains" class="flex gap-2">
            <?= Csrf::field() ?>
            <input name="domain" type="text" required placeholder="example.com"
                   pattern="[a-zA-Z0-9.-]+"
                   class="flex-1 rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
            <button class="rounded-lg bg-sky-500 hover:bg-sky-400 text-white text-sm font-medium px-4 transition-colors">
                Add
            </button>
        </form>

        <?php if (empty($domains)): ?>
            <p class="text-sm text-slate-500">No mail domains yet.</p>
        <?php else: ?>
            <ul class="divide-y divide-slate-800/70">
                <?php foreach ($domains as $d): ?>
                    <li class="py-3" x-data="{ showDns: false, confirmDelete: false }">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-200"><?= e($d['domain']) ?></span>
                            <span class="flex gap-3 text-xs">
                                <button @click="showDns = !showDns" class="text-sky-400 hover:underline">DNS records</button>
                                <button @click="confirmDelete = !confirmDelete" class="text-red-400/80 hover:text-red-300">Delete</button>
                            </span>
                        </div>
                        <pre x-show="showDns" x-cloak
                             class="mt-2 bg-slate-950 border border-slate-800 rounded-lg p-3 text-xs text-slate-400 overflow-x-auto mono"><?= e($d['dkim_dns'] ?: 'DNS info not recorded.') ?></pre>
                        <form x-show="confirmDelete" x-cloak method="post"
                              action="/mail/domains/<?= (int) $d['id'] ?>/delete"
                              class="mt-2 flex items-center gap-2">
                            <?= Csrf::field() ?>
                            <input name="confirm_domain" placeholder="type domain to confirm" required
                                   class="rounded bg-slate-950 border border-slate-700 px-2 py-1 text-xs flex-1 focus:border-red-500 focus:outline-none">
                            <button class="rounded bg-red-500/80 hover:bg-red-500 text-white text-xs px-2 py-1">
                                Confirm — deletes all mailboxes
                            </button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <!-- Mailboxes -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 space-y-4">
        <h2 class="text-sm font-medium text-white">Mailboxes</h2>

        <form method="post" action="/mail/mailboxes" class="space-y-2">
            <?= Csrf::field() ?>
            <div class="flex gap-2">
                <input name="address" type="email" required placeholder="admin@example.com"
                       class="flex-1 rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                <input name="password" type="password" placeholder="password (blank = generate)"
                       autocomplete="new-password"
                       class="flex-1 rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
                <button class="rounded-lg bg-sky-500 hover:bg-sky-400 text-white text-sm font-medium px-4 transition-colors">
                    Add
                </button>
            </div>
        </form>

        <?php if (empty($mailboxes)): ?>
            <p class="text-sm text-slate-500">No mailboxes yet.</p>
        <?php else: ?>
            <ul class="divide-y divide-slate-800/70">
                <?php foreach ($mailboxes as $m): ?>
                    <li class="py-3 flex items-center justify-between" x-data="{ confirmDelete: false }">
                        <span class="text-slate-200 mono text-sm"><?= e($m['address']) ?></span>
                        <span>
                            <button x-show="!confirmDelete" @click="confirmDelete = true"
                                    class="text-xs text-red-400/80 hover:text-red-300">Delete</button>
                            <form x-show="confirmDelete" x-cloak method="post" class="inline"
                                  action="/mail/mailboxes/<?= (int) $m['id'] ?>/delete">
                                <?= Csrf::field() ?>
                                <button class="rounded bg-red-500/80 hover:bg-red-500 text-white text-xs px-2 py-1">
                                    Really delete?
                                </button>
                            </form>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<!-- Delivery queue -->
<div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-800 flex items-center justify-between">
        <h2 class="text-sm font-medium text-white">Delivery queue
            <span class="text-slate-500 font-normal">— <?= count($queue) ?> message(s) waiting</span></h2>
        <?php if (!empty($queue)): ?>
            <form method="post" action="/mail/queue/flush">
                <?= Csrf::field() ?>
                <button class="rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs px-3 py-1.5">
                    Retry all now
                </button>
            </form>
        <?php endif; ?>
    </div>
    <?php if (empty($queue)): ?>
        <div class="p-6 text-center text-slate-500 text-sm">Queue is empty — all mail delivered.</div>
    <?php else: ?>
        <table class="w-full text-sm">
            <thead>
            <tr class="text-left text-xs text-slate-500 border-b border-slate-800">
                <th class="px-5 py-2.5 font-medium">ID</th>
                <th class="px-5 py-2.5 font-medium">From</th>
                <th class="px-5 py-2.5 font-medium">To / reason</th>
                <th class="px-5 py-2.5 font-medium text-right"></th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/70">
            <?php foreach ($queue as $msg): ?>
                <tr>
                    <td class="px-5 py-2.5 mono text-xs text-sky-300"><?= e($msg['queue_id'] ?? '') ?></td>
                    <td class="px-5 py-2.5 text-xs text-slate-300"><?= e($msg['sender'] ?? '') ?></td>
                    <td class="px-5 py-2.5 text-xs text-slate-400">
                        <?php foreach (($msg['recipients'] ?? []) as $rcpt): ?>
                            <div>
                                <?= e($rcpt['address'] ?? '') ?>
                                <?php if (!empty($rcpt['delay_reason'])): ?>
                                    <span class="text-amber-400/80">— <?= e($rcpt['delay_reason']) ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </td>
                    <td class="px-5 py-2.5 text-right">
                        <form method="post" action="/mail/queue/delete" class="inline" x-data
                              @submit="if (!confirm('Delete this message from the queue?')) $event.preventDefault()">
                            <?= Csrf::field() ?>
                            <input type="hidden" name="queue_id" value="<?= e($msg['queue_id'] ?? '') ?>">
                            <button class="text-xs text-red-400/80 hover:text-red-300">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Mail log -->
<?php if ($mailLog !== ''): ?>
    <div class="bg-slate-900 border border-slate-800 rounded-xl p-5 space-y-2" x-data="{ open: false }">
        <button @click="open = !open" class="text-sm font-medium text-white">
            Mail log <span class="text-sky-400 text-xs" x-text="open ? '(hide)' : '(show latest entries)'"></span>
        </button>
        <pre x-show="open" x-cloak
             class="bg-slate-950 border border-slate-800 rounded-lg p-3 text-xs text-slate-400 overflow-x-auto max-h-80 overflow-y-auto mono"><?= e($mailLog) ?></pre>
    </div>
<?php endif; ?>

<div class="bg-slate-900/50 border border-slate-800 rounded-xl p-5 text-xs text-slate-500 leading-relaxed space-y-1">
    <p><strong class="text-slate-400">Webmail:</strong>
        read and send mail in the browser at <a href="/webmail/" target="_blank" rel="noopener"
        class="text-sky-400 hover:underline">/webmail</a> (SnappyMail — log in with the full email address).</p>
    <p><strong class="text-slate-400">Client settings:</strong>
        IMAP <code class="text-slate-400">mail.&lt;domain&gt;:993 (SSL)</code> ·
        SMTP <code class="text-slate-400">mail.&lt;domain&gt;:587 (STARTTLS)</code> ·
        login with the full email address.</p>
    <p><strong class="text-slate-400">Deliverability:</strong> set the MX, SPF, DKIM and DMARC records
        (see "DNS records" per domain) and make sure your VPS provider has set the
        <em>reverse DNS (PTR)</em> of your IP to your mail hostname.</p>
</div>
