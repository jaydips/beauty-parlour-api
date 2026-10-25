<?php

declare(strict_types=1);

namespace App\Libraries;

use Config\WhatsApp as WhatsAppConfig;
use InvalidArgumentException;
use Throwable;

/**
 * The optional picture of an offer campaign. Uploads (JPEG, PNG or WebP, at most imageMaxBytes) are
 * resized to imageMaxEdge and re-encoded as JPEG into <uploadsDir>/<campaign id>.jpg, which lives
 * inside the web document root (on the host: public_html/<domain>/uploads/campaigns/) so the bridge
 * and the app can fetch it by URL. Text-only campaigns never touch this class.
 */
class CampaignImage
{
    private const MIMES = ['image/jpeg' => IMAGETYPE_JPEG, 'image/png' => IMAGETYPE_PNG, 'image/webp' => IMAGETYPE_WEBP];

    private readonly WhatsAppConfig $config;

    public function __construct(?WhatsAppConfig $config = null)
    {
        $this->config = $config ?? config('WhatsApp');
    }

    public function dir(): string
    {
        return rtrim($this->config->uploadsDir ?? (FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'campaigns'), '/\\');
    }

    /**
     * Rejects anything that is not a JPEG/PNG/WebP picture within the size limit.
     *
     * @throws InvalidArgumentException with a Gujarati message for the owner
     */
    public function validate(string $path, ?int $declaredSize = null): void
    {
        $size = $declaredSize ?? (is_file($path) ? (int) filesize($path) : 0);

        if (! is_file($path) || $size <= 0) {
            throw new InvalidArgumentException(lang('App.image_invalid'));
        }

        if ($size > $this->config->imageMaxBytes) {
            throw new InvalidArgumentException(lang('App.image_too_large', [(int) round($this->config->imageMaxBytes / 1048576)]));
        }

        $info = @getimagesize($path);

        if ($info === false || ! in_array($info[2], self::MIMES, true)) {
            throw new InvalidArgumentException(lang('App.image_invalid'));
        }
    }

    /**
     * Resizes/re-encodes the uploaded picture for campaign $campaignId. Returns the relative path
     * stored in campaigns.image_path (e.g. "uploads/campaigns/12.jpg").
     *
     * @throws InvalidArgumentException when the file cannot be processed
     */
    public function store(string $sourcePath, int $campaignId): string
    {
        $this->validate($sourcePath);

        $dir = $this->dir();

        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new InvalidArgumentException(lang('App.image_invalid'));
        }

        [$width, $height] = getimagesize($sourcePath);
        $edge   = max((int) $width, (int) $height);
        $target = $dir . DIRECTORY_SEPARATOR . $campaignId . '.jpg';

        try {
            $image = service('image')->withFile($sourcePath);

            if ($edge > $this->config->imageMaxEdge) {
                $scale = $this->config->imageMaxEdge / $edge;
                $image->resize(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)), true, 'auto');
            }

            $image->convert(IMAGETYPE_JPEG)->save($target, 85);
        } catch (Throwable $e) {
            log_message('error', 'campaign image failed: {message}', ['message' => $e->getMessage()]);

            throw new InvalidArgumentException(lang('App.image_invalid'));
        }

        @chmod($target, 0644);

        return trim($this->config->uploadsUrlPath, '/') . '/' . $campaignId . '.jpg';
    }

    /**
     * Absolute https URL of a stored image (null when the campaign has none).
     */
    public function url(?string $imagePath): ?string
    {
        return $imagePath !== null && $imagePath !== '' ? base_url($imagePath) : null;
    }

    public function delete(?string $imagePath): void
    {
        if ($imagePath === null || $imagePath === '') {
            return;
        }

        $file = $this->dir() . DIRECTORY_SEPARATOR . basename($imagePath);

        if (is_file($file)) {
            @unlink($file);
        }
    }
}
