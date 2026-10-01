<?php

use App\Models\Project;
use Illuminate\Database\Migrations\Migration;

/** Projects created before work templates existed get the standard works too. */
return new class extends Migration
{
    public function up(): void
    {
        Project::query()
            ->whereDoesntHave('tasks')
            ->each(fn (Project $project) => $project->applyWorkTemplates());
    }

    public function down(): void
    {
        // Leave the works in place.
    }
};
