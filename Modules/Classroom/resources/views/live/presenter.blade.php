<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Giáo viên — {{ $session->title }}</title>
    @vite(['resources/css/app.css'])
    <style>
        body { font-family: system-ui, sans-serif; margin: 0; padding: 1.5rem; background: #fff; color: #111; }
        [data-q-stem].q-hints-revealed mark[data-hint] {
            background-color: transparent;
            color: inherit;
            text-decoration: underline #ea580c;
            text-decoration-thickness: 2px;
            text-underline-offset: 4px;
        }
        [data-q-stem].q-hints-revealed [data-key-info] {
            text-decoration: underline #d97706;
            text-decoration-thickness: 2px;
            text-underline-offset: 4px;
        }
    </style>
</head>
<body data-presenter-root
    data-bootstrap-url="{{ $bootstrapUrl }}"
    data-question-url="{{ $questionUrl }}"
    data-marks-url="{{ $marksUrl }}"
    data-session-uuid="{{ $sessionUuid }}"
    data-can-moderate="{{ $canModerate ? '1' : '0' }}">
    <header style="margin-bottom:1rem;border-bottom:1px solid #e5e7eb;padding-bottom:0.75rem;">
        <p style="font-size:0.75rem;color:#6b7280;margin:0;">{{ $classroom->title }}</p>
        <h1 style="font-size:1.125rem;margin:0.25rem 0 0;">{{ $session->title }}</h1>
        <p data-q-index-label style="font-size:0.875rem;color:#6b7280;margin:0.5rem 0 0;">Đang tải…</p>
    </header>
    <div data-q-stem style="font-size:1rem;line-height:1.6;margin-bottom:1rem;user-select:text;"></div>
    <div data-q-stem-image style="display:none;margin-bottom:1rem;"></div>
    <div data-q-knowledge style="display:none;margin:0 0 1rem;padding:0.75rem;border:1px solid #fde68a;border-radius:0.5rem;background:#fffbeb;">
        <p style="margin:0 0 0.25rem;font-size:0.75rem;font-weight:700;color:#b45309;text-transform:uppercase;letter-spacing:.04em;">Kiến thức</p>
        <div data-q-knowledge-content style="font-size:0.875rem;line-height:1.5;font-style:italic;"></div>
    </div>
    <ul data-q-options style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:0.5rem;"></ul>
    <div data-q-explanation style="display:none;margin-top:1rem;padding:0.75rem;background:#eff6ff;border-radius:0.5rem;font-size:0.875rem;"></div>
    @if ($canModerate)
        <footer style="margin-top:1.5rem;display:flex;gap:0.5rem;flex-wrap:wrap;">
            <button type="button" data-q-prev style="padding:0.5rem 1rem;border:1px solid #d1d5db;border-radius:0.5rem;background:#fff;cursor:pointer;">← Trước</button>
            <button type="button" data-q-next style="padding:0.5rem 1rem;border:1px solid #d1d5db;border-radius:0.5rem;background:#fff;cursor:pointer;">Sau →</button>
            <button type="button" data-q-clear-marks style="padding:0.5rem 1rem;border:1px solid #d1d5db;border-radius:0.5rem;background:#fff;cursor:pointer;color:#6b7280;">Xóa tô màu</button>
        </footer>
        <p style="margin-top:1rem;font-size:0.75rem;color:#6b7280;">Cửa sổ này dùng để tham khảo hoặc đặt trên màn hình phụ. Học viên xem đề đồng bộ trong phòng trực tiếp — không cần chia sẻ cửa sổ này.</p>
    @endif
    @vite(['resources/js/classroom/presenter-window.js'])
</body>
</html>
