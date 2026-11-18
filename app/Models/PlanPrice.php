<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A fixed price for one plan in one country (overrides the USD-rate conversion). */
#[Fillable(['plan_id', 'country_code', 'currency_code', 'price_monthly', 'price_yearly'])]
class PlanPrice extends Model
{
    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
