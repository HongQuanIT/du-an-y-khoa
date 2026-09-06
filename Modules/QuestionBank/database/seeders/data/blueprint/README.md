# Medical Licensing Exam Blueprint — Core Clinical Topics

## Status

- Blueprint code: `medical_practice_licensing_exam`
- **17 sections** in `../MedicalLicensingExamBlueprint.php` (PSR-4 next to seeders)
- **128 core clinical topics** in `core_clinical_topics.php` (authoritative names — do not edit without official source)

## Architecture

- **Authoring:** questions attach only to medical taxonomy nodes (+ tags).
- **Blueprint CCT:** map each core clinical topic → medical nodes (`core_topic_medical_taxonomy_nodes`) and/or tags (`core_topic_tags`).
- Filtering / exams / sessions resolve CCT → mapped medical nodes (descendants expanded) **or** mapped tags. New blueprints only need mapping — no per-question re-tagging.

## Related seeders (idempotent, ordered)

```bash
php artisan db:seed --class=Modules\\QuestionBank\\Database\\Seeders\\MedicalLicensingExamBlueprintSeeder
php artisan db:seed --class=Modules\\QuestionBank\\Database\\Seeders\\MedicalKnowledgeTaxonomySeeder
php artisan db:seed --class=Modules\\QuestionBank\\Database\\Seeders\\QuestionDemoSeeder
```

Or via `QuestionBankDatabaseSeeder` / `php artisan db:seed`.
