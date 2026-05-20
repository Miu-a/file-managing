<?php declare(strict_types=1);

namespace Optimal\FileManaging;

use Optimal\FileManaging\Exception\CreateDirectoryException;
use Optimal\FileManaging\Exception\CreateFileException;
use Optimal\FileManaging\Exception\DeleteDirectoryException;
use Optimal\FileManaging\Exception\DeleteFileException;
use Optimal\FileManaging\Exception\DirectoryException;
use Optimal\FileManaging\Exception\DirectoryNotFoundException;
use Optimal\FileManaging\Exception\FileException;
use Optimal\FileManaging\Exception\FileNotFoundException;
use Optimal\FileManaging\Resources\AbstractImageFileResource;
use Optimal\FileManaging\Resources\BitmapImageFileResource;
use Optimal\FileManaging\Resources\VectorImageFileResource;
use Optimal\FileManaging\Utils\FilesTypes;
use Optimal\FileManaging\Utils\SystemPaths;
use Optimal\FileManaging\Resources\FileResource;
use RuntimeException;

class FileCommander
{
    private ?string $actualPath = null;
    private ?string $basePath = null;

    public static function getValidPath(string $path): ?string
    {
        $path = str_replace("\\", "/", $path);

        if (file_exists($path)) {
            return $path;
        }

        $path = SystemPaths::getScriptPath() . "/" . $path;
        if (file_exists($path)) {
            return $path;
        }

        return null;
    }

    /**
     * @throws DirectoryNotFoundException
     */
    public static function checkPath(string $path): string
    {
        $validPath = self::getValidPath($path);
        $relative = true;

        if ($validPath === SystemPaths::getScriptPath() . "/" . $path && $validPath === $path) {
            $relative = false;
        }

        if (is_null($validPath)) {
            throw new DirectoryNotFoundException("Directory with " . ($relative ? "relative" : "absolute") . " path: '" . $path . "' " . ($relative ? " and with absolute path: '" . SystemPaths::getScriptPath() . "/" . $path . "'" : "") . " not found ");
        }

        return $validPath;
    }

    public static function isImage(string $extension): bool
    {
        return in_array(strtolower($extension), FilesTypes::IMAGES, true);
    }

    public static function isBitmapImage(string $extension): bool
    {
        return in_array(strtolower($extension), FilesTypes::BITMAP_IMAGES, true);
    }

    /**
     * @throws DirectoryNotFoundException
     */
    public function setPath(string $path): void
    {
        $path = self::checkPath($path);

        $this->actualPath = $path;
        $this->basePath = $path;
    }

    /**
     * @throws DirectoryNotFoundException
     */
    public function getAbsolutePath(): string
    {
        if (is_null($this->actualPath)) {
            throw new DirectoryNotFoundException("No directory set");
        }

        if (str_contains($this->actualPath, SystemPaths::getScriptPath())) {
            return $this->actualPath;
        }

        return SystemPaths::getScriptPath() . "/" . $this->actualPath;
    }

    /**
     * @throws DirectoryNotFoundException
     */
    public function getRelativePath(): string
    {
        if (is_null($this->actualPath)) {
            throw new DirectoryNotFoundException("No directory set");
        }
        return ltrim(str_replace(SystemPaths::getScriptPath(), "", $this->actualPath), "/");
    }

    /**
     * @throws DirectoryNotFoundException
     */
    public function getUrlToDirectory(): string
    {
        return SystemPaths::getUrlDomain() . "/" . $this->getRelativePath();
    }

    private function sortFiles(array $files): array
    {
        usort($files, "strnatcasecmp");
        return $files;
    }

    /**
     * @throws DirectoryNotFoundException
     */
    public function moveToDirectory(string $name): void
    {
        if (is_null($this->actualPath)) {
            throw new DirectoryNotFoundException("No directory set");
        }

        $name = rtrim($name, "/");

        if ($this->directoryExists($name)) {
            $this->actualPath .= "/" . $name;
        }
        else {
            throw new DirectoryNotFoundException("Directory: " . $this->actualPath . "/" . $name . " is not exists");
        }
    }

    /**
     * @throws DirectoryNotFoundException
     */
    public function moveUp(): void
    {
        if (is_null($this->actualPath)) {
            throw new DirectoryNotFoundException("No directory set");
        }

        if ($this->basePath !== $this->actualPath) {
            $parts = explode("/", $this->actualPath);
            unset($parts[count($parts) - 1]);
            $this->actualPath = implode("/", $parts);
        }
    }

    /**
     * @throws DirectoryNotFoundException
     */
    public function directoryExists(string $name): bool
    {
        if (is_null($this->actualPath)) {
            throw new DirectoryNotFoundException("No directory set");
        }
        return file_exists($this->actualPath . "/" . $name) && is_dir($this->actualPath . "/" . $name);
    }

    /**
     * @throws CreateDirectoryException
     * @throws DirectoryNotFoundException
     */
    public function addDirectory(string $dirName, bool $moveToDir = false, int $chmod = 0777): void
    {
        if (is_null($this->actualPath)) {
            throw new DirectoryNotFoundException("No directory set");
        }

        $dirName = rtrim($dirName, "/");

        if (!$this->directoryExists($dirName)) {
            umask(0000);
            if (!mkdir($concurrentDirectory = $this->actualPath . "/" . $dirName, $chmod) && !is_dir($concurrentDirectory)) {
                throw new CreateDirectoryException("Creating directory " . $dirName . " in " . $this->actualPath . " is not successful, maybe access rights problem.");
            }
        }

        if ($moveToDir) {
            $this->moveToDirectory($dirName);
        }
    }

    /**
     * @throws DirectoryNotFoundException
     */
    public function getDirectories(bool $sort = true): array
    {
        if (is_null($this->actualPath)) {
            throw new DirectoryNotFoundException("No directory set");
        }

        $dirs = array_filter(scandir($this->actualPath), function ($v) {
            return is_dir($this->actualPath . "/" . $v) && !in_array($v, [".", ".."], true);
        });

        if ($sort) {
            return $this->sortFiles($dirs);
        }

        return $dirs;
    }

    /**
     * @throws DirectoryNotFoundException
     */
    public function searchDirectories(string $regex = ".*", bool $sort = true): array
    {
        if (is_null($this->actualPath)) {
            throw new DirectoryNotFoundException("No directory set");
        }

        $dirs = preg_grep("~$regex~", array_filter(scandir($this->actualPath), function ($v) {
            return is_dir($this->actualPath . "/" . $v) && !in_array($v, [".", ".."], true);
        }));

        if ($sort) {
            return $this->sortFiles($dirs);
        }

        return $dirs;
    }

    /**
     * @throws DeleteDirectoryException
     * @throws DeleteFileException
     * @throws DirectoryNotFoundException
     */
    public function removeDir(?string $name = null): void
    {
        if (is_null($this->actualPath)) {
            throw new DirectoryNotFoundException("No directory set");
        }

        $path = $this->actualPath;
        if ($name) {
            $path .= "/" . $name;
            if (!$this->directoryExists($name)) {
                throw new DirectoryNotFoundException("Directory : " . $path . " not found");
            }
        }

        $this->DeleteDir($path);

        if (!rmdir($path)) {
            throw new DeleteDirectoryException("Remove directory: " . $path . " wasn't successful");
        }
    }

    /**
     * @throws DeleteDirectoryException
     * @throws DeleteFileException
     * @throws DirectoryNotFoundException
     */
    public function clearDir(?string $name = null): void
    {
        if (is_null($this->actualPath)) {
            throw new DirectoryNotFoundException("No directory set");
        }

        $path = $this->actualPath;
        if (!is_null($name)) {
            $path .= "/" . $name;
            if (!$this->directoryExists($name)) {
                throw new DirectoryNotFoundException("Directory : " . $path . " not found");
            }
        }

        $this->DeleteDir($path);
    }

    /**
     * @throws DeleteDirectoryException
     * @throws DeleteFileException
     */
    private function DeleteDir(string $path): void
    {
        $files = array_diff(scandir($path), ['.', '..']);

        foreach ($files as $file) {
            if (is_dir($path . '/' . $file)) {
                $this->DeleteDir($path . '/' . $file);
                if (!rmdir($path . '/' . $file)) {
                    throw new DeleteDirectoryException("Remove directory: " . $path . "/" . $file . " wasn't successful");
                }
            }
            elseif (!unlink($path . '/' . $file)) {
                throw new DeleteFileException("Remove file: " . $path . "/" . $file . " wasn't successful");
            }
        }
    }

    /**
     * @throws DirectoryException
     * @throws DirectoryNotFoundException
     */
    public function renameDir(string $name, string $newName, bool $recursive = false): void
    {
        if (is_null($this->actualPath)) {
            throw new DirectoryNotFoundException("No directory set");
        }

        if ($this->directoryExists($name)) {
            if (rename($this->actualPath . '/' . $name, $this->actualPath . "/" . $newName)) {
                if ($recursive) {
                    $this->renameDirRec($this->actualPath, $name, $newName);
                }
            }
            else {
                throw new DirectoryException("Renaming directory: " . $this->actualPath . "/" . $name . " is not successful");
            }
        }
        else {
            throw new DirectoryNotFoundException("Directory : " . $this->actualPath . "/" . $name . " not found");
        }
    }

    /**
     * @throws DirectoryException
     * @throws DirectoryNotFoundException
     */
    private function renameDirRec(?string $path, string $name, string $newName): void
    {
        foreach ($this->getDirectories() as $dir) {

            if ($dir === $name) {
                if (!rename($path . '/' . $dir, $path . "/" . $newName)) {
                    throw new DirectoryException("Renaming directory: " . $path . "/" . $dir . " is not successful");
                }
            }

            $this->renameDirRec($path . '/' . $dir, $name, $newName);
        }
    }

    /**
     * @throws DirectoryNotFoundException
     */
    public function fileExists(string $name, ?string $extension = null): bool
    {
        if (is_null($this->actualPath)) {
            throw new DirectoryNotFoundException("No directory set");
        }

        if (is_null($extension)) {
            $parts = explode(".", $name);
            $name = $parts[0];
            $extension = $parts[1];
        }

        return file_exists($this->actualPath . "/" . $name . "." . $extension);
    }

    /**
     * @return FileResource[]
     * @throws DirectoryNotFoundException
     * @throws FileException
     */
    protected function getFilesRegex(string $pattern = ".*", bool $sort = true): array
    {
        if (is_null($this->actualPath)) {
            throw new DirectoryNotFoundException("No directory set");
        }

        $foundFiles = preg_grep("~$pattern~", array_filter(scandir($this->actualPath), function ($v) {
            return !is_dir($this->actualPath . '/' . $v);
        }));

        if ($sort) {
            $foundFiles = $this->sortFiles($foundFiles);
        }

        $fileResources = [];
        $actualPath = (string) $this->actualPath;

        foreach ($foundFiles as $file) {
            if (!self::isImage(pathinfo($this->actualPath . "." . $file, PATHINFO_EXTENSION))) {
                $fileResources[] = new FileResource($actualPath, $file);
            }
        }

        return $fileResources;
    }

    /**
     * @throws DirectoryNotFoundException
     * @throws FileNotFoundException
     */
    public function getFile(string $name, ?string $extension = null): FileResource
    {
        if (is_null($extension)) {
            $name = (string) pathinfo($this->actualPath . "/" . $name, PATHINFO_FILENAME);
            $extension = (string) pathinfo($this->actualPath . "/" . $name, PATHINFO_EXTENSION);
        }

        if (!$this->fileExists($name, $extension)) {
            throw new FileNotFoundException("File: " . $this->actualPath . "/" . $name . "." . $extension . " not found");
        }

        $actualPath = (string) $this->actualPath;
        return new FileResource($actualPath, $name, $extension);
    }

    /**
     * @return FileResource[]
     * @throws DirectoryNotFoundException
     * @throws FileException
     */
    public function getFiles(bool $sort = true): array
    {
        return $this->getFilesRegex(".*", $sort);
    }

    /**
     * @return FileResource[]
     * @throws DirectoryNotFoundException
     * @throws FileException
     */
    public function searchFiles(string $pattern = ".*", bool $sort = true): array
    {
        return $this->getFilesRegex($pattern, $sort);
    }

    /**
     * @return AbstractImageFileResource[]
     * @throws DirectoryNotFoundException
     */
    protected function getImagesRegex(string $pattern = ".*", bool $sort = true, bool $addBackupImage = true, bool $addThumbs = true): array
    {
        if (is_null($this->actualPath)) {
            throw new DirectoryNotFoundException("No directory set");
        }

        $foundImages = preg_grep("~$pattern\.(jpg|JPG|jpeg|JPEG|jfif|png|webp|gif|tiff|bmp)~", array_filter(scandir($this->actualPath), function ($v) {
            return !is_dir($this->actualPath . '/' . $v);
        }));

        if ($sort) {
            $foundImages = $this->sortFiles($foundImages);
        }

        $imageResources = [];

        foreach ($foundImages as $image) {
            $name = pathinfo($this->actualPath . "./" . $image, PATHINFO_FILENAME);
            $ext = pathinfo($this->actualPath . "./" . $image, PATHINFO_EXTENSION);
            $imageResources[] = $this->getImage($name, $ext, $addBackupImage, $addThumbs);
        }

        return $imageResources;
    }

    /**
     * @throws DirectoryNotFoundException
     */
    public function getImage(string $name, ?string $extension = null, bool $addBackupImage = true, bool $addThumb = true): AbstractImageFileResource
    {
        if (is_null($this->actualPath)) {
            throw new DirectoryNotFoundException("No directory set");
        }

        if (is_null($extension)) {
            $extension = (string) pathinfo($this->actualPath . "/" . $name, PATHINFO_EXTENSION);
            $name = (string) pathinfo($this->actualPath . "/" . $name, PATHINFO_FILENAME);
        }

        $actualPath = (string) $this->actualPath;

        if (self::isBitmapImage($extension)) {
            return new BitmapImageFileResource($actualPath, $name, $extension);
        }

        return new VectorImageFileResource($actualPath, $name, $extension);
    }

    /**
     * @return AbstractImageFileResource[]
     * @throws DirectoryNotFoundException
     * @throws FileException
     */
    public function getImages(bool $sort = true): array
    {
        return $this->getImagesRegex(".*", $sort);
    }

    /**
     * @return AbstractImageFileResource[]
     * @throws DirectoryNotFoundException
     * @throws FileException
     */
    public function searchImages(string $pattern = ".*", bool $sort = true): array
    {
        return $this->getImagesRegex($pattern, $sort);
    }

    /**
     * @throws CreateFileException
     * @throws DirectoryNotFoundException
     */
    public function createFile(string $name, ?string $extension = null, string $content = "\n"): bool
    {
        if (is_null($this->actualPath)) {
            throw new DirectoryNotFoundException("No directory set");
        }

        if ($name === "") {
            throw new CreateFileException("No file name is defined");
        }

        if (is_null($extension)) {
            $name = (string) pathinfo($this->actualPath . "/" . $name, PATHINFO_FILENAME);
            $extension = (string) pathinfo($this->actualPath . "/" . $name, PATHINFO_EXTENSION);
        }

        if ($extension === "") {
            throw new CreateFileException("Extension of file: " . $name . " is not defined");
        }

        if (in_array($extension, FilesTypes::DISALLOWED, true)) {
            throw new CreateFileException("Extension: " . $extension . " of file: " . $name . " is not allowed");
        }

        if (!$this->fileExists($name, $extension)) {
            $f = fopen($this->actualPath . "/" . $name . "." . $extension, 'wb+');
            fwrite($f, $content);
            fclose($f);
            return true;
        }

        return false;
    }

    /**
     * @throws CreateFileException
     * @throws DirectoryNotFoundException
     */
    public function writeToFile(string $name, ?string $extension, string $content = "\n", bool $append = true): void
    {
        if (is_null($this->actualPath)) {
            throw new DirectoryNotFoundException("No directory set");
        }

        if ($name === "") {
            throw new CreateFileException("No file name is defined");
        }

        if (is_null($extension)) {
            $name = (string) pathinfo($this->actualPath . "/" . $name, PATHINFO_FILENAME);
            $extension = (string) pathinfo($this->actualPath . "/" . $name, PATHINFO_EXTENSION);
        }

        if ($extension === "") {
            throw new CreateFileException("Extension of file: " . $name . " is not defined");
        }

        if ($append) {
            $currentData = file_get_contents($this->actualPath . "/" . $name . "." . $extension);
            file_put_contents($this->actualPath . "/" . $name . "." . $extension, $currentData . $content);
        }
        else {
            file_put_contents($this->actualPath . "/" . $name . "." . $extension, $content);
        }
    }

    /**
     * @throws DirectoryNotFoundException
     * @throws FileException
     */
    public function renameFileTo(string $name, ?string $extension = null, string $newName = ""): bool
    {
        if (is_null($this->actualPath)) {
            throw new DirectoryNotFoundException("No directory set");
        }

        if ($name === "") {
            throw new FileException("No file name is defined");
        }

        if ($newName === "") {
            throw new FileException("No file new name is defined");
        }

        if (is_null($extension)) {
            $name = (string) pathinfo($this->actualPath . "/" . $name, PATHINFO_FILENAME);
            $extension = (string) pathinfo($this->actualPath . "/" . $name, PATHINFO_EXTENSION);
        }

        return rename($this->actualPath . "/" . $name . "." . $extension, $this->actualPath . "/" . $newName . "." . $extension);
    }

    /**
     * @throws DirectoryNotFoundException
     * @throws FileNotFoundException
     */
    public function copyPasteFile(string $name, string $extension, string $targetName, string $targetExtension): void
    {
        if (is_null($this->actualPath)) {
            throw new DirectoryNotFoundException("No directory set");
        }

        if ($this->fileExists($name, $extension)) {
            copy($this->actualPath . "/" . $name . "." . $extension, $this->actualPath . "/" . $targetName . "." . $targetExtension);
        }
        else {
            throw new FileNotFoundException("File " . $this->actualPath . "/" . $name . "." . $extension . " not found");
        }
    }

    /**
     * @throws DirectoryNotFoundException
     */
    public function copyFileFromAnotherDirectory(string $path, ?string $name = null, ?string $extension = null, ?string $renameTo = null): bool
    {
        if (is_null($this->actualPath)) {
            throw new DirectoryNotFoundException("No directory set");
        }

        $validPath = self::checkPath($path);

        if (!is_dir($validPath)) {
            $name = (string) pathinfo($validPath, PATHINFO_FILENAME);
            $extension = (string) pathinfo($validPath, PATHINFO_EXTENSION);
            $validPath = pathinfo($validPath, PATHINFO_DIRNAME);
        }
        elseif (is_null($extension)) {
            $name = (string) pathinfo($validPath . "/" . $name, PATHINFO_FILENAME);
            $extension = (string) pathinfo($validPath . "/" . $name, PATHINFO_EXTENSION);
        }

        $validPath = self::checkPath($validPath . "/" . $name . "." . $extension);

        return copy($validPath, $this->actualPath . "/" . ($renameTo ?? $name) . "." . $extension);
    }

    /**
     * @throws DirectoryNotFoundException
     * @throws FileNotFoundException
     */
    public function copyFileToAnotherDirectory(string $name, ?string $extension, string $path, ?string $renameTo = null): bool
    {
        if (is_null($this->actualPath)) {
            throw new DirectoryNotFoundException("No directory set");
        }

        if (is_null($extension)) {
            $name = (string) pathinfo($this->actualPath . "/" . $name, PATHINFO_FILENAME);
            $extension = (string) pathinfo($this->actualPath . "/" . $name, PATHINFO_EXTENSION);
        }

        if (!$this->fileExists($name, $extension)) {
            throw new FileNotFoundException("File " . $this->actualPath . "/" . $name . "." . $extension . " not found");
        }

        $validPath = self::checkPath($path);

        return copy($this->actualPath . "/" . $name . "." . $extension, $validPath . "/" . ($renameTo ?? $name) . "." . $extension);
    }

    /**
     * @throws DeleteFileException
     * @throws DirectoryNotFoundException
     */
    public function removeFile(string $pattern): void
    {
        if (is_null($this->actualPath)) {
            throw new DirectoryNotFoundException("No directory set");
        }

        if ($pattern === "") {
            throw new DeleteFileException("No pattern defined to delete file/s");
        }

        $files = glob($this->actualPath . "/" . $pattern);

        foreach ($files as $file) {
            if (!unlink($file)) {
                throw new DeleteFileException("Removing file: " . $this->actualPath . "/" . $file . " is not successful");
            }
        }
    }

    private function copyDirectoryToRecursive(string $targetPath, string $destinationPath, int $permissions = 775): void
    {
        $dir = dir($targetPath);
        while (false !== $entry = $dir->read()) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (is_file($targetPath . "/" . $entry)) {
                copy($targetPath . "/" . $entry, $destinationPath . "/" . $entry);
            }
            else {
                if (!mkdir($destinationPath, $permissions) && !is_dir($destinationPath)) {
                    throw new RuntimeException(sprintf('Directory "%s" was not created', $destinationPath));
                }
                $this->copyDirectoryToRecursive($targetPath . "/" . $entry, $targetPath . "/" . $entry);
            }
        }

        $dir->close();
    }

    /**
     * @throws DirectoryNotFoundException
     */
    public function copyDirectoryTo(string $destPath, int $permissions = 775): void
    {
        if (is_null($this->actualPath)) {
            throw new DirectoryNotFoundException("No directory set");
        }

        $validPath = self::checkPath($destPath);
        $this->copyDirectoryToRecursive($this->getAbsolutePath(), $validPath, $permissions);
    }

}
