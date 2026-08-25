@props([
    'guestLabel' => 'Đăng ký',
    'authLabel' => 'Tạo phiên học',
])

@php
    use App\Support\Auth\Instructor;
    use App\Support\Auth\Staff;

    $user = auth()->user();

    if ($user !== null && Staff::isStaff($user)) {
        $href = route('admin.dashboard');
        $label = 'Vào quản trị';
    } elseif ($user !== null && Instructor::is($user)) {
        $href = route('teach.dashboard');
        $label = 'Vào Teach';
    } elseif ($user !== null) {
        $href = route('qbank.index');
        $label = $authLabel;
    } else {
        $href = route('register');
        $label = $guestLabel;
    }
@endphp

<a href="{{ $href }}" {{ $attributes }}>
    {{ $label }}
    {{ $slot }}
</a>
