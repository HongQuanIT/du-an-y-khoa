<?php

declare(strict_types=1);

namespace Modules\Media\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use Modules\Admin\Support\Auditor;
use Modules\Media\Models\Media;

final class UpdateMediaMetadataAction
{
    use AsAction;

    /**
     * @param  array{alt?: string|null, caption?: string|null, credit?: string|null, is_premium?: bool}  $input
     */
    public function handle(User $actor, Media $media, array $input): Media
    {
        $before = $media->only(['alt', 'caption', 'credit', 'is_premium']);

        $trim = static function (mixed $value): ?string {
            if ($value === null) {
                return null;
            }

            $text = trim(strip_tags((string) $value));

            return $text === '' ? null : $text;
        };

        $media->fill([
            'alt' => array_key_exists('alt', $input) ? $trim($input['alt']) : $media->alt,
            'caption' => array_key_exists('caption', $input) ? $trim($input['caption']) : $media->caption,
            'credit' => array_key_exists('credit', $input) ? $trim($input['credit']) : $media->credit,
            'is_premium' => array_key_exists('is_premium', $input)
                ? (bool) $input['is_premium']
                : $media->is_premium,
        ]);
        $media->save();

        Auditor::record(
            'media.update',
            $actor,
            $media,
            $before,
            $media->only(['alt', 'caption', 'credit', 'is_premium']),
        );

        return $media->refresh();
    }
}
