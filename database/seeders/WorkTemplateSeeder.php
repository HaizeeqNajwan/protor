<?php

namespace Database\Seeders;

use App\Models\WorkTemplate;
use Illuminate\Database\Seeder;

class WorkTemplateSeeder extends Seeder
{
    /** Inserts the default Pre-Design / Design / Post-Design works if none exist. */
    public function run(): void
    {
        WorkTemplate::seedDefaults();
    }
}
