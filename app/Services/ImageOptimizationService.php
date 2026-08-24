<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ImageOptimizationService
{
    public function store(UploadedFile $file, string $directory, string $baseName, array $metadata = []): string
    {
        $disk = Storage::disk(config('media.disk', 'public'));
        $directory = trim($directory, '/');
        $disk->makeDirectory($directory);

        $path = $directory.'/'.$this->filename($baseName, 'webp');
        $this->encodeWebp($file->getRealPath(), $disk->path($path), $this->metadata($metadata));

        return $path;
    }

    /**
     * Store a storefront AVIF plus a Google Merchant compatible WebP sibling.
     * The returned path always points to the AVIF used by the storefront.
     */
    public function storeProduct(UploadedFile $file, string $directory, string $baseName, array $metadata = []): string
    {
        $disk = Storage::disk(config('media.disk', 'public'));
        $directory = trim($directory, '/');
        $disk->makeDirectory($directory);

        $path = $directory.'/'.$this->filename($baseName, 'avif');
        $merchantPath = $this->merchantCompanionPath($path);
        $preparedMetadata = $this->metadata($metadata);

        $this->encodeAvif($file->getRealPath(), $disk->path($path), $preparedMetadata);
        $this->encodeWebp($file->getRealPath(), $disk->path($merchantPath), $preparedMetadata);

        return $path;
    }

    public function optimizeStored(string $path, string $directory, string $baseName, array $metadata = []): string
    {
        $disk = Storage::disk(config('media.disk', 'public'));

        if (! $disk->exists($path)) {
            throw new RuntimeException("Image [{$path}] does not exist on the media disk.");
        }

        $directory = trim($directory, '/');
        $disk->makeDirectory($directory);
        $newPath = $directory.'/'.$this->filename($baseName, 'webp');
        $temporary = tempnam(sys_get_temp_dir(), 'prinx-media-');

        if ($temporary === false) {
            throw new RuntimeException('Unable to create a temporary file for image optimization.');
        }

        try {
            file_put_contents($temporary, $disk->get($path));
            $this->encodeWebp($temporary, $disk->path($newPath), $this->metadata($metadata));

            if ($newPath !== $path) {
                $disk->delete($path);
            }
        } finally {
            @unlink($temporary);
        }

        return $newPath;
    }

    public function deleteLocal(?string $path): void
    {
        if (! $path || Str::startsWith($path, ['http://', 'https://', '/'])) {
            return;
        }

        $disk = Storage::disk(config('media.disk', 'public'));
        $paths = [$path];

        if (Str::endsWith(Str::lower($path), '.avif')) {
            $paths[] = $this->merchantCompanionPath($path);
        }

        $disk->delete(array_values(array_unique($paths)));
    }

    public function merchantCompanionPath(string $path): string
    {
        return preg_replace('/\.avif$/i', '.merchant.webp', $path) ?: $path.'.merchant.webp';
    }

    private function filename(string $baseName, string $extension): string
    {
        $slug = Str::limit(Str::slug($baseName) ?: 'prinx-product-image', 100, '');

        return $slug.'-'.Str::lower(Str::random(8)).'.'.$extension;
    }

    private function encodeAvif(string $source, string $destination, array $metadata): void
    {
        $quality = max(70, min(100, (int) config('media.avif_quality', 90)));
        [$maxWidth, $maxHeight] = $this->maxDimensions();

        if ($this->imagickSupports('AVIF')) {
            $this->encodeWithImagick($source, $destination, 'avif', $quality, $maxWidth, $maxHeight, $metadata);
            return;
        }

        if (extension_loaded('gd') && function_exists('imageavif')) {
            $this->encodeWithGd($source, $destination, 'avif', $quality, $maxWidth, $maxHeight);
            return;
        }

        throw new RuntimeException('AVIF conversion requires Imagick/libheif with AVIF support or GD compiled with AVIF support. Enable AVIF support on the server before uploading product images.');
    }

    private function encodeWebp(string $source, string $destination, array $metadata): void
    {
        $quality = max(70, min(100, (int) config('media.webp_quality', 90)));
        [$maxWidth, $maxHeight] = $this->maxDimensions();

        if ($this->imagickSupports('WEBP')) {
            $this->encodeWithImagick($source, $destination, 'webp', $quality, $maxWidth, $maxHeight, $metadata);
            return;
        }

        if (extension_loaded('gd') && function_exists('imagewebp')) {
            $this->encodeWithGd($source, $destination, 'webp', $quality, $maxWidth, $maxHeight);
            return;
        }

        throw new RuntimeException('WebP conversion requires Imagick or GD compiled with WebP support. This WebP copy is required for Google Merchant product images.');
    }

    private function encodeWithImagick(
        string $source,
        string $destination,
        string $format,
        int $quality,
        int $maxWidth,
        int $maxHeight,
        array $metadata,
    ): void {
        $image = new \Imagick();

        try {
            $image->readImage($source);
            $image->setIteratorIndex(0);
            $image->autoOrient();
            $image->setImageColorspace(\Imagick::COLORSPACE_SRGB);

            $digitalSourceType = $this->sourceDigitalSourceType($image);
            $width = $image->getImageWidth();
            $height = $image->getImageHeight();
            $scale = min(1, $maxWidth / max(1, $width), $maxHeight / max(1, $height));

            if ($scale < 1) {
                $image->resizeImage(
                    max(1, (int) round($width * $scale)),
                    max(1, (int) round($height * $scale)),
                    \Imagick::FILTER_LANCZOS,
                    1,
                    true,
                );
            }

            // Remove camera/private metadata, then write only intentional ecommerce metadata.
            $image->stripImage();
            $image->setImageFormat($format);
            $image->setImageCompressionQuality($quality);

            if ($format === 'avif') {
                $image->setOption('heic:speed', (string) max(0, min(9, (int) config('media.avif_effort', 6))));
                $image->setOption('heic:chroma', '444');
            } else {
                $image->setOption('webp:method', '6');
                $image->setOption('webp:alpha-quality', '100');
            }

            $image->setImageProperty('comment', $metadata['description']);
            $image->setImageProfile('xmp', $this->xmpPacket($metadata, $digitalSourceType));

            if (! $image->writeImage($destination)) {
                throw new RuntimeException('Imagick could not write the optimized '.strtoupper($format).' image.');
            }
        } catch (Throwable $exception) {
            throw new RuntimeException('Image conversion failed: '.$exception->getMessage(), previous: $exception);
        } finally {
            $image->clear();
            $image->destroy();
        }
    }

    private function encodeWithGd(
        string $source,
        string $destination,
        string $format,
        int $quality,
        int $maxWidth,
        int $maxHeight,
    ): void {
        $contents = file_get_contents($source);
        $sourceImage = $contents !== false ? @imagecreatefromstring($contents) : false;

        if (! $sourceImage) {
            throw new RuntimeException('GD could not read the uploaded image.');
        }

        try {
            $width = imagesx($sourceImage);
            $height = imagesy($sourceImage);
            $scale = min(1, $maxWidth / max(1, $width), $maxHeight / max(1, $height));
            $targetWidth = max(1, (int) round($width * $scale));
            $targetHeight = max(1, (int) round($height * $scale));
            $target = imagecreatetruecolor($targetWidth, $targetHeight);
            imagealphablending($target, false);
            imagesavealpha($target, true);
            $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
            imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $transparent);
            imagecopyresampled($target, $sourceImage, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

            $written = $format === 'avif'
                ? imageavif($target, $destination, $quality, max(0, min(9, (int) config('media.avif_effort', 6))))
                : imagewebp($target, $destination, $quality);

            imagedestroy($target);

            if (! $written) {
                throw new RuntimeException('GD could not write the optimized '.strtoupper($format).' image.');
            }
        } finally {
            imagedestroy($sourceImage);
        }
    }

    private function maxDimensions(): array
    {
        return [
            max(1500, (int) config('media.max_width', 2000)),
            max(1500, (int) config('media.max_height', 2000)),
        ];
    }

    private function imagickSupports(string $format): bool
    {
        return extension_loaded('imagick')
            && class_exists(\Imagick::class)
            && count(\Imagick::queryFormats($format)) > 0;
    }

    private function metadata(array $metadata): array
    {
        return [
            'title' => trim((string) ($metadata['title'] ?? config('app.name'))),
            'description' => trim((string) ($metadata['description'] ?? config('store.description'))),
            'keywords' => array_values(array_unique(array_filter(array_map('trim', $metadata['keywords'] ?? [])))),
            'creator' => trim((string) ($metadata['creator'] ?? config('media.creator'))),
            'credit' => trim((string) ($metadata['credit'] ?? config('media.credit'))),
            'copyright' => trim((string) ($metadata['copyright'] ?? config('media.copyright_notice'))),
            'rights_url' => trim((string) ($metadata['rights_url'] ?? config('media.rights_url'))),
            'source_url' => trim((string) ($metadata['source_url'] ?? '')),
            'identifier' => trim((string) ($metadata['identifier'] ?? '')),
            'brand' => trim((string) ($metadata['brand'] ?? '')),
            'category' => trim((string) ($metadata['category'] ?? '')),
        ];
    }

    private function sourceDigitalSourceType(\Imagick $image): ?string
    {
        try {
            $xmp = (string) $image->getImageProfile('xmp');
        } catch (Throwable) {
            return null;
        }

        foreach (['trainedAlgorithmicMedia', 'compositeSynthetic', 'algorithmicMedia'] as $type) {
            if (stripos($xmp, $type) !== false) {
                return $type;
            }
        }

        return null;
    }

    private function xmpPacket(array $metadata, ?string $digitalSourceType = null): string
    {
        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $keywords = collect($metadata['keywords'])
            ->map(fn (string $keyword): string => '<rdf:li>'.$escape($keyword).'</rdf:li>')
            ->implode('');
        $digitalSource = $digitalSourceType
            ? ' Iptc4xmpExt:DigitalSourceType="'.$escape('http://cv.iptc.org/newscodes/digitalsourcetype/'.$digitalSourceType).'"'
            : '';

        return '<?xpacket begin="﻿" id="W5M0MpCehiHzreSzNTczkc9d"?>'.
            '<x:xmpmeta xmlns:x="adobe:ns:meta/" x:xmptk="Prinx Ecommerce Media Optimizer">'.
            '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'.
            '<rdf:Description rdf:about="" '.
                'xmlns:dc="http://purl.org/dc/elements/1.1/" '.
                'xmlns:photoshop="http://ns.adobe.com/photoshop/1.0/" '.
                'xmlns:xmpRights="http://ns.adobe.com/xap/1.0/rights/" '.
                'xmlns:Iptc4xmpCore="http://iptc.org/std/Iptc4xmpCore/1.0/xmlns/" '.
                'xmlns:Iptc4xmpExt="http://iptc.org/std/Iptc4xmpExt/2008-02-29/" '.
                'photoshop:Credit="'.$escape($metadata['credit']).'" '.
                'Iptc4xmpCore:Credit="'.$escape($metadata['credit']).'" '.
                'xmpRights:Marked="True" '.
                'xmpRights:WebStatement="'.$escape($metadata['rights_url']).'"'.$digitalSource.'>'.
                '<dc:title><rdf:Alt><rdf:li xml:lang="x-default">'.$escape($metadata['title']).'</rdf:li></rdf:Alt></dc:title>'.
                '<dc:description><rdf:Alt><rdf:li xml:lang="x-default">'.$escape($metadata['description']).'</rdf:li></rdf:Alt></dc:description>'.
                '<dc:creator><rdf:Seq><rdf:li>'.$escape($metadata['creator']).'</rdf:li></rdf:Seq></dc:creator>'.
                ($metadata['identifier'] !== '' ? '<dc:identifier>'.$escape($metadata['identifier']).'</dc:identifier>' : '').
                ($metadata['source_url'] !== '' ? '<dc:source>'.$escape($metadata['source_url']).'</dc:source>' : '').
                ($metadata['copyright'] !== '' ? '<dc:rights><rdf:Alt><rdf:li xml:lang="x-default">'.$escape($metadata['copyright']).'</rdf:li></rdf:Alt></dc:rights>' : '').
                ($keywords !== '' ? '<dc:subject><rdf:Bag>'.$keywords.'</rdf:Bag></dc:subject>' : '').
            '</rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>';
    }
}
