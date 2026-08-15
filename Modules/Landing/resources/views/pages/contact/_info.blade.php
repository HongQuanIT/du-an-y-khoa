<div class="flex flex-col space-y-8">
    <div>
        <h1 class="text-headline-lg font-headline-lg text-on-background mb-4">{{ $content['intro']['title'] }}</h1>
        <p class="text-body-lg font-body-lg text-text-secondary">{{ $content['intro']['text'] }}</p>
    </div>

    <ul class="space-y-6">
        @foreach ([
            ['icon' => 'mail', 'label' => 'Email', 'value' => $content['email'], 'href' => 'mailto:'.$content['email']],
            ['icon' => 'call', 'label' => 'Hotline', 'value' => $content['hotline'], 'href' => 'tel:'.preg_replace('/\D+/', '', $content['hotline'])],
            ['icon' => 'location_on', 'label' => 'Địa chỉ', 'value' => $content['address'], 'href' => null],
            ['icon' => 'schedule', 'label' => 'Giờ làm việc', 'value' => $content['hours'], 'href' => null],
        ] as $item)
            <li class="flex items-start">
                <div class="flex-shrink-0 bg-surface-container w-12 h-12 rounded-full flex items-center justify-center mr-4">
                    <span class="material-symbols-outlined text-primary-container" style="font-variation-settings: 'FILL' 1;">{{ $item['icon'] }}</span>
                </div>
                <div>
                    <p class="text-label-md font-label-md text-on-surface">{{ $item['label'] }}</p>
                    @if ($item['href'])
                        <a href="{{ $item['href'] }}" class="text-body-md font-body-md text-primary-container hover:underline">{{ $item['value'] }}</a>
                    @else
                        <p class="text-body-md font-body-md text-text-secondary">{{ $item['value'] }}</p>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>

    <div>
        <p class="text-label-md font-label-md text-on-surface mb-3">Kết nối với chúng tôi</p>
        <div class="flex space-x-4">
            @foreach (['Facebook', 'Google', 'LinkedIn', 'YouTube'] as $social)
                <a href="#" aria-label="{{ $social }}"
                    class="w-10 h-10 rounded-full bg-surface border border-border flex items-center justify-center text-primary-container hover:bg-primary-container hover:text-on-primary transition-all duration-200 shadow-sm">
                    <span class="material-symbols-outlined text-[20px]">public</span>
                </a>
            @endforeach
        </div>
    </div>

    <div class="w-full h-48 bg-surface-container rounded-xl overflow-hidden border border-border relative">
        <img class="w-full h-full object-cover" alt="{{ $content['map']['image_alt'] }}" src="{{ $content['map']['image_url'] }}">
        <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
            <div class="bg-surface/80 backdrop-blur-sm px-4 py-2 rounded-lg shadow-sm border border-border/50">
                <span class="text-label-sm font-label-sm text-on-surface flex items-center">
                    <span class="material-symbols-outlined text-primary-container mr-1 text-sm">map</span>
                    Bản đồ văn phòng
                </span>
            </div>
        </div>
    </div>
</div>
