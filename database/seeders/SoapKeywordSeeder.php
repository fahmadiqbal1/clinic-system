<?php

namespace Database\Seeders;

use App\Models\SoapKeyword;
use Illuminate\Database\Seeder;

class SoapKeywordSeeder extends Seeder
{
    private array $seeds = [
        'S' => [
            'Fever', 'Cough', 'Shortness of breath', 'Headache', 'Chest pain',
            'Nausea', 'Fatigue', 'Dizziness', 'Sore throat', 'Abdominal pain',
            'Joint pain', 'Back pain', 'Vomiting', 'Loss of appetite', 'Rash',
        ],
        'O' => [
            'Physical exam NAD', 'Chest clear to auscultation', 'Abdomen soft non-tender',
            'No organomegaly', 'Alert and oriented', 'No focal neurological deficit',
            'Pupils equal and reactive', 'Mild epigastric tenderness',
            'Throat mildly erythematous', 'Bilateral air entry equal',
            'No pedal oedema', 'Skin warm and dry',
        ],
        'A' => [
            'Upper respiratory tract infection', 'Hypertension', 'Type 2 Diabetes Mellitus',
            'Community-acquired pneumonia', 'Acute gastroenteritis', 'Urinary tract infection',
            'Migraine', 'Musculoskeletal pain', 'Anxiety disorder', 'Iron deficiency anaemia',
            'Acute bronchitis', 'Viral syndrome', 'Gastro-oesophageal reflux disease',
            'Tension headache', 'Dehydration',
        ],
        'P' => [
            'CBC ordered', 'Blood glucose ordered', 'Urine R/E ordered',
            'Chest X-ray ordered', 'Rest and hydration advised', 'Follow up in 1 week',
            'Refer to specialist', 'Medication prescribed — see prescription',
            'Lifestyle counselling given', 'Repeat vitals in clinic',
            'LFTs and RFTs ordered', 'ECG ordered', 'Return if symptoms worsen',
            'Admitted for observation', 'Discharged — stable',
        ],
    ];

    public function run(): void
    {
        foreach ($this->seeds as $section => $keywords) {
            foreach ($keywords as $displayText) {
                $canonical = SoapKeyword::canonicalize($displayText);

                // Application-layer uniqueness check (no DB unique constraint due to MariaDB
                // NULL != NULL behaviour in unique indexes)
                $exists = SoapKeyword::where('section', $section)
                    ->where('canonical_key', $canonical)
                    ->whereNull('doctor_id')
                    ->exists();

                if (!$exists) {
                    SoapKeyword::create([
                        'section'       => $section,
                        'display_text'  => $displayText,
                        'canonical_key' => $canonical,
                        'doctor_id'     => null,
                        'usage_count'   => 0,
                    ]);
                }
            }
        }
    }
}
