<?php

use App\Services\SystemUpdateService;
use Illuminate\Support\Collection;

function invokeSystemUpdateServiceMethod(SystemUpdateService $service, string $method, array $arguments = []): mixed
{
    $reflection = new ReflectionMethod($service, $method);
    $reflection->setAccessible(true);

    return $reflection->invokeArgs($service, $arguments);
}

it('ignores deployment-only untracked files during update safety checks', function (): void {
    $service = app(SystemUpdateService::class);
    $status = "?? docker-compose.yml\n?? docker/\n";

    expect(invokeSystemUpdateServiceMethod($service, 'trackedChangesFromStatus', [$status]))->toBe([])
        ->and(invokeSystemUpdateServiceMethod($service, 'untrackedPathsFromStatus', [$status]))->toBe([
            'docker-compose.yml',
            'docker/',
        ]);

    $conflicts = invokeSystemUpdateServiceMethod($service, 'untrackedPathConflicts', [
        ['docker-compose.yml', 'docker/'],
        new Collection(['app/Services/SystemUpdateService.php', 'resources/views/welcome.blade.php']),
    ]);

    expect($conflicts)->toBe([]);
});

it('blocks update when an untracked deployment path would be overwritten', function (): void {
    $service = app(SystemUpdateService::class);

    $conflicts = invokeSystemUpdateServiceMethod($service, 'untrackedPathConflicts', [
        ['docker-compose.yml', 'docker/'],
        new Collection(['docker/nginx/default.conf', 'README.md']),
    ]);

    expect($conflicts)->toBe(['docker/']);

    $fileConflicts = invokeSystemUpdateServiceMethod($service, 'untrackedPathConflicts', [
        ['docker-compose.yml'],
        new Collection(['docker-compose.yml']),
    ]);

    expect($fileConflicts)->toBe(['docker-compose.yml']);
});

it('still blocks tracked local changes before update or rollback', function (): void {
    $service = app(SystemUpdateService::class);
    $status = " M app/Models/Order.php\nA  app/NewFile.php\n?? docker-compose.yml\n";

    expect(invokeSystemUpdateServiceMethod($service, 'trackedChangesFromStatus', [$status]))->toBe([
        'M app/Models/Order.php',
        'A  app/NewFile.php',
    ]);
});

it('uses temporary git safe directory config instead of writing global config', function (): void {
    $service = app(SystemUpdateService::class);

    $lines = invokeSystemUpdateServiceMethod($service, 'ensureGitSafeDirectory');

    expect($lines)->toHaveCount(1)
        ->and($lines[0])->toContain('safe.directory')
        ->and($lines[0])->not->toContain('--global')
        ->and($lines[0])->not->toContain('.gitconfig');
});
