<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Báo cáo định kỳ</title>
</head>
<body style="font-family: system-ui, sans-serif; color: #191c1e; line-height: 1.5;">
    <p>Xin chào,</p>

    <p>
        Đây là báo cáo định kỳ <strong>{{ $reportTitle }}</strong>
        (nhóm {{ $categoryTitle }}).
    </p>

    <ul style="padding-left: 1.25rem;">
        <li>Chu kỳ dữ liệu: <strong>{{ $schedule->range_key }}</strong></li>
        <li>Lịch gửi: {{ $schedule->frequencySummary() }}</li>
        <li>Thời điểm tạo email: {{ now()->format('d/m/Y H:i') }}</li>
    </ul>

    <p>File CSV đính kèm chứa bảng chi tiết của báo cáo.</p>

    <p>
        <a href="{{ route('admin.reports.show', [$schedule->category_slug, $schedule->report_slug, 'range' => $schedule->range_key]) }}"
            style="color: #005c55; font-weight: 600;">
            Xem báo cáo trên cổng quản trị
        </a>
    </p>

    <p style="color: #5f6368; font-size: 0.875rem;">
        Bạn nhận email này vì địa chỉ của bạn nằm trong danh sách người nhận của lịch báo cáo #{{ $schedule->id }}.
    </p>
</body>
</html>
