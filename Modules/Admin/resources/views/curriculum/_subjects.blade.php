@include('admin::curriculum._catalog-table', [
    'catalogKey' => 'subjects',
    'catalogLabel' => 'Môn học',
    'catalogSingular' => 'môn học',
    'items' => $subjects,
    'storeRoute' => route('admin.curriculum.subjects.store'),
    'updateRoute' => 'admin.curriculum.subjects.update',
    'destroyRoute' => 'admin.curriculum.subjects.destroy',
    'namePlaceholder' => 'Ví dụ: Nội khoa, Dược lý…',
    'emptyHint' => 'Thêm môn học để gắn vào bài học. Không hiển thị tên bài học tại đây.',
])
