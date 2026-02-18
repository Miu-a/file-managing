<?php declare(strict_types=1);

namespace Optimal\FileManaging;

use Exception;
use ImagickException;
use Optimal\FileManaging\Exception\CreateDirectoryException;
use Optimal\FileManaging\Exception\DeleteFileException;
use Optimal\FileManaging\Exception\DirectoryException;
use Optimal\FileManaging\Exception\DirectoryNotFoundException;
use Optimal\FileManaging\Exception\FileException;
use Optimal\FileManaging\Exception\FileNotFoundException;
use Optimal\FileManaging\Exception\UploadFileException;
use Optimal\FileManaging\Resources\UploadedFilesResource;
use Optimal\FileManaging\Utils\FilesTypes;
use Optimal\FileManaging\Utils\ImageCropSettings;
use Optimal\FileManaging\Utils\FileUploaderUploadLimits;

class FileUploader
{

    private static ?FileUploader $instance = null;
    private array $_FILES;
    private array $messages;
    private ?FileCommander $targetDirCommander;
    private ?FileCommander $tmpDirCommander;
    private ImagesManager $imagesManager;
    private string $imagesResourceType;
    private int $maxImageWidth;
    private int $maxImageHeight;
    private ?ImageCropSettings $imageCropSettings = null;
    private ?ImageCropSettings $imageThumbCropSettings = null;
    private FileUploaderUploadLimits $uploadLimits;
    private bool $autoRotateImages = true;
    private bool $backup = false;
    private array $successMessages;
    private array $errorMessages;
    private array $uploadedFiles;


    public static function getInstance(): FileUploader
    {
        if (is_null(self::$instance)) {
            self::$instance = new FileUploader();
        }

        return self::$instance;
    }

    private function __construct()
    {
        $this->uploadLimits = new FileUploaderUploadLimits();

        $this->imagesManager = new ImagesManager();

        $this->_FILES = [];

        foreach ($_FILES as $inputName => $filesItems) {
            if (is_array($filesItems["name"])) {
                foreach ($filesItems as $param => $values) {
                    foreach ($values as $key => $value) {
                        $this->_FILES[$inputName][$key][$param] = $value;
                        if ($param === "name") {
                            $this->_FILES[$inputName][$key]["only_name"] = strtolower(pathinfo($this->_FILES[$inputName][$key][$param], PATHINFO_FILENAME));
                            $this->_FILES[$inputName][$key]["only_extension"] = strtolower(pathinfo($this->_FILES[$inputName][$key][$param], PATHINFO_EXTENSION));
                        }
                    }
                }
            }
            else {
                foreach ($filesItems as $param => $value) {
                    $this->_FILES[$inputName][0][$param] = $value;
                    if ($param === "name") {
                        $this->_FILES[$inputName][0]["only_name"] = strtolower(pathinfo($this->_FILES[$inputName][0][$param], PATHINFO_FILENAME));
                        $this->_FILES[$inputName][0]["only_extension"] = strtolower(pathinfo($this->_FILES[$inputName][0][$param], PATHINFO_EXTENSION));
                    }
                }
            }
        }

        $this->maxImageWidth = 3840;
        $this->maxImageHeight = 2160;

        $this->uploadedFiles = [
            "images" => [],
            "files" => []
        ];
        $this->successMessages = [];
        $this->errorMessages = [];

        $this->messages = [
            "tooBig" => "Soubor: '{fileFull}' je příliš velký",
            "notFull" => "Soubor: '{fileFull}' byl nahrán pouze částečně",
            "otherProblem" => "S nahrávaným souborem: '{fileFull}' nastal nějaký problém",
            "notAllowed" => "Přípona: {fileExtension} souboru: '{fileName}' není povolena",
            "isEmpty" => "Soubor: '{fileFull}' je prázdný",
            "isTooLarge" => "Velikost souborů: '{fileFull}' je větší, než je povoleno",
            "wrongName" => "Název souboru: '{fileFull}' obsahuje podezřelé znaky",
            "success" => "Soubor: '{fileFull}' byl úspěšně nahrán",
            "nonSuccess" => "Soubor: '{fileFull}' nebyl úspěšně nahrán"
        ];

        $this->imagesResourceType = ImagesManager::RESOURCE_TYPE_GD;
    }

    public function enableBackup(bool $enable = false): void
    {
        $this->backup = $enable;
    }

    public function setMessages(array $messages): void
    {
        $this->messages = $messages;
    }

    private function parseMessage(string $msg, array $file): string
    {
        return strtr($msg, [
            '{fileName}' => $file['only_name'],
            '{fileExtension}' => $file['only_extension'],
            '{fileFull}' => "{$file['only_name']}.{$file['only_extension']}",
            '{fileSize}' => (string) $file['size'],
        ]);
    }

    public function setUploadLimits(FileUploaderUploadLimits $limits): void
    {
        $this->uploadLimits = $limits;
    }

    /**
     * @throws DirectoryNotFoundException
     */
    public function setTemporaryDirectory(string $directory): void
    {
        $this->tmpDirCommander = new FileCommander();
        $this->tmpDirCommander->setPath($directory);
    }

    /**
     * @throws DirectoryNotFoundException
     */
    public function setTargetDirectory(string $directory): void
    {
        $this->targetDirCommander = new FileCommander();
        $this->targetDirCommander->setPath($directory);
    }

    public function autoRotateImages(bool $rotate = true): void
    {
        $this->autoRotateImages = $rotate;
    }

    public function setImageManageResourceType(string $resource = ImagesManager::RESOURCE_TYPE_GD): void
    {
        $this->imagesResourceType = $resource;
    }

    public function getMaxImageWidth(): int
    {
        return $this->maxImageWidth;
    }

    public function setMaxImageWidth(int $maxImageWidth = 3840): void
    {
        $this->maxImageWidth = $maxImageWidth;
    }

    public function getMaxImageHeight(): int
    {
        return $this->maxImageHeight;
    }

    public function setMaxImageHeight(int $maxImageHeight = 2160): void
    {
        $this->maxImageHeight = $maxImageHeight;
    }

    public function isPostFile(string $inputName): bool
    {

        if (empty($this->_FILES)) {
            return false;
        }

        foreach ($this->_FILES as $key => $val) {

            if ($key !== $inputName) {
                continue;
            }

            if ($val[0]["error"] === 4) {
                return false;
            }
        }

        return true;
    }

    public function countInputFiles(string $inputName): int
    {
        if (!isset($this->_FILES[$inputName])) {
            return 0;
        }
        return count($this->_FILES[$inputName]);
    }

    public function getFileToUploadData(string $inputName, int $index): array
    {
        return $this->_FILES[$inputName][$index];
    }

    /**
     * @throws DirectoryException
     * @throws DirectoryNotFoundException
     * @throws CreateDirectoryException
     * @throws DeleteFileException
     */
    public function uploadFile(string $inputName, int $index, ?string $newFileName = null, bool $overwrite = true, ?callable $beforeUploadCallback = null, ?callable $afterUploadCallback = null): void
    {
        if (!$this->targetDirCommander || !$this->tmpDirCommander) {
            throw new DirectoryException("Temporary or target directory is not defined");
        }

        if ($this->checkFile($this->_FILES[$inputName][$index])) {

            $file = $this->_FILES[$inputName][$index];
            $newName = $newFileName ?? uniqid(more_entropy: true);

            if (!$overwrite) {
                $i = 1;
                if ($this->targetDirCommander->fileExists($newName, $file["only_extension"])) {
                    while ($this->targetDirCommander->fileExists($newName . "_" . $i, $file["only_extension"])) {
                        $i++;
                    }
                }
                $newName .= "_" . $i;
            }

            $this->moveFile($this->_FILES[$inputName][$index], $newName, $beforeUploadCallback, $afterUploadCallback);
        }
    }

    private function checkFile(array $file): bool
    {

        switch ($file['error']) {
            case 0:
                break;
            case 1:
            case 2:
                $this->errorMessages[] = $this->parseMessage($this->messages["tooBig"], $file);
                return false;
            case 3:
                $this->errorMessages[] = $this->parseMessage($this->messages["notFull"], $file);
                return false;
            default:
                $this->errorMessages[] = $this->parseMessage($this->messages["otherProblem"], $file);
                return false;
        }

        if ($file['size'] === 0) {
            $this->errorMessages[] = $this->parseMessage($this->messages["isEmpty"], $file);
            return false;
        }
        if ($file['size'] > $this->uploadLimits->getMaxFileSize()) {
            $this->errorMessages[] = $this->parseMessage($this->messages["isTooLarge"], $file);
            return false;
        }

        if (preg_match("/php|phtml\d*?/i", $file["only_extension"]) || in_array($file["only_extension"], FilesTypes::DISALLOWED, true) || !in_array($file["only_extension"], $this->uploadLimits->getAllowedExtensions(), true)) {
            $this->errorMessages[] = $this->parseMessage($this->messages["notAllowed"], $file);
            return false;
        }

        if (preg_match("/\/|\\|&|\||\?|\*/", $file["only_name"])) {
            $this->errorMessages[] = $this->parseMessage($this->messages["wrongName"], $file);
            return false;
        }

        return true;
    }

    /**
     * @throws DirectoryNotFoundException
     * @throws ImagickException
     * @throws Exception
     */
    private function moveFile(array $file, string $newName, ?callable $beforeUploadCallback = null, ?callable $afterUploadCallback = null): bool
    {

        if ($beforeUploadCallback) {
            $result = $beforeUploadCallback($file, $newName);
            if (is_array($result) && isset($result['newName'])) {
                $newName = $result['newName'];
            }
            if (is_bool($result) && !$result) {
                return false;
            }
        }

        $success = move_uploaded_file($file['tmp_name'], $this->tmpDirCommander->getAbsolutePath() . "/" . $newName . "." . $file["only_extension"]);

        if ($success) {
            $this->successMessages[] = $this->parseMessage($this->messages["success"], $file);
        }
        else {
            $this->successMessages[] = $this->parseMessage($this->messages["nonSuccess"], $file);
            return false;
        }

        if (FileCommander::isImage($file["only_extension"])) {

            if (FileCommander::isBitmapImage($file["only_extension"])) {

                $this->imagesManager->setSourceDirectory($this->tmpDirCommander->getRelativePath());

                $imageManageResource = $this->imagesManager->loadImageManageResource($newName, $file["only_extension"], $this->imagesResourceType);

                if ($this->autoRotateImages) {
                    $imageManageResource->autoRotate();
                }

                $imageManageResource->maxResize($this->maxImageWidth, $this->maxImageHeight);

                $imageManageResource->save($this->targetDirCommander->getRelativePath());
                $originalImageResource = $imageManageResource->getOutputImageResource();
                $originalImageResourceExt = $originalImageResource->getExtension();

                $thumbImageResource = null;
                if (!is_null($this->imageThumbCropSettings)) {

                    $this->imagesManager->setSourceDirectory($this->targetDirCommander->getRelativePath());

                    $imageManageResourceV = $this->imagesManager->loadImageManageResource($originalImageResource->getName(), $originalImageResourceExt, $this->imagesResourceType);

                    $imageManageResourceV->save($this->targetDirCommander->getRelativePath(), $newName . "-thumb");

                    $thumbImageResource = $imageManageResourceV->getOutputImageResource();
                }

                $currDir = $this->targetDirCommander->getRelativePath();

                if ($this->backup) {
                    $this->targetDirCommander->addDirectory("backup", true);
                    $this->targetDirCommander->copyFileFromAnotherDirectory($currDir, $newName, $file["only_extension"]);
                }

                $this->tmpDirCommander->removeFile($newName . "." . $file["only_extension"]);

                $this->targetDirCommander->moveUp();

                $this->uploadedFiles["images"][] = [
                    'original' => $originalImageResource,
                    'thumb' => $thumbImageResource
                ];

                if (isset($afterUploadCallback)) {
                    $afterUploadCallback($originalImageResource, $thumbImageResource);
                }

            }
            else {

                $this->tmpDirCommander->copyFileToAnotherDirectory($newName, $file["only_extension"], $this->targetDirCommander->getRelativePath());
                $this->tmpDirCommander->removeFile($newName . "." . $file["only_extension"]);

                $vectorImageResource = $this->targetDirCommander->getImage($newName, $file["only_extension"]);

                $this->uploadedFiles["images"] = ['original' => $vectorImageResource];

                if (isset($afterUploadCallback)) {
                    $afterUploadCallback($vectorImageResource);
                }

            }

        }
        else {
            $this->tmpDirCommander->copyFileToAnotherDirectory($newName, $file["only_extension"], $this->targetDirCommander->getRelativePath());
            $this->tmpDirCommander->removeFile($newName . "." . $file["only_extension"]);

            $fileResource = $this->targetDirCommander->getFile($newName, $file["only_extension"]);

            $this->uploadedFiles["files"][] = $fileResource;

            if (isset($afterUploadCallback)) {
                $afterUploadCallback($fileResource);
            }
        }

        return true;
    }

    public function getUploadedFiles(): UploadedFilesResource
    {
        return new UploadedFilesResource($this->uploadedFiles["files"], $this->uploadedFiles["images"]);
    }

    public function getSuccessMessages(): array
    {
        return $this->successMessages;
    }

    public function getErrorMessages(): array
    {
        return $this->errorMessages;
    }

    public function clear(): void
    {
        $this->uploadedFiles = [
            "images" => [],
            "files" => []
        ];
        $this->successMessages = [];
        $this->errorMessages = [];
    }

}