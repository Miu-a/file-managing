<?php declare(strict_types=1);

namespace Optimal\FileManaging\Resources;

use claviska\SimpleImage;
use Exception;
use Optimal\FileManaging\Exception\DeleteFileException;
use Optimal\FileManaging\Exception\DirectoryNotFoundException;
use Optimal\FileManaging\FileCommander;

abstract class ImageManageResource
{
    protected BitmapImageFileResource $image;
    protected SimpleImage $simpleImage;
    protected FileCommander $commander;

    public function __construct(BitmapImageFileResource $image, FileCommander $commander)
    {
        $this->image = $image;
        $this->commander = $commander;
    }

    public function getSourceImageResource(): BitmapImageFileResource
    {
        return $this->image;
    }

    public function getOutputImageResource(): BitmapImageFileResource
    {
        return  clone($this->image);
    }

    /**
     * @throws DeleteFileException
     * @throws DirectoryNotFoundException
     */
    public function removeOriginal(): void
    {
        $this->commander->removeFile($this->image->getNameExtension());
    }

    /**
     * @throws Exception
     */
    public function autoRotate(): void
    {
        $this->simpleImage->autoOrient();
    }

    abstract public function cropImage(int $x, int $y, int $width, int $height): void;

    abstract public function maxResize(?int $maxWidth = null, ?int $maxHeight = null): void;

    abstract public function resize(?int $width = null, ?int $height = null): void;

    abstract public function rotate(int $degree): void;

    abstract public function show(): void;

    abstract public function save(?string $myTarget = null, ?string $newName = null, ?string $newExtension = null): void;

}