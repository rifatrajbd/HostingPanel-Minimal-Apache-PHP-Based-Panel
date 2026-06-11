<?php use Panel\Support\Csrf; ?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'HostingPanel') ?> · HostingPanel</title>
    <script src="/assets/tailwind.js"></script>
    <script src="/assets/app.js"></script>
    <script src="/assets/alpine.min.js" defer></script>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="bg-slate-950 text-slate-200 min-h-screen antialiased">
<div class="flex min-h-screen">
    <!-- Sidebar -->
    <aside class="w-60 shrink-0 bg-slate-900 border-r border-slate-800 flex flex-col">
        <div class="px-5 py-5 border-b border-slate-800">
            <a href="/" class="text-lg font-semibold text-white tracking-tight">
                <span class="text-sky-400">Hosting</span>Panel
            </a>
        </div>
        <nav class="flex-1 px-3 py-4 space-y-1 text-sm">
            <?php
            $nav = [
                ['dashboard', '/', 'Dashboard', 'M3 12l9-9 9 9M5 10v10a1 1 0 001 1h4v-6h4v6h4a1 1 0 001-1V10'],
                ['sites', '/sites', 'Sites', 'M21 12a9 9 0 11-18 0 9 9 0 0118 0zM3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 010 18M12 3a15 15 0 000 18'],
                ['files', '/files', 'Files', 'M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z'],
                ['databases', '/databases', 'Databases', 'M4 6c0-1.7 3.6-3 8-3s8 1.3 8 3-3.6 3-8 3-8-1.3-8-3zm0 0v12c0 1.7 3.6 3 8 3s8-1.3 8-3V6M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3'],
                ['mail', '/mail', 'Mail', 'M3 8l9 6 9-6M4 6h16a1 1 0 011 1v10a1 1 0 01-1 1H4a1 1 0 01-1-1V7a1 1 0 011-1z'],
                ['ssl', '/ssl', 'SSL', 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4'],
                ['php', '/php', 'PHP', 'M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4'],
                ['security', '/security', 'Security', 'M12 3l8 4v5c0 5-3.4 8.4-8 9-4.6-.6-8-4-8-9V7l8-4z'],
                ['settings', '/settings', 'Settings', 'M10.3 4.3a1.7 1.7 0 013.4 0 1.7 1.7 0 002.6 1.1 1.7 1.7 0 012.4 2.4 1.7 1.7 0 001 2.5 1.7 1.7 0 010 3.4 1.7 1.7 0 00-1 2.5 1.7 1.7 0 01-2.4 2.4 1.7 1.7 0 00-2.6 1 1.7 1.7 0 01-3.4 0 1.7 1.7 0 00-2.6-1 1.7 1.7 0 01-2.4-2.4 1.7 1.7 0 00-1-2.5 1.7 1.7 0 010-3.4 1.7 1.7 0 001-2.5 1.7 1.7 0 012.4-2.4 1.7 1.7 0 002.6-1.1zM15 12a3 3 0 11-6 0 3 3 0 016 0z'],
            ];
            foreach ($nav as [$key, $href, $label, $icon]): ?>
                <a href="<?= e($href) ?>"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg <?= ($active ?? '') === $key
                       ? 'bg-sky-500/10 text-sky-400 font-medium'
                       : 'text-slate-400 hover:text-slate-100 hover:bg-slate-800/60' ?>">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8"
                         viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round">
                        <path d="<?= e($icon) ?>"/>
                    </svg>
                    <?= e($label) ?>
                </a>
            <?php endforeach; ?>
            <div class="pt-3 mt-3 border-t border-slate-800/70">
                <div class="px-3 pb-1 text-[10px] uppercase tracking-wider text-slate-600">Tools</div>
                <a href="/phpmyadmin/" target="_blank" rel="noopener"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-400 hover:text-slate-100 hover:bg-slate-800/60">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"
                         stroke-linecap="round" stroke-linejoin="round"><path d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    phpMyAdmin
                </a>
                <a href="/webmail/" target="_blank" rel="noopener"
                   class="flex items-center gap-3 px-3 py-2 rounded-lg text-slate-400 hover:text-slate-100 hover:bg-slate-800/60">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"
                         stroke-linecap="round" stroke-linejoin="round"><path d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    Webmail
                </a>
            </div>
        </nav>
        <div class="px-3 py-4 border-t border-slate-800">
            <form method="post" action="/logout">
                <?= Csrf::field() ?>
                <button class="w-full text-left flex items-center gap-3 px-3 py-2 rounded-lg text-sm text-slate-400 hover:text-red-400 hover:bg-slate-800/60">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H6a2 2 0 01-2-2V7a2 2 0 012-2h5a2 2 0 012 2v1"/>
                    </svg>
                    Sign out
                </button>
            </form>
        </div>
    </aside>

    <!-- Main -->
    <main class="flex-1 min-w-0">
        <header class="px-8 py-5 border-b border-slate-800/70 flex items-center justify-between">
            <h1 class="text-xl font-semibold text-white"><?= e($title ?? '') ?></h1>
            <span class="text-xs text-slate-500"><?= e(php_uname('n')) ?></span>
        </header>

        <div class="px-8 py-6 space-y-6">
            <?php foreach (($flash ?? []) as $msg): ?>
                <div class="rounded-lg px-4 py-3 text-sm border <?= $msg['type'] === 'success'
                    ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300'
                    : 'bg-red-500/10 border-red-500/30 text-red-300' ?>">
                    <?= e($msg['message']) ?>
                </div>
            <?php endforeach; ?>

            <?= $content ?? '' ?>
        </div>
    </main>
</div>
</body>
</html>
