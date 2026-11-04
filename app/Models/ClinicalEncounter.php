<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicalEncounter extends Model
{
    protected $fillable = ['client_encounter_id', 'patient_id', 'attending_user_id', 'encountered_at', 'encounter_type', 'chief_complaint', 'clinical_notes', 'temperature_celsius', 'systolic_bp', 'diastolic_bp', 'pulse_rate', 'respiratory_rate', 'oxygen_saturation', 'weight_kg', 'height_cm', 'follow_up_at'];
    protected function casts(): array { return ['encountered_at' => 'datetime', 'follow_up_at' => 'datetime']; }
    public function patient() { return $this->belongsTo(Patient::class); }
    public function attendingUser() { return $this->belongsTo(User::class, 'attending_user_id'); }
    public function diagnoses() { return $this->hasMany(ClinicalEncounterDiagnosis::class); }
}
