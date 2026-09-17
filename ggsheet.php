<?php

$spreadsheetId = '1xMLRJj9y-gp-rDSt34vE5m1qd8d8DLlh5kEbR5Vc2Mw';
$gid = '0'; // GID của sheet Course-subject
$csvUrl = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/export?format=csv&gid={$gid}";

// Đọc nội dung CSV trực tiếp qua URL
$content = file_get_contents($csvUrl);
if ($content === false) {
    die('Không thể kết nối đến Google Sheets');
}

$rows = array_map(static fn (string $line): array => str_getcsv($line, ',', '"', '\\'), explode("\n", $content));
$headers = [];
$result = [];

// Header row
if (!empty($rows)) {
    $firstRow = array_shift($rows);
    foreach ($firstRow as $idx => $name) {
        $name = trim($name);
        if ($name !== '') {
            $headers[$idx] = $name;
            $result[$name] = [];
        }
    }
}

// Data rows
foreach ($rows as $row) {
    foreach ($headers as $idx => $name) {
        if (isset($row[$idx])) {
            $val = trim($row[$idx]);
            if ($val !== '') {
                $result[$name][] = $val;
            }
        }
    }
}

$outPath = __DIR__.'/Modules/QuestionBank/database/seeders/data/courses_data.php';
$php = "<?php return ".var_export($result, true).";\n";
file_put_contents($outPath, $php);
echo "Wrote: {$outPath}\n";
echo 'Courses: '.count($result)."\n";