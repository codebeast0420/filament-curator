<?php

namespace Awcodes\Curator\View\Components;

use Awcodes\Curator\Models\Media;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\Component;

class Curation extends Component
{
    public ?array $curatedMedia = null;

    public function __construct(
        public int | Media | null $media,
        public ?string $curation = null,
    ) {
        if (! $media instanceof Media) {
            $this->media = app(Media::class)::where('id', $media)->first();
        }

        if ($this->media) {
            $this->curatedMedia = $this->media->getCuration($curation);
        }
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View | string | Closure
    {
        if ($this->curatedMedia) {
            $disk = $this->curatedMedia['disk'];
            $path = $this->curatedMedia['path'];
            $isPrivate = false;

            if (
                config('curator.should_check_exists', true)
                && Storage::disk($disk)->exists($path) === false
            ) {
                return '';
            }

            try {
                $isPrivate = config('curator.visibility', 'public') === 'private'
                    || Storage::disk($disk)->getVisibility($path) === 'private ';
            } catch (\Throwable) {
                // ACL not supported on Storage Bucket, Laravel only throws exception here so need to be careful.
                // so we assume it's private $isPrivate = config(sprintf('filesystems.disks.%s.visibility', $this->disk)) !== 'public';
            }

            $this->curatedMedia['url'] = $isPrivate
                ? Storage::disk($disk)->temporaryUrl($path, now()->addMinutes(5))
                : Storage::disk($disk)->url($path);
        }

        return function (array $data) {
            return 'curator::components.curation';
        };
    }
}
