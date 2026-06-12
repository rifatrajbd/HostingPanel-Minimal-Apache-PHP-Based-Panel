<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\SiteDatabase;
use App\Services\PanelCtl;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DatabaseController extends Controller
{
    public function export(SiteDatabase $database, PanelCtl $ctl): BinaryFileResponse
    {
        $result = $ctl->run('db:export', ['name' => $database->name]);
        abort_unless($result->ok(), 422, $result->output());

        $path = trim($result->stdout);
        abort_unless($path !== '' && is_file($path), 422, 'Export file was not created.');

        AuditLog::record('db.export', $database->name);
        return response()->download($path, basename($path))->deleteFileAfterSend();
    }
}
