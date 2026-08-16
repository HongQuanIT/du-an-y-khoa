<?php

declare(strict_types=1);

namespace Modules\Media\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enums\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Modules\Media\Actions\DeleteMediaAction;
use Modules\Media\Actions\RegisterExternalMediaAction;
use Modules\Media\Actions\UpdateMediaMetadataAction;
use Modules\Media\Actions\UploadMediaAction;
use Modules\Media\Http\Requests\RegisterExternalMediaRequest;
use Modules\Media\Http\Requests\StoreMediaRequest;
use Modules\Media\Http\Requests\UpdateMediaRequest;
use Modules\Media\Models\Media;
use Modules\Media\Support\Enums\MediaStatus;
use Modules\Media\Support\Enums\MediaType;
use RuntimeException;

final class MediaController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizePermission(Permission::MediaView);

        $query = Media::query()->latest('id');

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('original_name', 'like', "%{$search}%")
                    ->orWhere('alt', 'like', "%{$search}%")
                    ->orWhere('caption', 'like', "%{$search}%")
                    ->orWhere('path', 'like', "%{$search}%");
            });
        }

        if ($type = $request->query('type')) {
            $query->where('type', (string) $type);
        }

        if ($status = $request->query('status')) {
            $query->where('status', (string) $status);
        }

        $items = $query->paginate(36)->withQueryString();

        $stats = [
            'total' => Media::query()->count(),
            'images' => Media::query()->where('type', MediaType::Image->value)->count(),
            'videos' => Media::query()->where('type', MediaType::Video->value)->count(),
        ];

        return view('media::admin.index', [
            'items' => $items,
            'stats' => $stats,
            'types' => MediaType::cases(),
            'statuses' => MediaStatus::cases(),
            'filters' => [
                'q' => $search,
                'type' => $request->query('type'),
                'status' => $request->query('status'),
            ],
            'canManage' => $this->actor()->can(Permission::MediaManage->value),
        ]);
    }

    public function items(Request $request): JsonResponse
    {
        $this->authorizePermission(Permission::MediaView);

        $query = Media::query()->latest('id');

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('original_name', 'like', "%{$search}%")
                    ->orWhere('alt', 'like', "%{$search}%")
                    ->orWhere('path', 'like', "%{$search}%");
            });
        }

        $type = $request->query('type');
        if (is_string($type) && $type !== '' && $type !== 'all') {
            $query->where('type', $type);
        }

        $readyOnly = $request->boolean('ready', true);
        if ($readyOnly) {
            $query->where('status', MediaStatus::Ready->value);
        }

        $items = $query->paginate(24)->withQueryString();

        return response()->json([
            'data' => $items->getCollection()->map(fn (Media $media): array => $media->toPickerArray())->values(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function store(StoreMediaRequest $request, UploadMediaAction $upload): JsonResponse|RedirectResponse
    {
        $this->authorizePermission(Permission::MediaManage);

        $file = $request->file('file');
        assert($file !== null);

        $media = $upload->handle($this->actor(), $file, $request->validated('alt'));

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $media->toPickerArray(),
            ], 201);
        }

        return redirect()
            ->route('admin.media.show', $media)
            ->with('status', 'Đã tải lên media.');
    }

    public function storeFromUrl(RegisterExternalMediaRequest $request, RegisterExternalMediaAction $register): JsonResponse|RedirectResponse
    {
        $this->authorizePermission(Permission::MediaManage);

        try {
            $media = $register->handle(
                $this->actor(),
                (string) $request->validated('url'),
                $request->validated('alt'),
                $request->boolean('import'),
            );
        } catch (InvalidArgumentException|RuntimeException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['url' => $e->getMessage()])->withInput();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $media->toPickerArray(),
            ], 201);
        }

        return redirect()
            ->route('admin.media.show', $media)
            ->with('status', $request->boolean('import')
                ? 'Đã tải ảnh từ URL về máy chủ.'
                : 'Đã thêm ảnh CDN / URL ngoài vào thư viện.');
    }

    public function show(Media $media): View
    {
        $this->authorizePermission(Permission::MediaView);

        $media->load(['usages.usable', 'jobs', 'uploader']);

        return view('media::admin.show', [
            'media' => $media,
            'canManage' => $this->actor()->can(Permission::MediaManage->value),
        ]);
    }

    public function update(UpdateMediaRequest $request, Media $media, UpdateMediaMetadataAction $update): RedirectResponse
    {
        $this->authorizePermission(Permission::MediaManage);

        $update->handle($this->actor(), $media, $request->validated());

        return redirect()
            ->route('admin.media.show', $media)
            ->with('status', 'Đã cập nhật metadata.');
    }

    public function destroy(Media $media, DeleteMediaAction $delete): RedirectResponse
    {
        $this->authorizePermission(Permission::MediaManage);

        try {
            $delete->handle($this->actor(), $media);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('admin.media.show', $media)
                ->with('status', $e->getMessage());
        }

        return redirect()
            ->route('admin.media.index')
            ->with('status', 'Đã xóa media.');
    }

    private function authorizePermission(Permission $permission): void
    {
        abort_unless($this->actor()->can($permission->value), 403);
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
