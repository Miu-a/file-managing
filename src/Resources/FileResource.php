<?php declare(strict_types=1);

namespace Optimal\FileManaging\Resources;

use Exception;

class FileResource extends AbstractFileResource
{

    protected BitmapImageFileResource $previewImage;

    public function getPreviewImage(): AbstractFileResource
    {
        return $this->previewImage;
    }

    /**
     * @throws Exception
     */
    public function setPreviewImage(AbstractFileResource $previewImage): void
    {
        if (!$previewImage instanceof BitmapImageFileResource) {
            throw new Exception('Wrong object type, ImageFileResource is required');
        }
        $this->previewImage = $previewImage;
    }

}
