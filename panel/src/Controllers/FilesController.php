<?php

declare(strict_types=1);

namespace Panel\Controllers;

use Panel\Database;
use Panel\Services\PanelCtl;
use Panel\Support\View;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class FilesController extends Controller
{
    /** @param array<string, mixed> $settings */
    public function __construct(
        View $view,
        private readonly Database $db,
        private readonly PanelCtl $ctl,
        private readonly array $settings
    ) {
        parent::__construct($view);
    }

    public function index(Request $request, Response $response): Response
    {
        $sites = $this->db->all('SELECT * FROM sites ORDER BY domain');
        $params = $request->getQueryParams();
        $site = $this->resolveSite((string) ($params['site'] ?? ''), $sites);
        $path = $this->cleanPath((string) ($params['path'] ?? '/'));

        $items = [];
        $listError = null;
        if ($site !== null) {
            $result = $this->ctl->run('fs:list', ['domain' => (string) $site['domain'], 'path' => $path]);
            if ($result->ok()) {
                $decoded = json_decode($result->stdout, true);
                $items = is_array($decoded) ? $decoded : [];
                if (!is_array($decoded)) {
                    $listError = $this->settings['env'] === 'dev'
                        ? 'Dev mode: file operations are dry-run only on this machine.'
                        : 'Unexpected fs:list output.';
                }
            } else {
                $listError = $result->output();
            }
        }

        return $this->html($response, 'files/index', [
            'title' => 'File Manager',
            'active' => 'files',
            'sites' => $sites,
            'site' => $site,
            'path' => $path,
            'items' => $items,
            'listError' => $listError,
            'clipboard' => $_SESSION['fclip'] ?? null,
        ]);
    }

    public function download(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        [$site, $path] = $this->siteAndPath((string) ($params['site'] ?? ''), (string) ($params['path'] ?? ''));
        if ($site === null) {
            return $this->redirect($response, '/files');
        }

        $result = $this->ctl->run('fs:read', ['domain' => (string) $site['domain'], 'path' => $path]);
        if (!$result->ok()) {
            $this->flash('error', 'Download failed: ' . $result->output());
            return $this->back($response, $site, dirname($path));
        }
        $response->getBody()->write($result->stdout);
        return $response
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withHeader(
                'Content-Disposition',
                'attachment; filename="' . str_replace('"', '', basename($path)) . '"'
            );
    }

    public function edit(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        [$site, $path] = $this->siteAndPath((string) ($params['site'] ?? ''), (string) ($params['path'] ?? ''));
        if ($site === null) {
            return $this->redirect($response, '/files');
        }

        $result = $this->ctl->run('fs:read', ['domain' => (string) $site['domain'], 'path' => $path]);
        if (!$result->ok()) {
            $this->flash('error', 'Cannot open file: ' . $result->output());
            return $this->back($response, $site, dirname($path));
        }

        return $this->html($response, 'files/edit', [
            'title' => 'Edit: ' . basename($path),
            'active' => 'files',
            'site' => $site,
            'path' => $path,
            'content' => $result->stdout,
        ]);
    }

    public function save(Request $request, Response $response): Response
    {
        [$site, $path] = $this->siteAndPath($this->input($request, 'site'), $this->input($request, 'path'));
        if ($site === null) {
            return $this->redirect($response, '/files');
        }
        $body = $request->getParsedBody();
        $content = is_array($body) ? (string) ($body['content'] ?? '') : '';
        // Normalize line endings the textarea introduces
        $content = str_replace("\r\n", "\n", $content);

        $result = $this->ctl->run('fs:write', ['domain' => (string) $site['domain'], 'path' => $path], $content);
        $this->flash(
            $result->ok() ? 'success' : 'error',
            $result->ok() ? 'Saved ' . basename($path) : 'Save failed: ' . $result->output()
        );
        $this->db->audit($this->userId(), 'fs.write', $site['domain'] . ':' . $path, $this->ip($request));
        return $this->back($response, $site, dirname($path));
    }

    public function upload(Request $request, Response $response): Response
    {
        [$site, $path] = $this->siteAndPath($this->input($request, 'site'), $this->input($request, 'path'));
        if ($site === null) {
            return $this->redirect($response, '/files');
        }

        $uploadDir = $this->uploadDir();
        $count = 0;
        foreach ($request->getUploadedFiles()['files'] ?? [] as $file) {
            if ($file->getError() !== UPLOAD_ERR_OK) {
                $this->flash('error', 'Upload error for ' . ($file->getClientFilename() ?? '?'));
                continue;
            }
            $name = basename((string) $file->getClientFilename());
            if ($name === '' || str_starts_with($name, '.')) {
                continue;
            }
            $tmp = $uploadDir . '/' . bin2hex(random_bytes(12));
            $file->moveTo($tmp);
            $result = $this->ctl->run('fs:import', [
                'domain' => (string) $site['domain'],
                'path' => rtrim($path, '/') . '/' . $name,
                'src' => $tmp,
            ]);
            if (is_file($tmp)) {
                unlink($tmp); // dry-run / failure leftover
            }
            if ($result->ok()) {
                $count++;
            } else {
                $this->flash('error', "Upload of {$name} failed: " . $result->output());
            }
        }
        if ($count > 0) {
            $this->flash('success', "{$count} file(s) uploaded.");
            $this->db->audit($this->userId(), 'fs.upload', $site['domain'] . ':' . $path, $this->ip($request));
        }
        return $this->back($response, $site, $path);
    }

    public function action(Request $request, Response $response): Response
    {
        [$site, $path] = $this->siteAndPath($this->input($request, 'site'), $this->input($request, 'path'));
        if ($site === null) {
            return $this->redirect($response, '/files');
        }
        $domain = (string) $site['domain'];
        $do = $this->input($request, 'do');
        $name = $this->input($request, 'name');
        $items = $this->selectedItems($request);

        $result = null;
        switch ($do) {
            case 'mkdir':
            case 'newfile':
                if (!$this->validName($name)) {
                    $this->flash('error', 'Invalid name.');
                    return $this->back($response, $site, $path);
                }
                $target = rtrim($path, '/') . '/' . $name;
                $result = $do === 'mkdir'
                    ? $this->ctl->run('fs:mkdir', ['domain' => $domain, 'path' => $target])
                    : $this->ctl->run('fs:write', ['domain' => $domain, 'path' => $target], '');
                break;

            case 'rename':
                $to = $this->input($request, 'to');
                if (!$this->validName($to)) {
                    $this->flash('error', 'Invalid new name.');
                    return $this->back($response, $site, $path);
                }
                $result = $this->ctl->run('fs:rename', [
                    'domain' => $domain,
                    'from' => rtrim($path, '/') . '/' . $name,
                    'to' => rtrim($path, '/') . '/' . $to,
                ]);
                break;

            case 'chmod':
                $result = $this->ctl->run('fs:chmod', [
                    'domain' => $domain,
                    'path' => rtrim($path, '/') . '/' . $name,
                    'mode' => $this->input($request, 'mode'),
                ]);
                break;

            case 'delete':
                foreach ($items !== [] ? $items : [$name] as $item) {
                    $result = $this->ctl->run('fs:delete', [
                        'domain' => $domain,
                        'path' => rtrim($path, '/') . '/' . $item,
                    ]);
                    if (!$result->ok()) {
                        break;
                    }
                }
                break;

            case 'copy':
            case 'cut':
                if ($items === []) {
                    $this->flash('error', 'Select at least one item first.');
                    return $this->back($response, $site, $path);
                }
                $_SESSION['fclip'] = [
                    'mode' => $do, 'site_id' => (int) $site['id'],
                    'domain' => $domain, 'base' => $path, 'items' => $items,
                ];
                $this->flash('success', count($items) . ' item(s) on the clipboard — navigate and Paste.');
                return $this->back($response, $site, $path);

            case 'paste':
                return $this->paste($request, $response, $site, $path);

            case 'compress':
                if ($items === [] || !$this->validName($name)
                    || (!str_ends_with($name, '.zip') && !str_ends_with($name, '.tar.gz'))) {
                    $this->flash('error', 'Select items and give the archive a .zip or .tar.gz name.');
                    return $this->back($response, $site, $path);
                }
                $rel = array_map(fn ($i) => rtrim($path, '/') . '/' . $i, $items);
                $result = $this->ctl->run('fs:compress', [
                    'domain' => $domain,
                    'path' => $path,
                    'dest' => rtrim($path, '/') . '/' . $name,
                ], (string) json_encode($rel));
                break;

            case 'extract':
                $result = $this->ctl->run('fs:extract', [
                    'domain' => $domain,
                    'path' => rtrim($path, '/') . '/' . $name,
                    'dest' => $path,
                ]);
                break;

            default:
                $this->flash('error', 'Unknown action.');
                return $this->back($response, $site, $path);
        }

        if ($result !== null) {
            $this->flash($result->ok() ? 'success' : 'error', $result->output());
            if ($result->ok()) {
                $this->db->audit($this->userId(), 'fs.' . $do, $domain . ':' . $path, $this->ip($request));
            }
        }
        return $this->back($response, $site, $path);
    }

    /** @param array<string, mixed> $site */
    private function paste(Request $request, Response $response, array $site, string $path): Response
    {
        $clip = $_SESSION['fclip'] ?? null;
        if (!is_array($clip) || (int) $clip['site_id'] !== (int) $site['id']) {
            $this->flash('error', 'Clipboard is empty (paste works within the same site).');
            return $this->back($response, $site, $path);
        }
        $command = $clip['mode'] === 'cut' ? 'fs:rename' : 'fs:copy';
        $ok = 0;
        foreach ($clip['items'] as $item) {
            $result = $this->ctl->run($command, [
                'domain' => (string) $site['domain'],
                'from' => rtrim((string) $clip['base'], '/') . '/' . $item,
                'to' => rtrim($path, '/') . '/' . $item,
            ]);
            if ($result->ok()) {
                $ok++;
            } else {
                $this->flash('error', "{$item}: " . $result->output());
            }
        }
        if ($clip['mode'] === 'cut') {
            unset($_SESSION['fclip']);
        }
        $this->flash('success', "{$ok} item(s) pasted.");
        $this->db->audit($this->userId(), 'fs.paste', $site['domain'] . ':' . $path, $this->ip($request));
        return $this->back($response, $site, $path);
    }

    // ------------------------------------------------------------- helpers

    /**
     * @param list<array<string, mixed>> $sites
     * @return array<string, mixed>|null
     */
    private function resolveSite(string $id, array $sites): ?array
    {
        foreach ($sites as $site) {
            if ((string) $site['id'] === $id) {
                return $site;
            }
        }
        return $sites[0] ?? null;
    }

    /** @return array{0: array<string, mixed>|null, 1: string} */
    private function siteAndPath(string $siteId, string $path): array
    {
        $site = $this->db->one('SELECT * FROM sites WHERE id = ?', [(int) $siteId]);
        return [$site, $this->cleanPath($path)];
    }

    private function cleanPath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        if (str_contains($path, '..') || str_contains($path, "\0")) {
            return '/';
        }
        $path = '/' . trim($path, '/');
        return $path === '//' ? '/' : $path;
    }

    private function validName(string $name): bool
    {
        return $name !== '' && strlen($name) <= 255
            && !str_contains($name, '/') && !str_contains($name, '\\')
            && !str_contains($name, "\0") && $name !== '.' && $name !== '..';
    }

    /** @return list<string> */
    private function selectedItems(Request $request): array
    {
        $body = $request->getParsedBody();
        $raw = is_array($body) ? ($body['items'] ?? []) : [];
        $items = [];
        foreach ((array) $raw as $item) {
            if (is_string($item) && $this->validName($item)) {
                $items[] = $item;
            }
        }
        return $items;
    }

    /** @param array<string, mixed> $site */
    private function back(Response $response, array $site, string $path): Response
    {
        $path = $this->cleanPath($path === '.' ? '/' : $path);
        return $this->redirect($response, '/files?site=' . (int) $site['id'] . '&path=' . rawurlencode($path));
    }

    private function uploadDir(): string
    {
        $dir = $this->settings['env'] === 'dev' || PHP_OS_FAMILY === 'Windows'
            ? dirname(__DIR__, 2) . '/var/uploads'
            : '/var/lib/hostingpanel/uploads';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        return $dir;
    }
}
