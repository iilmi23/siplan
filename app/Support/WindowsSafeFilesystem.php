<?php

namespace App\Support;

use Illuminate\Filesystem\Filesystem;

class WindowsSafeFilesystem extends Filesystem
{
    /**
     * Windows can occasionally reject an atomic rename while compiling Blade views.
     * Fall back to copy so local development does not fail with "Access is denied".
     */
    public function replace($path, $content, $mode = null): void
    {
        clearstatcache(true, $path);

        $path = (string) $path;
        $tempPath = tempnam(dirname($path), basename($path));

        if (! is_null($mode)) {
            @chmod($tempPath, $mode);
        } else {
            @chmod($tempPath, 0777 - umask());
        }

        file_put_contents($tempPath, $content);

        if (@rename($tempPath, $path)) {
            return;
        }

        if (@copy($tempPath, $path)) {
            @unlink($tempPath);

            return;
        }

        rename($tempPath, $path);
    }
}
