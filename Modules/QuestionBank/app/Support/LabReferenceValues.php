<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Support;

/** Reference ranges displayed by the shared study-session Lab tool. */
final class LabReferenceValues
{
    /**
     * @return array<string, array{label: string, rows: list<array{test?: string, reference?: string, si?: string, section?: string, nested?: bool}>}>
     */
    public static function groups(): array
    {
        return [
            'serum' => [
                'label' => 'Serum',
                'rows' => [
                    self::row('Alanine aminotransferase (ALT)', '10–40 U/L', '10–40 U/L'),
                    self::row('Aspartate aminotransferase (AST)', '12–38 U/L', '12–38 U/L'),
                    self::row('Alkaline phosphatase', '25–100 U/L', '25–100 U/L'),
                    self::row('Amylase', '25–125 U/L', '25–125 U/L'),
                    self::row('Bilirubin, Total // Direct', '0.1–1.0 mg/dL // 0.0–0.3 mg/dL', '2–17 µmol/L // 0–5 µmol/L'),
                    self::row('Calcium', '8.4–10.2 mg/dL', '2.1–2.6 mmol/L'),
                    self::section('Cholesterol'),
                    self::row('Total', 'Normal: <200 mg/dL // High: >240 mg/dL', '<5.2 mmol/L // >6.2 mmol/L', true),
                    self::row('HDL', '40–60 mg/dL', '1.0–1.6 mmol/L', true),
                    self::row('LDL', '<160 mg/dL', '<4.2 mmol/L', true),
                    self::row('Triglycerides', 'Normal: <150 mg/dL // Borderline: 151–199 mg/dL', '<1.70 mmol/L // 1.71–2.25 mmol/L'),
                    self::row('Cortisol', "0800 h: 5–23 µg/dL\n1600 h: 3–15 µg/dL\n2000 h: <50% of 0800 h", "138–635 nmol/L\n82–413 nmol/L\nFraction of 0800 h: <0.50"),
                    self::row('Creatine kinase', "Male: 25–90 U/L\nFemale: 10–70 U/L", "25–90 U/L\n10–70 U/L"),
                    self::row('Creatinine', '0.6–1.2 mg/dL', '53–106 µmol/L'),
                    self::row('Urea nitrogen', '7–18 mg/dL', '2.5–6.4 mmol/L'),
                    self::row('Creatinine clearance', "Male: 97–137 mL/min\nFemale: 88–128 mL/min", "97–137 mL/min\n88–128 mL/min"),
                    self::section('Electrolytes, serum'),
                    self::row('Sodium (Na+)', '136–146 mEq/L', '136–146 mmol/L', true),
                    self::row('Potassium (K+)', '3.5–5.0 mEq/L', '3.5–5.0 mmol/L', true),
                    self::row('Chloride (Cl−)', '95–105 mEq/L', '95–105 mmol/L', true),
                    self::row('Bicarbonate (HCO3−)', '22–28 mEq/L', '22–28 mmol/L', true),
                    self::row('Magnesium (Mg2+)', '1.5–2.0 mg/dL', '0.75–1.0 mmol/L', true),
                    self::row('Ferritin', "Male: 20–250 ng/mL\nFemale: 10–120 ng/mL", "20–250 µg/L\n10–120 µg/L"),
                    self::row('Follicle-stimulating hormone', "Male: 4–25 mIU/mL\nFemale: premenopause 4–30 mIU/mL\nmidcycle peak 10–90 mIU/mL\npostmenopause 40–250 mIU/mL", "4–25 U/L\n4–30 U/L\n10–90 U/L\n40–250 U/L"),
                    self::row('Glucose', "Fasting: 70–100 mg/dL\nRandom, non-fasting: <140 mg/dL", "3.8–5.6 mmol/L\n<7.77 mmol/L"),
                    self::row('Growth hormone – arginine stimulation', "Fasting: <5 ng/mL\nProvocative stimuli: >7 ng/mL", "<5 µg/L\n>7 µg/L"),
                    self::row('Iron', "Male: 65–175 µg/dL\nFemale: 50–170 µg/dL", "11.6–31.3 µmol/L\n9.0–30.4 µmol/L"),
                    self::row('Total iron-binding capacity', '250–400 µg/dL', '44.8–71.6 µmol/L'),
                    self::row('Transferrin', '200–360 mg/dL', '2.0–3.6 g/L'),
                    self::row('Lactate dehydrogenase', '45–200 U/L', '45–200 U/L'),
                    self::row('Lipase', '13–60 U/L', '13–60 U/L'),
                    self::row('Luteinizing hormone', "Male: 6–23 mIU/mL\nFemale: follicular phase 5–30 mIU/mL\nmidcycle 75–150 mIU/mL\npostmenopause 30–200 mIU/mL", "6–23 U/L\n5–30 IU/L\n75–150 IU/L\n30–200 IU/L"),
                    self::row('Osmolality', '275–295 mOsmol/kg H2O', '275–295 mOsmol/kg H2O'),
                    self::row('Intact parathyroid hormone (PTH)', '10–60 pg/mL', '10–60 ng/L'),
                    self::row('Phosphorus (inorganic)', '3.0–4.5 mg/dL', '1.0–1.5 mmol/L'),
                    self::row('Prolactin (hPRL)', "Male: <17 ng/mL\nFemale: <25 ng/mL", "<17 µg/L\n<25 µg/L"),
                    self::section('Proteins'),
                    self::row('Total', '6.0–7.8 g/dL', '60–78 g/L', true),
                    self::row('Albumin', '3.5–5.5 g/dL', '35–55 g/L', true),
                    self::row('Globulin', '2.3–3.5 g/dL', '23–35 g/L', true),
                    self::row('Troponin I', '≤0.04 ng/mL', '≤0.04 µg/L'),
                    self::row('TSH', '0.4–4.0 μU/mL', '0.4–4.0 mIU/L'),
                    self::row('Thyroidal iodine (123I) uptake', '8%–30% of administered dose/24 h', '0.08–0.30/24 h'),
                    self::row('Thyroxine (T4)', '5–12 µg/dL', '64–155 nmol/L'),
                    self::row('Free T4', '0.9–1.7 ng/dL', '12.0–21.9 pmol/L'),
                    self::row('Triiodothyronine (T3) (RIA)', '100–200 ng/dL', '1.5–3.1 nmol/L'),
                    self::row('Triiodothyronine (T3) resin uptake', '25%–35%', '0.25–0.35'),
                    self::row('Uric acid', '3.0–8.2 mg/dL', '0.18–0.48 mmol/L'),
                    self::section('Immunoglobulins'),
                    self::row('IgA', '76–390 mg/dL', '0.76–3.90 g/L', true),
                    self::row('IgE', '0–380 IU/mL', '0–380 kIU/L', true),
                    self::row('IgG', '650–1500 mg/dL', '6.5–15.0 g/L', true),
                    self::row('IgM', '50–300 mg/dL', '0.5–3.0 g/L', true),
                    self::section('Gases, arterial blood (room air)'),
                    self::row('pH', '7.35–7.45', '[H+] 36–44 nmol/L', true),
                    self::row('Pco2', '33–45 mm Hg', '4.4–5.9 kPa', true),
                    self::row('Po2', '75–105 mm Hg', '10.0–14.0 kPa', true),
                ],
            ],
            'cerebrospinal' => [
                'label' => 'Cerebrospinal Fluid',
                'rows' => [
                    self::row('Cell count', '0–5/mm³', '0–5 × 10⁶/L'),
                    self::row('Chloride', '118–132 mEq/L', '118–132 mmol/L'),
                    self::row('Gamma globulin', '3%–12% total proteins', '0.03–0.12'),
                    self::row('Glucose', '40–70 mg/dL', '2.2–3.9 mmol/L'),
                    self::row('Pressure', '70–180 mm H2O', '70–180 mm H2O'),
                    self::row('Proteins, total', '<40 mg/dL', '<0.40 g/L'),
                ],
            ],
            'blood' => [
                'label' => 'Blood',
                'rows' => [
                    self::row('Erythrocyte count', "Male: 4.3–5.9 million/mm³\nFemale: 3.5–5.5 million/mm³", "4.3–5.9 × 10¹²/L\n3.5–5.5 × 10¹²/L"),
                    self::row('Erythrocyte sedimentation rate (Westergren)', "Male: 0–15 mm/h\nFemale: 0–20 mm/h", "0–15 mm/h\n0–20 mm/h"),
                    self::row('Hematocrit', "Male: 41%–53%\nFemale: 36%–46%", "0.41–0.53\n0.36–0.46"),
                    self::row('Hemoglobin, blood', "Male: 13.5–17.5 g/dL\nFemale: 12.0–16.0 g/dL", "135–175 g/L\n120–160 g/L"),
                    self::row('Hemoglobin A1c', '≤6%', '≤42 mmol/mol'),
                    self::row('Hemoglobin, plasma', '<4 mg/dL', '<0.62 mmol/L'),
                    self::row('Leukocyte count (WBC)', '4500–11,000/mm³', '4.5–11.0 × 10⁹/L'),
                    self::row('Neutrophils, segmented', '54%–62%', '0.54–0.62', true),
                    self::row('Neutrophils, bands', '3%–5%', '0.03–0.05', true),
                    self::row('Eosinophils', '1%–3%', '0.01–0.03', true),
                    self::row('Basophils', '0%–0.75%', '0.00–0.0075', true),
                    self::row('Lymphocytes', '25%–33%', '0.25–0.33', true),
                    self::row('Monocytes', '3%–7%', '0.03–0.07', true),
                    self::row('CD4+ T-lymphocyte count', '>500/mm³', '>0.5 × 10⁹/L'),
                    self::row('Platelet count', '150,000–400,000/mm³', '150–400 × 10⁹/L'),
                    self::row('Reticulocyte count', '0.5%–1.5%', '0.005–0.015'),
                    self::row('D-Dimer', '≤250 ng/mL', '≤1.4 nmol/L'),
                    self::row('Partial thromboplastin time (PTT) (activated)', '25–40 seconds', '25–40 seconds'),
                    self::row('Prothrombin time (PT)', '11–15 seconds', '11–15 seconds'),
                    self::row('Mean corpuscular hemoglobin (MCH)', '25–35 pg/cell', '0.39–0.54 fmol/cell'),
                    self::row('Mean corpuscular hemoglobin concentration (MCHC)', '31%–36% Hb/cell', '4.8–5.6 mmol Hb/L'),
                    self::row('Mean corpuscular volume (MCV)', '80–100 µm³', '80–100 fL'),
                    self::section('Volume'),
                    self::row('Plasma', "Male: 25–43 mL/kg\nFemale: 28–45 mL/kg", "0.025–0.043 L/kg\n0.028–0.045 L/kg", true),
                    self::row('Red cell', "Male: 20–36 mL/kg\nFemale: 19–31 mL/kg", "0.020–0.036 L/kg\n0.019–0.031 L/kg", true),
                ],
            ],
            'urine_bmi' => [
                'label' => 'Urine & BMI',
                'rows' => [
                    self::section('Urine'),
                    self::row('Calcium', '100–300 mg/24 h', '2.5–7.5 mmol/24 h'),
                    self::row('Creatinine clearance', "Male: 97–137 mL/min\nFemale: 88–128 mL/min", "97–137 mL/min\n88–128 mL/min"),
                    self::row('Osmolality', '50–1200 mOsmol/kg H2O', '50–1200 mOsmol/kg H2O'),
                    self::row('Oxalate', '8–40 µg/mL', '90–445 µmol/L'),
                    self::row('Proteins, total', '<150 mg/24 h', '<0.15 g/24 h'),
                    self::row('17-Hydroxycorticosteroids', "Male: 3.0–10.0 mg/24 h\nFemale: 2.0–8.0 mg/24 h", "8.2–27.6 µmol/24 h\n5.5–22.0 µmol/24 h"),
                    self::row('17-Ketosteroids, total', "Male: 8–20 mg/24 h\nFemale: 6–15 mg/24 h", "28–70 µmol/24 h\n21–52 µmol/24 h"),
                    self::section('Body Mass Index (BMI)'),
                    self::row('Body Mass Index (BMI)', 'Adult: 19–25 kg/m²', '—'),
                ],
            ],
        ];
    }

    /** @return array{test: string, reference: string, si: string, nested: bool} */
    private static function row(string $test, string $reference, string $si, bool $nested = false): array
    {
        return compact('test', 'reference', 'si', 'nested');
    }

    /** @return array{section: string} */
    private static function section(string $section): array
    {
        return compact('section');
    }
}
