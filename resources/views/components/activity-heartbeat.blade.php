@if (config('audit.activity.enabled', true) && auth()->check())
    <meta name="activity-heartbeat-url" content="{{ route('activity.heartbeat') }}">
    <meta name="activity-heartbeat-seconds" content="{{ max(60, (int) config('audit.activity.heartbeat_seconds', 120)) }}">
@endif
