<?php use Panel\Support\Csrf; ?>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <!-- Change password -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 space-y-4">
        <h2 class="text-sm font-medium text-white">Change password</h2>
        <form method="post" action="/security/password" class="space-y-3">
            <?= Csrf::field() ?>
            <input name="current_password" type="password" required placeholder="Current password"
                   autocomplete="current-password"
                   class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
            <input name="new_password" type="password" required placeholder="New password (min 12 chars)"
                   minlength="12" autocomplete="new-password"
                   class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
            <input name="confirm_password" type="password" required placeholder="Repeat new password"
                   minlength="12" autocomplete="new-password"
                   class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
            <button class="rounded-lg bg-sky-500 hover:bg-sky-400 text-white text-sm font-medium px-5 py-2.5 transition-colors">
                Update password
            </button>
        </form>
    </div>

    <!-- 2FA -->
    <div class="bg-slate-900 border border-slate-800 rounded-xl p-6 space-y-4">
        <h2 class="text-sm font-medium text-white">Two-factor authentication</h2>

        <?php if ($user !== null && (int) $user['totp_enabled'] === 1): ?>
            <p class="text-sm text-emerald-400">✓ 2FA is enabled on this account.</p>
            <form method="post" action="/security/2fa/disable" class="flex gap-2">
                <?= Csrf::field() ?>
                <input name="password" type="password" required placeholder="Account password"
                       class="flex-1 rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm focus:border-red-500 focus:outline-none">
                <button class="rounded-lg bg-red-500/80 hover:bg-red-500 text-white text-sm font-medium px-4 transition-colors">
                    Disable
                </button>
            </form>

        <?php elseif ($enrollSecret !== null): ?>
            <p class="text-sm text-slate-400">
                Add this secret to your authenticator app (Google Authenticator, Aegis, 1Password…),
                then enter the 6-digit code to confirm.
            </p>
            <div class="bg-slate-950 border border-slate-800 rounded-lg p-4 space-y-2">
                <div class="text-xs text-slate-500">Secret (manual entry)</div>
                <div class="mono text-sm text-sky-300 break-all"><?= e($enrollSecret) ?></div>
                <div class="text-xs text-slate-500 pt-2">otpauth URI</div>
                <div class="mono text-xs text-slate-400 break-all"><?= e($enrollUri) ?></div>
            </div>
            <form method="post" action="/security/2fa/confirm" class="flex gap-2">
                <?= Csrf::field() ?>
                <input name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required
                       placeholder="000000" autocomplete="one-time-code"
                       class="flex-1 rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-center tracking-[0.4em] focus:border-sky-500 focus:outline-none">
                <button class="rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white text-sm font-medium px-4 transition-colors">
                    Confirm & enable
                </button>
            </form>

        <?php else: ?>
            <p class="text-sm text-slate-400">
                Protect your panel login with a time-based one-time code. Strongly recommended —
                this panel controls your whole server.
            </p>
            <form method="post" action="/security/2fa/start">
                <?= Csrf::field() ?>
                <button class="rounded-lg bg-sky-500 hover:bg-sky-400 text-white text-sm font-medium px-5 py-2.5 transition-colors">
                    Set up 2FA
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Audit log -->
<div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-800">
        <h2 class="text-sm font-medium text-white">Audit log</h2>
    </div>
    <?php if (empty($auditLog)): ?>
        <div class="p-8 text-center text-slate-500 text-sm">No entries yet.</div>
    <?php else: ?>
        <table class="w-full text-sm">
            <tbody class="divide-y divide-slate-800/70">
            <?php foreach ($auditLog as $row): ?>
                <tr>
                    <td class="px-5 py-2.5 text-sky-400 text-xs mono"><?= e($row['action']) ?></td>
                    <td class="px-5 py-2.5 text-slate-300"><?= e($row['details']) ?></td>
                    <td class="px-5 py-2.5 text-slate-500 text-xs mono"><?= e($row['ip']) ?></td>
                    <td class="px-5 py-2.5 text-slate-500 text-xs text-right whitespace-nowrap">
                        <?= e(date('M j, Y H:i', (int) $row['created_at'])) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
