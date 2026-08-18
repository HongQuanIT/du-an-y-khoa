<?php

declare(strict_types=1);

namespace Modules\Media\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Media\Support\Enums\MediaType;

final class StoreMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $videoMax = (int) config('media.video_max_kb', 102400);

        return [
            'file' => ['required', 'file', 'max:'.$videoMax],
            'alt' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', Rule::enum(MediaType::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $file = $this->file('file');
            if ($file === null) {
                return;
            }

            $mime = (string) ($file->getMimeType() ?: $file->getClientMimeType());
            $imageMimes = (array) config('media.image_mimes', []);
            $videoMimes = (array) config('media.video_mimes', []);
            $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');

            $isImage = str_starts_with($mime, 'image/') && in_array($ext, $imageMimes, true);
            $isVideo = str_starts_with($mime, 'video/') && in_array($ext, $videoMimes, true);

            if (! $isImage && ! $isVideo) {
                $validator->errors()->add('file', 'Chỉ chấp nhận ảnh (jpg, png, gif, webp) hoặc video (mp4, webm, mov).');

                return;
            }

            $maxKb = $isImage
                ? (int) config('media.image_max_kb', 10240)
                : (int) config('media.video_max_kb', 102400);

            if (($file->getSize() ?: 0) > $maxKb * 1024) {
                $label = $isImage ? 'ảnh' : 'video';
                $mb = (int) round($maxKb / 1024);
                $validator->errors()->add('file', "Dung lượng {$label} tối đa {$mb} MB.");
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Vui lòng chọn tệp để tải lên.',
            'file.max' => 'Tệp vượt quá dung lượng cho phép.',
        ];
    }
}
