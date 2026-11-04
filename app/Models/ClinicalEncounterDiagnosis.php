<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicalEncounterDiagnosis extends Model
{
    protected $fillable = ['clinical_encounter_id', 'icd10_code_id', 'is_primary', 'diagnosis_notes'];
    protected function casts(): array { return ['is_primary' => 'boolean']; }
    public function encounter() { return $this->belongsTo(ClinicalEncounter::class, 'clinical_encounter_id'); }
    public function code() { return $this->belongsTo(Icd10Code::class, 'icd10_code_id'); }
}
