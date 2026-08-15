<?php

declare(strict_types=1);

namespace Modules\Landing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Modules\Admin\Models\Faq;
use Modules\Admin\Support\Cms\CmsPageSeo;
use Modules\Admin\Support\Enums\CmsPageKey;
use Modules\Admin\Support\Enums\FaqCategory;
use Modules\Billing\Actions\ListPublicPlansAction;
use Modules\Billing\Models\PlanPrice;
use Modules\Billing\Support\CurrentSubscription;
use Modules\Landing\Support\PublicSeo;
use Modules\Landing\Support\ResolvedCmsPage;

class LandingController extends Controller
{
    public function home(): View
    {
        return $this->renderLandingBlocks(CmsPageKey::Home, 'landing::home');
    }

    public function features(): View
    {
        return $this->renderLandingBlocks(CmsPageKey::Features, 'landing::features');
    }

    public function pricing(Request $request, ListPublicPlansAction $list): View
    {
        $catalog = $list->handle();
        $current = CurrentSubscription::for($request->user());

        $yearlyForAlpine = $catalog['yearlyPrices']->mapWithKeys(function (PlanPrice $price): array {
            $years = max(1, (int) round(($price->duration_days ?? 365) / 365));

            return [$years => [
                'id' => $price->id,
                'label' => $price->label,
                'total' => $price->price_cents,
                'perMonth' => $price->perMonthCents() ?? 0,
                'save' => $price->displaySavingsPercent() ?? 0,
                'listPrice' => $price->listPriceCents() ?? 0,
                'months' => (int) round(($price->duration_days ?? 365) / 30),
                'badge' => $price->badge_label,
                'cta' => $price->cta_label ?? 'Mua gói '.$price->label,
            ]];
        });

        return view('landing::pricing', [
            'free' => $catalog['free'],
            'premium' => $catalog['premium'],
            'monthlyPrice' => $catalog['monthlyPrice'],
            'yearlyForAlpine' => $yearlyForAlpine,
            'current' => $current,
            'seo' => PublicSeo::forStatic(
                title: 'Bảng giá',
                description: 'So sánh gói Free và Premium — chọn lộ trình ôn thi Y khoa phù hợp ngân sách của bạn.',
                routeName: 'landing.pricing',
            ),
        ]);
    }

    public function about(): View
    {
        return $this->renderCmsPage(CmsPageKey::About, 'landing::about');
    }

    public function contact(): View
    {
        return $this->renderCmsPage(CmsPageKey::Contact, 'landing::contact');
    }

    public function terms(): View
    {
        return $this->renderCmsPage(CmsPageKey::Terms, 'landing::terms');
    }

    public function privacy(): View
    {
        return $this->renderCmsPage(CmsPageKey::Privacy, 'landing::privacy');
    }

    public function faq(): View
    {
        /** @var Collection<string, Collection<int, Faq>> $faqsByCategory */
        $faqsByCategory = Faq::query()
            ->published()
            ->ordered()
            ->get()
            ->groupBy(fn (Faq $faq): string => $faq->category->value);

        return view('landing::faq', [
            'categories' => FaqCategory::cases(),
            'faqsByCategory' => $faqsByCategory,
            'seo' => PublicSeo::forStatic(
                title: 'Câu hỏi thường gặp',
                description: 'Giải đáp thắc mắc về tài khoản, gói học, thanh toán và cách luyện thi trên nền tảng.',
                routeName: 'landing.faq',
                schemaType: CmsPageSeo::SCHEMA_WEB_PAGE,
            ),
        ]);
    }

    private function renderCmsPage(CmsPageKey $key, string $view): View
    {
        $page = ResolvedCmsPage::published($key);

        abort_if($page === null, 404);

        return view($view, [
            'page' => $page,
            'content' => ResolvedCmsPage::content($key),
            'pageTitle' => $page->title ?: $key->defaultTitle(),
            'seo' => PublicSeo::forCms($key, $page),
        ]);
    }

    /** Home/Features always render; draft falls back to defaults (no 404). */
    private function renderLandingBlocks(CmsPageKey $key, string $view): View
    {
        $page = ResolvedCmsPage::published($key);

        return view($view, [
            'page' => $page,
            'content' => ResolvedCmsPage::content($key),
            'seo' => $page !== null
                ? PublicSeo::forCms($key, $page)
                : PublicSeo::forStatic(
                    title: $key->defaultTitle(),
                    description: $key->defaultSeoDescription(),
                    routeName: $key->routeName(),
                ),
        ]);
    }
}
