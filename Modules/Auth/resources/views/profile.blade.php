@php
    $tab = $tab ?? 'career';
    [$pageTitle, $pageDescription] = match ($tab) {
        'membership' => ['Gói & giấy phép', 'Xem gói hiện tại và quản lý quyền truy cập Premium.'],
        'invoices' => ['Hóa đơn', 'Lịch sử thanh toán và biên lai giao dịch.'],
        'redeem' => ['Đổi mã', 'Kích hoạt mã từ trường, tổ chức hoặc khuyến mãi.'],
        'notes' => ['Ghi chú cá nhân', 'Ghi chú riêng — chỉ bạn mới thấy.'],
        'org-license' => ['Giấy phép tổ chức', 'Kích hoạt và quản lý giấy phép từ trường hoặc bệnh viện.'],
        'security' => ['Bảo mật', 'Quản lý mật khẩu và bảo vệ tài khoản.'],
        'notifications' => ['Thông báo', 'Chọn loại thông báo bạn muốn nhận.'],
        'contact' => ['Liên hệ', 'Tên hiển thị và thông tin liên lạc trên tài khoản.'],
        default => ['Hồ sơ cá nhân', 'Quản lý thông tin nghề nghiệp và mục tiêu học tập để cá nhân hóa lộ trình ôn luyện.'],
    };
@endphp

<x-auth::account-layout
    :account-active="$tab"
    :account-title="$pageTitle"
    :account-description="$pageDescription"
>
    @if ($tab === 'career')
        @include('auth::partials.account-career-panel')
    @else
        @include('auth::partials.account-settings-panel')
    @endif
</x-auth::account-layout>
