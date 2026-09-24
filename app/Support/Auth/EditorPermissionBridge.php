<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Models\User;
use App\Support\Enums\Role;

/**
 * Compatibility bridge while content use-cases move out of Admin controllers.
 *
 * The role stores only editor-owned permissions. Legacy checks inside shared
 * domain/UI code are resolved to their Editor equivalent without granting the
 * corresponding Admin permission in the database.
 */
final class EditorPermissionBridge
{
    /** @var array<string, string> */
    private const MAP = [
        'question.view' => 'editor_question.view',
        'question.create' => 'editor_question.create',
        'question.update' => 'editor_question.update',
        'question.delete' => 'editor_question.delete',
        'question.submit' => 'editor_question.submit',
        'question.import' => 'editor_question.import',
        'question.export' => 'editor_question.export',
        'taxonomy.view' => 'editor_taxonomy.view',
        'blueprint.view' => 'editor_blueprint.view',
        'blueprint.create' => 'editor_blueprint.create',
        'blueprint.update' => 'editor_blueprint.update',
        'blueprint.delete' => 'editor_blueprint.delete',
        'curriculum.view' => 'editor_curriculum.view',
        'curriculum.create' => 'editor_curriculum.create',
        'curriculum.update' => 'editor_curriculum.update',
        'curriculum.delete' => 'editor_curriculum.delete',
        'tag.view' => 'editor_tag.view',
        'tag.create' => 'editor_tag.create',
        'tag.update' => 'editor_tag.update',
        'tag.delete' => 'editor_tag.delete',
        'cms.view' => 'editor_page.view',
        'cms.create' => 'editor_faq.create',
        'cms.update' => 'editor_page.update',
        'cms.delete' => 'editor_faq.delete',
        'media.view' => 'editor_media.view',
        'media.upload' => 'editor_media.upload',
        'media.update' => 'editor_media.update',
        'media.delete' => 'editor_media.delete',
        'profile.view' => 'editor_profile.view',
        'profile.update' => 'editor_profile.update',
        'profile.password_update' => 'editor_profile.password_update',
        'profile.avatar_update' => 'editor_profile.avatar_update',
        'profile.two_factor_toggle' => 'editor_profile.two_factor_toggle',
    ];

    public static function resolve(User $user, string $ability): ?bool
    {
        if (! $user->hasRole(Role::ContentEditor->value)) {
            return null;
        }

        $editorAbility = self::MAP[$ability] ?? null;

        return $editorAbility === null ? null : $user->hasPermissionTo($editorAbility);
    }
}
