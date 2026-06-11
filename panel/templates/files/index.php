<?php

use Panel\Support\Csrf;

$siteId = $site !== null ? (int) $site['id'] : 0;
$enc = rawurlencode($path);
$fmtSize = static function (int $bytes): string {
    if ($bytes >= 1073741824) {
        return round($bytes / 1073741824, 1) . ' GB';
    }
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024) . ' KB';
    }
    return $bytes . ' B';
};
?>
<?php if (empty($sites)): ?>
    <div class="bg-slate-900 border border-slate-800 rounded-xl p-10 text-center text-slate-500 text-sm">
        Create a site first — the file manager works per site.
    </div>
<?php else: ?>

<div class="flex flex-wrap items-center gap-3" x-data>
    <!-- Site selector -->
    <form method="get" action="/files" x-data>
        <select name="site" @change="$el.form.submit()"
                class="rounded-lg bg-slate-900 border border-slate-700 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none">
            <?php foreach ($sites as $s): ?>
                <option value="<?= (int) $s['id'] ?>" <?= $siteId === (int) $s['id'] ? 'selected' : '' ?>>
                    <?= e($s['domain']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <!-- Breadcrumbs -->
    <nav class="text-sm text-slate-400 flex items-center gap-1 flex-wrap">
        <a href="/files?site=<?= $siteId ?>&amp;path=%2F" class="text-sky-400 hover:underline">root</a>
        <?php
        $crumb = '';
        foreach (array_filter(explode('/', $path)) as $part):
            $crumb .= '/' . $part; ?>
            <span class="text-slate-600">/</span>
            <a href="/files?site=<?= $siteId ?>&amp;path=<?= rawurlencode($crumb) ?>"
               class="text-sky-400 hover:underline"><?= e($part) ?></a>
        <?php endforeach; ?>
    </nav>
</div>

<?php if (!empty($listError)): ?>
    <div class="rounded-lg px-4 py-3 text-sm border bg-amber-500/10 border-amber-500/30 text-amber-300">
        <?= e($listError) ?>
    </div>
<?php endif; ?>

<!-- Toolbar -->
<div class="bg-slate-900 border border-slate-800 rounded-xl p-4 flex flex-wrap items-center gap-2"
     x-data="{ panel: '' }">
    <button @click="panel = panel === 'upload' ? '' : 'upload'"
            class="rounded-lg bg-sky-500 hover:bg-sky-400 text-white text-xs font-medium px-3 py-2">Upload</button>
    <button @click="panel = panel === 'mkdir' ? '' : 'mkdir'"
            class="rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs px-3 py-2">New folder</button>
    <button @click="panel = panel === 'newfile' ? '' : 'newfile'"
            class="rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs px-3 py-2">New file</button>
    <button @click="panel = panel === 'compress' ? '' : 'compress'"
            class="rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs px-3 py-2">Compress selected</button>
    <button type="submit" form="bulkform" name="do" value="copy"
            class="rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs px-3 py-2">Copy</button>
    <button type="submit" form="bulkform" name="do" value="cut"
            class="rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs px-3 py-2">Cut</button>
    <?php if (!empty($clipboard)): ?>
        <form method="post" action="/files/action" class="inline">
            <?= Csrf::field() ?>
            <input type="hidden" name="site" value="<?= $siteId ?>">
            <input type="hidden" name="path" value="<?= e($path) ?>">
            <input type="hidden" name="do" value="paste">
            <button class="rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs px-3 py-2">
                Paste <?= count($clipboard['items']) ?> item(s) here
            </button>
        </form>
    <?php endif; ?>
    <button type="submit" form="bulkform" name="do" value="delete"
            @click="if (!confirm('Delete the selected items? This cannot be undone.')) $event.preventDefault()"
            class="rounded-lg bg-red-500/80 hover:bg-red-500 text-white text-xs px-3 py-2 ml-auto">Delete selected</button>

    <!-- Toolbar panels -->
    <div x-show="panel === 'upload'" x-cloak class="w-full mt-2">
        <form method="post" action="/files/upload" enctype="multipart/form-data" class="flex gap-2">
            <?= Csrf::field() ?>
            <input type="hidden" name="site" value="<?= $siteId ?>">
            <input type="hidden" name="path" value="<?= e($path) ?>">
            <input type="file" name="files[]" multiple required
                   class="flex-1 text-xs text-slate-400 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-800 file:text-slate-200 file:px-3 file:py-2">
            <button class="rounded-lg bg-sky-500 hover:bg-sky-400 text-white text-xs px-4">Upload here</button>
        </form>
    </div>
    <?php foreach (['mkdir' => 'Folder name', 'newfile' => 'File name'] as $act => $placeholder): ?>
        <div x-show="panel === '<?= $act ?>'" x-cloak class="w-full mt-2">
            <form method="post" action="/files/action" class="flex gap-2">
                <?= Csrf::field() ?>
                <input type="hidden" name="site" value="<?= $siteId ?>">
                <input type="hidden" name="path" value="<?= e($path) ?>">
                <input type="hidden" name="do" value="<?= $act ?>">
                <input name="name" required placeholder="<?= $placeholder ?>"
                       class="flex-1 rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-xs focus:border-sky-500 focus:outline-none">
                <button class="rounded-lg bg-sky-500 hover:bg-sky-400 text-white text-xs px-4">Create</button>
            </form>
        </div>
    <?php endforeach; ?>
    <div x-show="panel === 'compress'" x-cloak class="w-full mt-2">
        <form method="post" action="/files/action" class="flex gap-2" x-data
              @submit="hpCopySelection($el)">
            <?= Csrf::field() ?>
            <input type="hidden" name="site" value="<?= $siteId ?>">
            <input type="hidden" name="path" value="<?= e($path) ?>">
            <input type="hidden" name="do" value="compress">
            <span class="selection-slot"></span>
            <input name="name" required placeholder="archive.zip or archive.tar.gz"
                   class="flex-1 rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-xs focus:border-sky-500 focus:outline-none">
            <button class="rounded-lg bg-sky-500 hover:bg-sky-400 text-white text-xs px-4">Compress</button>
        </form>
    </div>
</div>

<!-- Listing: bulkform wraps the checkboxes -->
<form id="bulkform" method="post" action="/files/action">
    <?= Csrf::field() ?>
    <input type="hidden" name="site" value="<?= $siteId ?>">
    <input type="hidden" name="path" value="<?= e($path) ?>">

    <div class="bg-slate-900 border border-slate-800 rounded-xl overflow-hidden">
        <table class="w-full text-sm">
            <thead>
            <tr class="text-left text-xs text-slate-500 border-b border-slate-800">
                <th class="px-4 py-3 w-8"><input type="checkbox" x-data
                        @click="hpToggleAll($el)"
                        class="accent-sky-500"></th>
                <th class="px-3 py-3 font-medium">Name</th>
                <th class="px-3 py-3 font-medium">Size</th>
                <th class="px-3 py-3 font-medium">Perms</th>
                <th class="px-3 py-3 font-medium">Modified</th>
                <th class="px-3 py-3 font-medium text-right">Actions</th>
            </tr>
            </thead>
            <tbody class="divide-y divide-slate-800/70">
            <?php if ($path !== '/'): ?>
                <tr>
                    <td class="px-4 py-2.5"></td>
                    <td class="px-3 py-2.5" colspan="5">
                        <a href="/files?site=<?= $siteId ?>&amp;path=<?= rawurlencode(dirname($path)) ?>"
                           class="text-slate-400 hover:text-sky-400">↩ ..</a>
                    </td>
                </tr>
            <?php endif; ?>
            <?php if (empty($items)): ?>
                <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">Empty folder.</td></tr>
            <?php endif; ?>
            <?php foreach ($items as $item):
                $isDir = !empty($item['dir']);
                $itemPath = rtrim($path, '/') . '/' . $item['name'];
                $itemEnc = rawurlencode($itemPath);
                $isArchive = (bool) preg_match('/\.(zip|tar\.gz|tgz|tar)$/i', (string) $item['name']);
                $isText = (bool) preg_match(
                    '/\.(php|html?|css|js|json|txt|md|xml|ini|conf|env|htaccess|sql|yml|yaml|log)$/i',
                    (string) $item['name']
                ) || str_starts_with((string) $item['name'], '.');
                ?>
                <tr x-data="{ act: '' }">
                    <td class="px-4 py-2.5">
                        <input type="checkbox" name="items[]" value="<?= e($item['name']) ?>" class="fsel accent-sky-500">
                    </td>
                    <td class="px-3 py-2.5">
                        <?php if ($isDir): ?>
                            <a href="/files?site=<?= $siteId ?>&amp;path=<?= $itemEnc ?>"
                               class="text-sky-400 hover:underline">📁 <?= e($item['name']) ?></a>
                        <?php else: ?>
                            <span class="text-slate-200">📄 <?= e($item['name']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-3 py-2.5 text-slate-500 text-xs"><?= $isDir ? '—' : e($fmtSize((int) $item['size'])) ?></td>
                    <td class="px-3 py-2.5 text-slate-500 text-xs mono"><?= e($item['mode']) ?></td>
                    <td class="px-3 py-2.5 text-slate-500 text-xs"><?= e(date('M j, Y H:i', (int) $item['mtime'])) ?></td>
                    <td class="px-3 py-2.5 text-right text-xs whitespace-nowrap">
                        <?php if (!$isDir): ?>
                            <a href="/files/download?site=<?= $siteId ?>&amp;path=<?= $itemEnc ?>"
                               class="text-slate-400 hover:text-sky-400 mr-2">Download</a>
                            <?php if ($isText): ?>
                                <a href="/files/edit?site=<?= $siteId ?>&amp;path=<?= $itemEnc ?>"
                                   class="text-slate-400 hover:text-sky-400 mr-2">Edit</a>
                            <?php endif; ?>
                            <?php if ($isArchive): ?>
                                <button type="submit" form="row-<?= md5((string) $item['name']) ?>"
                                        name="do" value="extract"
                                        class="text-slate-400 hover:text-emerald-400 mr-2">Extract</button>
                            <?php endif; ?>
                        <?php endif; ?>
                        <button type="button" @click="act = act === 'rename' ? '' : 'rename'"
                                class="text-slate-400 hover:text-sky-400 mr-2">Rename</button>
                        <button type="button" @click="act = act === 'chmod' ? '' : 'chmod'"
                                class="text-slate-400 hover:text-sky-400">Chmod</button>

                        <div x-show="act === 'rename'" x-cloak class="mt-2">
                            <input form="row-<?= md5((string) $item['name']) ?>" name="to"
                                   value="<?= e($item['name']) ?>"
                                   class="rounded bg-slate-950 border border-slate-700 px-2 py-1 text-xs w-44 focus:border-sky-500 focus:outline-none">
                            <button type="submit" form="row-<?= md5((string) $item['name']) ?>" name="do" value="rename"
                                    class="rounded bg-sky-500 hover:bg-sky-400 text-white px-2 py-1">OK</button>
                        </div>
                        <div x-show="act === 'chmod'" x-cloak class="mt-2">
                            <input form="row-<?= md5((string) $item['name']) ?>" name="mode"
                                   value="<?= e($item['mode']) ?>" maxlength="3" pattern="[0-7]{3}"
                                   class="rounded bg-slate-950 border border-slate-700 px-2 py-1 text-xs w-16 mono focus:border-sky-500 focus:outline-none">
                            <button type="submit" form="row-<?= md5((string) $item['name']) ?>" name="do" value="chmod"
                                    class="rounded bg-sky-500 hover:bg-sky-400 text-white px-2 py-1">OK</button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</form>

<!-- Per-row mini-forms (rename / chmod / extract target a single item) -->
<?php foreach ($items as $item): ?>
    <form id="row-<?= md5((string) $item['name']) ?>" method="post" action="/files/action" class="hidden">
        <?= Csrf::field() ?>
        <input type="hidden" name="site" value="<?= $siteId ?>">
        <input type="hidden" name="path" value="<?= e($path) ?>">
        <input type="hidden" name="name" value="<?= e($item['name']) ?>">
    </form>
<?php endforeach; ?>

<?php endif; ?>
