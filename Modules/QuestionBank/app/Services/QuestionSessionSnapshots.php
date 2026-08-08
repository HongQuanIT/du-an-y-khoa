<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Services;

use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionOption;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Models\QuestionSessionSnapshot;
use Modules\QuestionBank\Models\Topic;
use RuntimeException;

/** Capture and rehydrate immutable question content for session runtime/review. */
final class QuestionSessionSnapshots
{
    /** @var array<string, array<string, Question>> */
    private array $maps = [];

    public function capture(QuestionSession $session): void
    {
        $questionIds = array_values(array_map('strval', $session->question_ids ?? []));
        $questions = Question::query()
            ->with(['options', 'topic'])
            ->whereIn('id', $questionIds)
            ->get()
            ->keyBy(fn (Question $question): string => (string) $question->getKey());

        foreach ($questionIds as $position => $questionId) {
            $question = $questions->get($questionId);

            if (! $question instanceof Question) {
                throw new RuntimeException('Câu hỏi đã thay đổi trước khi phiên được tạo. Vui lòng thử lại.');
            }

            QuestionSessionSnapshot::updateOrCreate(
                ['session_id' => $session->getKey(), 'question_id' => $questionId],
                [
                    'position' => $position,
                    'question_version' => (int) $question->version,
                    'payload' => $this->payload($question, (string) $session->getKey()),
                ],
            );
        }

        unset($this->maps[(string) $session->getKey()]);
        $session->unsetRelation('snapshots');
    }

    /** @param array<int, string> $questionIds */
    public function copy(QuestionSession $original, QuestionSession $target, array $questionIds): void
    {
        $originalSnapshots = $original->snapshots()
            ->whereIn('question_id', $questionIds)
            ->get()
            ->keyBy('question_id');
        $missingIds = collect($questionIds)
            ->reject(fn (string $questionId): bool => $originalSnapshots->has($questionId))
            ->values()
            ->all();
        $liveQuestions = Question::query()
            ->with(['options', 'topic'])
            ->whereIn('id', $missingIds)
            ->get()
            ->keyBy(fn (Question $question): string => (string) $question->getKey());

        foreach (array_values($questionIds) as $position => $questionId) {
            $snapshot = $originalSnapshots->get($questionId);

            if ($snapshot instanceof QuestionSessionSnapshot) {
                QuestionSessionSnapshot::create([
                    'session_id' => $target->getKey(),
                    'question_id' => $questionId,
                    'position' => $position,
                    'question_version' => $snapshot->question_version,
                    'payload' => $snapshot->payload,
                ]);

                continue;
            }

            $question = $liveQuestions->get($questionId);
            if (! $question instanceof Question) {
                throw new RuntimeException('Câu hỏi gốc không còn tồn tại để tạo phiên làm lại.');
            }

            QuestionSessionSnapshot::create([
                'session_id' => $target->getKey(),
                'question_id' => $questionId,
                'position' => $position,
                'question_version' => (int) $question->version,
                'payload' => $this->payload($question, (string) $target->getKey()),
            ]);
        }

        unset($this->maps[(string) $target->getKey()]);
        $target->unsetRelation('snapshots');
    }

    public function question(QuestionSession $session, string $questionId): ?Question
    {
        return $this->questionMap($session)[$questionId] ?? null;
    }

    /** @return array<string, Question> */
    public function questionMap(QuestionSession $session): array
    {
        $sessionKey = (string) $session->getKey();

        if (isset($this->maps[$sessionKey])) {
            return $this->maps[$sessionKey];
        }

        $questionIds = array_values(array_map('strval', $session->question_ids ?? []));
        $snapshots = ($session->relationLoaded('snapshots')
            ? $session->snapshots
            : $session->snapshots()->get()
        )->keyBy('question_id');
        $map = [];
        $missing = [];

        foreach ($questionIds as $questionId) {
            $snapshot = $snapshots->get($questionId);

            if ($snapshot instanceof QuestionSessionSnapshot) {
                $map[$questionId] = $this->hydrate($snapshot->payload);
            } else {
                $missing[] = $questionId;
            }
        }

        if ($missing !== []) {
            $liveQuestions = Question::withTrashed()
                ->with(['options', 'topic'])
                ->whereIn('id', $missing)
                ->get()
                ->keyBy(fn (Question $question): string => (string) $question->getKey());

            foreach ($missing as $questionId) {
                $question = $liveQuestions->get($questionId);

                if ($question instanceof Question) {
                    $question->setRelation('options', $question->optionsForSession($sessionKey));
                    $map[$questionId] = $question;
                }
            }
        }

        return $this->maps[$sessionKey] = $map;
    }

    /** @return array<string, mixed> */
    private function payload(Question $question, string $sessionKey): array
    {
        $topic = $question->topic;
        $options = $question->optionsForSession($sessionKey);

        return [
            'id' => (string) $question->getKey(),
            'stem' => (string) $question->stem,
            'explanation' => $question->explanation,
            'key_info' => array_values((array) $question->key_info),
            'attending_tip' => $question->attending_tip,
            'difficulty' => $question->difficulty->value,
            'status' => $question->status->value,
            'topic_id' => $question->topic_id,
            'is_free' => (bool) $question->is_free,
            'version' => (int) $question->version,
            'topic' => $topic instanceof Topic ? [
                'id' => (int) $topic->getKey(),
                'name' => (string) $topic->name,
                'slug' => (string) $topic->slug,
                'type' => (string) $topic->type,
            ] : null,
            'options' => $options->map(fn (QuestionOption $option): array => [
                'id' => (int) $option->getKey(),
                'question_id' => (string) $question->getKey(),
                'label' => (string) $option->label,
                'content' => (string) $option->content,
                'is_correct' => (bool) $option->is_correct,
                'explanation' => $option->explanation,
                'order' => (int) $option->order,
            ])->values()->all(),
        ];
    }

    /** @param array<string, mixed> $payload */
    private function hydrate(array $payload): Question
    {
        $question = new Question;
        $question->forceFill([
            'id' => (string) $payload['id'],
            'stem' => (string) $payload['stem'],
            'explanation' => $payload['explanation'] ?? null,
            'key_info' => array_values((array) ($payload['key_info'] ?? [])),
            'attending_tip' => $payload['attending_tip'] ?? null,
            'difficulty' => (string) $payload['difficulty'],
            'status' => (string) $payload['status'],
            'topic_id' => $payload['topic_id'] ?? null,
            'is_free' => (bool) ($payload['is_free'] ?? false),
            'version' => (int) ($payload['version'] ?? 1),
        ]);

        $options = collect($payload['options'] ?? [])->map(function (array $data): QuestionOption {
            $option = new QuestionOption;
            $option->forceFill($data);

            return $option;
        });
        $question->setRelation('options', $options);

        $topicData = $payload['topic'] ?? null;
        if (is_array($topicData)) {
            $topic = new Topic;
            $topic->forceFill($topicData);
            $question->setRelation('topic', $topic);
        } else {
            $question->setRelation('topic', null);
        }

        return $question;
    }
}
