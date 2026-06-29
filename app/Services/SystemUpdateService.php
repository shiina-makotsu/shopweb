<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Symfony\Component\Process\Process;

class SystemUpdateService
{
    /**
     * @return array<int, array{hash: string, short_hash: string, date: string, subject: string, label: string}>
     */
    public function recentVersions(int $limit = 20): array
    {
        $inside = $this->run(['git', 'rev-parse', '--is-inside-work-tree']);
        if (! $inside['ok'] || trim($inside['output']) !== 'true') {
            return [];
        }

        $log = $this->run(['git', 'log', '-n', (string) max(1, $limit), '--pretty=format:%H%x09%h%x09%ci%x09%s']);
        if (! $log['ok']) {
            return [];
        }

        return collect(explode("\n", trim($log['output'])))
            ->map(function (string $line): ?array {
                $parts = explode("\t", $line, 4);

                if (count($parts) < 4) {
                    return null;
                }

                [$hash, $shortHash, $date, $subject] = $parts;

                return [
                    'hash' => $hash,
                    'short_hash' => $shortHash,
                    'date' => $date,
                    'subject' => $subject,
                    'label' => "{$shortHash} · {$date} · {$subject}",
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array{status: string, title: string, lines: array<int, string>}
     */
    public function pullAndBuild(): array
    {
        $lines = $this->ensureGitSafeDirectory();

        $inside = $this->run(['git', 'rev-parse', '--is-inside-work-tree']);
        if (! $inside['ok'] || trim($inside['output']) !== 'true') {
            return $this->result('error', '当前目录不是 Git 仓库。', [$inside['output']]);
        }

        $upstream = $this->run(['git', 'rev-parse', '--abbrev-ref', '--symbolic-full-name', '@{u}']);
        if (! $upstream['ok']) {
            return $this->result('error', '当前分支没有设置上游仓库，无法自动拉取。', [$upstream['output']]);
        }

        $dirty = $this->run(['git', 'status', '--porcelain']);
        if (! $dirty['ok']) {
            return $this->result('error', '无法读取 Git 工作区状态。', [$dirty['output']]);
        }

        $trackedDirty = $this->trackedChangesFromStatus($dirty['output']);
        $untrackedPaths = $this->untrackedPathsFromStatus($dirty['output']);

        if ($trackedDirty !== []) {
            return $this->result('warning', '工作区存在未提交改动，已停止自动更新。', [
                '请先提交、暂存或清理生产环境中的本地改动，再执行更新。',
                implode("\n", $trackedDirty),
            ]);
        }

        $head = $this->run(['git', 'rev-parse', 'HEAD']);
        if (! $head['ok']) {
            return $this->result('error', '无法读取当前版本。', [$head['output']]);
        }

        $lines[] = '当前版本：'.trim($head['output']);
        $lines[] = '上游分支：'.trim($upstream['output']);

        $fetch = $this->run(['git', 'fetch', '--prune']);
        $lines[] = $this->formatStep('git fetch --prune', $fetch);
        if (! $fetch['ok']) {
            return $this->result('error', '拉取远端信息失败。', $lines);
        }

        $remote = $this->run(['git', 'rev-parse', '@{u}']);
        if (! $remote['ok']) {
            return $this->result('error', '无法读取远端版本。', [...$lines, $remote['output']]);
        }

        $oldHead = trim($head['output']);
        $newHead = trim($remote['output']);
        $lines[] = '远端版本：'.$newHead;

        if ($oldHead === $newHead) {
            return $this->result('info', '当前已经是最新版本。', $lines);
        }

        $incoming = $this->run(['git', 'diff', '--name-only', $oldHead, $newHead]);
        if (! $incoming['ok']) {
            return $this->result('error', '无法比对远端更新文件。', [...$lines, $incoming['output']]);
        }

        $incomingFiles = $this->changedFilesFromOutput($incoming['output']);
        $conflicts = $this->untrackedPathConflicts($untrackedPaths, $incomingFiles);
        if ($conflicts !== []) {
            return $this->result('warning', '未跟踪文件会被本次更新覆盖，已停止自动更新。', [
                ...$lines,
                '请先移动、提交或清理以下路径后再执行更新：',
                implode("\n", $conflicts),
            ]);
        }

        if ($untrackedPaths !== []) {
            $lines[] = '已忽略不会被本次更新覆盖的未跟踪部署文件：'.implode(', ', array_slice($untrackedPaths, 0, 12));
        }

        $ancestor = $this->run(['git', 'merge-base', '--is-ancestor', 'HEAD', '@{u}']);
        if (! $ancestor['ok']) {
            return $this->result('warning', '本地分支无法快进到远端，已停止自动更新。', [
                ...$lines,
                '请人工处理分支分叉后再执行更新。',
                $ancestor['output'],
            ]);
        }

        $pull = $this->run(['git', 'pull', '--ff-only'], 180);
        $lines[] = $this->formatStep('git pull --ff-only', $pull);
        if (! $pull['ok']) {
            return $this->result('error', '代码拉取失败。', $lines);
        }

        $changed = $this->run(['git', 'diff', '--name-only', $oldHead, 'HEAD']);
        $changedFiles = collect(explode("\n", trim($changed['output'])))
            ->map(fn (string $file): string => trim($file))
            ->filter()
            ->values();

        if ($this->shouldInstallNodeDependencies($changedFiles)) {
            $install = $this->run(['npm', 'ci'], 300);
            $lines[] = $this->formatStep('npm ci', $install);
            if (! $install['ok']) {
                return $this->result('error', '前端依赖安装失败。', $lines);
            }
        }

        $build = $this->run(['npm', 'run', 'build'], 300);
        $lines[] = $this->formatStep('npm run build', $build);
        if (! $build['ok']) {
            return $this->result('error', '前端资源构建失败。', $lines);
        }

        $clear = $this->run([$this->phpBinary(), 'artisan', 'optimize:clear'], 120);
        $lines[] = $this->formatStep('php artisan optimize:clear', $clear);

        $prewarm = $this->run([$this->phpBinary(), 'artisan', 'shop:cache-prewarm'], 120);
        $lines[] = $this->formatStep('php artisan shop:cache-prewarm', $prewarm);

        return $this->result('success', '代码已更新并完成前端资源构建。', $lines);
    }

    /**
     * @return array{status: string, title: string, lines: array<int, string>}
     */
    public function rollbackTo(string $commit): array
    {
        $commit = trim($commit);

        if (! preg_match('/^[a-f0-9]{7,40}$/i', $commit)) {
            return $this->result('error', '回滚版本格式不正确。', []);
        }

        $lines = $this->ensureGitSafeDirectory();

        $inside = $this->run(['git', 'rev-parse', '--is-inside-work-tree']);
        if (! $inside['ok'] || trim($inside['output']) !== 'true') {
            return $this->result('error', '当前目录不是 Git 仓库。', [$inside['output']]);
        }

        $dirty = $this->run(['git', 'status', '--porcelain']);
        if (! $dirty['ok']) {
            return $this->result('error', '无法读取 Git 工作区状态。', [$dirty['output']]);
        }

        $trackedDirty = $this->trackedChangesFromStatus($dirty['output']);
        $untrackedPaths = $this->untrackedPathsFromStatus($dirty['output']);

        if ($trackedDirty !== []) {
            return $this->result('warning', '工作区存在未提交改动，已停止回滚。', [
                '请先提交、暂存或清理生产环境中的本地改动，再执行回滚。',
                implode("\n", $trackedDirty),
            ]);
        }

        $target = $this->run(['git', 'rev-parse', $commit.'^{commit}']);
        if (! $target['ok']) {
            return $this->result('error', '找不到要回滚的 Git 版本。', [$target['output']]);
        }

        $head = $this->run(['git', 'rev-parse', 'HEAD']);
        if (! $head['ok']) {
            return $this->result('error', '无法读取当前版本。', [$head['output']]);
        }

        $oldHead = trim($head['output']);
        $targetHead = trim($target['output']);
        $lines = [
            ...$lines,
            '当前版本：'.$oldHead,
            '目标版本：'.$targetHead,
        ];

        if ($oldHead === $targetHead) {
            return $this->result('info', '当前已经位于该版本，无需回滚。', $lines);
        }

        $changed = $this->run(['git', 'diff', '--name-only', $oldHead, $targetHead]);
        $changedFiles = $this->changedFilesFromOutput($changed['output']);
        $conflicts = $this->untrackedPathConflicts($untrackedPaths, $changedFiles);
        if ($conflicts !== []) {
            return $this->result('warning', '未跟踪文件会被目标版本覆盖，已停止回滚。', [
                ...$lines,
                '请先移动、提交或清理以下路径后再执行回滚：',
                implode("\n", $conflicts),
            ]);
        }

        if ($untrackedPaths !== []) {
            $lines[] = '已忽略不会被目标版本覆盖的未跟踪部署文件：'.implode(', ', array_slice($untrackedPaths, 0, 12));
        }

        $reset = $this->run(['git', 'reset', '--hard', $targetHead], 120);
        $lines[] = $this->formatStep('git reset --hard '.$targetHead, $reset);
        if (! $reset['ok']) {
            return $this->result('error', '代码回滚失败。', $lines);
        }

        if ($this->shouldInstallNodeDependencies($changedFiles)) {
            $install = $this->run(['npm', 'ci'], 300);
            $lines[] = $this->formatStep('npm ci', $install);
            if (! $install['ok']) {
                return $this->result('error', '前端依赖安装失败。', $lines);
            }
        }

        $build = $this->run(['npm', 'run', 'build'], 300);
        $lines[] = $this->formatStep('npm run build', $build);
        if (! $build['ok']) {
            return $this->result('error', '前端资源构建失败。', $lines);
        }

        $clear = $this->run([$this->phpBinary(), 'artisan', 'optimize:clear'], 120);
        $lines[] = $this->formatStep('php artisan optimize:clear', $clear);

        $prewarm = $this->run([$this->phpBinary(), 'artisan', 'shop:cache-prewarm'], 120);
        $lines[] = $this->formatStep('php artisan shop:cache-prewarm', $prewarm);

        return $this->result('success', '代码已回滚并完成前端资源重构。', $lines);
    }

    /**
     * @param  array<int, string>  $command
     * @return array{ok: bool, output: string, exit_code: int|null}
     */
    private function run(array $command, int $timeout = 60): array
    {
        $process = new Process($command, base_path());
        $process->setEnv($this->processEnvironment($command));
        $process->setTimeout($timeout);
        $process->run();

        $output = trim($process->getOutput()."\n".$process->getErrorOutput());

        return [
            'ok' => $process->isSuccessful(),
            'output' => $output,
            'exit_code' => $process->getExitCode(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function ensureGitSafeDirectory(): array
    {
        $existing = $this->runRaw(['git', 'config', '--global', '--get-all', 'safe.directory']);
        $safeDirectories = collect(explode("\n", trim($existing['output'])))
            ->map(fn (string $path): string => trim($path))
            ->filter()
            ->all();

        $paths = $this->safeDirectoryPaths();
        $missing = array_values(array_filter(
            $paths,
            fn (string $path): bool => ! in_array($path, $safeDirectories, true)
        ));

        if ($missing === []) {
            return ['Git 安全目录：已存在 '.implode(' / ', $paths)];
        }

        $failed = [];

        foreach ($missing as $path) {
            $safe = $this->runRaw(['git', 'config', '--global', '--add', 'safe.directory', $path]);

            if (! $safe['ok']) {
                $failed[] = trim($safe['output']);
            }
        }

        if ($failed === []) {
            return ['Git 安全目录：已确认 '.implode(' / ', $paths)];
        }

        return [
            'Git 安全目录：无法写入全局配置，已为本次更新命令启用临时 safe.directory。',
            implode("\n", array_filter($failed)),
        ];
    }

    /**
     * @param  array<int, string>  $command
     * @return array<string, string>
     */
    private function processEnvironment(array $command): array
    {
        if (($command[0] ?? null) !== 'git') {
            return [];
        }

        $env = [
            'GIT_CONFIG_COUNT' => (string) count($this->safeDirectoryPaths()),
        ];

        foreach ($this->safeDirectoryPaths() as $index => $path) {
            $env['GIT_CONFIG_KEY_'.$index] = 'safe.directory';
            $env['GIT_CONFIG_VALUE_'.$index] = $path;
        }

        return $env;
    }

    /**
     * @param  array<int, string>  $command
     * @return array{ok: bool, output: string, exit_code: int|null}
     */
    private function runRaw(array $command, int $timeout = 60): array
    {
        $process = new Process($command, base_path());
        $process->setTimeout($timeout);
        $process->run();

        $output = trim($process->getOutput()."\n".$process->getErrorOutput());

        return [
            'ok' => $process->isSuccessful(),
            'output' => $output,
            'exit_code' => $process->getExitCode(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function safeDirectoryPaths(): array
    {
        return collect([base_path(), realpath(base_path()) ?: null])
            ->filter(fn (?string $path): bool => filled($path))
            ->map(fn (string $path): string => str_replace('\\', '/', $path))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array{ok: bool, output: string, exit_code: int|null}  $step
     */
    private function formatStep(string $label, array $step): string
    {
        $status = $step['ok'] ? 'OK' : 'FAILED';
        $output = trim($step['output']);

        return $output === ''
            ? "[{$status}] {$label}"
            : "[{$status}] {$label}\n{$output}";
    }

    private function phpBinary(): string
    {
        return PHP_BINARY ?: 'php';
    }

    /**
     * @return array<int, string>
     */
    private function trackedChangesFromStatus(string $status): array
    {
        return collect(explode("\n", trim($status)))
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->reject(fn (string $line): bool => str_starts_with($line, '?? '))
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function untrackedPathsFromStatus(string $status): array
    {
        return collect(explode("\n", trim($status)))
            ->map(fn (string $line): string => trim($line))
            ->filter(fn (string $line): bool => str_starts_with($line, '?? '))
            ->map(fn (string $line): string => $this->normalizeGitPath(substr($line, 3)))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, string>
     */
    private function changedFilesFromOutput(string $output): Collection
    {
        return collect(explode("\n", trim($output)))
            ->map(fn (string $file): string => $this->normalizeGitPath($file))
            ->filter()
            ->values();
    }

    /**
     * @param  array<int, string>  $untrackedPaths
     * @param  Collection<int, string>  $changedFiles
     * @return array<int, string>
     */
    private function untrackedPathConflicts(array $untrackedPaths, Collection $changedFiles): array
    {
        if ($untrackedPaths === [] || $changedFiles->isEmpty()) {
            return [];
        }

        return collect($untrackedPaths)
            ->filter(function (string $untracked) use ($changedFiles): bool {
                $prefix = rtrim($untracked, '/').'/';

                return $changedFiles->contains(function (string $changed) use ($untracked, $prefix): bool {
                    return $changed === $untracked
                        || str_starts_with($changed, $prefix)
                        || str_starts_with($untracked, rtrim($changed, '/').'/');
                });
            })
            ->values()
            ->all();
    }

    private function normalizeGitPath(string $path): string
    {
        return trim(str_replace('\\', '/', trim($path)), " \t\n\r\0\x0B\"'");
    }

    /**
     * @param  Collection<int, string>  $changedFiles
     */
    private function shouldInstallNodeDependencies(Collection $changedFiles): bool
    {
        return ! is_dir(base_path('node_modules'))
            || $changedFiles->intersect(['package.json', 'package-lock.json'])->isNotEmpty();
    }

    /**
     * @param  array<int, string>  $lines
     * @return array{status: string, title: string, lines: array<int, string>}
     */
    private function result(string $status, string $title, array $lines): array
    {
        return [
            'status' => $status,
            'title' => $title,
            'lines' => array_values(array_filter($lines, fn (?string $line): bool => filled($line))),
        ];
    }
}
