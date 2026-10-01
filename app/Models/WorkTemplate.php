<?php

namespace App\Models;

use App\Enums\ProjectStage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The standard list of works copied into every new project, per stage.
 * Managers maintain it under Settings → Work Templates.
 */
class WorkTemplate extends Model
{
    /** Default engineering-consultancy works, used to seed the table. */
    public const DEFAULTS = [
        'pre_design' => [
            'Client brief & terms of reference',
            'Site visit & topographic survey',
            'Soil investigation review',
            'Feasibility study',
            'Fee proposal & letter of appointment',
        ],
        'design' => [
            'Conceptual design',
            'Analysis & design calculations',
            'Detailed design drawings',
            'Specifications & bill of quantities',
            'Authority submission & approval',
            'Internal design review & sign-off',
        ],
        'post_design' => [
            'Tender documentation & evaluation',
            'Shop drawing & RFI review',
            'Site inspection & construction supervision',
            'Testing & commissioning',
            'CCC, handover & as-built drawings',
        ],
    ];

    protected $fillable = [
        'stage',
        'title',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'stage' => ProjectStage::class,
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderByRaw("case stage when 'pre_design' then 1 when 'design' then 2 else 3 end")
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /** Insert the default list if the table is empty. */
    public static function seedDefaults(): void
    {
        if (static::query()->exists()) {
            return;
        }

        foreach (static::DEFAULTS as $stage => $titles) {
            foreach ($titles as $index => $title) {
                static::query()->create([
                    'stage' => $stage,
                    'title' => $title,
                    'sort_order' => ($index + 1) * 10,
                    'is_active' => true,
                ]);
            }
        }
    }
}
