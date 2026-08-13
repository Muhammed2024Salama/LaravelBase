<?php

declare(strict_types=1);

namespace MuhammedSalama\Base\Traits;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

trait ImageUploadTrait
{
    public function uploadImage(Request $request, string $inputName, string $path): ?string
    {
        if (! $request->hasFile($inputName)) {
            return null;
        }

        $image = $request->file($inputName);

        return $image instanceof UploadedFile
            ? $this->storeUploadedImage($image, $path)
            : null;
    }

    /**
     * @return array<int, string>
     */
    public function uploadMultiImage(Request $request, string $inputName, string $path): array
    {
        $paths = [];

        if (! $request->hasFile($inputName)) {
            return $paths;
        }

        foreach ((array) $request->file($inputName) as $image) {
            if (! $image instanceof UploadedFile) {
                continue;
            }

            $stored = $this->storeUploadedImage($image, $path);

            if ($stored !== null) {
                $paths[] = $stored;
            }
        }

        return $paths;
    }

    /**
     * Replace an image. The previous file is only removed once the replacement
     * has been written successfully, so a rejected or failed upload never
     * destroys the existing image.
     */
    public function updateImage(Request $request, string $inputName, string $path, ?string $oldPath = null): ?string
    {
        if (! $request->hasFile($inputName)) {
            return null;
        }

        $image = $request->file($inputName);

        if (! $image instanceof UploadedFile) {
            return null;
        }

        $stored = $this->storeUploadedImage($image, $path);

        if ($stored === null) {
            return null;
        }

        if ($oldPath !== null && $oldPath !== $stored) {
            $this->deleteImage($oldPath);
        }

        return $stored;
    }

    public function deleteImage(string $path): void
    {
        $relative = $this->normaliseRelativePath($path);

        if ($relative === null) {
            return;
        }

        $absolute = public_path($relative);

        if (File::exists($absolute) && File::isFile($absolute)) {
            File::delete($absolute);
        }
    }

    /**
     * Detected MIME type => extension the file will be stored with.
     *
     * Override in the consuming class to widen or narrow the allow-list.
     * Only add types that are inert when served from the web root — never
     * add image/svg+xml (scriptable), text/* or application/* entries.
     *
     * @return array<string, string>
     */
    protected function allowedImageMimeTypes(): array
    {
        return [
            'image/jpeg' => 'jpg',
            'image/pjpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            'image/bmp' => 'bmp',
            'image/x-ms-bmp' => 'bmp',
            'image/tiff' => 'tiff',
        ];
    }

    /**
     * Move a validated upload into place and return its public-relative path,
     * or null when the file is not an allowed image or cannot be written.
     */
    protected function storeUploadedImage(UploadedFile $image, string $path): ?string
    {
        if (! $image->isValid()) {
            return null;
        }

        $extension = $this->resolveImageExtension($image);
        $relativePath = $this->normaliseRelativePath($path);

        if ($extension === null || $relativePath === null) {
            return null;
        }

        $imageName = 'media_'.Str::random(32).'.'.$extension;

        try {
            $image->move(public_path($relativePath), $imageName);
        } catch (Throwable) {
            return null;
        }

        return $relativePath.'/'.$imageName;
    }

    /**
     * Resolve the storage extension from the *detected* MIME type.
     * Returns null for anything outside the allow-list.
     */
    protected function resolveImageExtension(UploadedFile $image): ?string
    {
        try {
            $mime = $image->getMimeType();
        } catch (Throwable) {
            return null;
        }

        if (! is_string($mime)) {
            return null;
        }

        return $this->allowedImageMimeTypes()[strtolower($mime)] ?? null;
    }

    /**
     * Reduce a caller-supplied directory to a safe, public_path()-relative path.
     * Rejects absolute paths and any '..' traversal segment.
     */
    protected function normaliseRelativePath(string $path): ?string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = trim($path, '/');

        if ($path === '') {
            return null;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '..' || $segment === '.' || $segment === '') {
                return null;
            }
        }

        return $path;
    }
}
