<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variation_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('vo_number', 20);                       // VO-001 (per project)
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('discipline', 30)->nullable();          // EngineeringDiscipline
            $table->string('requested_by')->nullable();            // Client / Architect / Contractor / Authority
            $table->string('status', 20)->default('identified');   // VariationOrderStatus
            $table->boolean('is_scope_creep')->default(false);     // outside original ToR
            $table->decimal('construction_cost_impact', 15, 2)->default(0); // impact on contract sum (RM)
            $table->decimal('fee_claimed', 15, 2)->default(0);     // additional consultancy fee claimed (RM)
            $table->decimal('fee_approved', 15, 2)->default(0);
            $table->decimal('estimated_manhours', 8, 1)->default(0);
            $table->integer('time_impact_days')->default(0);
            $table->date('date_identified')->nullable();
            $table->date('date_submitted')->nullable();
            $table->date('date_decided')->nullable();
            $table->foreignId('raised_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('supporting_documents')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'vo_number']);
            $table->index(['status', 'is_scope_creep']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variation_orders');
    }
};
