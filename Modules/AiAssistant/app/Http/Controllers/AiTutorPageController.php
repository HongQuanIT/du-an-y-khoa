<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\AiAssistant\Models\AiThread;
use Modules\AiAssistant\Services\AiQuotaService;

final class AiTutorPageController extends Controller
{
    public function __invoke(Request $request, AiQuotaService $quota): View
    {
        $threads = AiThread::query()
            ->where('user_id', $request->user()->getKey())
            ->latest()
            ->limit(30)
            ->get(['id', 'title', 'created_at']);

        return view('aiassistant::page', [
            'threads' => $threads,
            'quota' => $quota->snapshot($request->user()),
        ]);
    }
}
