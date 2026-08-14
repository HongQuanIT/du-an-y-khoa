<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookmark_folders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 128);
            $table->timestamps();

            $table->unique(['user_id', 'name'], 'bookmark_folders_user_name_unique');
        });

        Schema::create('bookmark_folder_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('folder_id')->constrained('bookmark_folders')->cascadeOnDelete();
            $table->string('question_id', 64);
            $table->timestamps();

            $table->unique(['folder_id', 'question_id'], 'bookmark_folder_items_unique');
            $table->index(['question_id'], 'bookmark_folder_items_question_index');
        });

        // Migrate existing flat bookmarks into default "câu hỏi lưu" folder for each user
        $existingBookmarks = DB::table('bookmarks')
            ->where('bookmarkable_type', 'question')
            ->get();

        foreach ($existingBookmarks as $bm) {
            $userId = (int) $bm->user_id;
            $questionId = (string) $bm->bookmarkable_id;

            $folder = DB::table('bookmark_folders')
                ->where('user_id', $userId)
                ->where('name', 'câu hỏi lưu')
                ->first();

            if ($folder === null) {
                $folderId = DB::table('bookmark_folders')->insertGetId([
                    'user_id' => $userId,
                    'name' => 'câu hỏi lưu',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $folderId = $folder->id;
            }

            DB::table('bookmark_folder_items')->insertOrIgnore([
                'folder_id' => $folderId,
                'question_id' => $questionId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bookmark_folder_items');
        Schema::dropIfExists('bookmark_folders');
    }
};
