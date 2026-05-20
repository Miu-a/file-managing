<?php declare(strict_types=1);

namespace Optimal\FileManaging;

use Exception;
use ImagickException;
use Optimal\FileManaging\Exception\DirectoryNotFoundException;
use Optimal\FileManaging\Exception\FileException;
use Optimal\FileManaging\Exception\FileNotFoundException;
use Optimal\FileManaging\Resources\BitmapImageFileResource;
use Optimal\FileManaging\Resources\ImageManageGDResource;
use Optimal\FileManaging\Resources\ImageManageResource;
use Optimal\FileManaging\Resources\ImageManageImagickResource;
use RuntimeException;

class ImagesManager
{

    public const RESOURCE_TYPE_GD      = "gd";
    public const RESOURCE_TYPE_IMAGICK = "imagick";

    private FileCommander $commander;

    public function __construct()
    {
        $this->commander = new FileCommander();
    }

    /**
     * @throws DirectoryNotFoundException
     */
    public function setSourceDirectory(string $dir): void
    {
        $validPath = FileCommander::checkPath($dir);
        $this->commander->setPath($validPath);
    }

    /**
     * @throws DirectoryNotFoundException
     */
    public function getSourceDirectory(): string
    {
        return $this->commander->getAbsolutePath();
    }

    /**
     * @throws DirectoryNotFoundException
     * @throws FileException
     * @throws FileNotFoundException
     * @throws ImagickException
     * @throws Exception
     */
    public function loadImageManageResource(string $imgName, ?string $imgExtension = null, string $resourceType = self::RESOURCE_TYPE_GD): ImageManageResource
    {

        if (empty($imgName)) {
            throw new FileException("Image name is required");
        }

        if (is_null($imgExtension)) {
            $imgExtension = pathinfo($this->commander->getAbsolutePath() . "/" . $imgName, PATHINFO_EXTENSION);
            $imgName = pathinfo($this->commander->getAbsolutePath() . "/" . $imgName, PATHINFO_FILENAME);
        }

        if ($this->commander->fileExists($imgName, $imgExtension)) {

            $image = $this->commander->getImage($imgName, $imgExtension);

            if ($resourceType === self::RESOURCE_TYPE_GD && $image instanceof BitmapImageFileResource) {
                return new ImageManageGDResource($image, $this->commander);
            }

            if ($resourceType === self::RESOURCE_TYPE_IMAGICK && $image instanceof BitmapImageFileResource) {
                return new ImageManageImagickResource($image, $this->commander);
            }

            if ($resourceType !== self::RESOURCE_TYPE_GD && $resourceType !== self::RESOURCE_TYPE_IMAGICK) {
                throw new RuntimeException('Wrong resource type');
            }

            throw new RuntimeException('Image is not bitmap!');
        }

        throw new FileNotFoundException("File: " . $imgName . "." . $imgExtension . " not found in " . $this->commander->getRelativePath());

    }

}