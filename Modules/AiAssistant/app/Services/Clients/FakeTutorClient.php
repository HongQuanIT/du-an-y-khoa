<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Services\Clients;

use Modules\AiAssistant\Contracts\AiTutorClient;
use Modules\AiAssistant\Contracts\TutorReply;

/**
 * Deterministic canned tutor used when no API key is configured or in tests.
 * Streams a short, safe Vietnamese answer chunk-by-chunk so the whole UI and
 * broadcasting path can be exercised without hitting a paid provider.
 */
final class FakeTutorClient implements AiTutorClient
{
    public function stream(string $system, array $messages, callable $onDelta, ?callable $shouldStop = null): TutorReply
    {
        $prompt = '';
        foreach (array_reverse($messages) as $message) {
            if (($message['role'] ?? '') === 'user') {
                $prompt = (string) ($message['content'] ?? '');
                break;
            }
        }

        $answer = $this->composeAnswer($prompt);
        $emitted = '';

        foreach ($this->chunk($answer) as $piece) {
            if ($shouldStop !== null && $shouldStop()) {
                return new TutorReply($emitted, $this->citations(), stopped: true);
            }

            $emitted .= $piece;
            $onDelta($piece);

            // Tiny pause so the client sees a genuine stream (skipped under tests).
            if (! app()->runningUnitTests()) {
                usleep(35_000);
            }
        }

        return new TutorReply(
            content: $answer,
            citations: $this->citations(),
            tokensIn: (int) ceil(mb_strlen($prompt) / 4),
            tokensOut: (int) ceil(mb_strlen($answer) / 4),
        );
    }

    private function composeAnswer(string $prompt): string
    {
        $excerpt = trim(mb_substr($prompt, 0, 140));

        return <<<MD
        **Phân tích nhanh (chế độ demo)**

        Đây là câu trả lời mẫu từ AI Tutor khi chưa cấu hình khóa API thật. Nội dung
        bám theo yêu cầu: "{$excerpt}".

        - **Điểm then chốt:** đọc kỹ dữ kiện lâm sàng và loại trừ đáp án nhiễu theo cơ chế.
        - **Lập luận:** đối chiếu triệu chứng với tiêu chuẩn chẩn đoán trong bài liên quan.
        - **Mẹo ghi nhớ:** gắn mỗi đáp án với một đặc điểm phân biệt duy nhất.

        _Chỉ phục vụ học tập, không thay thế ý kiến chuyên môn._
        MD;
    }

    /** @return array<int, array<string, mixed>> */
    private function citations(): array
    {
        return [];
    }

    /** @return iterable<int, string> */
    private function chunk(string $text): iterable
    {
        // Split on word boundaries to mimic token streaming.
        $tokens = preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];

        return array_values(array_filter($tokens, static fn (string $t): bool => $t !== ''));
    }
}
