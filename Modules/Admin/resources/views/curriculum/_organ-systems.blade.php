@include('admin::curriculum._catalog-table', [
    'catalogKey' => 'organ-systems',
    'catalogLabel' => 'Hệ cơ quan',
    'catalogSingular' => 'hệ cơ quan',
    'items' => $organSystems,
    'storeRoute' => route(\App\Support\Auth\PortalRoute::content('curriculum.organ-systems.store')),
    'updateRoute' => 'admin.curriculum.organ-systems.update',
    'destroyRoute' => 'admin.curriculum.organ-systems.destroy',
    'namePlaceholder' => 'Ví dụ: Hệ tim mạch',
    'emptyHint' => 'Thêm hệ cơ quan để gắn vào bài học. Không hiển thị tên bài học tại đây.',
])
