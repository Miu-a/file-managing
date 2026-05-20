<?php declare(strict_types=1);

namespace Optimal\FileManaging\Resources;

use Optimal\FileManaging\Utils\FilesTypes;

class BitmapImageFileResource extends AbstractImageFileResource
{

    protected int $width;
    protected int $height;

    protected function setFileInfo():void
    {
        parent::setFileInfo();

        [$width, $height] = getimagesize($this->path . "/" . $this->name . "." . $this->extension);
        $this->width = $width;
        $this->height = $height;

    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function setWidth(int $width): static
    {
        $this->width = $width;
        return $this;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function setHeight(int $height): static
    {
        $this->height = $height;
        return $this;
    }

    public function isWebp(): bool
    {
        return in_array($this->extension, FilesTypes::IMAGES_WEBP, true);
    }

    public function isJPG(): bool
    {
        return in_array($this->extension, FilesTypes::IMAGES_JPG, true);
    }

    public function isPNG(): bool
    {
        return in_array($this->extension, FilesTypes::IMAGES_PNG, true);
    }

    public function isGIF(): bool
    {
        return in_array($this->extension, FilesTypes::IMAGES_GIF, true);
    }

    public function parseString(string $string): string
    {

        $string = parent::parseString($string);

        $string = str_replace("{width}", (string) $this->width, $string);
        $string = str_replace("{height}", (string) $this->height, $string);

        return $string;
    }

}