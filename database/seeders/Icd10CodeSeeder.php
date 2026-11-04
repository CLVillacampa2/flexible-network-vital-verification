<?php

namespace Database\Seeders;

use App\Models\Icd10Code;
use Illuminate\Database\Seeder;

class Icd10CodeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['J00', 'Acute nasopharyngitis (common cold)', 'Respiratory'],
            ['A09', 'Infectious gastroenteritis and colitis, unspecified', 'Digestive'],
            ['J18.9', 'Pneumonia, unspecified organism', 'Respiratory'],
            ['E11', 'Type 2 diabetes mellitus', 'Endocrine'],
            ['I10', 'Essential (primary) hypertension', 'Circulatory'],
            ['L03', 'Cellulitis', 'Skin'], ['B05', 'Measles', 'Infectious'],
            ['A01.0', 'Typhoid fever', 'Infectious'], ['J45', 'Asthma', 'Respiratory'],
        ] as [$code, $description, $category]) {
            Icd10Code::updateOrCreate(['code' => $code], compact('description', 'category') + ['is_active' => true]);
        }
    }
}
