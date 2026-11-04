<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use SoftDeletes;

    protected $fillable = ['household_id', 'patient_uuid', 'patient_number', 'qr_code_token', 'first_name', 'middle_name', 'last_name', 'suffix', 'date_of_birth', 'sex_at_birth', 'contact_number', 'email', 'guardian_name', 'guardian_relationship', 'guardian_contact_number', 'address_notes', 'philhealth_id', 'is_household_head'];
    protected function casts(): array { return ['date_of_birth' => 'date', 'is_household_head' => 'boolean']; }
    public function household() { return $this->belongsTo(Household::class); }
    public function encounters() { return $this->hasMany(ClinicalEncounter::class); }
    public function immunizations() { return $this->hasMany(ImmunizationRecord::class); }
    public function getFullNameAttribute(): string { return trim(implode(' ', array_filter([$this->first_name, $this->middle_name, $this->last_name, $this->suffix]))); }
}
