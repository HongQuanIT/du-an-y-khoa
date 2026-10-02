<?php

declare(strict_types=1);

use App\Support\LearnerCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->char('learner_code', 6)->nullable()->unique()->after('id');
        });

        $used = [];

        DB::table('users')->orderBy('id')->select('id')->chunkById(200, function ($rows) use (&$used): void {
            foreach ($rows as $row) {
                do {
                    $code = LearnerCode::random();
                } while (isset($used[$code]));

                $used[$code] = true;
                DB::table('users')->where('id', $row->id)->update(['learner_code' => $code]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['learner_code']);
            $table->dropColumn('learner_code');
        });
    }
};
