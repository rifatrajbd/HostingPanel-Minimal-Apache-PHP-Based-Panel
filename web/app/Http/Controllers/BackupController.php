<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\PanelCtl;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function download(Request $request, PanelCtl $ctl): BinaryFileResponse
    {
        $type = $request->query('type') === 'site' ? 'site' : 'full';
        $flags = ['type' => $type];
        if ($type === 'site') {
            $flags['domain'] = (string) $request->query('domain');
        }

        $result = $ctl->run('backup:download', $flags);
        abort_unless($result->ok(), 422, $result->output());

        $path = trim($result->stdout);
        abort_unless($path !== '' && is_file($path), 422, 'Backup archive was not created.');

        AuditLog::record('backup.download', $type . ($type === 'site' ? ':' . $flags['domain'] : ''));
        return response()->download($path, basename($path))->deleteFileAfterSend();
    }
}
