<?php declare(strict_types=1);

namespace Optimal\FileManaging\Resources;

use Optimal\FileManaging\Exception\DirectoryNotFoundException;
use Optimal\FileManaging\FileCommander;
use Optimal\FileManaging\Utils\SystemPaths;

abstract class AbstractFileResource
{

    protected ?string $name;
    protected ?string $extension;
    protected int $size;
    protected string $path;

    /**
     * @throws DirectoryNotFoundException
     */
    public function __construct(string $path, ?string $name = null, ?string $extension = null)
    {
        $validPath = FileCommander::checkPath($path);

        if (!is_dir($validPath)) {
            $name = (string) pathinfo($validPath, PATHINFO_FILENAME);
            $extension = (string) pathinfo($validPath, PATHINFO_EXTENSION);
            $validPath = (string) pathinfo($validPath, PATHINFO_DIRNAME);
        }
        else if ($extension === null) {
            $filePath = $validPath . "/" . $name;
            $name = (string) pathinfo($filePath, PATHINFO_FILENAME);
            $extension = (string) pathinfo($filePath, PATHINFO_EXTENSION);
        }

        FileCommander::checkPath($validPath . "/" . $name . "." . $extension);

        $this->name = $name;
        $this->extension = $extension;
        $this->path = $validPath;
        $this->setFileInfo();
    }

    protected function setFileInfo():void
    {
        $this->size = filesize($this->path . "/" . $this->name . "." . $this->extension);
    }

    public function getExtension(): string
    {
        return $this->extension;
    }

    public function setExtension(string $extension): void
    {
        $this->extension = $extension;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getNameExtension(): string
    {
        return $this->name . "." . $this->extension;
    }

    public function getFileDirectoryPath(): string
    {
        return $this->path;
    }

    public function setFileDirectoryPath($dir): void
    {
        $this->path = $dir;
    }

    public function getFilePath(): string
    {
        return $this->path . "/" . $this->getNameExtension();
    }

    public function getFileRelativePath(): string
    {
        return ltrim(str_replace(SystemPaths::getScriptPath(), "", $this->path), "/") . "/" . $this->name . "." . $this->extension;
    }

    public function getUrlToFile(): string
    {
        return SystemPaths::getBaseUrl() . "/" . $this->path . "/" . $this->name . "." . $this->extension;
    }

    public function getFileSize(): int
    {
        return $this->size;
    }

    public function parseString(string $string): string
    {
        $string = str_replace("{realName}", $this->getName(), $string);
        $string = str_replace("{realExtension}", $this->getExtension(), $string);
        $string = str_replace("{realNameEx}", $this->getNameExtension(), $string);
        $string = str_replace("{realFileSize}", (string) $this->getFileSize(), $string);

        return $string;
    }

}