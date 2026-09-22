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

    #[Test]
    public function it_writes_column_widths_wrap_and_rich_text(): void
    {
        $spreadsheet = new QuestionSpreadsheet;
        $headers = QuestionImportSchema::headers();
        $row = QuestionImportSchema::sampleRow();
        $row[1] = '<p>Bệnh nhân <strong>55 tuổi</strong> đau ngực.</p>';

        $path = sys_get_temp_dir().'/qbank-import-'.uniqid().'.xlsx';
        $spreadsheet->writeXlsx($path, $headers, [$row], QuestionImportSchema::guideRows());

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $shared = (string) $zip->getFromName('xl/sharedStrings.xml');
        $styles = (string) $zip->getFromName('xl/styles.xml');
        $zip->close();

        $this->assertStringContainsString('customWidth="1"', $sheet);
        $this->assertStringContainsString('width="58"', $sheet);
        $this->assertStringContainsString('width="12"', $sheet);
        $this->assertStringContainsString('wrapText="1"', $styles);
        $this->assertStringContainsString('state="frozen"', $sheet);
        $this->assertStringContainsString('<autoFilter ', $sheet);
        $this->assertStringContainsString('<b/>', $shared);
        $this->assertStringContainsString('55 tuổi', $shared);

        $parsed = $spreadsheet->read($path);
        @unlink($path);

        $this->assertStringContainsString('<strong>55 tuổi</strong>', $parsed['rows'][0][1]);
        $this->assertStringContainsString('Bệnh nhân', $parsed['rows'][0][1]);
    }

    #[Test]
    public function it_writes_error_rows_with_red_fill(): void
    {
        $spreadsheet = new QuestionSpreadsheet;
        $path = sys_get_temp_dir().'/qbank-errors-'.uniqid().'.xlsx';
        $spreadsheet->writeXlsx(
            $path,
            ['stem', 'errors'],
            [['Thiếu đáp án', 'Mỗi câu hỏi phải có đúng 4 đáp án (A–D).']],
            options: ['highlight_errors' => true],
        );

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $sheet = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $styles = (string) $zip->getFromName('xl/styles.xml');
        $zip->close();
        @unlink($path);

        $this->assertStringContainsString('s="3"', $sheet);
        $this->assertStringContainsString('s="4"', $sheet);
        $this->assertStringContainsString('FFFEE2E2', $styles);
    }
}
