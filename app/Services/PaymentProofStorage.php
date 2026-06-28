<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentProofFile;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PaymentProofStorage
{
    private const DISK = 'payment_proofs';

    public function store(Order $order, UploadedFile $file): string
    {
        $directory = $this->directoryFor($order);
        $extension = $this->extensionFor($file);
        $filename = now()->format('YmdHis').'-'.Str::random(12).'.'.$extension;
        $path = $directory.'/'.$filename;

        try {
            $disk = $this->disk();
            $this->ensureDirectory($disk, $directory);
            $storedPath = $disk->putFileAs($directory, $file, $filename);

            if (is_string($storedPath) && $storedPath !== '' && $disk->exists($storedPath)) {
                return $storedPath;
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        try {
            $disk = $this->disk();
            $this->writeByStream($disk, $file, $path);

            if ($disk->exists($path)) {
                return $path;
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return $this->storeInDatabase($order, $file, $path);
    }

    public function delete(?string $path): void
    {
        if (blank($path)) {
            return;
        }

        if ($this->isDatabasePath($path)) {
            try {
                PaymentProofFile::query()->whereKey($this->databaseIdFromPath($path))->delete();
            } catch (Throwable $exception) {
                report($exception);
            }

            return;
        }

        try {
            $this->disk()->delete($this->normalizePath($path));
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function exists(?string $path): bool
    {
        if (blank($path)) {
            return false;
        }

        if ($this->isDatabasePath($path)) {
            try {
                return PaymentProofFile::query()->whereKey($this->databaseIdFromPath($path))->exists();
            } catch (Throwable $exception) {
                report($exception);

                return false;
            }
        }

        try {
            return $this->disk()->exists($this->normalizePath($path));
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    public function response(string $path): StreamedResponse|Response
    {
        if ($this->isDatabasePath($path)) {
            $file = PaymentProofFile::query()->findOrFail($this->databaseIdFromPath($path));

            $content = base64_decode((string) $file->content, true);

            if (! is_string($content)) {
                throw new RuntimeException('Database payment proof content is invalid.');
            }

            return response($content, 200, [
                'Content-Type' => $file->mime_type ?: 'application/octet-stream',
                'Content-Length' => (string) $file->size,
                'Content-Disposition' => 'inline; filename="'.addslashes($file->original_name ?: 'payment-proof').'"',
            ]);
        }

        return $this->disk()->response($this->normalizePath($path));
    }

    private function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }

    private function ensureDirectory(Filesystem $disk, string $directory): void
    {
        try {
            if (! $disk->exists($directory)) {
                $disk->makeDirectory($directory);
            }
        } catch (Throwable $exception) {
            throw new RuntimeException('Payment proof directory is not writable: '.$directory, 0, $exception);
        }

        $root = config('filesystems.disks.'.self::DISK.'.root');

        if (! is_string($root) || $root === '') {
            return;
        }

        $absoluteDirectory = rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $directory);

        if (! is_dir($absoluteDirectory) && ! @mkdir($absoluteDirectory, 0775, true) && ! is_dir($absoluteDirectory)) {
            throw new RuntimeException('Payment proof directory cannot be created: '.$absoluteDirectory);
        }

        if (! is_writable($absoluteDirectory)) {
            throw new RuntimeException('Payment proof directory is not writable: '.$absoluteDirectory);
        }
    }

    private function writeByStream(Filesystem $disk, UploadedFile $file, string $path): void
    {
        $sourcePath = $file->getRealPath() ?: $file->path();
        $stream = @fopen($sourcePath, 'rb');

        if (! is_resource($stream)) {
            throw new RuntimeException('Payment proof temporary file cannot be read.');
        }

        try {
            $written = $disk->put($path, $stream);
        } finally {
            fclose($stream);
        }

        if (! $written) {
            throw new RuntimeException('Payment proof file cannot be written to disk.');
        }
    }

    private function storeInDatabase(Order $order, UploadedFile $file, string $intendedPath): string
    {
        if (! Schema::hasTable('payment_proof_files')) {
            throw new RuntimeException('Payment proof file storage failed and database fallback is not migrated.');
        }

        $content = @file_get_contents($file->getRealPath() ?: $file->path());

        if (! is_string($content) || $content === '') {
            throw new RuntimeException('Payment proof temporary file cannot be read for database fallback.');
        }

        $proof = DB::transaction(function () use ($order, $file, $intendedPath, $content): PaymentProofFile {
            return PaymentProofFile::query()->create([
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'disk_path' => $intendedPath,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => strlen($content),
                'content' => base64_encode($content),
            ]);
        });

        return 'db:'.$proof->id;
    }

    private function directoryFor(Order $order): string
    {
        $base = $order->order_number !== ''
            ? $order->order_number
            : 'order-'.$order->id;

        return Str::of($base)
            ->replaceMatches('/[^A-Za-z0-9._-]+/', '-')
            ->trim('-._')
            ->whenEmpty(fn (): string => 'order-'.$order->id)
            ->toString();
    }

    private function extensionFor(UploadedFile $file): string
    {
        $extension = strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin'));
        $extension = preg_replace('/[^a-z0-9]+/', '', $extension) ?: 'bin';

        return $extension;
    }

    private function normalizePath(string $path): string
    {
        return ltrim(str_replace('\\', '/', $path), '/');
    }

    private function isDatabasePath(string $path): bool
    {
        return str_starts_with($path, 'db:');
    }

    private function databaseIdFromPath(string $path): int
    {
        $id = (int) substr($path, 3);

        if ($id <= 0) {
            throw new RuntimeException('Invalid database payment proof path.');
        }

        return $id;
    }
}
