<?php

namespace WpComet\AISays\Tests\Unit;

use PHPUnit\Framework\TestCase;

class MimeAndImageTest extends TestCase
{
    /**
     * @dataProvider mimeTypeProvider
     */
    public function test_mime_type_cleaning(string $rawMime, string $expectedMime): void
    {
        $mime = $rawMime;
        if (false !== strpos($mime, ';')) {
            $parts = explode(';', $mime);
            $mime = trim($parts[0]);
        }
        $mime = strtolower($mime);

        $this->assertSame($expectedMime, $mime);
    }

    public function mimeTypeProvider(): array
    {
        return [
            'Clean jpeg' => ['image/jpeg', 'image/jpeg'],
            'Mime with charset' => ['image/jpeg; charset=binary', 'image/jpeg'],
            'Uppercase PNG' => ['IMAGE/PNG', 'image/png'],
            'Mime with extra whitespace' => ['image/webp ; charset=utf-8', 'image/webp'],
            'AVIF mime' => ['image/avif', 'image/avif'],
        ];
    }

    /**
     * @dataProvider imageOptimizationDecisionProvider
     */
    public function test_image_needs_optimization_rules(string $mime, string $path, int $sizeBytes, bool $expectedNeedsOpt): void
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $is_avif = ('avif' === $ext || 'image/avif' === $mime);
        $needs_opt = $is_avif || !in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true) || $sizeBytes > 300 * 1024;

        $this->assertSame($expectedNeedsOpt, $needs_opt);
    }

    public function imageOptimizationDecisionProvider(): array
    {
        return [
            'Standard small JPEG' => ['image/jpeg', '/uploads/item.jpg', 50 * 1024, false],
            'Standard small PNG' => ['image/png', '/uploads/item.png', 120 * 1024, false],
            'Standard small WebP' => ['image/webp', '/uploads/item.webp', 80 * 1024, false],
            'Large JPEG > 300KB' => ['image/jpeg', '/uploads/huge.jpg', 500 * 1024, true],
            'AVIF image by extension' => ['image/jpeg', '/uploads/photo.avif', 40 * 1024, true],
            'AVIF image by mime' => ['image/avif', '/uploads/photo.bin', 40 * 1024, true],
            'Unsupported format (BMP)' => ['image/bmp', '/uploads/icon.bmp', 20 * 1024, true],
            'Unsupported format (TIFF)' => ['image/tiff', '/uploads/doc.tiff', 100 * 1024, true],
        ];
    }
}
