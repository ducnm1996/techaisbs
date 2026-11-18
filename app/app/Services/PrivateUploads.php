<?php

namespace App\Services;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class PrivateUploads
{
    public function migrateLegacy(): void
    {
        if (! config('filesystems.migrate_legacy_uploads')) {
            return;
        }
        $disk = Storage::disk('local');
        if ($disk->exists('.legacy-import-complete')) {
            return;
        }
        $lock = null;
        try {
            File::ensureDirectoryExists($disk->path(''), 0700, true);
            $lock = fopen($disk->path('.legacy-import.lock'), 'c');
            if (! $lock || ! flock($lock, LOCK_EX)) {
                throw new RuntimeException('Cannot lock the private upload migration.');
            }
            if ($disk->exists('.legacy-import-complete')) {
                return;
            }
            foreach (Storage::disk('legacy_uploads')->allFiles() as $path) {
                if (! str_starts_with(basename($path), '.')) {
                    $this->copyLegacyFile($path);
                }
            }
            if (! $disk->put('.legacy-import-complete', now()->toIso8601String())) {
                throw new RuntimeException('Cannot complete the private upload migration.');
            }
        } catch (Throwable $error) {
            Log::error('Private upload migration needs attention.', ['exception' => $error]);
        } finally {
            if (is_resource($lock)) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    public function diskFor(string $path): FilesystemAdapter
    {
        $disk = Storage::disk('local');
        if ($disk->exists($path) || ! config('filesystems.migrate_legacy_uploads')) {
            return $disk;
        }
        $legacy = Storage::disk('legacy_uploads');
        if (! $legacy->exists($path)) {
            return $disk;
        }
        try {
            $this->copyLegacyFile($path);

            return $disk;
        } catch (Throwable $error) {
            Log::error('A legacy upload could not be copied.', ['exception' => $error]);

            return $legacy;
        }
    }

    public function photosFor(string $directory): array
    {
        $paths = Storage::disk('local')->files($directory);
        if (config('filesystems.migrate_legacy_uploads')) {
            foreach (Storage::disk('legacy_uploads')->files($directory) as $path) {
                $this->diskFor($path);
                $paths[] = $path;
            }
        }
        $photos = array_map('basename', $paths);

        return array_values(array_unique(array_filter($photos, fn (string $photo) => preg_match('/^[a-zA-Z0-9]{40}\.(jpg|jpeg|png|webp)$/', $photo))));
    }

    public function delete(string|array $paths): void
    {
        Storage::disk('local')->delete($paths);
        if (config('filesystems.migrate_legacy_uploads')) {
            Storage::disk('legacy_uploads')->delete($paths);
        }
    }

    public function deleteDirectory(string $directory): void
    {
        Storage::disk('local')->deleteDirectory($directory);
        if (config('filesystems.migrate_legacy_uploads')) {
            Storage::disk('legacy_uploads')->deleteDirectory($directory);
        }
    }

    private function copyLegacyFile(string $path): void
    {
        $disk = Storage::disk('local');
        if ($disk->exists($path)) {
            return;
        }
        $source = Storage::disk('legacy_uploads')->readStream($path);
        if (! is_resource($source)) {
            throw new RuntimeException('Cannot read a legacy upload.');
        }
        $temporary = $path.'.import-'.bin2hex(random_bytes(8));
        try {
            if (! $disk->put($temporary, $source) || ! $disk->move($temporary, $path)) {
                throw new RuntimeException('Cannot preserve a legacy upload.');
            }
        } finally {
            $disk->delete($temporary);
            if (is_resource($source)) {
                fclose($source);
            }
        }
    }
}
