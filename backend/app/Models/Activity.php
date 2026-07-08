<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * Overrides Spatie's default Activity model to use UUID primary keys,
 * matching this project's UUID-everywhere convention (see the activity_log
 * migration, which defines a uuid `id` and uuidMorphs rather than Spatie's
 * default auto-increment schema).
 */
class Activity extends SpatieActivity
{
    use HasUuids;
}
