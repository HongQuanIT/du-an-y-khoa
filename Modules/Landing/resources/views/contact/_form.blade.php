@php
    use Modules\Admin\Enums\ContactSubject;

    $user = auth()->user();
    $submitted = session('contact_success');
    $reference = session('contact_reference');
    $fieldClass = 'w-full h-12 bg-background border rounded-lg px-4 text-body-md text-on-background focus:ring-0 focus:border-primary-container transition-colors';
    $errorClass = 'border-error';
    $okClass = 'border-border';
@endphp

<div class="bg-surface border border-border rounded-xl p-6 md:p-8 shadow-sm">
    @if ($submitted)
        <div class="flex flex-col items-start gap-4" role="status" aria-live="polite">
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-700">
                <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">check_circle</span>
            </div>
            <div>
                <h2 class="text-headline-sm font-headline-sm text-on-background">Đã gửi liên hệ thành công</h2>
                <p class="mt-2 text-body-md font-body-md text-text-secondary">
                    Cảm ơn bạn đã liên hệ. Đội ngũ hỗ trợ sẽ phản hồi qua email trong thời gian sớm nhất.
                </p>
                @if ($reference)
                    <p class="mt-4 inline-flex items-center gap-2 rounded-lg border border-border bg-background px-3 py-2 text-label-sm font-label-sm text-on-surface">
                        <span class="text-text-secondary">Mã liên hệ:</span>
                        <span class="font-semibold tracking-wide text-primary-container">{{ $reference }}</span>
                    </p>
                @endif
            </div>
            <a href="{{ route('landing.contact') }}"
                class="mt-2 inline-flex items-center gap-2 rounded-xl border border-border px-5 py-2.5 font-label-md text-on-surface hover:bg-surface-container transition-colors">
                <span class="material-symbols-outlined text-sm">add</span>
                Gửi liên hệ khác
            </a>
        </div>
    @else
        <h2 class="text-headline-sm font-headline-sm text-on-background mb-2">Gửi tin nhắn cho chúng tôi</h2>
        <p class="text-body-sm font-body-sm text-text-secondary mb-6">
            Điền form bên dưới — chúng tôi thường phản hồi trong giờ làm việc.
        </p>

        @if ($errors->any())
            <div class="mb-5 rounded-xl border border-error/30 bg-error-container/20 px-4 py-3 text-body-sm text-on-surface" role="alert">
                <p class="font-label-md mb-1">Vui lòng kiểm tra lại thông tin:</p>
                <ul class="list-disc space-y-1 ps-4 text-text-secondary">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('landing.contact.store') }}" method="POST" class="space-y-5" novalidate
            x-data="{ submitting: false }"
            @submit="submitting = true">
            @csrf

            {{-- Honeypot: hidden from users, filled by naive bots --}}
            <div class="absolute -left-[9999px] top-auto h-0 w-0 overflow-hidden" aria-hidden="true">
                <label for="company_website">Website công ty</label>
                <input id="company_website" type="text" name="company_website" value="" tabindex="-1" autocomplete="off">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-label-sm font-label-sm text-on-surface mb-1.5" for="name">
                        Họ tên <span class="text-error">*</span>
                    </label>
                    <input
                        class="{{ $fieldClass }} {{ $errors->has('name') ? $errorClass : $okClass }}"
                        id="name" name="name" type="text" required maxlength="120" autocomplete="name"
                        placeholder="Nhập họ và tên của bạn"
                        value="{{ old('name', $user?->name) }}"
                        aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">
                    @error('name')
                        <p class="mt-1.5 text-body-sm text-error">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-label-sm font-label-sm text-on-surface mb-1.5" for="email">
                        Email <span class="text-error">*</span>
                    </label>
                    <input
                        class="{{ $fieldClass }} {{ $errors->has('email') ? $errorClass : $okClass }}"
                        id="email" name="email" type="email" required maxlength="255" autocomplete="email"
                        placeholder="example@email.com"
                        value="{{ old('email', $user?->email) }}"
                        aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
                    @error('email')
                        <p class="mt-1.5 text-body-sm text-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-label-sm font-label-sm text-on-surface mb-1.5" for="phone">
                        Số điện thoại <span class="text-text-secondary font-normal">(tuỳ chọn)</span>
                    </label>
                    <input
                        class="{{ $fieldClass }} {{ $errors->has('phone') ? $errorClass : $okClass }}"
                        id="phone" name="phone" type="tel" maxlength="32" autocomplete="tel"
                        placeholder="0901 234 567"
                        value="{{ old('phone') }}"
                        aria-invalid="{{ $errors->has('phone') ? 'true' : 'false' }}">
                    @error('phone')
                        <p class="mt-1.5 text-body-sm text-error">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-label-sm font-label-sm text-on-surface mb-1.5" for="subject">
                        Chủ đề <span class="text-error">*</span>
                    </label>
                    <div class="relative">
                        <select
                            class="{{ $fieldClass }} appearance-none cursor-pointer pr-10 {{ $errors->has('subject') ? $errorClass : $okClass }}"
                            id="subject" name="subject" required
                            aria-invalid="{{ $errors->has('subject') ? 'true' : 'false' }}">
                            <option value="" disabled @selected(old('subject') === null || old('subject') === '')>Chọn chủ đề liên hệ</option>
                            @foreach (ContactSubject::cases() as $subject)
                                <option value="{{ $subject->value }}" @selected(old('subject') === $subject->value)>
                                    {{ $subject->label() }}
                                </option>
                            @endforeach
                        </select>
                        <div class="absolute inset-y-0 right-0 flex items-center px-3 pointer-events-none">
                            <span class="material-symbols-outlined text-text-secondary">expand_more</span>
                        </div>
                    </div>
                    @error('subject')
                        <p class="mt-1.5 text-body-sm text-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <label class="block text-label-sm font-label-sm text-on-surface mb-1.5" for="message">
                    Nội dung <span class="text-error">*</span>
                </label>
                <textarea
                    class="w-full bg-background border rounded-lg p-4 text-body-md text-on-background focus:ring-0 focus:border-primary-container transition-colors resize-y min-h-[140px] {{ $errors->has('message') ? $errorClass : $okClass }}"
                    id="message" name="message" required rows="5" maxlength="5000"
                    placeholder="Mô tả chi tiết vấn đề hoặc yêu cầu của bạn..."
                    aria-invalid="{{ $errors->has('message') ? 'true' : 'false' }}">{{ old('message') }}</textarea>
                @error('message')
                    <p class="mt-1.5 text-body-sm text-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-start pt-1">
                <div class="flex items-center h-5">
                    <input
                        class="w-5 h-5 bg-background border-border rounded text-primary-container focus:ring-primary-container cursor-pointer {{ $errors->has('privacy') ? 'border-error' : '' }}"
                        id="privacy" name="privacy" type="checkbox" value="1" required
                        @checked(old('privacy'))>
                </div>
                <div class="ml-3">
                    <label class="text-body-sm font-body-sm text-text-secondary cursor-pointer select-none" for="privacy">
                        Tôi đồng ý với
                        <a class="text-primary-container hover:underline" href="{{ route('landing.privacy') }}" target="_blank" rel="noopener">
                            chính sách bảo mật
                        </a>
                        của {{ config('app.name') }} khi gửi thông tin này.
                    </label>
                    @error('privacy')
                        <p class="mt-1 text-body-sm text-error">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="pt-3 flex flex-col sm:flex-row sm:items-center gap-3">
                <button
                    class="w-full sm:w-auto px-8 py-3 bg-primary-container text-on-primary rounded-xl font-label-md hover:bg-primary hover:shadow-md transition-all duration-200 flex items-center justify-center disabled:opacity-60 disabled:cursor-not-allowed"
                    type="submit"
                    :disabled="submitting">
                    <span x-show="!submitting" class="inline-flex items-center">
                        Gửi liên hệ
                        <span class="material-symbols-outlined ml-2 text-sm">send</span>
                    </span>
                    <span x-cloak x-show="submitting" class="inline-flex items-center">
                        Đang gửi…
                        <span class="material-symbols-outlined ml-2 text-sm animate-spin">progress_activity</span>
                    </span>
                </button>
                <p class="text-body-sm text-text-secondary">
                    Hoặc email trực tiếp
                    <a href="mailto:{{ $content['email'] ?? 'hotro@medpro.vn' }}" class="text-primary-container hover:underline">
                        {{ $content['email'] ?? 'hotro@medpro.vn' }}
                    </a>
                </p>
            </div>
        </form>
    @endif
</div>
