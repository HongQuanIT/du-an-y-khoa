<x-layouts.auth title="Đăng nhập reviewer" description="Cổng review câu hỏi.">
    <x-auth.shell tagline="Cổng reviewer">
        <h2 class="mb-2 font-headline-md text-headline-md text-on-surface">Đăng nhập reviewer</h2>
        <p class="mb-8 font-body-sm text-body-sm text-on-surface-variant">Dành cho tài khoản reviewer.</p>
        <x-auth.errors />
        <form class="space-y-5" action="{{ route('reviewer.login.store') }}" method="post">
            @csrf
            <x-auth.input name="email" label="Email" type="email" placeholder="reviewer@example.com" required autofocus autocomplete="username" />
            <x-auth.password-input name="password" label="Mật khẩu" required autocomplete="current-password" />
            <x-auth.submit>Đăng nhập</x-auth.submit>
        </form>
    </x-auth.shell>
</x-layouts.auth>
