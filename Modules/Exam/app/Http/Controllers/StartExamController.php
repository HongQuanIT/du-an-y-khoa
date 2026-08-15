<?php

declare(strict_types=1);

namespace Modules\Exam\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\TargetExams;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Modules\Exam\Http\Requests\StartExamRequest;
use Modules\QuestionBank\Actions\CreateQuestionSessionAction;
use Modules\QuestionBank\Data\CreateSessionData;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionSource;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class StartExamController extends Controller
{
    public function __construct(private readonly CreateQuestionSessionAction $createSession) {}

    public function __invoke(StartExamRequest $request, string $examKey): RedirectResponse
    {
        if (! array_key_exists($examKey, TargetExams::selectable())) {
            throw new NotFoundHttpException;
        }

        $user = $request->user();
        abort_unless($user instanceof User, 403);

        try {
            $session = $this->createSession->handle($user, new CreateSessionData(
                mode: SessionMode::Exam,
                source: SessionSource::Exam,
                count: $request->count(),
                examKey: $examKey,
            ));
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['count' => $exception->getMessage()]);
        }

        return redirect()
            ->route('qbank.session', $session)
            ->with('status', 'Đã bắt đầu đề thi mô phỏng.');
    }
}
