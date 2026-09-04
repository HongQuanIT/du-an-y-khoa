@if (config('audit.activity.enabled', true) && auth()->check())
    <meta name="activity-heartbeat-url" content="{{ route('activity.heartbeat') }}">
@endif
