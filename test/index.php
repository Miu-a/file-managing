<?php declare(strict_types=1);

/**
 * Test runner for optimal/file-managing
 *
 * Usage:
 *   php test/index.php
 *
 * From a browser also works — point your docroot at the project root and open /test/.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

require __DIR__ . '/../vendor/autoload.php';

use Optimal\FileManaging\FileCommander;
use Optimal\FileManaging\ImagesManager;
use Optimal\FileManaging\FileUploader;
use Optimal\FileManaging\Resources\BitmapImageFileResource;
use Optimal\FileManaging\Resources\ImageManageGDResource;
use Optimal\FileManaging\Resources\ImageManageImagickResource;
use Optimal\FileManaging\Utils\FilesTypes;
use Optimal\FileManaging\Utils\IniInfo;
use Optimal\FileManaging\Utils\ImageCropSettings;
use Optimal\FileManaging\Utils\ImageResolutionSettings;
use Optimal\FileManaging\Utils\ImageResolutionsSettings;
use Optimal\FileManaging\Utils\FileUploaderUploadLimits;
use Optimal\FileManaging\Utils\SystemPaths;

$isCli = PHP_SAPI === 'cli';
$nl = $isCli ? "\n" : "<br>\n";
$hr = $isCli ? str_repeat('-', 70) . "\n" : "<hr>";

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<pre>";
}

$pass = 0;
$fail = 0;
$failures = [];

function ok(string $label, bool $cond, string $detail = ''): void
{
    global $pass, $fail, $failures, $nl;
    if ($cond) {
        $pass++;
        echo "  [PASS] {$label}{$nl}";
    } else {
        $fail++;
        $failures[] = "{$label} — {$detail}";
        echo "  [FAIL] {$label}" . ($detail ? " ({$detail})" : '') . "{$nl}";
    }
}

function section(string $name): void
{
    global $nl, $hr;
    echo "{$nl}{$hr}== {$name} =={$nl}";
}

// ----------------------------------------------------------------------------
// Setup sandbox + fixtures
// ----------------------------------------------------------------------------

$sandbox = __DIR__ . '/sandbox';
if (is_dir($sandbox)) {
    $rrmdir = static function (string $p) use (&$rrmdir): void {
        foreach (array_diff(scandir($p), ['.', '..']) as $f) {
            $full = $p . '/' . $f;
            is_dir($full) ? $rrmdir($full) : unlink($full);
        }
        rmdir($p);
    };
    $rrmdir($sandbox);
}
mkdir($sandbox, 0777, true);
mkdir($sandbox . '/source', 0777, true);
mkdir($sandbox . '/target', 0777, true);
mkdir($sandbox . '/tmp', 0777, true);

// SystemPaths reads $_SERVER. Spoof it for CLI runs.
$_SERVER['SCRIPT_FILENAME'] = $_SERVER['SCRIPT_FILENAME'] ?? __FILE__;
$_SERVER['SCRIPT_NAME']     = $_SERVER['SCRIPT_NAME']     ?? '/test/index.php';

// Force SystemPaths to use the project root so relative paths resolve there.
SystemPaths::$absolutePath = dirname(__DIR__);

// Detect what GD can encode on this build.
$hasJpeg = function_exists('imagejpeg');
$hasGif  = function_exists('imagegif');
$hasWebp = function_exists('imagewebp');

// Generate fixture images via GD.
function makeImage(string $path, int $w, int $h, array $rgb, string $format = 'png'): void
{
    $img = imagecreatetruecolor($w, $h);
    $color = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
    imagefilledrectangle($img, 0, 0, $w - 1, $h - 1, $color);
    $white = imagecolorallocate($img, 255, 255, 255);
    imagestring($img, 5, 10, 10, "{$w}x{$h}", $white);
    match ($format) {
        'jpg', 'jpeg' => imagejpeg($img, $path, 90),
        'gif'         => imagegif($img, $path),
        'webp'        => imagewebp($img, $path, 90),
        default       => imagepng($img, $path),
    };
    imagedestroy($img);
}

$src = $sandbox . '/source';
makeImage($src . '/sample.png',  800, 600, [200, 30, 30],  'png');
makeImage($src . '/banner.png', 1024, 768, [30, 120, 200], 'png');
if ($hasJpeg) {
    makeImage($src . '/photo.jpg', 1024, 768, [30, 120, 200], 'jpg');
}
if ($hasGif) {
    makeImage($src . '/tiny.gif', 120, 120, [60, 180, 60], 'gif');
}
file_put_contents($src . '/notes.txt', "hello world\n");
// Dummy "vector" — extension drives the type check, content doesn't matter.
file_put_contents($src . '/diagram.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"></svg>');

echo "Running file-managing test suite on PHP " . PHP_VERSION . $nl;
echo "Sandbox: {$sandbox}{$nl}";

// ----------------------------------------------------------------------------
// SystemPaths
// ----------------------------------------------------------------------------
section('SystemPaths');
ok('getScriptPath returns project root', SystemPaths::getScriptPath() === dirname(__DIR__));

// ----------------------------------------------------------------------------
// FilesTypes
// ----------------------------------------------------------------------------
section('Utils\\FilesTypes');
ok('IMAGES contains jpg', in_array('jpg', FilesTypes::IMAGES, true));
ok('BITMAP_IMAGES contains png', in_array('png', FilesTypes::BITMAP_IMAGES, true));
ok('DISALLOWED is array', is_array(FilesTypes::DISALLOWED));

// ----------------------------------------------------------------------------
// IniInfo
// ----------------------------------------------------------------------------
section('Utils\\IniInfo');
ok('toBytes 10K = 10240',     IniInfo::toBytes('10K') === 10240);
ok('toBytes 2M  = 2097152',   IniInfo::toBytes('2M')  === 2 * 1024 * 1024);
ok('toBytes 1G  = 1073741824', IniInfo::toBytes('1G') === 1024 * 1024 * 1024);
ok('toBytes plain 5000 = 5000', IniInfo::toBytes('5000') === 5000);
ok('getPostMaxSize > 0', IniInfo::getPostMaxSize() > 0);
ok('getMaxFileSize > 0', IniInfo::getMaxFileSize() > 0);

// ----------------------------------------------------------------------------
// ImageResolution(s)Settings
// ----------------------------------------------------------------------------
section('Utils\\ImageResolution(s)Settings');
$r = new ImageResolutionSettings(640, 480);
ok('ImageResolutionSettings width',  $r->getWidth() === 640);
ok('ImageResolutionSettings height', $r->getHeight() === 480);
$r->setWidth(800);
ok('ImageResolutionSettings setWidth', $r->getWidth() === 800);

$rs = new ImageResolutionsSettings();
$rs->addResolutionSettings(320, 240);
$rs->addResolutionSettingsByObject(new ImageResolutionSettings(1280, 720));
ok('ImageResolutionsSettings count', count($rs->getResolutionsSettings()) === 2);

// ----------------------------------------------------------------------------
// ImageCropSettings + FileUploaderUploadLimits — at least instantiate
// ----------------------------------------------------------------------------
section('Utils\\ImageCropSettings + FileUploaderUploadLimits');
ok('ImageCropSettings instantiates', (new ImageCropSettings()) instanceof ImageCropSettings);
ok('FileUploaderUploadLimits instantiates', (new FileUploaderUploadLimits()) instanceof FileUploaderUploadLimits);

// ----------------------------------------------------------------------------
// FileCommander
// ----------------------------------------------------------------------------
section('FileCommander');
$cmd = new FileCommander();
$cmd->setPath($src);
ok('setPath/getAbsolutePath', realpath($cmd->getAbsolutePath()) === realpath($src));

ok('isImage(jpg)',     FileCommander::isImage('jpg'));
ok('isImage(txt)=no', !FileCommander::isImage('txt'));
ok('isBitmapImage(png)', FileCommander::isBitmapImage('png'));
ok('isBitmapImage(svg)=no', !FileCommander::isBitmapImage('svg'));

ok('fileExists(sample, png)',  $cmd->fileExists('sample', 'png'));
ok('fileExists(missing)=no', !$cmd->fileExists('nope', 'png'));

// FileCommander::getImages only matches bitmap extensions (jpg/png/webp/gif/...),
// SVG is excluded by design.
$expectedImages = 2 /* sample.png, banner.png */
    + ($hasJpeg ? 1 : 0)
    + ($hasGif  ? 1 : 0);
$images = $cmd->getImages();
ok("getImages finds {$expectedImages} images", count($images) === $expectedImages, 'found ' . count($images));

$files = $cmd->getFiles();
ok('getFiles includes non-images', count($files) >= 1);

$searched = $cmd->searchImages('sample');
ok('searchImages(sample) finds 1', count($searched) === 1);

$img = $cmd->getImage('sample', 'png');
ok('getImage returns BitmapImageFileResource', $img instanceof BitmapImageFileResource);
ok('image width is 800',  $img->getWidth() === 800);
ok('image height is 600', $img->getHeight() === 600);

$svg = $cmd->getImage('diagram', 'svg');
ok('getImage(svg) returns VectorImageFileResource', $svg instanceof \Optimal\FileManaging\Resources\VectorImageFileResource);

// Directory ops
$cmd->setPath($sandbox);
$cmd->addDirectory('subdir');
ok('addDirectory created', is_dir($sandbox . '/subdir'));
$cmd->moveToDirectory('subdir');
ok('moveToDirectory', basename($cmd->getAbsolutePath()) === 'subdir');
$cmd->moveUp();
ok('moveUp', realpath($cmd->getAbsolutePath()) === realpath($sandbox));

$cmd->renameDir('subdir', 'renamed');
ok('renameDir', is_dir($sandbox . '/renamed') && !is_dir($sandbox . '/subdir'));

$cmd->removeDir('renamed');
ok('removeDir', !is_dir($sandbox . '/renamed'));

// File ops
$cmd->createFile('note', 'txt', 'first line');
ok('createFile', file_exists($sandbox . '/note.txt'));
$cmd->writeToFile('note', 'txt', "\nsecond line", true);
ok('writeToFile append', str_contains((string) file_get_contents($sandbox . '/note.txt'), 'second line'));

$cmd->renameFileTo('note', 'txt', 'renamed-note');
ok('renameFileTo', file_exists($sandbox . '/renamed-note.txt'));

$cmd->copyPasteFile('renamed-note', 'txt', 'duplicate-note', 'txt');
ok('copyPasteFile', file_exists($sandbox . '/duplicate-note.txt'));

$cmd->removeFile('renamed-note.txt');
$cmd->removeFile('duplicate-note.txt');
ok('removeFile', !file_exists($sandbox . '/renamed-note.txt'));

// Copy across directories
$cmd->setPath($sandbox . '/target');
$cmd->copyFileFromAnotherDirectory($src, 'sample', 'png');
ok('copyFileFromAnotherDirectory', file_exists($sandbox . '/target/sample.png'));
$cmd->copyFileToAnotherDirectory('sample', 'png', $sandbox . '/tmp', 'sample-copy');
ok('copyFileToAnotherDirectory', file_exists($sandbox . '/tmp/sample-copy.png'));

// ----------------------------------------------------------------------------
// ImagesManager + ImageManageGDResource
// ----------------------------------------------------------------------------
section('ImagesManager + GD resource');
$im = new ImagesManager();
$im->setSourceDirectory($src);

$gd = $im->loadImageManageResource('sample', 'png', ImagesManager::RESOURCE_TYPE_GD);
ok('loadImageManageResource returns GD', $gd instanceof ImageManageGDResource);

$gd->resize(400, 300);
ok('resize sets width 400',  $gd->getSourceImageResource()->getWidth() === 400);
ok('resize sets height 300', $gd->getSourceImageResource()->getHeight() === 300);

$gd2 = $im->loadImageManageResource('banner', 'png', ImagesManager::RESOURCE_TYPE_GD);
$gd2->maxResize(512, 512);
ok('maxResize bounded width', $gd2->getSourceImageResource()->getWidth() <= 512);
ok('maxResize bounded height', $gd2->getSourceImageResource()->getHeight() <= 512);

$gd3 = $im->loadImageManageResource('sample', 'png', ImagesManager::RESOURCE_TYPE_GD);
$gd3->rotate(90);
ok('rotate did not throw', true);

$gd4 = $im->loadImageManageResource('banner', 'png', ImagesManager::RESOURCE_TYPE_GD);
$gd4->cropImage(10, 10, 200, 150);
ok('cropImage did not throw', true);

$gd5 = $im->loadImageManageResource('sample', 'png', ImagesManager::RESOURCE_TYPE_GD);
$gd5->resize(200, 150);
$gd5->save($sandbox . '/target', 'sample-resized', 'png');
ok('save -> target', file_exists($sandbox . '/target/sample-resized.png'));

[$w, $h] = getimagesize($sandbox . '/target/sample-resized.png');
ok('saved image width=200',  $w === 200);
ok('saved image height=150', $h === 150);

// Convert format on save (requires GD jpeg support)
if ($hasJpeg) {
    $gd6 = $im->loadImageManageResource('sample', 'png', ImagesManager::RESOURCE_TYPE_GD);
    $gd6->save($sandbox . '/target', 'sample-converted', 'jpg');
    ok('save converts to jpg', file_exists($sandbox . '/target/sample-converted.jpg'));
} else {
    echo "  [SKIP] GD has no JPEG support — format-conversion test skipped{$nl}";
}

// ----------------------------------------------------------------------------
// ImagesManager + Imagick (only if ext-imagick present)
// ----------------------------------------------------------------------------
section('ImagesManager + Imagick resource');
if (extension_loaded('imagick')) {
    $imk = $im->loadImageManageResource('sample', 'png', ImagesManager::RESOURCE_TYPE_IMAGICK);
    ok('loadImageManageResource returns Imagick', $imk instanceof ImageManageImagickResource);
    $imk->resize(320, 240);
    ok('Imagick resize width 320',  $imk->getSourceImageResource()->getWidth() === 320);
    ok('Imagick resize height 240', $imk->getSourceImageResource()->getHeight() === 240);
    $imk->save($sandbox . '/target', 'imagick-out', 'png');
    ok('Imagick save -> target', file_exists($sandbox . '/target/imagick-out.png'));
} else {
    echo "  [SKIP] ext-imagick not loaded — Imagick tests skipped{$nl}";
}

// ----------------------------------------------------------------------------
// Error paths
// ----------------------------------------------------------------------------
section('Error paths');
try {
    $cmd->setPath('/path/that/does/not/exist/at/all');
    ok('DirectoryNotFoundException on missing path', false, 'no exception thrown');
} catch (\Optimal\FileManaging\Exception\DirectoryNotFoundException) {
    ok('DirectoryNotFoundException on missing path', true);
}

try {
    $im->loadImageManageResource('not-here', 'png');
    ok('FileNotFoundException on missing image', false, 'no exception thrown');
} catch (\Optimal\FileManaging\Exception\FileNotFoundException) {
    ok('FileNotFoundException on missing image', true);
}

try {
    $im->loadImageManageResource('sample', 'png', 'bogus-type');
    ok('Exception on unknown resource type', false, 'no exception thrown');
} catch (\Exception) {
    ok('Exception on unknown resource type', true);
}

// ----------------------------------------------------------------------------
// FileUploader — instantiate + non-upload helpers
// ----------------------------------------------------------------------------
section('FileUploader (no real upload)');
$_FILES = []; // singleton reads $_FILES in its constructor
$uploader = FileUploader::getInstance();
ok('FileUploader singleton', $uploader instanceof FileUploader);
ok('isPostFile=false when no files', $uploader->isPostFile('whatever') === false);
ok('countInputFiles=0 when no files', $uploader->countInputFiles('whatever') === 0);
$uploader->setTemporaryDirectory($sandbox . '/tmp');
$uploader->setTargetDirectory($sandbox . '/target');
$uploader->setMaxImageWidth(1920);
$uploader->setMaxImageHeight(1080);
ok('setMaxImageWidth/Height', $uploader->getMaxImageWidth() === 1920 && $uploader->getMaxImageHeight() === 1080);
$uploader->autoRotateImages(false);
$uploader->enableBackup(false);
$uploader->setImageManageResourceType(ImagesManager::RESOURCE_TYPE_GD);
ok('uploader setters do not throw', true);
ok('getSuccessMessages empty', $uploader->getSuccessMessages() === []);
ok('getErrorMessages empty',   $uploader->getErrorMessages() === []);
$uploader->clear();
ok('clear does not throw', true);

// ----------------------------------------------------------------------------
// Summary
// ----------------------------------------------------------------------------
echo "{$nl}{$hr}";
echo "Passed: {$pass}{$nl}";
echo "Failed: {$fail}{$nl}";
if ($fail > 0) {
    echo "{$nl}Failures:{$nl}";
    foreach ($failures as $f) {
        echo "  - {$f}{$nl}";
    }
}
echo "{$nl}Sandbox left at: {$sandbox}{$nl}";

if (!$isCli) {
    echo "</pre>";
}

exit($fail === 0 ? 0 : 1);
