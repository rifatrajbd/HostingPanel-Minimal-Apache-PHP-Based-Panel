<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Services\PanelCtl;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FilesController extends Controller
{
    public function download(Request $request, PanelCtl $ctl): BinaryFileResponse
    {
        $site = Site::findOrFail($request->integer('site'));
        $path = $this->clean($request->string('path'));

        $result = $ctl->run('fs:read', ['domain' => $site->domain, 'path' => $path]);
        abort_unless($result->ok(), 422, $result->output());

        $staged = trim($result->stdout);
        abort_unless($staged !== '' && is_file($staged), 422, 'Could not read the file.');

        return response()->download($staged, basename($path))->deleteFileAfterSend();
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
