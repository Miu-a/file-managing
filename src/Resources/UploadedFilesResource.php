<?php declare(strict_types=1);

namespace Optimal\FileManaging\Resources;

class UploadedFilesResource
{
    private array $uploadedFiles;
    private array $uploadedImages;

    public function __construct(array $uploadedFiles, array $uploadedImages)
    {
        $this->uploadedFiles = $uploadedFiles;
        $this->uploadedImages = $uploadedImages;
    }

    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    public function getUploadedImages(): array
    {
        return $this->uploadedImages;
    }

}