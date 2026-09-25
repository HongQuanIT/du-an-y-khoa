<x-layouts.reviewer title="Mã khôi phục 2FA">
    <div class="mx-auto max-w-2xl space-y-6">
        <h1 class="font-headline-lg">Lưu mã khôi phục</h1>
        <p>Mỗi mã chỉ dùng một lần. Hãy lưu an toàn; trang này chỉ hiển thị một lần.</p>
        <div class="rounded-xl border border-outline-variant bg-surface p-6">
            <ul class="mb-6 grid gap-2 sm:grid-cols-2">@foreach ($codes as $code)<li class="rounded-lg bg-surface-container-low p-2 font-mono">{{ $code }}</li>@endforeach</ul>
            <form method="post" action="{{ route('reviewer.profile.2fa.recovery.finish') }}">@csrf<button class="rounded-lg bg-primary px-4 py-2 text-on-primary">Đã lưu mã</button></form>
        </div>
    </div>
</x-layouts.reviewer>
