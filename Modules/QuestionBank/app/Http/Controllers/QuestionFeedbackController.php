<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\QuestionBank\Models\QuestionFeedback;
use Modules\QuestionBank\Models\QuestionOption;
use Modules\QuestionBank\Models\QuestionSession;

final class QuestionFeedbackController extends Controller
{
    public function store(Request $request, QuestionSession $session): JsonResponse
    {
        $this->authorize('view', $session);

        $validated = $request->validate([
            'question_id' => ['required', 'string', Rule::in(array_map('strval', $session->question_ids ?? []))],
            'target' => ['required', Rule::in(['question', 'knowledge', 'answer'])],
            'option_id' => ['nullable', 'integer'],
            'category' => ['required', Rule::in([
                'grammar', 'incorrect', 'missing', 'improvement', 'technical', 'media', 'search', 'other',
            ])],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $optionId = isset($validated['option_id']) ? (int) $validated['option_id'] : null;
        if ($validated['target'] === 'answer') {
            abort_unless(
                $optionId !== null && QuestionOption::query()
                    ->whereKey($optionId)
                    ->where('question_id', $validated['question_id'])
                    ->exists(),
                422,
                'Đáp án phản hồi không hợp lệ.',
            );
        } else {
            $optionId = null;
        }

        $feedback = QuestionFeedback::query()->create([
            'user_id' => (int) $request->user()->getAuthIdentifier(),
            'question_id' => $validated['question_id'],
            'question_session_id' => $session->getKey(),
            'question_option_id' => $optionId,
            'target' => $validated['target'],
            'category' => $validated['category'],
            'message' => filled($validated['message'] ?? null) ? trim((string) $validated['message']) : null,
            'status' => 'pending',
        ]);

        return ApiResponse::item(
            ['id' => $feedback->getKey()],
            201,
            ['message' => 'Cảm ơn bạn. Phản hồi đã được ghi nhận.'],
        );
    }
}
