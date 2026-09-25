@props(['title' => null, 'pendingCount' => 0])

<x-layouts.admin :title="$title" portal="reviewer" :pending-count="$pendingCount">
    {{ $slot }}
</x-layouts.admin>
