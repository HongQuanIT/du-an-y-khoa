<?php

declare(strict_types=1);

namespace Modules\Classroom\Tests\Unit;

use Modules\Classroom\Events\LiveQuestionChanged;
use Modules\Classroom\Models\LiveSession;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class LiveQuestionChangedTest extends TestCase
{
    #[Test]
    public function broadcast_payload_omits_question_html_to_stay_under_reverb_limit(): void
    {
        $session = new LiveSession([
            'uuid' => '01TESTSESSIONUUID000000000',
            'question_set' => [
                'type' => 'manual',
                'question_ids' => array_map(static fn (int $i): string => (string) $i, range(1, 80)),
            ],
        ]);

        $event = new LiveQuestionChanged(
            $session,
            index: 12,
            showAnswer: false,
            revealedOptionIds: [101, 102, 103],
            actorUserId: 55,
        );

        $payload = $event->broadcastWith();
        $encoded = (string) json_encode($payload);

        $this->assertArrayNotHasKey('question', $payload);
        $this->assertSame(12, $payload['index']);
        $this->assertSame(80, $payload['total']);
        $this->assertSame([101, 102, 103], $payload['revealed_option_ids']);
        $this->assertSame(55, $payload['actor_user_id']);
        // Reverb/Pusher historically reject payloads > 10KB; metadata must stay tiny.
        $this->assertLessThan(1_000, strlen($encoded), $encoded);
    }
}
