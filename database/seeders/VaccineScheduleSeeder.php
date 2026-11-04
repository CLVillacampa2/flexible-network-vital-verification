<?php

namespace Database\Seeders;

use App\Models\VaccineSchedule;
use Illuminate\Database\Seeder;

class VaccineScheduleSeeder extends Seeder
{
    public function run(): void
    {
        $vaccines = [
            ['BCG', 'Bacillus Calmette-Guérin', [0]], ['HepB', 'Hepatitis B', [0]],
            ['Penta', 'Pentavalent', [45, 75, 105]], ['OPV', 'Oral Polio Vaccine', [45, 75, 105]],
            ['IPV', 'Inactivated Polio Vaccine', [105, 270]], ['PCV', 'Pneumococcal Conjugate Vaccine', [45, 75, 105]],
            ['MMR', 'Measles, Mumps, Rubella', [270, 365]],
        ];
        foreach ($vaccines as [$code, $name, $ages]) {
            foreach ($ages as $index => $age) {
                $dose = $index + 1;
                VaccineSchedule::updateOrCreate(['vaccine_code' => $code, 'dose_number' => $dose], [
                    'vaccine_name' => $name, 'recommended_age_days' => $age,
                    'recommended_age_months' => (int) round($age / 30),
                    'minimum_age_days' => $age === 0 ? 0 : max(0, $age - 7),
                    'earliest_age_days' => $age === 0 ? 0 : max(0, $age - 7),
                    'minimum_interval_days' => $dose > 1 ? 28 : null,
                    'dose_label' => $age === 0 ? 'Birth dose' : 'Dose '.$dose,
                    'notes' => 'Routine childhood immunization schedule. Confirm current DOH guidance before administration.',
                    'is_active' => true,
                ]);
            }
        }
    }
}
