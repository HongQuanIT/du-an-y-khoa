<?php

declare(strict_types=1);

namespace Modules\Editor\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Auth\PortalRoute;
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
        $query = Media::query()->latest('id');
        $search = trim((string) $request->query('q', ''));

        if ($search !== '') {
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

        return view('media::admin.index', [
            'items' => $query->paginate(36)->withQueryString(),
            'stats' => [
                'total' => Media::query()->count(),
                'images' => Media::query()->where('type', MediaType::Image->value)->count(),
                'videos' => Media::query()->where('type', MediaType::Video->value)->count(),
            ],
            'types' => MediaType::cases(),
            'statuses' => MediaStatus::cases(),
            'filters' => [
                'q' => $search,
                'type' => $request->query('type'),
                'status' => $request->query('status'),
            ],
            'canManage' => $this->actor()->canAny(['media.upload', 'media.update', 'media.delete']),
        ]);
    }

    public function items(Request $request): JsonResponse
    {
        $query = Media::query()->latest('id');

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(fn ($builder) => $builder
                ->where('original_name', 'like', "%{$search}%")
                ->orWhere('alt', 'like', "%{$search}%")
                ->orWhere('path', 'like', "%{$search}%"));
        }

        $type = $request->query('type');
        if (is_string($type) && $type !== '' && $type !== 'all') {
            $query->where('type', $type);
        }
        if ($request->boolean('ready', true)) {
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
        $file = $request->file('file');
        assert($file !== null);
        $media = $upload->handle($this->actor(), $file, $request->validated('alt'));

        return $request->expectsJson()
            ? response()->json(['data' => $media->toPickerArray()], 201)
            : redirect()->route(PortalRoute::content('media.show'), $media)->with('status', 'Đã tải lên media.');
    }

    public function storeFromUrl(RegisterExternalMediaRequest $request, RegisterExternalMediaAction $register): JsonResponse|RedirectResponse
    {
        try {
            $media = $register->handle(
                $this->actor(),
                (string) $request->validated('url'),
                $request->validated('alt'),
                $request->boolean('import'),
            );
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return $request->expectsJson()
                ? response()->json(['message' => $exception->getMessage()], 422)
                : back()->withErrors(['url' => $exception->getMessage()])->withInput();
        }

        return $request->expectsJson()
            ? response()->json(['data' => $media->toPickerArray()], 201)
            : redirect()->route(PortalRoute::content('media.show'), $media)->with('status', $request->boolean('import')
                ? 'Đã tải ảnh từ URL về máy chủ.'
                : 'Đã thêm ảnh CDN / URL ngoài vào thư viện.');
    }

    public function show(Media $media): View
    {
        $media->load(['usages.usable', 'jobs', 'uploader']);

        return view('media::admin.show', [
            'media' => $media,
            'canManage' => $this->actor()->canAny(['media.update', 'media.delete']),
        ]);
    }

    public function update(UpdateMediaRequest $request, Media $media, UpdateMediaMetadataAction $update): RedirectResponse
    {
        $update->handle($this->actor(), $media, $request->validated());

        return redirect()
            ->route(PortalRoute::content('media.show'), $media)
            ->with('status', 'Đã cập nhật metadata.');
    }

    public function destroy(Media $media, DeleteMediaAction $delete): RedirectResponse
    {
        try {
            $delete->handle($this->actor(), $media);
        } catch (RuntimeException $exception) {
            return redirect()
                ->route(PortalRoute::content('media.show'), $media)
                ->with('status', $exception->getMessage());
        }

        return redirect()
            ->route(PortalRoute::content('media.index'))
            ->with('status', 'Đã xóa media.');
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
