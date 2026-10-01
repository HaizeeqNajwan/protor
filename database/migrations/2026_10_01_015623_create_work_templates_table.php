<?php

use App\Models\WorkTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_templates', function (Blueprint $table) {
            $table->id();
            $table->string('stage', 20);                        // ProjectStage
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['stage', 'sort_order']);
        });

        // The standard works should exist from day one.
        WorkTemplate::seedDefaults();
    }

    public function down(): void
    {
        Schema::dropIfExists('work_templates');
    }
};
