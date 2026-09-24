<x-layouts.editor title="Mã khôi phục 2FA">
    <div class="mx-auto max-w-2xl space-y-6">
        <header><h2 class="font-headline-lg text-on-surface">Lưu mã khôi phục</h2><p class="mt-2 text-on-surface-variant">Mỗi mã chỉ dùng một lần nếu bạn mất thiết bị Authenticator. Hãy sao chép và cất giữ an toàn; trang này chỉ hiển thị một lần.</p></header>
        <section class="rounded-xl border border-outline-variant bg-surface p-6"><ul class="mb-6 grid grid-cols-1 gap-2 sm:grid-cols-2">@foreach ($codes as $code)<li class="rounded-lg border border-outline-variant bg-surface-container-low px-3 py-2 font-mono text-sm">{{ $code }}</li>@endforeach</ul><form method="post" action="{{ route('editor.profile.2fa.recovery.finish') }}">@csrf<button class="rounded-lg bg-primary px-5 py-2.5 font-semibold text-on-primary">Đã lưu — về bảo mật</button></form></section>
    </div>
</x-layouts.editor>
