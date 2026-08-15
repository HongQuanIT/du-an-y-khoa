<div class="max-w-3xl mx-auto px-margin-mobile md:px-gutter py-16 md:py-24">
    <h1 class="font-headline-lg text-headline-lg text-on-background mb-6">{{ $pageTitle }}</h1>
    <p class="font-body-md text-text-secondary mb-8">{{ $content['intro'] }}</p>

    @foreach ($content['sections'] as $section)
        <section class="space-y-4 mb-10 last:mb-0">
            <h2 class="font-headline-sm text-headline-sm text-on-background">{{ $section['title'] }}</h2>
            @foreach (preg_split("/\r\n|\n|\r/", (string) $section['body']) as $paragraph)
                @if (trim($paragraph) !== '')
                    <p class="font-body-md text-text-secondary leading-relaxed">{{ $paragraph }}</p>
                @endif
            @endforeach
        </section>
    @endforeach
</div>
