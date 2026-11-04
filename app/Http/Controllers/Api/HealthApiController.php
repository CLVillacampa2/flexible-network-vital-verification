<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClinicalEncounter;
use App\Models\ClinicalEncounterDiagnosis;
use App\Models\Household;
use App\Models\Icd10Code;
use App\Models\ImmunizationRecord;
use App\Models\Patient;
use App\Models\SyncLog;
use App\Models\User;
use App\Models\VaccineSchedule;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class HealthApiController extends Controller
{
    public function summary()
    {
        return response()->json([
            'patients' => Patient::count(),
            'encounters' => ClinicalEncounter::count(),
            'immunizations' => ImmunizationRecord::count(),
        ]);
    }

    public function users() { return User::query()->orderBy('name')->get(['id', 'name', 'email', 'role', 'created_at']); }

    public function createUser(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', 'unique:users'], 'password' => ['required', 'string', 'min:12'], 'role' => ['required', Rule::in(['super_admin', 'health_worker'])]]);
        $user = User::create($data);
        return response()->json($user->only('id', 'name', 'email', 'role'), 201);
    }

    public function updateUser(Request $request, User $user)
    {
        $data = $request->validate(['name' => ['sometimes', 'required', 'string', 'max:255'], 'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)], 'password' => ['sometimes', 'required', 'string', 'min:12'], 'role' => ['sometimes', 'required', Rule::in(['super_admin', 'health_worker'])]]);
        $user->update($data);
        return response()->json($user->only('id', 'name', 'email', 'role'));
    }

    public function households()
    {
        return Household::withCount('patients')->orderBy('household_code')->get();
    }

    public function createHousehold(Request $request)
    {
        $data = $request->validate([
            'household_code' => ['required', 'string', 'max:40', 'unique:households'],
            'region' => ['nullable', 'string', 'max:100'], 'province' => ['nullable', 'string', 'max:100'],
            'city_municipality' => ['nullable', 'string', 'max:100'], 'barangay' => ['required', 'string', 'max:100'],
            'purok_sitio' => ['nullable', 'string', 'max:100'], 'street' => ['nullable', 'string', 'max:150'],
            'house_number' => ['nullable', 'string', 'max:50'], 'postal_code' => ['nullable', 'string', 'max:10'],
            'contact_number' => ['nullable', 'string', 'max:30'], 'address_notes' => ['nullable', 'string'],
        ]);
        $data['household_uuid'] = (string) Str::uuid();
        return response()->json(Household::create($data), 201);
    }

    public function icd10(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        return Icd10Code::where('is_active', true)
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('code', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%")))
            ->orderBy('code')->limit(100)->get(['id', 'code', 'description', 'category']);
    }

    public function patients(Request $request)
    {
        $query = Patient::with('household:id,household_code,barangay')->latest();
        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(fn ($q) => $q->where('first_name', 'like', "%{$search}%")
                ->orWhere('middle_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('patient_number', 'like', "%{$search}%")->orWhere('philhealth_id', 'like', "%{$search}%"));
        }
        return $query->paginate(min((int) $request->query('per_page', 25), 100));
    }

    public function createPatient(Request $request)
    {
        $data = $request->validate([
            'household_id' => ['nullable', 'exists:households,id'], 'patient_number' => ['required', 'string', 'max:40', 'unique:patients'],
            'first_name' => ['required', 'string', 'max:100'], 'middle_name' => ['nullable', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100'], 'suffix' => ['nullable', 'string', 'max:20'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today'], 'sex_at_birth' => ['required', 'string', 'max:20'], 'contact_number' => ['nullable', 'string', 'max:30'], 'email' => ['nullable', 'email', 'max:150'],
            'guardian_name' => ['nullable', 'string', 'max:200'], 'guardian_relationship' => ['nullable', 'string', 'max:50'], 'guardian_contact_number' => ['nullable', 'string', 'max:30'], 'address_notes' => ['nullable', 'string'],
            'philhealth_id' => ['nullable', 'string', 'max:30', 'unique:patients'], 'is_household_head' => ['sometimes', 'boolean'],
        ]);
        $data['patient_uuid'] = (string) Str::uuid();
        $data['qr_code_token'] = (string) Str::uuid();
        return response()->json(Patient::create($data)->load('household'), 201);
    }

    public function updatePatient(Request $request, Patient $patient)
    {
        $data = $request->validate([
            'household_id' => ['sometimes', 'nullable', 'exists:households,id'], 'patient_number' => ['sometimes', 'required', 'string', 'max:40', Rule::unique('patients')->ignore($patient->id)],
            'first_name' => ['sometimes', 'required', 'string', 'max:100'], 'middle_name' => ['nullable', 'string', 'max:100'], 'last_name' => ['sometimes', 'required', 'string', 'max:100'], 'suffix' => ['nullable', 'string', 'max:20'],
            'date_of_birth' => ['sometimes', 'required', 'date', 'before_or_equal:today'], 'sex_at_birth' => ['sometimes', 'required', 'string', 'max:20'], 'contact_number' => ['nullable', 'string', 'max:30'], 'email' => ['nullable', 'email', 'max:150'],
            'guardian_name' => ['nullable', 'string', 'max:200'], 'guardian_relationship' => ['nullable', 'string', 'max:50'], 'guardian_contact_number' => ['nullable', 'string', 'max:30'], 'address_notes' => ['nullable', 'string'],
            'philhealth_id' => ['nullable', 'string', 'max:30', Rule::unique('patients')->ignore($patient->id)], 'is_household_head' => ['sometimes', 'boolean'],
        ]);
        $patient->update($data);
        return response()->json($patient->fresh()->load('household'));
    }

    public function deletePatient(Patient $patient) { $patient->delete(); return response()->noContent(); }

    public function encounters(Request $request)
    {
        return ClinicalEncounter::with(['patient:id,first_name,last_name,patient_number', 'diagnoses.code:id,code,description'])
            ->when($request->filled('patient_id'), fn ($q) => $q->where('patient_id', $request->integer('patient_id')))
            ->latest('encountered_at')->paginate(25);
    }

    public function createEncounter(Request $request)
    {
        $data = $request->validate([
            'client_encounter_id' => ['nullable', 'uuid', 'unique:clinical_encounters,client_encounter_id'], 'patient_id' => ['required', 'exists:patients,id'],
            'encountered_at' => ['required', 'date'], 'encounter_type' => ['sometimes', 'string', 'max:50'], 'chief_complaint' => ['nullable', 'string', 'max:500'], 'clinical_notes' => ['nullable', 'string'],
            'temperature_celsius' => ['nullable', 'numeric', 'between:20,50'], 'systolic_bp' => ['nullable', 'integer', 'between:0,400'], 'diastolic_bp' => ['nullable', 'integer', 'between:0,300'],
            'pulse_rate' => ['nullable', 'integer', 'between:0,400'], 'respiratory_rate' => ['nullable', 'integer', 'between:0,200'], 'oxygen_saturation' => ['nullable', 'numeric', 'between:0,100'],
            'weight_kg' => ['nullable', 'numeric', 'between:0,1000'], 'height_cm' => ['nullable', 'numeric', 'between:0,300'], 'follow_up_at' => ['nullable', 'date'],
            'diagnoses' => ['sometimes', 'array'], 'diagnoses.*.icd10_code_id' => ['required', 'integer', 'exists:icd10_codes,id'], 'diagnoses.*.is_primary' => ['sometimes', 'boolean'], 'diagnoses.*.diagnosis_notes' => ['nullable', 'string'],
        ]);
        $diagnoses = $data['diagnoses'] ?? [];
        if (count(array_filter($diagnoses, fn ($d) => $d['is_primary'] ?? false)) > 1) {
            return response()->json(['message' => 'Only one diagnosis may be primary.'], 422);
        }
        $data['client_encounter_id'] = $data['client_encounter_id'] ?? (string) Str::uuid();
        $data['attending_user_id'] = $request->user()->id;
        unset($data['diagnoses']);
        $encounter = DB::transaction(function () use ($data, $diagnoses) {
            $encounter = ClinicalEncounter::create($data);
            foreach ($diagnoses as $diagnosis) { $encounter->diagnoses()->create($diagnosis); }
            return $encounter;
        });
        return response()->json($encounter->load('diagnoses.code', 'patient'), 201);
    }

    public function immunizations(Request $request)
    {
        return ImmunizationRecord::with(['patient:id,first_name,last_name,patient_number', 'schedule', 'administrator:id,name'])
            ->when($request->filled('patient_id'), fn ($q) => $q->where('patient_id', $request->integer('patient_id')))
            ->latest('administered_at')->paginate(50);
    }

    public function vaccineSchedules() { return VaccineSchedule::where('is_active', true)->orderBy('recommended_age_days')->get(); }

    public function createImmunization(Request $request)
    {
        $data = $request->validate(['client_record_id' => ['nullable', 'uuid', 'unique:immunization_records,client_record_id'], 'patient_id' => ['required', 'exists:patients,id'], 'vaccine_schedule_id' => ['required', 'exists:vaccine_schedules,id'], 'administered_at' => ['required', 'date'], 'dose_label' => ['nullable', 'string', 'max:50'], 'lot_number' => ['nullable', 'string', 'max:100'], 'vaccine_expiry_date' => ['nullable', 'date'], 'administration_site' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string']]);
        $data['client_record_id'] = $data['client_record_id'] ?? (string) Str::uuid();
        $data['administered_by'] = $request->user()->id;
        return response()->json(ImmunizationRecord::create($data)->load('schedule', 'patient'), 201);
    }

    public function sync(Request $request)
    {
        $data = $request->validate([
            'client_device_id' => ['required', 'string', 'max:100'],
            'transactions' => ['required', 'array', 'min:1', 'max:100'],
            'transactions.*.client_transaction_id' => ['required', 'uuid'],
            'transactions.*.entity_type' => ['required', Rule::in(['patient', 'encounter', 'immunization'])],
            'transactions.*.client_entity_id' => ['nullable', 'uuid'],
            'transactions.*.operation' => ['required', Rule::in(['create'])],
            'transactions.*.payload' => ['required', 'array'],
        ]);

        $results = [];

        foreach ($data['transactions'] as $transaction) {
            $clientEntityId = $transaction['client_entity_id'] ?? null;
            $hash = hash('sha256', json_encode([
                'entity_type' => $transaction['entity_type'],
                'client_entity_id' => $clientEntityId,
                'operation' => $transaction['operation'],
                'payload' => $transaction['payload'],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            $log = SyncLog::where('client_device_id', $data['client_device_id'])
                ->where('client_transaction_id', $transaction['client_transaction_id'])
                ->first();

            // A transaction ID must never be reused for different content.
            if ($log && ! hash_equals((string) $log->payload_hash, $hash)) {
                $results[] = [
                    'client_transaction_id' => $transaction['client_transaction_id'],
                    'status' => 'failed',
                    'message' => 'Transaction ID was previously used with different content.',
                ];
                continue;
            }

            // Only a completed transaction is safe to acknowledge as a retry.
            // Pending and failed rows must be allowed to run again.
            if ($log && in_array($log->status, ['processed', 'completed'], true)) {
                $results[] = [
                    'client_transaction_id' => $log->client_transaction_id,
                    'status' => $log->status,
                    'idempotent' => true,
                    'metadata' => $log->result_metadata,
                ];
                continue;
            }

            try {
                if (! $log) {
                    $log = SyncLog::create([
                        'user_id' => $request->user()->id,
                        'client_device_id' => $data['client_device_id'],
                        'client_transaction_id' => $transaction['client_transaction_id'],
                        'entity_type' => $transaction['entity_type'],
                        'client_entity_id' => $clientEntityId,
                        'operation' => $transaction['operation'],
                        'status' => 'pending',
                        'payload_hash' => $hash,
                        'received_at' => now(),
                    ]);
                } else {
                    $log->update([
                        'status' => 'pending',
                        'error_message' => null,
                        'processed_at' => null,
                    ]);
                }

                $metadata = DB::transaction(function () use ($transaction, $request, $clientEntityId) {
                    $payload = $transaction['payload'];

                    if ($transaction['entity_type'] === 'patient') {
                        // Recover safely if the patient row was committed but the
                        // sync-log status update was interrupted.
                        $existingPatient = $clientEntityId
                            ? Patient::where('patient_uuid', $clientEntityId)->first()
                            : null;

                        if ($existingPatient) {
                            return [
                                'patient_id' => $existingPatient->id,
                                'patient_number' => $existingPatient->patient_number,
                            ];
                        }

                        $validated = validator($payload, [
                            'household_id' => ['nullable', 'exists:households,id'],
                            'patient_number' => ['required', 'string', 'max:40', 'unique:patients'],
                            'first_name' => ['required', 'string', 'max:100'],
                            'middle_name' => ['nullable', 'string', 'max:100'],
                            'last_name' => ['required', 'string', 'max:100'],
                            'suffix' => ['nullable', 'string', 'max:20'],
                            'date_of_birth' => ['required', 'date', 'before_or_equal:today'],
                            'sex_at_birth' => ['required', 'string', 'max:20'],
                            'contact_number' => ['nullable', 'string', 'max:30'],
                            'email' => ['nullable', 'email', 'max:150'],
                            'guardian_name' => ['nullable', 'string', 'max:200'],
                            'guardian_relationship' => ['nullable', 'string', 'max:50'],
                            'guardian_contact_number' => ['nullable', 'string', 'max:30'],
                            'address_notes' => ['nullable', 'string'],
                            'philhealth_id' => ['nullable', 'string', 'max:30', 'unique:patients'],
                            'is_household_head' => ['sometimes', 'boolean'],
                        ])->validate();

                        $patient = Patient::create($validated + [
                            'patient_uuid' => $clientEntityId ?? (string) Str::uuid(),
                            'qr_code_token' => (string) Str::uuid(),
                        ]);

                        return [
                            'patient_id' => $patient->id,
                            'patient_number' => $patient->patient_number,
                        ];
                    }

                    if ($transaction['entity_type'] === 'encounter') {
                        $clientEncounterId = $clientEntityId
                            ?? ($payload['client_encounter_id'] ?? (string) Str::uuid());

                        $existingEncounter = ClinicalEncounter::where('client_encounter_id', $clientEncounterId)->first();

                        if ($existingEncounter) {
                            return ['encounter_id' => $existingEncounter->id];
                        }

                        $validated = validator($payload, [
                            'patient_id' => ['required', 'exists:patients,id'],
                            'encountered_at' => ['required', 'date'],
                            'encounter_type' => ['sometimes', 'string', 'max:50'],
                            'chief_complaint' => ['nullable', 'string', 'max:500'],
                            'clinical_notes' => ['nullable', 'string'],
                            'temperature_celsius' => ['nullable', 'numeric', 'between:20,50'],
                            'systolic_bp' => ['nullable', 'integer', 'between:0,400'],
                            'diastolic_bp' => ['nullable', 'integer', 'between:0,300'],
                            'pulse_rate' => ['nullable', 'integer', 'between:0,400'],
                            'respiratory_rate' => ['nullable', 'integer', 'between:0,200'],
                            'oxygen_saturation' => ['nullable', 'numeric', 'between:0,100'],
                            'weight_kg' => ['nullable', 'numeric', 'between:0,1000'],
                            'height_cm' => ['nullable', 'numeric', 'between:0,300'],
                            'follow_up_at' => ['nullable', 'date'],
                            'diagnoses' => ['sometimes', 'array'],
                            'diagnoses.*.icd10_code_id' => ['required', 'integer', 'exists:icd10_codes,id'],
                            'diagnoses.*.is_primary' => ['sometimes', 'boolean'],
                            'diagnoses.*.diagnosis_notes' => ['nullable', 'string'],
                        ])->validate();

                        $diagnoses = $validated['diagnoses'] ?? [];
                        if (count(array_filter($diagnoses, fn ($diagnosis) => $diagnosis['is_primary'] ?? false)) > 1) {
                            throw \Illuminate\Validation\ValidationException::withMessages([
                                'diagnoses' => 'Only one diagnosis may be primary.',
                            ]);
                        }

                        unset($validated['diagnoses']);

                        $encounter = ClinicalEncounter::create($validated + [
                            'client_encounter_id' => $clientEncounterId,
                            'attending_user_id' => $request->user()->id,
                        ]);

                        foreach ($diagnoses as $diagnosis) {
                            $encounter->diagnoses()->create($diagnosis);
                        }

                        return ['encounter_id' => $encounter->id];
                    }

                    $clientRecordId = $clientEntityId
                        ?? ($payload['client_record_id'] ?? (string) Str::uuid());

                    $existingRecord = ImmunizationRecord::where('client_record_id', $clientRecordId)->first();

                    if ($existingRecord) {
                        return ['immunization_record_id' => $existingRecord->id];
                    }

                    $validated = validator($payload, [
                        'patient_id' => ['required', 'exists:patients,id'],
                        'vaccine_schedule_id' => ['required', 'exists:vaccine_schedules,id'],
                        'administered_at' => ['required', 'date'],
                        'dose_label' => ['nullable', 'string', 'max:50'],
                        'lot_number' => ['nullable', 'string', 'max:100'],
                        'vaccine_expiry_date' => ['nullable', 'date'],
                        'administration_site' => ['nullable', 'string', 'max:100'],
                        'notes' => ['nullable', 'string'],
                    ])->validate();

                    $record = ImmunizationRecord::create($validated + [
                        'client_record_id' => $clientRecordId,
                        'administered_by' => $request->user()->id,
                    ]);

                    return ['immunization_record_id' => $record->id];
                });

                $log->update([
                    'status' => 'processed',
                    'result_metadata' => $metadata,
                    'error_message' => null,
                    'processed_at' => now(),
                ]);

                $results[] = [
                    'client_transaction_id' => $log->client_transaction_id,
                    'status' => 'processed',
                    'metadata' => $metadata,
                ];
            } catch (Throwable $exception) {
                if ($log) {
                    try {
                        $log->update([
                            'status' => 'failed',
                            'error_message' => mb_substr($exception->getMessage(), 0, 5000),
                            'processed_at' => now(),
                        ]);
                    } catch (Throwable) {
                        // Keep the response useful even if logging fails.
                    }
                }

                $results[] = [
                    'client_transaction_id' => $transaction['client_transaction_id'],
                    'status' => 'failed',
                    'message' => 'Transaction failed validation or processing.',
                ];
            }
        }

        return response()->json(['results' => $results]);
    }

    public function diagnosisReport(Request $request)
    {
        $filters = $request->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from']]);
        $rows = DB::table('clinical_encounter_diagnoses as d')->join('clinical_encounters as e', 'e.id', '=', 'd.clinical_encounter_id')->join('icd10_codes as c', 'c.id', '=', 'd.icd10_code_id')
            ->whereBetween('e.encountered_at', [$filters['from'].' 00:00:00', $filters['to'].' 23:59:59'])
            ->groupBy('c.id', 'c.code', 'c.description')->select('c.code', 'c.description', DB::raw('COUNT(*) as total_count'), DB::raw('SUM(CASE WHEN d.is_primary = 1 THEN 1 ELSE 0 END) as primary_count'))->orderByDesc('total_count')->get();
        return response()->json(['from' => $filters['from'], 'to' => $filters['to'], 'diagnoses' => $rows]);
    }
}
