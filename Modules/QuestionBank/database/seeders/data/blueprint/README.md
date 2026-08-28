# Medical Licensing Exam Blueprint — Core Clinical Topics

## Status

- Blueprint code: `medical_practice_licensing_exam`
- **17 sections** in `../MedicalLicensingExamBlueprint.php` (PSR-4 next to seeders)
- **128 core clinical topics** in `core_clinical_topics.php` (authoritative names — do not edit without official source)

## Related seeders (idempotent, ordered)

```bash
php artisan db:seed --class=Modules\\QuestionBank\\Database\\Seeders\\MedicalLicensingExamBlueprintSeeder
php artisan db:seed --class=Modules\\QuestionBank\\Database\\Seeders\\MedicalKnowledgeTaxonomySeeder
php artisan db:seed --class=Modules\\QuestionBank\\Database\\Seeders\\QuestionDemoSeeder
```

Or via `QuestionBankDatabaseSeeder` / `php artisan db:seed`.
