<?php

namespace Tests\Feature;

use App\Models\Icd10Code;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class HimApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_issues_a_bearer_token_and_profile_is_protected(): void
    {
        $user = User::create(['name' => 'Nurse Maria', 'email' => 'nurse@example.test', 'password' => 'a-strong-password', 'role' => 'health_worker']);

        $response = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'a-strong-password']);
        $response->assertOk()->assertJsonPath('user.role', 'health_worker')->assertJsonStructure(['token', 'token_type', 'user']);

        $this->withToken($response->json('token'))->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('user.id', $user->id);
    }

    public function test_staff_management_is_restricted_to_super_admins(): void
    {
        $worker = User::create(['name' => 'Health Worker', 'email' => 'worker@example.test', 'password' => 'a-strong-password', 'role' => 'health_worker']);
        $admin = User::create(['name' => 'Administrator', 'email' => 'admin@example.test', 'password' => 'a-strong-password', 'role' => 'super_admin']);

        $this->actingAs($worker)->getJson('/api/v1/users')->assertForbidden();
        $this->actingAs($admin)->postJson('/api/v1/users', [
            'name' => 'New Worker', 'email' => 'new@example.test', 'password' => 'another-strong-password', 'role' => 'health_worker',
        ])->assertCreated()->assertJsonPath('role', 'health_worker');
    }

    public function test_patient_creation_and_diagnosis_reporting_work(): void
    {
        $worker = User::create(['name' => 'Health Worker', 'email' => 'worker@example.test', 'password' => 'a-strong-password', 'role' => 'health_worker']);
        $code = Icd10Code::create(['code' => 'J00', 'description' => 'Acute nasopharyngitis', 'is_active' => true]);
        $patient = $this->actingAs($worker)->postJson('/api/v1/patients', [
            'patient_number' => 'BHC-001', 'first_name' => 'Ana', 'last_name' => 'Santos', 'date_of_birth' => '2020-01-01', 'sex_at_birth' => 'female',
        ])->assertCreated()->assertJsonPath('patient_number', 'BHC-001')->json();

        $this->postJson('/api/v1/encounters', [
            'patient_id' => $patient['id'], 'encountered_at' => now()->toDateTimeString(), 'chief_complaint' => 'Cough',
            'diagnoses' => [['icd10_code_id' => $code->id, 'is_primary' => true]],
        ])->assertCreated();

        $this->getJson('/api/v1/reports/fhsis/diagnoses?from='.now()->toDateString().'&to='.now()->toDateString())
            ->assertOk()->assertJsonPath('diagnoses.0.code', 'J00')->assertJsonPath('diagnoses.0.primary_count', 1);
    }

    public function test_sync_processes_a_transaction_only_once(): void
    {
        $worker = User::create(['name' => 'Health Worker', 'email' => 'worker@example.test', 'password' => 'a-strong-password', 'role' => 'health_worker']);
        $clientTransactionId = (string) Str::uuid();
        $patientUuid = (string) Str::uuid();
        $request = [
            'client_device_id' => 'clinic-tablet-1',
            'transactions' => [[
                'client_transaction_id' => $clientTransactionId, 'client_entity_id' => $patientUuid,
                'entity_type' => 'patient', 'operation' => 'create',
                'payload' => ['patient_number' => 'OFFLINE-001', 'first_name' => 'Lia', 'last_name' => 'Reyes', 'date_of_birth' => '2021-06-01', 'sex_at_birth' => 'female'],
            ]],
        ];

        $this->actingAs($worker)->postJson('/api/v1/sync', $request)->assertOk()->assertJsonPath('results.0.status', 'processed');
        $this->postJson('/api/v1/sync', $request)->assertOk()->assertJsonPath('results.0.idempotent', true);
        $this->assertSame(1, Patient::where('patient_number', 'OFFLINE-001')->count());
    }
}
