<?php

declare(strict_types=1);

namespace Modules\Personalization\Actions;

use App\Models\User;
use App\Support\Audit\Auditor;
use App\Support\Audit\Enums\AuditAction;
use App\Support\Concerns\AsAction;
use InvalidArgumentException;
use Modules\Personalization\Models\Note;
use Modules\Personalization\Support\NoteHtml;

final class UpsertNoteAction
{
    use AsAction;

    /**
     * @return array{note: Note|null, deleted: bool}
     */
    public function handle(
        User $user,
        ?string $bodyHtml,
        ?string $body = null,
        ?string $notableType = null,
        ?string $notableId = null,
        ?string $color = null,
        ?Note $existing = null,
    ): array {
        $content = NoteHtml::fromHtml($bodyHtml, $body);

        if ($color !== null && $color !== '' && ! in_array($color, Note::COLORS, true)) {
            throw new InvalidArgumentException('Màu ghi chú không hợp lệ.');
        }

        $isFree = $notableType === null && $notableId === null;
        $isAttached = $notableType !== null && $notableId !== null;

        if (! $isFree && ! $isAttached) {
            throw new InvalidArgumentException('Ghi chú phải gắn nội dung hoặc thuộc sổ tay.');
        }

        if ($isAttached) {
            if (! in_array($notableType, Note::ATTACHED_TYPES, true)) {
                throw new InvalidArgumentException('Loại nội dung ghi chú không hợp lệ.');
            }

            if (! Note::targetExists($notableType, $notableId)) {
                throw new InvalidArgumentException('Nội dung gốc không tồn tại.');
            }
        }

        if (NoteHtml::isEmpty($content['body'], $content['body_html'])) {
            if ($existing !== null) {
                $existing->delete();
                Auditor::record(AuditAction::LearningNoteDeleted, $user, $existing);

                return ['note' => null, 'deleted' => true];
            }

            if ($isAttached) {
                $found = Note::query()
                    ->where('user_id', $user->getKey())
                    ->where('notable_type', $notableType)
                    ->where('notable_id', $notableId)
                    ->first();

                if ($found !== null) {
                    $found->delete();
                    Auditor::record(AuditAction::LearningNoteDeleted, $user, $found);

                    return ['note' => null, 'deleted' => true];
                }
            }

            return ['note' => null, 'deleted' => false];
        }

        $attributes = [
            'body' => $content['body'],
            'body_html' => $content['body_html'],
        ];

        if ($color !== null) {
            $attributes['color'] = $color === '' ? null : $color;
        }

        if ($existing !== null) {
            abort_unless((int) $existing->user_id === (int) $user->getKey(), 403);
            $existing->fill($attributes)->save();
            Auditor::record(AuditAction::LearningNoteUpdated, $user, $existing);

            return ['note' => $existing->refresh(), 'deleted' => false];
        }

        if ($isAttached) {
            $note = Note::query()->firstOrNew([
                'user_id' => $user->getKey(),
                'notable_type' => $notableType,
                'notable_id' => $notableId,
            ]);
            $wasRecentlyCreated = ! $note->exists;
            $note->fill($attributes)->save();

            Auditor::record(
                $wasRecentlyCreated ? AuditAction::LearningNoteCreated : AuditAction::LearningNoteUpdated,
                $user,
                $note,
            );

            return ['note' => $note->refresh(), 'deleted' => false];
        }

        $note = Note::query()->create([
            'user_id' => $user->getKey(),
            'notable_type' => null,
            'notable_id' => null,
            'color' => $attributes['color'] ?? null,
            'body' => $attributes['body'],
            'body_html' => $attributes['body_html'],
        ]);

        Auditor::record(AuditAction::LearningNoteCreated, $user, $note);

        return ['note' => $note, 'deleted' => false];
    }
}
