<?php declare(strict_types=1);

namespace Optimal\FileManaging\Utils;

class ImageResolutionsSettings
{

    private array $resolutions = [];

    public function addResolutionSettingsByObject(ImageResolutionSettings $settings): void
    {
        $this->resolutions[] = $settings;
    }

    public function addResolutionSettings(?int $width, ?int $height = null): void
    {
        $this->resolutions[] = new ImageResolutionSettings($width, $height);
    }

    /**
     * @return ImageResolutionSettings[]
     */
    public function getResolutionsSettings(): array
    {
        return $this->resolutions;
    }

}