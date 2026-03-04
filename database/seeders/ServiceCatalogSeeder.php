<?php

namespace Database\Seeders;

use App\Models\Equipment;
use App\Models\ServiceCatalog;
use Illuminate\Database\Seeder;

/**
 * Seeds the service catalog with realistic clinic services for all departments,
 * and basic equipment for lab and radiology.
 *
 * Run: php artisan db:seed --class=ServiceCatalogSeeder
 */
class ServiceCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedConsultationServices();
        $this->seedLaboratoryServices();
        $this->seedRadiologyServices();
        $this->seedPharmacyServices();
        $this->seedEquipment();

        $this->command->info('Service catalog seeded: ' . ServiceCatalog::count() . ' services across all departments.');
    }

    private function seedConsultationServices(): void
    {
        $services = [
            ['name' => 'General Consultation', 'code' => 'CON-GEN', 'category' => 'General', 'price' => 1500, 'description' => 'Standard doctor consultation visit', 'turnaround_time' => '30 min'],
            ['name' => 'Follow-up Consultation', 'code' => 'CON-FUP', 'category' => 'General', 'price' => 800, 'description' => 'Follow-up visit for existing condition', 'turnaround_time' => '15 min'],
            ['name' => 'Specialist Consultation', 'code' => 'CON-SPC', 'category' => 'Specialist', 'price' => 2500, 'description' => 'Specialist referral consultation', 'turnaround_time' => '45 min'],
            ['name' => 'Pediatric Consultation', 'code' => 'CON-PED', 'category' => 'Pediatric', 'price' => 1800, 'description' => 'Consultation for children under 12', 'turnaround_time' => '30 min'],
            ['name' => 'Emergency Consultation', 'code' => 'CON-EMG', 'category' => 'Emergency', 'price' => 3000, 'description' => 'Emergency walk-in consultation', 'turnaround_time' => '15 min'],
        ];

        foreach ($services as $s) {
            ServiceCatalog::updateOrCreate(
                ['code' => $s['code']],
                array_merge($s, ['department' => 'consultation', 'is_active' => true])
            );
        }

        $this->command->info('  Consultation: ' . count($services) . ' services');
    }

    private function seedLaboratoryServices(): void
    {
        $services = [
            // Hematology
            ['name' => 'Complete Blood Count (CBC)', 'code' => 'LAB-CBC', 'category' => 'Hematology', 'price' => 800, 'description' => 'Full blood count with differential', 'turnaround_time' => '2 hours',
                'default_parameters' => [
                    ['test_name' => 'WBC (White Blood Cells)', 'unit' => '×10³/µL', 'reference_range' => '4.5–11.0'],
                    ['test_name' => 'RBC (Red Blood Cells)', 'unit' => '×10⁶/µL', 'reference_range' => '4.5–5.5'],
                    ['test_name' => 'Hemoglobin (Hb)', 'unit' => 'g/dL', 'reference_range' => '13.5–17.5'],
                    ['test_name' => 'Hematocrit (HCT)', 'unit' => '%', 'reference_range' => '38–50'],
                    ['test_name' => 'MCV', 'unit' => 'fL', 'reference_range' => '80–100'],
                    ['test_name' => 'MCH', 'unit' => 'pg', 'reference_range' => '27–33'],
                    ['test_name' => 'MCHC', 'unit' => 'g/dL', 'reference_range' => '32–36'],
                    ['test_name' => 'Platelets', 'unit' => '×10³/µL', 'reference_range' => '150–400'],
                    ['test_name' => 'Neutrophils', 'unit' => '%', 'reference_range' => '40–70'],
                    ['test_name' => 'Lymphocytes', 'unit' => '%', 'reference_range' => '20–40'],
                    ['test_name' => 'Monocytes', 'unit' => '%', 'reference_range' => '2–8'],
                    ['test_name' => 'Eosinophils', 'unit' => '%', 'reference_range' => '1–4'],
                    ['test_name' => 'Basophils', 'unit' => '%', 'reference_range' => '0–1'],
                ],
            ],
            ['name' => 'Erythrocyte Sedimentation Rate (ESR)', 'code' => 'LAB-ESR', 'category' => 'Hematology', 'price' => 400, 'description' => 'Inflammation marker test', 'turnaround_time' => '1 hour',
                'default_parameters' => [
                    ['test_name' => 'ESR (1 hour)', 'unit' => 'mm/hr', 'reference_range' => '0–20'],
                ],
            ],
            ['name' => 'Blood Group & Rh Factor', 'code' => 'LAB-BGP', 'category' => 'Hematology', 'price' => 500, 'description' => 'ABO and Rh typing', 'turnaround_time' => '30 min',
                'default_parameters' => [
                    ['test_name' => 'Blood Group (ABO)', 'unit' => '', 'reference_range' => 'A / B / AB / O'],
                    ['test_name' => 'Rh Factor', 'unit' => '', 'reference_range' => 'Positive / Negative'],
                ],
            ],
            ['name' => 'Peripheral Blood Smear', 'code' => 'LAB-PBS', 'category' => 'Hematology', 'price' => 600, 'description' => 'Microscopic examination of blood film', 'turnaround_time' => '2 hours',
                'default_parameters' => [
                    ['test_name' => 'RBC Morphology', 'unit' => '', 'reference_range' => 'Normocytic Normochromic'],
                    ['test_name' => 'WBC Morphology', 'unit' => '', 'reference_range' => 'Normal'],
                    ['test_name' => 'Platelet Estimate', 'unit' => '', 'reference_range' => 'Adequate'],
                ],
            ],

            // Clinical Chemistry
            ['name' => 'Blood Glucose (Fasting)', 'code' => 'LAB-GLF', 'category' => 'Clinical Chemistry', 'price' => 350, 'description' => 'Fasting blood sugar level', 'turnaround_time' => '1 hour',
                'default_parameters' => [
                    ['test_name' => 'Fasting Blood Glucose', 'unit' => 'mg/dL', 'reference_range' => '70–100'],
                ],
            ],
            ['name' => 'Blood Glucose (Random)', 'code' => 'LAB-GLR', 'category' => 'Clinical Chemistry', 'price' => 300, 'description' => 'Random blood sugar level', 'turnaround_time' => '30 min',
                'default_parameters' => [
                    ['test_name' => 'Random Blood Glucose', 'unit' => 'mg/dL', 'reference_range' => '70–140'],
                ],
            ],
            ['name' => 'HbA1c (Glycated Hemoglobin)', 'code' => 'LAB-HBA', 'category' => 'Clinical Chemistry', 'price' => 1200, 'description' => '3-month average blood sugar control', 'turnaround_time' => '4 hours',
                'default_parameters' => [
                    ['test_name' => 'HbA1c', 'unit' => '%', 'reference_range' => '< 5.7'],
                    ['test_name' => 'Estimated Average Glucose', 'unit' => 'mg/dL', 'reference_range' => '< 117'],
                ],
            ],
            ['name' => 'Lipid Profile', 'code' => 'LAB-LPD', 'category' => 'Clinical Chemistry', 'price' => 1000, 'description' => 'Total cholesterol, HDL, LDL, triglycerides', 'turnaround_time' => '3 hours',
                'default_parameters' => [
                    ['test_name' => 'Total Cholesterol', 'unit' => 'mg/dL', 'reference_range' => '< 200'],
                    ['test_name' => 'HDL Cholesterol', 'unit' => 'mg/dL', 'reference_range' => '> 40'],
                    ['test_name' => 'LDL Cholesterol', 'unit' => 'mg/dL', 'reference_range' => '< 100'],
                    ['test_name' => 'Triglycerides', 'unit' => 'mg/dL', 'reference_range' => '< 150'],
                    ['test_name' => 'VLDL', 'unit' => 'mg/dL', 'reference_range' => '< 30'],
                ],
            ],
            ['name' => 'Liver Function Test (LFT)', 'code' => 'LAB-LFT', 'category' => 'Clinical Chemistry', 'price' => 1200, 'description' => 'ALT, AST, ALP, bilirubin, albumin, total protein', 'turnaround_time' => '4 hours',
                'default_parameters' => [
                    ['test_name' => 'ALT (SGPT)', 'unit' => 'U/L', 'reference_range' => '7–56'],
                    ['test_name' => 'AST (SGOT)', 'unit' => 'U/L', 'reference_range' => '10–40'],
                    ['test_name' => 'ALP (Alkaline Phosphatase)', 'unit' => 'U/L', 'reference_range' => '44–147'],
                    ['test_name' => 'Total Bilirubin', 'unit' => 'mg/dL', 'reference_range' => '0.1–1.2'],
                    ['test_name' => 'Direct Bilirubin', 'unit' => 'mg/dL', 'reference_range' => '0.0–0.3'],
                    ['test_name' => 'Albumin', 'unit' => 'g/dL', 'reference_range' => '3.5–5.5'],
                    ['test_name' => 'Total Protein', 'unit' => 'g/dL', 'reference_range' => '6.0–8.3'],
                ],
            ],
            ['name' => 'Renal Function Test (RFT)', 'code' => 'LAB-RFT', 'category' => 'Clinical Chemistry', 'price' => 1000, 'description' => 'Urea, creatinine, electrolytes', 'turnaround_time' => '3 hours',
                'default_parameters' => [
                    ['test_name' => 'Blood Urea', 'unit' => 'mg/dL', 'reference_range' => '7–20'],
                    ['test_name' => 'Serum Creatinine', 'unit' => 'mg/dL', 'reference_range' => '0.7–1.3'],
                    ['test_name' => 'Sodium (Na⁺)', 'unit' => 'mEq/L', 'reference_range' => '136–145'],
                    ['test_name' => 'Potassium (K⁺)', 'unit' => 'mEq/L', 'reference_range' => '3.5–5.0'],
                    ['test_name' => 'Chloride (Cl⁻)', 'unit' => 'mEq/L', 'reference_range' => '98–106'],
                    ['test_name' => 'Bicarbonate (HCO₃⁻)', 'unit' => 'mEq/L', 'reference_range' => '22–29'],
                    ['test_name' => 'BUN/Creatinine Ratio', 'unit' => '', 'reference_range' => '10–20'],
                ],
            ],
            ['name' => 'Thyroid Function (TSH + T3/T4)', 'code' => 'LAB-THY', 'category' => 'Clinical Chemistry', 'price' => 1800, 'description' => 'Thyroid stimulating hormone and free T3/T4', 'turnaround_time' => '6 hours',
                'default_parameters' => [
                    ['test_name' => 'TSH', 'unit' => 'mIU/L', 'reference_range' => '0.4–4.0'],
                    ['test_name' => 'Free T3', 'unit' => 'pg/mL', 'reference_range' => '2.3–4.2'],
                    ['test_name' => 'Free T4', 'unit' => 'ng/dL', 'reference_range' => '0.8–1.8'],
                ],
            ],
            ['name' => 'Serum Electrolytes', 'code' => 'LAB-ELT', 'category' => 'Clinical Chemistry', 'price' => 600, 'description' => 'Sodium, potassium, chloride, bicarbonate', 'turnaround_time' => '2 hours',
                'default_parameters' => [
                    ['test_name' => 'Sodium (Na⁺)', 'unit' => 'mEq/L', 'reference_range' => '136–145'],
                    ['test_name' => 'Potassium (K⁺)', 'unit' => 'mEq/L', 'reference_range' => '3.5–5.0'],
                    ['test_name' => 'Chloride (Cl⁻)', 'unit' => 'mEq/L', 'reference_range' => '98–106'],
                    ['test_name' => 'Bicarbonate (HCO₃⁻)', 'unit' => 'mEq/L', 'reference_range' => '22–29'],
                ],
            ],
            ['name' => 'Uric Acid', 'code' => 'LAB-URC', 'category' => 'Clinical Chemistry', 'price' => 400, 'description' => 'Serum uric acid level', 'turnaround_time' => '2 hours',
                'default_parameters' => [
                    ['test_name' => 'Serum Uric Acid', 'unit' => 'mg/dL', 'reference_range' => '3.4–7.0'],
                ],
            ],

            // Serology / Immunology
            ['name' => 'HIV Rapid Test', 'code' => 'LAB-HIV', 'category' => 'Serology', 'price' => 500, 'description' => 'HIV 1/2 antibody rapid test', 'turnaround_time' => '30 min',
                'default_parameters' => [
                    ['test_name' => 'HIV 1/2 Antibody', 'unit' => '', 'reference_range' => 'Non-Reactive'],
                ],
            ],
            ['name' => 'Hepatitis B Surface Antigen (HBsAg)', 'code' => 'LAB-HBS', 'category' => 'Serology', 'price' => 600, 'description' => 'Hepatitis B screening', 'turnaround_time' => '1 hour',
                'default_parameters' => [
                    ['test_name' => 'HBsAg', 'unit' => '', 'reference_range' => 'Non-Reactive'],
                ],
            ],
            ['name' => 'Hepatitis C Antibody', 'code' => 'LAB-HCV', 'category' => 'Serology', 'price' => 700, 'description' => 'Hepatitis C screening', 'turnaround_time' => '1 hour',
                'default_parameters' => [
                    ['test_name' => 'HCV Antibody', 'unit' => '', 'reference_range' => 'Non-Reactive'],
                ],
            ],
            ['name' => 'Widal Test (Typhoid)', 'code' => 'LAB-WDL', 'category' => 'Serology', 'price' => 500, 'description' => 'Salmonella antibody detection', 'turnaround_time' => '2 hours',
                'default_parameters' => [
                    ['test_name' => 'Salmonella Typhi O', 'unit' => 'Titer', 'reference_range' => '< 1:80'],
                    ['test_name' => 'Salmonella Typhi H', 'unit' => 'Titer', 'reference_range' => '< 1:80'],
                    ['test_name' => 'Salmonella Paratyphi AH', 'unit' => 'Titer', 'reference_range' => '< 1:80'],
                    ['test_name' => 'Salmonella Paratyphi BH', 'unit' => 'Titer', 'reference_range' => '< 1:80'],
                ],
            ],
            ['name' => 'Malaria Rapid Test', 'code' => 'LAB-MAL', 'category' => 'Serology', 'price' => 400, 'description' => 'Plasmodium antigen detection', 'turnaround_time' => '30 min',
                'default_parameters' => [
                    ['test_name' => 'P. falciparum', 'unit' => '', 'reference_range' => 'Negative'],
                    ['test_name' => 'P. vivax', 'unit' => '', 'reference_range' => 'Negative'],
                ],
            ],
            ['name' => 'Dengue NS1 Antigen', 'code' => 'LAB-DNG', 'category' => 'Serology', 'price' => 800, 'description' => 'Early dengue detection', 'turnaround_time' => '1 hour',
                'default_parameters' => [
                    ['test_name' => 'Dengue NS1 Antigen', 'unit' => '', 'reference_range' => 'Negative'],
                ],
            ],
            ['name' => 'Pregnancy Test (hCG)', 'code' => 'LAB-HCG', 'category' => 'Serology', 'price' => 300, 'description' => 'Urine/serum pregnancy test', 'turnaround_time' => '15 min',
                'default_parameters' => [
                    ['test_name' => 'hCG (Pregnancy)', 'unit' => '', 'reference_range' => 'Negative'],
                ],
            ],
            ['name' => 'C-Reactive Protein (CRP)', 'code' => 'LAB-CRP', 'category' => 'Serology', 'price' => 600, 'description' => 'Quantitative inflammation marker', 'turnaround_time' => '2 hours',
                'default_parameters' => [
                    ['test_name' => 'C-Reactive Protein', 'unit' => 'mg/L', 'reference_range' => '< 5.0'],
                ],
            ],

            // Urinalysis & Stool
            ['name' => 'Urinalysis (Routine)', 'code' => 'LAB-URN', 'category' => 'Urinalysis', 'price' => 300, 'description' => 'Dipstick and microscopy', 'turnaround_time' => '1 hour',
                'default_parameters' => [
                    ['test_name' => 'Color', 'unit' => '', 'reference_range' => 'Pale Yellow'],
                    ['test_name' => 'Appearance', 'unit' => '', 'reference_range' => 'Clear'],
                    ['test_name' => 'pH', 'unit' => '', 'reference_range' => '4.6–8.0'],
                    ['test_name' => 'Specific Gravity', 'unit' => '', 'reference_range' => '1.005–1.030'],
                    ['test_name' => 'Protein', 'unit' => '', 'reference_range' => 'Negative'],
                    ['test_name' => 'Glucose', 'unit' => '', 'reference_range' => 'Negative'],
                    ['test_name' => 'Blood', 'unit' => '', 'reference_range' => 'Negative'],
                    ['test_name' => 'WBC', 'unit' => '/hpf', 'reference_range' => '0–5'],
                    ['test_name' => 'RBC', 'unit' => '/hpf', 'reference_range' => '0–2'],
                    ['test_name' => 'Epithelial Cells', 'unit' => '/hpf', 'reference_range' => '0–5'],
                    ['test_name' => 'Bacteria', 'unit' => '', 'reference_range' => 'None'],
                ],
            ],
            ['name' => 'Urine Culture & Sensitivity', 'code' => 'LAB-UCS', 'category' => 'Urinalysis', 'price' => 1200, 'description' => 'Bacterial culture with antibiotic sensitivity', 'turnaround_time' => '48 hours',
                'default_parameters' => [
                    ['test_name' => 'Organism Isolated', 'unit' => '', 'reference_range' => 'No Growth'],
                    ['test_name' => 'Colony Count', 'unit' => 'CFU/mL', 'reference_range' => '< 100,000'],
                    ['test_name' => 'Antibiotic Sensitivity', 'unit' => '', 'reference_range' => '—'],
                ],
            ],
            ['name' => 'Stool Routine & Microscopy', 'code' => 'LAB-STL', 'category' => 'Stool Analysis', 'price' => 400, 'description' => 'Ova, cysts, parasites, occult blood', 'turnaround_time' => '2 hours',
                'default_parameters' => [
                    ['test_name' => 'Color', 'unit' => '', 'reference_range' => 'Brown'],
                    ['test_name' => 'Consistency', 'unit' => '', 'reference_range' => 'Formed'],
                    ['test_name' => 'Occult Blood', 'unit' => '', 'reference_range' => 'Negative'],
                    ['test_name' => 'Ova / Parasites', 'unit' => '', 'reference_range' => 'Not Seen'],
                    ['test_name' => 'WBC', 'unit' => '/hpf', 'reference_range' => 'Absent'],
                    ['test_name' => 'RBC', 'unit' => '/hpf', 'reference_range' => 'Absent'],
                ],
            ],
            ['name' => 'Stool Culture', 'code' => 'LAB-STC', 'category' => 'Stool Analysis', 'price' => 1000, 'description' => 'Bacterial culture of stool sample', 'turnaround_time' => '48 hours',
                'default_parameters' => [
                    ['test_name' => 'Organism Isolated', 'unit' => '', 'reference_range' => 'No Growth'],
                    ['test_name' => 'Antibiotic Sensitivity', 'unit' => '', 'reference_range' => '—'],
                ],
            ],

            // Microbiology
            ['name' => 'Blood Culture & Sensitivity', 'code' => 'LAB-BCL', 'category' => 'Microbiology', 'price' => 1500, 'description' => 'Aerobic blood culture with sensitivity', 'turnaround_time' => '72 hours',
                'default_parameters' => [
                    ['test_name' => 'Organism Isolated', 'unit' => '', 'reference_range' => 'No Growth'],
                    ['test_name' => 'Colony Count', 'unit' => 'CFU/mL', 'reference_range' => '—'],
                    ['test_name' => 'Antibiotic Sensitivity', 'unit' => '', 'reference_range' => '—'],
                ],
            ],
            ['name' => 'Sputum Culture & Sensitivity', 'code' => 'LAB-SPT', 'category' => 'Microbiology', 'price' => 1200, 'description' => 'Respiratory specimen culture', 'turnaround_time' => '48 hours',
                'default_parameters' => [
                    ['test_name' => 'Organism Isolated', 'unit' => '', 'reference_range' => 'No Growth'],
                    ['test_name' => 'Antibiotic Sensitivity', 'unit' => '', 'reference_range' => '—'],
                ],
            ],
            ['name' => 'Wound Swab Culture', 'code' => 'LAB-WSC', 'category' => 'Microbiology', 'price' => 1000, 'description' => 'Wound infection culture and sensitivity', 'turnaround_time' => '48 hours',
                'default_parameters' => [
                    ['test_name' => 'Organism Isolated', 'unit' => '', 'reference_range' => 'No Growth'],
                    ['test_name' => 'Antibiotic Sensitivity', 'unit' => '', 'reference_range' => '—'],
                ],
            ],
        ];

        foreach ($services as $s) {
            ServiceCatalog::updateOrCreate(
                ['code' => $s['code']],
                array_merge($s, ['department' => 'lab', 'is_active' => true])
            );
        }

        $this->command->info('  Laboratory: ' . count($services) . ' services');
    }

    private function seedRadiologyServices(): void
    {
        $services = [
            // X-Ray
            ['name' => 'Chest X-Ray (PA View)', 'code' => 'RAD-CXR', 'category' => 'X-Ray', 'price' => 1500, 'description' => 'Posterior-anterior chest radiograph', 'turnaround_time' => '1 hour'],
            ['name' => 'Chest X-Ray (AP & Lateral)', 'code' => 'RAD-CXL', 'category' => 'X-Ray', 'price' => 2000, 'description' => 'Two-view chest radiograph', 'turnaround_time' => '1 hour'],
            ['name' => 'Abdominal X-Ray', 'code' => 'RAD-ABD', 'category' => 'X-Ray', 'price' => 1500, 'description' => 'Plain abdominal film', 'turnaround_time' => '1 hour'],
            ['name' => 'Cervical Spine X-Ray', 'code' => 'RAD-CSP', 'category' => 'X-Ray', 'price' => 1800, 'description' => 'Cervical spine radiograph', 'turnaround_time' => '1 hour'],
            ['name' => 'Lumbar Spine X-Ray', 'code' => 'RAD-LSP', 'category' => 'X-Ray', 'price' => 2000, 'description' => 'Lumbar spine radiograph', 'turnaround_time' => '1 hour'],
            ['name' => 'Extremity X-Ray (Single View)', 'code' => 'RAD-EXT', 'category' => 'X-Ray', 'price' => 1200, 'description' => 'Limb radiograph — hand, foot, knee, shoulder etc.', 'turnaround_time' => '45 min'],
            ['name' => 'Pelvic X-Ray', 'code' => 'RAD-PEL', 'category' => 'X-Ray', 'price' => 1800, 'description' => 'Pelvis anterior-posterior view', 'turnaround_time' => '1 hour'],
            ['name' => 'Skull X-Ray', 'code' => 'RAD-SKL', 'category' => 'X-Ray', 'price' => 1500, 'description' => 'AP and lateral skull views', 'turnaround_time' => '1 hour'],

            // Ultrasound
            ['name' => 'Abdominal Ultrasound', 'code' => 'RAD-UAB', 'category' => 'Ultrasound', 'price' => 3500, 'description' => 'Liver, gallbladder, kidneys, spleen, pancreas', 'turnaround_time' => '1 hour'],
            ['name' => 'Pelvic Ultrasound', 'code' => 'RAD-UPL', 'category' => 'Ultrasound', 'price' => 3500, 'description' => 'Urinary bladder, uterus, ovaries / prostate', 'turnaround_time' => '1 hour'],
            ['name' => 'Obstetric Ultrasound', 'code' => 'RAD-UOB', 'category' => 'Ultrasound', 'price' => 4000, 'description' => 'Pregnancy dating, fetal assessment', 'turnaround_time' => '1 hour'],
            ['name' => 'Renal Ultrasound', 'code' => 'RAD-URN', 'category' => 'Ultrasound', 'price' => 3000, 'description' => 'Kidneys & urinary tract', 'turnaround_time' => '45 min'],
            ['name' => 'Thyroid Ultrasound', 'code' => 'RAD-UTH', 'category' => 'Ultrasound', 'price' => 3000, 'description' => 'Thyroid gland imaging', 'turnaround_time' => '45 min'],
            ['name' => 'Breast Ultrasound', 'code' => 'RAD-UBR', 'category' => 'Ultrasound', 'price' => 3500, 'description' => 'Breast tissue evaluation', 'turnaround_time' => '45 min'],
            ['name' => 'Doppler Ultrasound (Vascular)', 'code' => 'RAD-UDO', 'category' => 'Ultrasound', 'price' => 5000, 'description' => 'Blood flow assessment in vessels', 'turnaround_time' => '1.5 hours'],
            ['name' => 'Musculoskeletal Ultrasound', 'code' => 'RAD-UMS', 'category' => 'Ultrasound', 'price' => 3500, 'description' => 'Joint, tendon, soft tissue imaging', 'turnaround_time' => '1 hour'],

            // ECG
            ['name' => 'ECG (12-Lead)', 'code' => 'RAD-ECG', 'category' => 'Cardiac', 'price' => 1500, 'description' => 'Standard 12-lead electrocardiogram', 'turnaround_time' => '30 min'],
            ['name' => 'Echocardiogram', 'code' => 'RAD-ECH', 'category' => 'Cardiac', 'price' => 6000, 'description' => 'Cardiac ultrasound imaging', 'turnaround_time' => '1.5 hours'],
        ];

        foreach ($services as $s) {
            ServiceCatalog::updateOrCreate(
                ['code' => $s['code']],
                array_merge($s, ['department' => 'radiology', 'is_active' => true])
            );
        }

        $this->command->info('  Radiology: ' . count($services) . ' services');
    }

    private function seedPharmacyServices(): void
    {
        $services = [
            ['name' => 'Prescription Dispensing', 'code' => 'PHR-DIS', 'category' => 'Dispensing', 'price' => 200, 'description' => 'Standard prescription dispensing fee', 'turnaround_time' => '15 min'],
            ['name' => 'Injection Administration', 'code' => 'PHR-INJ', 'category' => 'Administration', 'price' => 300, 'description' => 'Intramuscular or subcutaneous injection', 'turnaround_time' => '10 min'],
            ['name' => 'IV Infusion Setup', 'code' => 'PHR-IVS', 'category' => 'Administration', 'price' => 500, 'description' => 'Intravenous drip setup and monitoring', 'turnaround_time' => '30 min'],
            ['name' => 'Wound Dressing', 'code' => 'PHR-WND', 'category' => 'Procedures', 'price' => 500, 'description' => 'Wound cleaning and dressing change', 'turnaround_time' => '20 min'],
            ['name' => 'Nebulization', 'code' => 'PHR-NEB', 'category' => 'Administration', 'price' => 400, 'description' => 'Nebulizer treatment session', 'turnaround_time' => '15 min'],
            ['name' => 'Drug Counseling', 'code' => 'PHR-CNS', 'category' => 'Counseling', 'price' => 0, 'description' => 'Medication usage counseling (complimentary)', 'turnaround_time' => '10 min'],
        ];

        foreach ($services as $s) {
            ServiceCatalog::updateOrCreate(
                ['code' => $s['code']],
                array_merge($s, ['department' => 'pharmacy', 'is_active' => true])
            );
        }

        $this->command->info('  Pharmacy: ' . count($services) . ' services');
    }

    private function seedEquipment(): void
    {
        $labEquipment = [
            ['name' => 'Hematology Analyzer', 'model' => 'Sysmex XN-550', 'serial_number' => 'SN-LAB-001', 'status' => 'operational', 'notes' => 'Primary CBC analyzer'],
            ['name' => 'Chemistry Analyzer', 'model' => 'Roche Cobas c311', 'serial_number' => 'SN-LAB-002', 'status' => 'operational', 'notes' => 'Liver, renal function, lipid panel'],
            ['name' => 'Centrifuge', 'model' => 'Eppendorf 5810R', 'serial_number' => 'SN-LAB-003', 'status' => 'operational', 'notes' => 'Sample preparation'],
            ['name' => 'Microscope (Binocular)', 'model' => 'Olympus CX23', 'serial_number' => 'SN-LAB-004', 'status' => 'operational', 'notes' => 'Slide examination'],
            ['name' => 'Incubator (37°C)', 'model' => 'Memmert IN110', 'serial_number' => 'SN-LAB-005', 'status' => 'operational', 'notes' => 'Culture and sensitivity'],
        ];

        foreach ($labEquipment as $eq) {
            Equipment::updateOrCreate(
                ['serial_number' => $eq['serial_number']],
                array_merge($eq, ['department' => 'lab', 'is_active' => true])
            );
        }

        $radiologyEquipment = [
            ['name' => 'Digital X-Ray Machine', 'model' => 'GE Optima XR240amx', 'serial_number' => 'SN-RAD-001', 'status' => 'operational', 'notes' => 'Primary radiography unit'],
            ['name' => 'Ultrasound Machine', 'model' => 'GE LOGIQ E10', 'serial_number' => 'SN-RAD-002', 'status' => 'operational', 'notes' => 'General and OB/GYN ultrasound'],
            ['name' => 'Portable Ultrasound', 'model' => 'Butterfly iQ+', 'serial_number' => 'SN-RAD-003', 'status' => 'operational', 'notes' => 'Point-of-care ultrasound'],
            ['name' => 'ECG Machine', 'model' => 'GE MAC 2000', 'serial_number' => 'SN-RAD-004', 'status' => 'operational', 'notes' => '12-lead ECG recordings'],
            ['name' => 'Echocardiography System', 'model' => 'Philips Affiniti 50', 'serial_number' => 'SN-RAD-005', 'status' => 'operational', 'notes' => 'Cardiac imaging'],
        ];

        foreach ($radiologyEquipment as $eq) {
            Equipment::updateOrCreate(
                ['serial_number' => $eq['serial_number']],
                array_merge($eq, ['department' => 'radiology', 'is_active' => true])
            );
        }

        $this->command->info('  Equipment: ' . count($labEquipment) . ' lab + ' . count($radiologyEquipment) . ' radiology');
    }
}
