<?php

use Illuminate\Support\Facades\Broadcast;
use Modules\Classroom\Models\Classroom;
use Modules\Classroom\Models\LiveSession;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('classroom.{classroomUuid}', function ($user, string $classroomUuid) {
    $classroom = Classroom::query()->where('uuid', $classroomUuid)->first();

    if ($classroom === null || ! $classroom->isActiveMember($user)) {
        return false;
    }

    return [
        'id' => $user->getKey(),
        'name' => $user->name,
        'role' => $classroom->roleFor($user)?->value ?? 'member',
    ];
});

Broadcast::channel('live-session.{sessionUuid}', function ($user, string $sessionUuid) {
    $session = LiveSession::query()->where('uuid', $sessionUuid)->first();

    if ($session === null) {
        return false;
    }

    $classroom = $session->classroom;

    if ($classroom === null || ! $classroom->isActiveMember($user)) {
        return false;
    }

    return [
        'id' => $user->getKey(),
        'name' => $user->name,
        'role' => $classroom->roleFor($user)?->value ?? 'member',
    ];
});
