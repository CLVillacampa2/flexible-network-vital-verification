<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('households')) {
            Schema::create('households', function (Blueprint $table) {
            $table->id();
            $table->uuid('household_uuid')->nullable()->unique();
            $table->string('household_code', 40)->unique();
            $table->string('region', 100)->nullable();
            $table->string('province', 100)->nullable();
            $table->string('city_municipality', 100)->nullable();
            $table->string('barangay', 100);
            $table->string('purok_sitio', 100)->nullable();
            $table->string('street', 150)->nullable();
            $table->string('house_number', 50)->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('contact_number', 30)->nullable();
            $table->text('address_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            });
        }

        if (!Schema::hasTable('patients')) {
            Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->nullable()->constrained()->nullOnDelete();
            $table->uuid('patient_uuid')->nullable()->unique();
            $table->string('patient_number', 40)->unique();
            $table->uuid('qr_code_token')->unique();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('suffix', 20)->nullable();
            $table->date('date_of_birth')->index();
            $table->string('sex_at_birth', 20)->default('unknown');
            $table->string('contact_number', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('guardian_name', 200)->nullable();
            $table->string('guardian_relationship', 50)->nullable();
            $table->string('guardian_contact_number', 30)->nullable();
            $table->text('address_notes')->nullable();
            $table->string('philhealth_id', 30)->nullable()->unique();
            $table->boolean('is_household_head')->default(false);
            $table->timestamps();
            $table->softDeletes();
            });
        }

        if (!Schema::hasTable('clinical_encounters')) {
            Schema::create('clinical_encounters', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_encounter_id')->unique();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('attending_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('encountered_at')->index();
            $table->string('encounter_type', 50)->default('consultation');
            $table->string('chief_complaint', 500)->nullable();
            $table->text('clinical_notes')->nullable();
            $table->decimal('temperature_celsius', 4, 1)->nullable();
            $table->unsignedSmallInteger('systolic_bp')->nullable();
            $table->unsignedSmallInteger('diastolic_bp')->nullable();
            $table->unsignedSmallInteger('pulse_rate')->nullable();
            $table->unsignedSmallInteger('respiratory_rate')->nullable();
            $table->decimal('oxygen_saturation', 5, 2)->nullable();
            $table->decimal('weight_kg', 6, 2)->nullable();
            $table->decimal('height_cm', 6, 2)->nullable();
            $table->dateTime('follow_up_at')->nullable();
            $table->timestamps();
            });
        }

        if (!Schema::hasTable('icd10_codes')) {
            Schema::create('icd10_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('description', 255);
            $table->string('category', 150)->nullable();
            $table->string('release_version', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            });
        }

        if (!Schema::hasTable('clinical_encounter_diagnoses')) {
            Schema::create('clinical_encounter_diagnoses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinical_encounter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('icd10_code_id')->constrained('icd10_codes')->restrictOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->text('diagnosis_notes')->nullable();
            $table->timestamps();
            $table->unique(['clinical_encounter_id', 'icd10_code_id']);
            });
        }

        if (!Schema::hasTable('vaccine_schedules')) {
            Schema::create('vaccine_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('vaccine_code', 30);
            $table->string('vaccine_name', 100);
            $table->unsignedSmallInteger('dose_number');
            $table->unsignedSmallInteger('recommended_age_days');
            $table->unsignedSmallInteger('minimum_age_days')->nullable();
            $table->unsignedSmallInteger('minimum_interval_days')->nullable();
            $table->string('dose_label', 50)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('earliest_age_days')->default(0);
            $table->unsignedSmallInteger('latest_age_days')->nullable();
            $table->unsignedTinyInteger('recommended_age_months')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['vaccine_code', 'dose_number']);
            });
        }

        if (!Schema::hasTable('immunization_records')) {
            Schema::create('immunization_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_record_id')->unique();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('vaccine_schedule_id')->constrained()->restrictOnDelete();
            $table->foreignId('administered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('administered_at')->index();
            $table->string('dose_label', 50)->nullable();
            $table->string('lot_number', 100)->nullable();
            $table->date('vaccine_expiry_date')->nullable();
            $table->string('administration_site', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            });
        }

        if (!Schema::hasTable('sync_logs')) {
            Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('client_device_id', 100);
            $table->uuid('client_transaction_id');
            $table->string('entity_type', 80);
            $table->uuid('client_entity_id')->nullable();
            $table->string('operation', 20);
            $table->string('status', 20)->default('pending');
            $table->char('payload_hash', 64)->nullable();
            $table->json('result_metadata')->nullable();
            $table->text('error_message')->nullable();
            $table->dateTime('received_at');
            $table->dateTime('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['client_device_id', 'client_transaction_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
        Schema::dropIfExists('immunization_records');
        Schema::dropIfExists('vaccine_schedules');
        Schema::dropIfExists('clinical_encounter_diagnoses');
        Schema::dropIfExists('icd10_codes');
        Schema::dropIfExists('clinical_encounters');
        Schema::dropIfExists('patients');
        Schema::dropIfExists('households');
    }
};
