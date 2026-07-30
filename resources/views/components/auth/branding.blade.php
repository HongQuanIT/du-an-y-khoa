@props(['tagline'])

<div class="hidden lg:flex lg:w-1/2 bg-primary-container p-12 flex-col justify-between relative overflow-hidden">
    <div class="z-10">
        <a href="{{ route('landing.home') }}"
            class="text-white font-headline-lg text-headline-lg font-extrabold tracking-tight">{{ config('app.name') }}</a>
        <p class="text-on-primary-container text-body-lg font-body-lg mt-2">{{ $tagline }}</p>
    </div>
    <div class="relative z-10 flex flex-col items-center">
        <div
            class="w-full rounded-xl overflow-hidden shadow-2xl transform hover:scale-[1.02] transition-transform duration-500 border border-white/20">
            <img alt="Giao diện học tập y khoa chuyên nghiệp" class="w-full object-cover"
                src="https://lh3.googleusercontent.com/aida/AP1WRLv_p2rdQZm0QQ4HvbOfjWopS8En2e5kGHO6v7tyXV3e0jRVKf5mWIcMZc5_oq8ifygyXE5b7z672n2t_r74rpWb4TDXrHyM7iKQSkSlnUJdXgJoq6OE4tprXxLOP0gxlo2YJtAemTUVE3g03K1IgJ_25DBeV9a9anSstVhbpiLRASFVWDnT9UedXkXeGddCz1blvvS5VY3Yh4NyV09frO4ywaIMeH0HGe-veAamQR8wvchErRMU7KYFjDM">
        </div>
        <div class="mt-8 text-center text-white/90">
            <p class="font-headline-sm text-headline-sm">Làm chủ kiến thức y khoa</p>
            <p class="text-body-md opacity-80 mt-2">Nền tảng ôn luyện thông minh dành cho bác sĩ tương lai</p>
        </div>
    </div>
    <div class="absolute top-[-10%] right-[-10%] w-64 h-64 bg-primary rounded-full blur-3xl opacity-30"></div>
    <div class="absolute bottom-[-5%] left-[-5%] w-80 h-80 bg-on-primary-fixed-variant rounded-full blur-3xl opacity-20">
    </div>
</div>
