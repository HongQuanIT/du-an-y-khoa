<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Enums\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Temporary image upload for the question rich editor (until Media module lands).
 */
final class EditorImageUploadController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can(Permission::QuestionUpdate->value)
            || $request->user()?->can(Permission::QuestionCreate->value), 403);

        $request->validate([
            'image' => ['required', 'image', 'max:5120', 'mimes:jpg,jpeg,png,gif,webp'],
        ]);

        $file = $request->file('image');
        assert($file !== null);

        $name = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs('question-editor/'.now()->format('Y/m'), $name, 'public');

        return response()->json([
            'url' => Storage::disk('public')->url($path),
        ]);
    }
}
