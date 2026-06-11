<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Site;
use App\Services\PanelCtl;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FilesController extends Controller
{
    public function download(Request $request, PanelCtl $ctl): StreamedResponse
    {
        $site = Site::findOrFail($request->integer('site'));
        $path = $this->clean($request->string('path'));

        $result = $ctl->run('fs:read', ['domain' => $site->domain, 'path' => $path]);
        abort_unless($result->ok(), 422, $result->output());

        $name = basename($path);
        return response()->streamDownload(fn () => print($result->stdout), $name);
    }

    public function edit(Request $request, PanelCtl $ctl)
    {
        $site = Site::findOrFail($request->integer('site'));
        $path = $this->clean($request->string('path'));

        $result = $ctl->run('fs:read', ['domain' => $site->domain, 'path' => $path]);
        abort_unless($result->ok(), 422, $result->output());

        return view('files.edit', [
            'site' => $site,
            'path' => $path,
            'content' => $result->stdout,
        ]);
    }

    public function save(Request $request, PanelCtl $ctl)
    {
        $site = Site::findOrFail($request->integer('site'));
        $path = $this->clean($request->string('path'));
        $content = str_replace("\r\n", "\n", (string) $request->input('content'));

        $result = $ctl->run('fs:write', ['domain' => $site->domain, 'path' => $path], $content);
        AuditLog::record('fs.write', $site->domain . ':' . $path);

        return redirect()
            ->route('files.edit', ['site' => $site->id, 'path' => $path])
            ->with($result->ok() ? 'status' : 'error', $result->ok() ? 'Saved ' . basename($path) : $result->output());
    }

    private function clean(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        if (str_contains($path, '..')) {
            abort(400, 'Invalid path');
        }
        return '/' . trim($path, '/');
    }
}
