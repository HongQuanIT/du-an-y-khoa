@php
    $lessonOptions = ($subjectLessonOptions ?? collect())->map(fn ($lesson) => [
        'id' => $lesson->id,
        'name' => $lesson->name,
        'slug' => $lesson->slug,
        'status' => $lesson->status->value,
    ])->values()->all();
@endphp

@include('admin::curriculum._catalog-table', [
    'catalogKey' => 'subjects',
    'catalogLabel' => 'Môn học',
    'catalogSingular' => 'môn học',
    'items' => $subjects,
    'storeRoute' => route(\App\Support\Auth\PortalRoute::content('curriculum.subjects.store')),
    'updateRoute' => 'admin.curriculum.subjects.update',
    'destroyRoute' => 'admin.curriculum.subjects.destroy',
    'namePlaceholder' => 'Ví dụ: Nội khoa, Dược lý…',
    'emptyHint' => 'Thêm môn học, rồi gắn bài học ngay trong drawer sửa.',
    'manageLessons' => true,
    'lessonOptions' => $lessonOptions,
    'canCreateLesson' => $canCreate,
])
