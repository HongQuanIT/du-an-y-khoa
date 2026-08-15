<?php

declare(strict_types=1);

namespace Modules\Exam\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Enums\Entitlement;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Exam\Services\ExamCatalogService;

final class ExamIndexController extends Controller
{
    public function __construct(private readonly ExamCatalogService $catalog) {}

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return view('exam::index', [
            'examCards' => $this->catalog->cards($user),
            'recentSessions' => $this->catalog->recentSessions($user),
            'canStartExam' => $user->hasEntitlement(Entitlement::ExamSimulation->value),
        ]);
    }
}
