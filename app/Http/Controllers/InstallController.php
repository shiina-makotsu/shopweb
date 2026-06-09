<?php

namespace App\Http\Controllers;

use App\Services\ShopInstaller;
use App\Support\SystemInfo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use PDO;

class InstallController extends Controller
{
    public function show(): View
    {
        abort_if($this->installed() && ! config('shop.installer_enabled'), 404);

        return view('install.show', [
            'checks' => $this->environmentChecks(),
        ]);
    }

    public function checkDatabase(Request $request): JsonResponse
    {
        abort_if($this->installed() && ! config('shop.installer_enabled'), 404);

        $data = $request->validate($this->databaseRules());

        try {
            $this->pdo($data);
        } catch (\Throwable $exception) {
            return response()->json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'message' => '数据库连接成功。']);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_if($this->installed() && ! config('shop.installer_enabled'), 404);

        $data = $request->validate([
            ...$this->databaseRules(),
            'app_url' => ['required', 'url', 'max:255'],
            'site_name' => ['required', 'string', 'max:255'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'confirmed', Password::min(8)],
            'contact_info' => ['nullable', 'string', 'max:2000'],
            'payment_instructions' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->pdo($data);
        app(ShopInstaller::class)->install($data);

        return redirect()->route('home')->with('status', '安装完成。');
    }

    private function installed(): bool
    {
        return File::exists(storage_path('app/install.lock'));
    }

    private function databaseRules(): array
    {
        return [
            'db_host' => ['required', 'string', 'max:255'],
            'db_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'db_database' => ['required', 'string', 'max:255'],
            'db_username' => ['required', 'string', 'max:255'],
            'db_password' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function pdo(array $data): PDO
    {
        return new PDO(
            "mysql:host={$data['db_host']};port={$data['db_port']};dbname={$data['db_database']};charset=utf8mb4",
            $data['db_username'],
            $data['db_password'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }

    private function environmentChecks(): array
    {
        $checks = [
            'PHP >= 8.3' => version_compare(PHP_VERSION, '8.3.0', '>='),
            'PDO MySQL' => extension_loaded('pdo_mysql'),
            'OpenSSL' => extension_loaded('openssl'),
            'Fileinfo' => extension_loaded('fileinfo'),
        ];

        foreach (app(SystemInfo::class)->writablePaths() as $path) {
            $checks['可写 '.$path['label']] = $path['status'];
        }

        return $checks;
    }
}
