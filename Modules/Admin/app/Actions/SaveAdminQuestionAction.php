<?php

declare(strict_types=1);

namespace Modules\Admin\Actions;

use App\Models\User;
use App\Support\Concerns\AsAction;
use App\Support\Html\SafeHtml;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Admin\Enums\AuditAction;
use Modules\Admin\Support\Auditor;
use Modules\Admin\Support\AuditSnapshot;
use Modules\Admin\Support\QuestionAccess;
use Modules\QuestionBank\Enums\Difficulty;
use Modules\QuestionBank\Enums\QuestionReviewAction;
use Modules\QuestionBank\Enums\QuestionReviewStatus;
use Modules\QuestionBank\Enums\QuestionStatus;
use Modules\QuestionBank\Enums\TaxonomyStatus;
use Modules\QuestionBank\Models\MedicalTaxonomyNode;
use Modules\QuestionBank\Models\Question;
use Modules\QuestionBank\Models\QuestionHint;
use Modules\QuestionBank\Models\QuestionOption;
use Modules\QuestionBank\Models\QuestionReviewRequest;

/**
 * Create or update a question + options (admin editor).
 */
final class SaveAdminQuestionAction
{
    use AsAction;

    public function __construct(
        private readonly CaptureQuestionVersionAction $captureVersion,
    ) {}

    /**
     * @param  array{
     *     stem: string,
     *     stem_image_path: ?string,
     *     explanation: ?string,
     *     key_info: array<int, string>,
     *     attending_tip: ?string,
     *     difficulty: string,
     *     core_clinical_topic_ids?: list<int>,
     *     medical_taxonomy_node_ids?: list<int>,
     *     medical_taxonomy_links?: list<array{id: int, relationship_type?: ?string, is_primary?: ?bool}>,
     *     tag_ids?: list<int>,
     *     hints?: list<array{id?: int|null, content: string, sort_order?: int}>,
     *     is_free: bool,
     *     exam_flag?: bool,
     *     options: list<array{id?: int|null, content: string, is_correct: bool, explanation?: ?string}>
     * }  $data
     */
    public function handle(User $actor, ?Question $question, array $data): Question
    {
        $options = $data['options'];
        $this->assertOptionsValid($options);
        $this->assertMedicalTaxonomyPresent($data);

        return DB::transaction(function () use ($actor, $question, $data, $options): Question {
            $before = $question ? AuditSnapshot::question($question) : null;
            $isReviewer = QuestionAccess::isReviewer($actor);
            $reviewRequest = null;

            if ($question === null) {
                $question = new Question;
                $question->status = QuestionStatus::Draft;
                $question->created_by = $actor->getKey();
            } else {
                if (! $isReviewer && $question->status === QuestionStatus::Published) {
                    $this->queueUpdate($actor, $question, $data);

                    return $question->fresh(['options', 'hints', 'medicalTaxonomyNodes', 'pendingReviewRequest']);
                }

                $this->captureVersion->handle($question, null, 'baseline');
            }

            if ($question->status === QuestionStatus::Rejected) {
                throw ValidationException::withMessages([
                    'status' => 'Câu đã bị từ chối. Chuyển về nháp trước khi chỉnh sửa.',
                ]);
            }

            $hints = array_key_exists('hints', $data)
                ? $this->normalizeHints($data['hints'] ?? [])
                : null;
            $keyInfo = $hints !== null
                ? array_values(array_map(fn (array $hint): string => $hint['content'], $hints))
                : $this->resolveKeyInfo($question, $data['key_info'] ?? []);

            $question->fill([
                'stem' => SafeHtml::fromEditor($data['stem']),
                'stem_image_path' => $this->sanitizeStemImagePath($data['stem_image_path'] ?? null),
                'explanation' => SafeHtml::fromEditor(
                    $this->generalExplanation($data['explanation'] ?? null, $options),
                ) ?: null,
                'key_info' => $keyInfo,
                'attending_tip' => SafeHtml::fromEditor($data['attending_tip'] ?? null) ?: null,
                'difficulty' => Difficulty::from($data['difficulty']),
                'is_free' => $data['is_free'],
                'exam_flag' => (bool) ($data['exam_flag'] ?? false),
                'updated_by' => $actor->getKey(),
            ]);
            $question->version = ($question->version ?: 0) + 1;
            $question->save();
            $this->syncTaxonomyRelations($question, $data);
            if ($hints !== null) {
                $this->syncHints($question, $hints);
            }

            if (SafeHtml::isBlank($question->stem)) {
                throw ValidationException::withMessages([
                    'stem' => 'Vui lòng nhập nội dung câu hỏi.',
                ]);
            }
            $this->syncOptions($question, $options);

            $question->load('options', 'hints', 'coreClinicalTopics', 'medicalTaxonomyNodes', 'tags');
            $this->captureVersion->handle($question, $actor);

            if (! $isReviewer) {
                $reviewRequest = $this->queueCreationReview($actor, $question);
            }

            Auditor::record(
                $before === null ? AuditAction::QuestionCreated : AuditAction::QuestionUpdated,
                $actor,
                $question,
                $before,
                AuditSnapshot::question($question),
                metadata: $reviewRequest !== null ? [
                    'review_request_id' => $reviewRequest->getKey(),
                    'review_action' => $reviewRequest->action->value,
                ] : null,
            );

            return $question;
        });
    }

    /** @param array<string, mixed> $data */
    private function assertMedicalTaxonomyPresent(array $data): void
    {
        $sync = $this->buildMedicalNodeSyncPayload($data);

        if ($sync === []) {
            throw ValidationException::withMessages([
                'medical_taxonomy_node_ids' => 'Vui lòng chọn ít nhất một mục danh mục y khoa.',
            ]);
        }
    }

    /** @param array<string, mixed> $data */
    private function queueUpdate(User $actor, Question $question, array $data): void
    {
        if ($question->reviewRequests()
            ->where('status', QuestionReviewStatus::Pending->value)
            ->exists()) {
            throw ValidationException::withMessages([
                'review' => 'Câu hỏi đang có một yêu cầu chờ duyệt. Vui lòng chờ admin xử lý trước khi gửi thay đổi mới.',
            ]);
        }

        $reviewRequest = QuestionReviewRequest::query()->create([
            'question_id' => $question->getKey(),
            'action' => QuestionReviewAction::Update,
            'payload' => $data,
            'status' => QuestionReviewStatus::Pending,
            'requested_by' => $actor->getKey(),
        ]);

        Auditor::record(
            AuditAction::QuestionUpdateRequested,
            $actor,
            $question,
            AuditSnapshot::question($question),
            AuditSnapshot::questionPayload($data),
            metadata: [
                'review_request_id' => $reviewRequest->getKey(),
                'review_action' => QuestionReviewAction::Update->value,
            ],
        );
    }

    private function queueCreationReview(User $actor, Question $question): QuestionReviewRequest
    {
        $pending = $question->reviewRequests()
            ->where('status', QuestionReviewStatus::Pending->value)
            ->latest('id')
            ->first();

        if ($pending !== null) {
            if ($pending->action !== QuestionReviewAction::Create) {
                throw ValidationException::withMessages([
                    'review' => 'Câu hỏi đang có một yêu cầu khác chờ duyệt.',
                ]);
            }

            $pending->forceFill(['updated_at' => now()])->save();

            return $pending;
        }

        return QuestionReviewRequest::query()->create([
            'question_id' => $question->getKey(),
            'action' => QuestionReviewAction::Create,
            'status' => QuestionReviewStatus::Pending,
            'requested_by' => $actor->getKey(),
        ]);
    }

    private function sanitizeStemImagePath(?string $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        $parsed = parse_url($path, PHP_URL_PATH) ?: $path;

        if (str_starts_with($parsed, '/storage/')) {
            $parsed = substr($parsed, 9);
        } elseif (str_starts_with($parsed, 'storage/')) {
            $parsed = substr($parsed, 8);
        }

        return $parsed !== '' ? $parsed : null;
    }

    /**
     * @param  list<array{id?: int|null, content: string, is_correct: bool, explanation?: ?string}>  $options
     */
    private function generalExplanation(?string $explanation, array $options): ?string
    {
        if (! SafeHtml::isBlank($explanation)) {
            return $explanation;
        }

        foreach ($options as $option) {
            if (($option['is_correct'] ?? false) && ! SafeHtml::isBlank($option['explanation'] ?? null)) {
                return $option['explanation'];
            }
        }

        return $explanation;
    }

    /**
     * @param  array<int, string>  $keyInfo
     * @return array<int, string>
     */
    private function sanitizeKeyInfo(array $keyInfo): array
    {
        return collect($keyInfo)
            ->map(fn (mixed $item): string => trim(strip_tags((string) $item)))
            ->filter(fn (string $item): bool => $item !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $keyInfo
     * @return array<int, string>
     */
    private function resolveKeyInfo(?Question $question, array $keyInfo): array
    {
        $sanitized = $this->sanitizeKeyInfo($keyInfo);

        if ($sanitized !== [] || $question === null) {
            return $sanitized;
        }

        return array_values((array) $question->key_info);
    }

    /**
     * @param  list<array{id?: int|null, content: string, is_correct: bool, explanation?: ?string}>  $options
     */
    private function assertOptionsValid(array $options): void
    {
        if (count($options) < 2) {
            throw ValidationException::withMessages([
                'options' => 'Cần ít nhất 2 đáp án.',
            ]);
        }

        $correct = collect($options)->where('is_correct', true)->count();

        if ($correct !== 1) {
            throw ValidationException::withMessages([
                'options' => 'Phải có đúng 1 đáp án đúng (single best answer).',
            ]);
        }
    }

    /**
     * @param  list<array{id?: int|null, content: string, is_correct: bool, explanation?: ?string}>  $options
     */
    private function syncOptions(Question $question, array $options): void
    {
        $keepIds = [];
        $labels = range('A', 'Z');

        foreach (array_values($options) as $index => $row) {
            $payload = [
                'label' => $labels[$index] ?? (string) ($index + 1),
                'content' => $row['content'],
                'is_correct' => (bool) $row['is_correct'],
                'explanation' => SafeHtml::fromEditor($row['explanation'] ?? null) ?: null,
                'order' => $index + 1,
            ];

            $id = isset($row['id']) ? (int) $row['id'] : null;

            if ($id) {
                $option = QuestionOption::query()
                    ->where('question_id', $question->id)
                    ->whereKey($id)
                    ->first();

                if ($option) {
                    $option->fill($payload)->save();
                    $keepIds[] = $option->id;

                    continue;
                }
            }

            $created = $question->options()->create($payload);
            $keepIds[] = $created->id;
        }

        $question->options()->whereNotIn('id', $keepIds)->delete();
    }

    /** @param  array<string, mixed>  $data */
    private function syncTaxonomyRelations(Question $question, array $data): void
    {
        if (array_key_exists('core_clinical_topic_ids', $data)) {
            $coreIds = collect($data['core_clinical_topic_ids'])
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all();
            $question->coreClinicalTopics()->sync($coreIds);
        }

        $question->medicalTaxonomyNodes()->sync($this->buildMedicalNodeSyncPayload($data));

        if (array_key_exists('tag_ids', $data)) {
            $tagIds = collect($data['tag_ids'])
                ->map(fn ($id): int => (int) $id)
                ->filter(fn (int $id): bool => $id > 0)
                ->unique()
                ->values()
                ->all();
            $question->tags()->sync($tagIds);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array{relationship_type: ?string, is_primary: ?bool}>
     */
    private function buildMedicalNodeSyncPayload(array $data): array
    {
        /** @var array<int, array{relationship_type: ?string, is_primary: ?bool}> $sync */
        $sync = [];

        $links = $data['medical_taxonomy_links'] ?? null;
        if (is_array($links) && $links !== []) {
            foreach ($links as $link) {
                $id = (int) ($link['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $sync[$id] = [
                    'relationship_type' => isset($link['relationship_type']) ? (string) $link['relationship_type'] : null,
                    'is_primary' => array_key_exists('is_primary', $link) ? (bool) $link['is_primary'] : null,
                ];
            }

            return $sync;
        }

        $ids = collect($data['medical_taxonomy_node_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return [];
        }

        $nodes = MedicalTaxonomyNode::query()
            ->whereIn('id', $ids)
            ->get(['id', 'node_type'])
            ->keyBy('id');

        $primaryAssigned = false;

        foreach ($ids as $id) {
            $nodeType = (string) ($nodes->get($id)?->node_type ?? '');
            [$relationshipType, $isPrimary] = $this->relationshipForNodeType($nodeType, $primaryAssigned);
            if ($isPrimary === true) {
                $primaryAssigned = true;
            }

            $sync[$id] = [
                'relationship_type' => $relationshipType,
                'is_primary' => $isPrimary,
            ];
        }

        return $sync;
    }

    /**
     * @return array{0: string, 1: ?bool}
     */
    private function relationshipForNodeType(string $nodeType, bool $primaryAssigned): array
    {
        return match ($nodeType) {
            'disease', 'condition' => $primaryAssigned
                ? ['related', false]
                : ['primary', true],
            'concept' => ['tested', false],
            'symptom', 'sign', 'clinical_finding', 'lab_finding', 'imaging_finding' => ['related', false],
            default => ['contextual', false],
        };
    }

    /**
     * @param  list<array{id?: int|null, content: string, sort_order?: int}>|null  $hints
     * @return list<array{id: ?int, content: string, sort_order: int}>
     */
    private function normalizeHints(?array $hints): array
    {
        if ($hints === null) {
            return [];
        }

        $normalized = [];
        $order = 1;

        foreach ($hints as $hint) {
            $content = trim(strip_tags((string) ($hint['content'] ?? '')));
            if ($content === '') {
                continue;
            }

            $normalized[] = [
                'id' => isset($hint['id']) && $hint['id'] !== null && $hint['id'] !== ''
                    ? (int) $hint['id']
                    : null,
                'content' => $content,
                'sort_order' => $order,
            ];
            $order++;
        }

        return $normalized;
    }

    /**
     * @param  list<array{id: ?int, content: string, sort_order: int}>  $hints
     */
    private function syncHints(Question $question, array $hints): void
    {
        $keepIds = [];

        foreach ($hints as $hint) {
            $payload = [
                'content' => $hint['content'],
                'sort_order' => $hint['sort_order'],
                'status' => TaxonomyStatus::Active,
            ];

            if ($hint['id']) {
                $existing = QuestionHint::query()
                    ->where('question_id', $question->id)
                    ->whereKey($hint['id'])
                    ->first();

                if ($existing) {
                    $existing->fill($payload)->save();
                    $keepIds[] = $existing->id;

                    continue;
                }
            }

            $created = $question->hints()->create($payload);
            $keepIds[] = $created->id;
        }

        $question->hints()->whereNotIn('id', $keepIds)->delete();
    }
}
