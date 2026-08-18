<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Modules\Exam\Models\Exam;
use Modules\Exam\Enums\ExamStatus;
use Modules\QuestionBank\Models\QuestionSession;
use Modules\QuestionBank\Enums\SessionMode;
use Modules\QuestionBank\Enums\SessionSource;
use Modules\QuestionBank\Enums\SessionStatus;
use App\Models\User;

$user = User::first();
$user->givePermissionTo('exam.simulation'); // Ensure entitlement

// 1. Create a dummy exam
$exam = Exam::create([
    'title' => 'Test Exam ' . rand(),
    'description' => 'Test',
    'status' => ExamStatus::Published,
    'duration_minutes' => 60,
    'is_published' => true,
]);

// 2. Create a completed session for this exam
$session = QuestionSession::create([
    'user_id' => $user->id,
    'mode' => SessionMode::Exam,
    'source' => SessionSource::Exam,
    'status' => SessionStatus::Completed,
    'exam_id' => $exam->id,
    'total' => 10,
    'answered_count' => 10,
    'correct_count' => 8,
]);

// 3. Render the view via controller or service
$service = app(\Modules\Exam\Services\ExamCatalogService::class);
$cards = $service->cards($user);

foreach($cards as $card) {
    if ($card['id'] == $exam->id) {
        dump($card['session'] ? $card['session']->status : 'No session');
    }
}
