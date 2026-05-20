<?php declare(strict_types=1);

namespace Optimal\FileManaging\Resources;

use Imagick;
use ImagickException;
use Optimal\FileManaging\Exception\DeleteFileException;
use Optimal\FileManaging\Exception\DirectoryNotFoundException;
use Optimal\FileManaging\Exception\FileException;
use Optimal\FileManaging\FileCommander;

final class ImageManageImagickResource extends ImageManageResource
{
    protected Imagick $resource;

    /**
     * @param BitmapImageFileResource $image
     * @param FileCommander $commander
     * @throws ImagickException
     */
    public function __construct(BitmapImageFileResource $image, FileCommander $commander)
    {
        parent::__construct($image, $commander);
        $this->resource = new Imagick($image->getFilePath());
    }

    /**
     * @return Imagick
     */
    public function getImagick(): Imagick
    {
        return $this->resource;
    }

    /**
     * @throws ImagickException
     */
    public function autoRotate(): void
    {
        switch ($this->resource->getImageOrientation()) {
            case Imagick::ORIENTATION_TOPRIGHT:
                $this->resource->flopImage();
                break;
            case Imagick::ORIENTATION_BOTTOMRIGHT:
                $this->resource->rotateImage('#000', 180);
                break;
            case Imagick::ORIENTATION_BOTTOMLEFT:
                $this->resource->flopImage();
                $this->resource->rotateImage('#000', 180);
                break;
            case Imagick::ORIENTATION_LEFTTOP:
                $this->resource->flopImage();
                $this->resource->rotateImage('#000', -90);
                break;
            case Imagick::ORIENTATION_RIGHTTOP:
                $this->resource->rotateImage('#000', 90);
                break;
            case Imagick::ORIENTATION_RIGHTBOTTOM:
                $this->resource->flopImage();
                $this->resource->rotateImage('#000', 90);
                break;
            case Imagick::ORIENTATION_LEFTBOTTOM:
                $this->resource->rotateImage('#000', -90);
                break;
        }
        $this->resource->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
    }

    /**
     * @throws ImagickException
     */
    public function rotate(int $degree): void
    {
        $this->resource->rotateImage('#000', $degree);
    }

    /**
     * @throws ImagickException
     */
    public function maxResize(?int $maxWidth = null, ?int $maxHeight = null): void
    {
        $imgWidth = $this->resource->getImageWidth();
        $imgHeight = $this->resource->getImageHeight();

        if (($maxWidth !== null && $imgWidth > $maxWidth) || ($maxHeight !== null && $imgHeight > $maxHeight)) {
            if ($imgWidth >= $imgHeight) {
                $this->resource->resizeImage((int) $maxWidth, 0, Imagick::FILTER_LANCZOS, 1);
            }
            else {
                $this->resource->resizeImage(0, (int) $maxHeight, Imagick::FILTER_LANCZOS, 1);
            }
        }

        $this->image->setWidth($this->resource->getImageWidth());
        $this->image->setHeight($this->resource->getImageHeight());
    }

    /**
     * @throws ImagickException
     */
    public function resize(?int $width = null, ?int $height = null): void
    {
        $this->resource->resizeImage($width ?? 0, $height ?? 0, Imagick::FILTER_LANCZOS, 1);
        $this->image->setWidth($this->resource->getImageWidth());
        $this->image->setHeight($this->resource->getImageHeight());
    }

    /**
     * @throws ImagickException
     */
    public function cropImage(int $x, int $y, int $width, int $height): void
    {
        $this->resource->cropImage($width, $height, $x, $y);
        $this->resource->setImagePage(0, 0, 0, 0);
    }

    /**
     * @throws ImagickException
     */
    public function show(): void
    {
        header('Content-Type: image/' . $this->resource->getImageFormat());
        echo $this->resource->getImageBlob();
    }

    /**
     * @throws ImagickException
     * @throws DeleteFileException
     * @throws DirectoryNotFoundException
     * @throws FileException
     */
    public function save(?string $myTarget = null, ?string $newName = null, ?string $newExtension = null): void
    {
        $sameNameInSameDir = false;

        if ((is_null($newName) || $this->image->getName() === $newName) && $this->image->getFileDirectoryPath() === $myTarget) {
            $sameNameInSameDir = true;
        }

        $pom = "";
        if ($sameNameInSameDir && $this->image->getFileDirectoryPath() === $myTarget) {
            $pom = "_";
        }

        if (!is_null($myTarget)) {
            $this->commander->setPath($myTarget);
        }
        else {
            $this->commander->setPath($this->image->getFileDirectoryPath());
        }

        if (!is_null($newName)) {
            $filesWithSameName = $this->commander->searchImages($newName);
        }
        else {
            $filesWithSameName = $this->commander->searchImages($this->image->getName());
        }

        if (!empty($filesWithSameName)) {
            foreach ($filesWithSameName as $file) {
                if ($file->getExtension() !== $this->image->getExtension()) {
                    $this->commander->removeFile($file->getNameExtension());
                }
            }
        }

        $extension = $newExtension ?? $this->image->getExtension();
        $name = $newName ?? $this->image->getName();

        $fileDestination = $this->commander->getAbsolutePath() . "/" . $pom . $name . '.' . $extension;
        $finalDestination = $this->commander->getAbsolutePath() . "/" . $name . '.' . $extension;

        $this->resource->setImageFormat(($extension === "jpg" || $extension === 'jpeg') ? 'jpeg' : $extension);
        $this->resource->writeImage($fileDestination);

        if ($sameNameInSameDir) {
            $this->commander->removeFile($this->image->getNameExtension());
            rename($fileDestination, $finalDestination);
        }

        $this->commander->setPath($this->image->getFileDirectoryPath());

        if (!is_null($newName)) {
            $this->image->setName($newName);
        }
        if (!is_null($newExtension)) {
            $this->image->setExtension($newExtension);
        }
        if (!is_null($myTarget)) {
            $this->image->setFileDirectoryPath($myTarget);
        }
    }

}