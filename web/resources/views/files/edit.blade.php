<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit {{ basename($path) }} · HostingPanel</title>
    @vite('resources/css/app.css')
    <style>
        body { background:#0f172a; color:#e2e8f0; font-family:ui-sans-serif,system-ui,sans-serif; margin:0; }
        .wrap { max-width:1100px; margin:0 auto; padding:24px; }
        .bar { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; }
        textarea { width:100%; height:70vh; background:#020617; color:#e2e8f0; border:1px solid #1e293b;
                   border-radius:12px; padding:16px; font-family:ui-monospace,Consolas,monospace; font-size:13px; }
        a, button { font-size:14px; }
        .btn { background:#0ea5e9; color:#fff; border:0; border-radius:8px; padding:8px 18px; cursor:pointer; }
        .btn-ghost { background:#1e293b; color:#e2e8f0; border-radius:8px; padding:8px 18px; text-decoration:none; }
        .msg { padding:8px 14px; border-radius:8px; margin-bottom:12px; font-size:14px; }
        .ok { background:rgba(16,185,129,.1); color:#6ee7b7; }
        .err { background:rgba(239,68,68,.1); color:#fca5a5; }
        .mono { font-family:ui-monospace,Consolas,monospace; color:#94a3b8; font-size:13px; }
    </style>
</head>
<body>
<div class="wrap">
    @if (session('status'))<div class="msg ok">{{ session('status') }}</div>@endif
    @if (session('error'))<div class="msg err">{{ session('error') }}</div>@endif

    <form method="post" action="{{ route('files.save') }}">
        @csrf
        <input type="hidden" name="site" value="{{ $site->id }}">
        <input type="hidden" name="path" value="{{ $path }}">
        <div class="bar">
            <span class="mono">{{ $site->domain }}:{{ $path }}</span>
            <span>
                <a class="btn-ghost" href="{{ route('filament.admin.pages.file-manager', ['site' => $site->id, 'path' => dirname($path)]) }}">Back</a>
                <button class="btn" type="submit">Save</button>
            </span>
        </div>
        <textarea name="content" spellcheck="false">{{ $content }}</textarea>
    </form>
</div>
</body>
</html>
