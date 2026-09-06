<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('core_topic_tags')) {
            return;
        }

        Schema::create('core_topic_tags', function (Blueprint $table): void {
            $table->unsignedBigInteger('core_clinical_topic_id');
            $table->unsignedBigInteger('tag_id');
            $table->timestamps();

            $table->primary(['core_clinical_topic_id', 'tag_id'], 'ct_tag_primary');
            $table->foreign('core_clinical_topic_id', 'ct_tag_core_fk')
                ->references('id')->on('core_clinical_topics')->cascadeOnDelete();
            $table->foreign('tag_id', 'ct_tag_tag_fk')
                ->references('id')->on('tags')->cascadeOnDelete();
            $table->index('tag_id', 'ct_tag_tag_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_topic_tags');
    }
};
