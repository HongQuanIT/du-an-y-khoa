<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Tests\Unit;

use Modules\QuestionBank\Support\QuestionImportSchema;
use Modules\QuestionBank\Support\QuestionSpreadsheet;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class QuestionSpreadsheetTest extends TestCase
{
    #[Test]
    public function it_round_trips_xlsx_and_maps_standard_headers(): void
    {
        $spreadsheet = new QuestionSpreadsheet;
        $headers = QuestionImportSchema::headers();
        $row = QuestionImportSchema::sampleRow();

        $path = sys_get_temp_dir().'/qbank-import-'.uniqid().'.xlsx';
        $spreadsheet->writeXlsx($path, $headers, [$row], QuestionImportSchema::guideRows());

        $parsed = $spreadsheet->read($path);
        @unlink($path);

        $this->assertSame('stem', $parsed['headers'][1]);
        $this->assertSame('Bệnh nhân 55 tuổi đau ngực. Chẩn đoán nào phù hợp nhất?', $parsed['rows'][0][1]);

        $map = QuestionImportSchema::autoMap($parsed['headers']);
        $this->assertArrayHasKey('stem', $map);
        $this->assertArrayHasKey('correct', $map);
        $this->assertArrayHasKey('lesson_slugs', $map);
    }

    #[Test]
    public function it_reads_csv_with_bom(): void
    {
        $spreadsheet = new QuestionSpreadsheet;
        $path = sys_get_temp_dir().'/qbank-import-'.uniqid().'.csv';
        $spreadsheet->writeCsv($path, ['stem', 'option_a', 'option_b', 'correct'], [
            ['Câu hỏi UTF-8', 'A đúng', 'B sai', 'A'],
        ]);

        $parsed = $spreadsheet->read($path);
        @unlink($path);

        $this->assertSame(['stem', 'option_a', 'option_b', 'correct'], $parsed['headers']);
        $this->assertSame('Câu hỏi UTF-8', $parsed['rows'][0][0]);
    }
}
