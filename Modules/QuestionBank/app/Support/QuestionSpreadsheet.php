<?php

declare(strict_types=1);

namespace Modules\QuestionBank\Support;

use RuntimeException;
use ZipArchive;

/**
 * CSV (UTF-8 BOM) + simple XLSX reader/writer for the flattened question template.
 */
final class QuestionSpreadsheet
{
    public const MAX_ROWS = 500;

    /**
     * @return array{headers: list<string>, rows: list<list<string>>}
     */
    public function read(string $path): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $table = $extension === 'xlsx'
            ? $this->readXlsx($path)
            : $this->readCsv($path);

        $headers = array_map(fn (mixed $cell): string => trim((string) $cell), $table[0] ?? []);
        $rows = [];

        foreach (array_slice($table, 1) as $row) {
            $cells = array_map(fn (mixed $cell): string => trim((string) $cell), $row);
            if ($this->isEmptyRow($cells)) {
                continue;
            }
            $rows[] = $cells;
        }

        if (count($rows) > self::MAX_ROWS) {
            throw new RuntimeException('Tệp vượt quá '.self::MAX_ROWS.' dòng dữ liệu.');
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     */
    public function writeCsv(string $path, array $headers, array $rows): void
    {
        $handle = fopen($path, 'w');
        if ($handle === false) {
            throw new RuntimeException('Không ghi được tệp CSV.');
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     */
    public function csvString(array $headers, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new RuntimeException('Không tạo được CSV.');
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $contents = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $contents;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     * @param  list<list<string>>|null  $guideRows
     */
    public function writeXlsx(string $path, array $headers, array $rows, ?array $guideRows = null): void
    {
        if (file_put_contents($path, $this->xlsxBinary($headers, $rows, $guideRows)) === false) {
            throw new RuntimeException('Không ghi được tệp Excel.');
        }
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     * @param  list<list<string>>|null  $guideRows
     */
    public function xlsxBinary(array $headers, array $rows, ?array $guideRows = null): string
    {
        $sheets = [
            ['name' => 'Cau_hoi', 'rows' => array_merge([$headers], $rows)],
        ];
        if ($guideRows !== null) {
            $sheets[] = ['name' => 'Huong_dan', 'rows' => $guideRows];
        }

        $tmp = tempnam(sys_get_temp_dir(), 'qbank-xlsx-');
        if ($tmp === false) {
            throw new RuntimeException('Không tạo được tệp tạm Excel.');
        }

        $zip = new ZipArchive;
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Không tạo được gói Excel.');
        }

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';

        $workbookSheets = '';
        $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'<Relationship Id="rIdShared" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';

        $shared = [];
        $sheetXml = [];
        foreach ($sheets as $index => $sheet) {
            $sheetId = $index + 1;
            $contentTypes .= '<Override PartName="/xl/worksheets/sheet'.$sheetId.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $workbookSheets .= '<sheet name="'.$this->xml($sheet['name']).'" sheetId="'.$sheetId.'" r:id="rIdSheet'.$sheetId.'"/>';
            $workbookRels .= '<Relationship Id="rIdSheet'.$sheetId.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$sheetId.'.xml"/>';
            $sheetXml[$sheetId] = $this->worksheetXml($sheet['rows'], $shared);
        }

        $contentTypes .= '</Types>';
        $workbookRels .= '</Relationships>';

        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$workbookSheets.'</sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('xl/sharedStrings.xml', $this->sharedStringsXml($shared));

        foreach ($sheetXml as $sheetId => $xml) {
            $zip->addFromString('xl/worksheets/sheet'.$sheetId.'.xml', $xml);
        }

        $zip->close();
        $binary = file_get_contents($tmp);
        @unlink($tmp);

        if ($binary === false) {
            throw new RuntimeException('Không đọc được tệp Excel vừa tạo.');
        }

        return $binary;
    }

    /**
     * @return list<list<string>>
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException('Không đọc được tệp CSV.');
        }

        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);

            return [];
        }
        rewind($handle);
        if ($bom === "\xEF\xBB\xBF") {
            fread($handle, 3);
            $firstLine = substr($firstLine, 0) ?: $firstLine;
        }

        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        $table = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $table[] = array_map(fn (mixed $cell): string => (string) $cell, $row);
        }
        fclose($handle);

        return $table;
    }

    /**
     * @return list<list<string>>
     */
    private function readXlsx(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Tệp Excel không hợp lệ.');
        }

        $shared = $this->parseSharedStrings((string) $zip->getFromName('xl/sharedStrings.xml'));
        $sheetPath = $this->firstSheetPath($zip);
        $sheetXml = $zip->getFromName($sheetPath);
        $zip->close();

        if (! is_string($sheetXml) || $sheetXml === '') {
            throw new RuntimeException('Không đọc được sheet câu hỏi.');
        }

        return $this->parseSheet($sheetXml, $shared);
    }

    /**
     * @return list<string>
     */
    private function parseSharedStrings(string $xml): array
    {
        if ($xml === '') {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($document === false) {
            return [];
        }

        $document->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $strings = [];
        foreach ($document->si as $item) {
            $strings[] = trim(html_entity_decode(strip_tags($item->asXML() ?: ''), ENT_QUOTES | ENT_XML1, 'UTF-8'));
        }

        return $strings;
    }

    private function firstSheetPath(ZipArchive $zip): string
    {
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if (is_string($rels) && $rels !== '') {
            $previous = libxml_use_internal_errors(true);
            $document = simplexml_load_string($rels);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            if ($document !== false) {
                foreach ($document->Relationship as $rel) {
                    $type = (string) $rel['Type'];
                    if (str_contains($type, '/worksheet')) {
                        $target = ltrim((string) $rel['Target'], '/');
                        if (! str_starts_with($target, 'xl/')) {
                            $target = 'xl/'.$target;
                        }

                        return $target;
                    }
                }
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    /**
     * @param  list<string>  $shared
     * @return list<list<string>>
     */
    private function parseSheet(string $xml, array $shared): array
    {
        $previous = libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if ($document === false) {
            throw new RuntimeException('Sheet Excel không đọc được.');
        }

        $document->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rows = [];
        $maxCol = 0;

        foreach ($document->sheetData->row ?? [] as $row) {
            $index = (int) $row['r'];
            $cells = [];
            foreach ($row->c as $cell) {
                $ref = (string) $cell['r'];
                $col = $this->columnIndex($ref);
                $maxCol = max($maxCol, $col);
                $type = (string) $cell['t'];
                $value = (string) ($cell->v ?? '');
                $cells[$col] = $type === 's'
                    ? (string) ($shared[(int) $value] ?? '')
                    : $value;
            }
            $rows[$index] = $cells;
        }

        if ($rows === []) {
            return [];
        }

        ksort($rows);
        $width = $maxCol + 1;
        $table = [];
        foreach ($rows as $cells) {
            $line = array_fill(0, $width, '');
            foreach ($cells as $col => $value) {
                $line[$col] = $value;
            }
            $table[] = $line;
        }

        return $table;
    }

    private function columnIndex(string $ref): int
    {
        preg_match('/^[A-Z]+/i', $ref, $matches);
        $letters = strtoupper($matches[0] ?? 'A');
        $index = 0;
        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index - 1;
    }

    /**
     * @param  list<list<string>>  $rows
     * @param  array<string, int>  $shared
     */
    private function worksheetXml(array $rows, array &$shared): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach (array_values($rows) as $r => $row) {
            $rowNumber = $r + 1;
            $xml .= '<row r="'.$rowNumber.'">';
            foreach (array_values($row) as $c => $value) {
                $ref = $this->cellRef($c, $rowNumber);
                $style = $r === 0 ? ' s="1"' : '';
                $index = $this->sharedIndex($shared, (string) $value);
                $xml .= '<c r="'.$ref.'" t="s"'.$style.'><v>'.$index.'</v></c>';
            }
            $xml .= '</row>';
        }

        return $xml.'</sheetData></worksheet>';
    }

    /**
     * @param  array<string, int>  $shared
     */
    private function sharedIndex(array &$shared, string $value): int
    {
        if (! array_key_exists($value, $shared)) {
            $shared[$value] = count($shared);
        }

        return $shared[$value];
    }

    /**
     * @param  array<string, int>  $shared
     */
    private function sharedStringsXml(array $shared): string
    {
        $count = count($shared);
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'.$count.'" uniqueCount="'.$count.'">';
        foreach (array_keys($shared) as $value) {
            $xml .= '<si><t xml:space="preserve">'.$this->xml((string) $value).'</t></si>';
        }

        return $xml.'</sst>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            .'<borders count="1"><border/></borders>'
            .'<cellXfs count="2"><xf fontId="0"/><xf fontId="1" applyFont="1"/></cellXfs>'
            .'</styleSheet>';
    }

    private function cellRef(int $col, int $row): string
    {
        $name = '';
        $col++;
        while ($col > 0) {
            $col--;
            $name = chr(65 + ($col % 26)).$name;
            $col = intdiv($col, 26);
        }

        return $name.$row;
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** @param  list<string>  $cells */
    private function isEmptyRow(array $cells): bool
    {
        foreach ($cells as $cell) {
            if (trim($cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
