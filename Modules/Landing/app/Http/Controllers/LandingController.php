<?php

declare(strict_types=1);

namespace Modules\Landing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class LandingController extends Controller
{
    public function home(): View
    {
        return view('landing::home');
    }

    public function features(): View
    {
        return view('landing::features');
    }

    public function pricing(): View
    {
        return view('landing::pricing');
    }

    public function about(): View
    {
        return view('landing::about');
    }

    public function contact(): View
    {
        return view('landing::contact');
    }

    public function faq(): View
    {
        return view('landing::faq');
    }
}
