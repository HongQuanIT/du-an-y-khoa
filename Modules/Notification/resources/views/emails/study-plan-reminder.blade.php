<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Nhắc kế hoạch học</title>
</head>
<body style="font-family: system-ui, sans-serif; color: #191c1e; line-height: 1.5;">
    <p>Xin chào {{ $user->name }},</p>

    <p>
        Bạn có <strong>{{ $tasks->count() }}</strong> nhiệm vụ Study Plan
        @if ($reminderDate->isToday())
            hôm nay ({{ $reminderDate->locale('vi')->isoFormat('D/M/YYYY') }}):
        @else
            đến ngày {{ $reminderDate->locale('vi')->isoFormat('D/M/YYYY') }}:
        @endif
    </p>

    <ul style="padding-left: 1.25rem;">
        @foreach ($tasks as $task)
            @php
                $plan = $task->plan;
                $isOverdue = $task->date->lessThan($reminderDate->copy()->startOfDay());
            @endphp
            <li style="margin-bottom: 0.5rem;">
                <strong>{{ $task->title() }}</strong>
                @if ($plan)
                    <span style="color: #5f6368;">— {{ $plan->name }}</span>
                @endif
                @if ($isOverdue)
                    <span style="color: #ba1a1a;">(quá hạn {{ $task->date->locale('vi')->isoFormat('D/M') }})</span>
                @endif
            </li>
        @endforeach
    </ul>

    <p>
        <a href="{{ route('study-plan.index') }}" style="color: #005c55; font-weight: 600;">
            Mở kế hoạch học tập
        </a>
    </p>

    <p style="color: #5f6368; font-size: 0.875rem;">
        Bạn có thể tắt email nhắc kế hoạch trong Cài đặt → Liên hệ & cài đặt → Thông báo.
    </p>
</body>
</html>
