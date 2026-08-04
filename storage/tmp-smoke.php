<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

$user = App\Models\User::where('email', 'student@medlearn.local')->firstOrFail();
$plan = Modules\StudyPlan\Models\StudyPlan::where('user_id', $user->id)->firstOrFail();
$task = $plan->todayTasks()->firstOrFail();

Auth::login($user);

$kernel = app(Illuminate\Contracts\Http\Kernel::class);

$pages = [
    'dashboard' => '/dashboard',
    'index' => '/study-plan',
    'create' => '/study-plan/create',
    'detail' => "/study-plan/{$plan->id}",
    'edit' => "/study-plan/{$plan->id}/edit",
    'schedule' => "/study-plan/{$plan->id}/schedule",
    'session' => "/study-plan/{$plan->id}/tasks/{$task->id}/session",
];

foreach ($pages as $name => $url) {
    $request = Request::create($url, 'GET');
    $request->setLaravelSession(app('session.store'));
    $response = $kernel->handle($request);
    $status = $response->getStatusCode();
    $note = '';
    if ($status >= 400) {
        $note = ' :: '.mb_substr(strip_tags((string) $response->getContent()), 0, 300);
    }
    echo str_pad($name, 10)." {$status}{$note}".PHP_EOL;
}
