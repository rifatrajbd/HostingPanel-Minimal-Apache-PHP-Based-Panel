<?php use Panel\Support\Csrf; ?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · HostingPanel</title>
    <script src="/assets/tailwind.js"></script>
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="bg-slate-950 text-slate-200 min-h-screen flex items-center justify-center antialiased">
<div class="w-full max-w-sm px-6">
    <div class="text-center mb-8">
        <div class="text-2xl font-semibold text-white tracking-tight">
            <span class="text-sky-400">Hosting</span>Panel
        </div>
        <p class="text-sm text-slate-500 mt-1">Sign in to manage your server</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="mb-4 rounded-lg px-4 py-3 text-sm bg-red-500/10 border border-red-500/30 text-red-300">
            <?= e($error) ?>
        </div>
    <?php endif; ?>
    <?php foreach (($flash ?? []) as $msg): ?>
        <div class="mb-4 rounded-lg px-4 py-3 text-sm bg-red-500/10 border border-red-500/30 text-red-300">
            <?= e($msg['message']) ?>
        </div>
    <?php endforeach; ?>

    <form method="post" action="/login"
          class="bg-slate-900 border border-slate-800 rounded-xl p-6 space-y-4">
        <?= Csrf::field() ?>
        <div>
            <label class="block text-xs font-medium text-slate-400 mb-1.5" for="username">Username</label>
            <input id="username" name="username" type="text" required autofocus autocomplete="username"
                   class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-400 mb-1.5" for="password">Password</label>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                   class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
        </div>
        <button class="w-full rounded-lg bg-sky-500 hover:bg-sky-400 text-white text-sm font-medium py-2.5 transition-colors">
            Sign in
        </button>
    </form>
</div>
</body>
</html>
