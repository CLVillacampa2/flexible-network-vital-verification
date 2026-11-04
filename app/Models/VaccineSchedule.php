<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VaccineSchedule extends Model
{
    protected $fillable = ['vaccine_code', 'vaccine_name', 'dose_number', 'recommended_age_days', 'minimum_age_days', 'minimum_interval_days', 'dose_label', 'notes', 'earliest_age_days', 'latest_age_days', 'recommended_age_months', 'is_active'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function records() { return $this->hasMany(ImmunizationRecord::class); }
}
