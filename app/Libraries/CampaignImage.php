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
     * Resizes/re-encodes the uploaded picture into a temporary file inside the uploads directory
     * (so the expensive part runs BEFORE a campaign row exists). Returns the absolute temp path;
     * commit() renames it to <campaign id>.jpg, discard() removes it.
     *
     * @throws InvalidArgumentException with a Gujarati message when the file cannot be processed
     */
    public function storeTemp(string $sourcePath, ?int $declaredSize = null): string
    {
        $this->validate($sourcePath, $declaredSize);

        $dir = $this->dir();

        if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
            log_message('error', 'campaign image: uploads directory cannot be created');

            throw new InvalidArgumentException(lang('App.image_store_failed'));
        }

        [$width, $height] = getimagesize($sourcePath);
        $edge = max((int) $width, (int) $height);
        $temp = $dir . DIRECTORY_SEPARATOR . 'tmp_' . uniqid('', true) . '.jpg';

        try {
            $image = service('image')->withFile($sourcePath);

            if ($edge > $this->config->imageMaxEdge) {
                $scale = $this->config->imageMaxEdge / $edge;
                $image->resize(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)), true, 'auto');
            }

            $image->convert(IMAGETYPE_JPEG)->save($temp, 85);
        } catch (Throwable $e) {
            log_message('error', 'campaign image failed: {class}: {message}', ['class' => $e::class, 'message' => $e->getMessage()]);
            $this->discard($temp);

            throw new InvalidArgumentException(lang('App.image_store_failed'));
        }

        if (! is_file($temp) || filesize($temp) === 0) {
            $this->discard($temp);

            throw new InvalidArgumentException(lang('App.image_store_failed'));
        }

        @chmod($temp, 0644);

        return $temp;
    }

    /**
     * Gives a temp file its final name. Returns the relative path stored in campaigns.image_path
     * (e.g. "uploads/campaigns/12.jpg").
     *
     * @throws InvalidArgumentException when the rename fails
     */
    public function commit(string $tempPath, int $campaignId): string
    {
        $target = $this->dir() . DIRECTORY_SEPARATOR . $campaignId . '.jpg';

        if (! @rename($tempPath, $target)) {
            log_message('error', 'campaign image: rename to final name failed');
            $this->discard($tempPath);

            throw new InvalidArgumentException(lang('App.image_store_failed'));
        }

        return trim($this->config->uploadsUrlPath, '/') . '/' . $campaignId . '.jpg';
    }

    /** Removes a temp file (silently; nothing to do when it is gone already). */
    public function discard(?string $tempPath): void
    {
        if ($tempPath !== null && is_file($tempPath)) {
            @unlink($tempPath);
        }
    }

    /**
     * One-step variant (storeTemp + commit) for callers that already have the campaign id.
     *
     * @throws InvalidArgumentException
     */
    public function store(string $sourcePath, int $campaignId): string
    {
        return $this->commit($this->storeTemp($sourcePath), $campaignId);
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
