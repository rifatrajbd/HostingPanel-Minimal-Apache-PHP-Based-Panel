<?php use Panel\Support\Csrf; ?>
<form method="post" action="/files/save" class="space-y-3">
    <?= Csrf::field() ?>
    <input type="hidden" name="site" value="<?= (int) $site['id'] ?>">
    <input type="hidden" name="path" value="<?= e($path) ?>">

    <div class="flex items-center justify-between">
        <div class="text-sm text-slate-400 mono"><?= e($site['domain']) ?>:<?= e($path) ?></div>
        <div class="flex gap-2">
            <a href="/files?site=<?= (int) $site['id'] ?>&amp;path=<?= rawurlencode(dirname($path)) ?>"
               class="rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-sm px-4 py-2">Back</a>
            <button class="rounded-lg bg-sky-500 hover:bg-sky-400 text-white text-sm font-medium px-5 py-2">
                Save
            </button>
        </div>
    </div>

    <textarea name="content" rows="28" spellcheck="false"
              class="w-full rounded-xl bg-slate-950 border border-slate-800 p-4 text-sm mono text-slate-200 leading-relaxed focus:border-sky-500 focus:outline-none"><?= e($content) ?></textarea>
</form>
