<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create migration was edited in-place from body → content, so existing DBs
 * still have `body` while the model expects `content` (strict mode throws).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cms_pages')) {
            return;
        }

        if (! Schema::hasColumn('cms_pages', 'content')) {
            Schema::table('cms_pages', function (Blueprint $table): void {
                $table->json('content')->nullable()->after('title');
            });
        }

        if (Schema::hasColumn('cms_pages', 'body')) {
            Schema::table('cms_pages', function (Blueprint $table): void {
                $table->dropColumn('body');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('cms_pages')) {
            return;
        }

        if (! Schema::hasColumn('cms_pages', 'body')) {
            Schema::table('cms_pages', function (Blueprint $table): void {
                $table->longText('body')->nullable()->after('title');
            });
        }

        if (Schema::hasColumn('cms_pages', 'content')) {
            Schema::table('cms_pages', function (Blueprint $table): void {
                $table->dropColumn('content');
            });
        }
    }
};
