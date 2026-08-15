<x-layouts.public :seo="$seo">
    <div class="py-16 px-margin-mobile md:px-gutter max-w-container-max mx-auto w-full">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-8 md:gap-12">
            <div class="md:col-span-5">
                @include('landing::pages.contact._info')
            </div>
            <div class="md:col-span-7">
                @include('landing::contact._form')
            </div>
        </div>
    </div>
</x-layouts.public>
