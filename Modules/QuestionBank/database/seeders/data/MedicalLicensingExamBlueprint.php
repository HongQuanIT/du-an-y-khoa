<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Database\Seeders\Data;

/**
 * 17 blueprint sections for the national medical licensing exam.
 *
 * 128 core clinical topics are defined in data/blueprint/core_clinical_topics.php.
 */
final class MedicalLicensingExamBlueprint
{
    public const CODE = 'medical_practice_licensing_exam';

    /** Legacy code kept for idempotent re-seed / migration of older local DBs. */
    public const LEGACY_CODE = 'medical-licensing-exam-vn';

    public const NAME = 'Kỳ thi đánh giá năng lực hành nghề Bác sĩ Y khoa';

    /** @return list<array{name: string, slug: string, sort_order: int}> */
    public static function sections(): array
    {
        return [
            ['name' => 'Hệ Miễn dịch', 'slug' => 'he-mien-dich', 'sort_order' => 1],
            ['name' => 'Hệ Máu - Lưới - Bạch huyết', 'slug' => 'he-mau-luoi-bach-huyet', 'sort_order' => 2],
            ['name' => 'Sức khỏe hành vi - Tâm thần', 'slug' => 'suc-khoe-hanh-vi-tam-than', 'sort_order' => 3],
            ['name' => 'Hệ thần kinh và các giác quan', 'slug' => 'he-than-kinh-va-cac-giac-quan', 'sort_order' => 4],
            ['name' => 'Da - mô dưới da', 'slug' => 'da-mo-duoi-da', 'sort_order' => 5],
            ['name' => 'Hệ cơ xương khớp', 'slug' => 'he-co-xuong-khop', 'sort_order' => 6],
            ['name' => 'Hệ tim mạch', 'slug' => 'he-tim-mach', 'sort_order' => 7],
            ['name' => 'Hệ hô hấp', 'slug' => 'he-ho-hap', 'sort_order' => 8],
            ['name' => 'Hệ tiêu hóa', 'slug' => 'he-tieu-hoa', 'sort_order' => 9],
            ['name' => 'Hệ thận - niệu', 'slug' => 'he-than-nieu', 'sort_order' => 10],
            ['name' => 'Thai kỳ, Chuyển dạ, Sinh đẻ và Hậu sản', 'slug' => 'thai-ky-chuyen-da-sinh-de-va-hau-san', 'sort_order' => 11],
            ['name' => 'Hệ sinh dục, vú và sức khỏe sinh sản', 'slug' => 'he-sinh-duc-vu-va-suc-khoe-sinh-san', 'sort_order' => 12],
            ['name' => 'Hệ nội tiết - dinh dưỡng - chuyển hóa', 'slug' => 'he-noi-tiet-dinh-duong-chuyen-hoa', 'sort_order' => 13],
            ['name' => 'Rối loạn đa cơ quan', 'slug' => 'roi-loan-da-co-quan', 'sort_order' => 14],
            ['name' => 'Y học cấp cứu', 'slug' => 'y-hoc-cap-cuu', 'sort_order' => 15],
            ['name' => 'Thống kê y sinh học, Dịch tễ học/Sức khỏe dân số và Diễn giải y văn', 'slug' => 'thong-ke-y-sinh-hoc-dich-te-hoc', 'sort_order' => 16],
            ['name' => 'Khoa học xã hội trong y học', 'slug' => 'khoa-hoc-xa-hoi-trong-y-hoc', 'sort_order' => 17],
        ];
    }
}
