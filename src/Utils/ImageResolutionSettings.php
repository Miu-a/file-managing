<?php declare(strict_types=1);

namespace Optimal\FileManaging\Utils;

use Optimal\FileManaging\Resources\ImageManageResource;

class ImageResolutionSettings
{
    const EXTENSION_DEFAULT = "default";

    private ?int $width;
    private ?int $height;

    public function __construct(?int $width, ?int $height = null)
    {
        $this->width = $width;
        $this->height = $height;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function setWidth(?int $width): void
    {
        $this->width = $width;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function setHeight(?int $height): void
    {
        $this->height = $height;
    }

}