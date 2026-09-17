<?php
// URL xuất định dạng CSV của tab 'Course' (gid = 525646758)
$spreadsheetId = '1iKJhTw3HhYc7ZDasnn3-gVOFp7L5q2hNKSgKr9FD1kk';
$gid = '525646758';
$csvUrl = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/export?format=csv&gid={$gid}";

$courseSubjects = [];

if (($handle = fopen($csvUrl, "r")) !== FALSE) {
    while (($row = fgetcsv($handle, 0, ",", '"', '\\')) !== FALSE) {
        // Cột B (index 1): Tên Course
        $course = isset($row[1]) ? trim($row[1]) : '';
        if (empty($course)) {
            continue;
        }

        // Cột C (index 2): Danh sách Subject phân cách bằng dấu xuống dòng (\n)
        $subjectsRaw = isset($row[2]) ? $row[2] : '';

        if (!empty(trim($subjectsRaw))) {
            // Tách theo dòng, loại bỏ khoảng trắng thừa và dòng trống
            $lines = explode("\n", $subjectsRaw);
            $subjects = array_values(array_filter(array_map('trim', $lines), function ($item) {
                return $item !== '';
            }));
        } else {
            $subjects = [];
        }

        $courseSubjects[$course] = $subjects;
    }
    fclose($handle);
}

// Kiểm tra kết quả
echo '<pre>';
print_r($courseSubjects);
echo '</pre>';

// Xuất file PHP với short array syntax []
$lines = ["<?php return ["];
foreach ($courseSubjects as $course => $subjects) {
    $courseKey = var_export($course, true);
    if (empty($subjects)) {
        $lines[] = "    {$courseKey} => [],";
        continue;
    }

    $lines[] = "    {$courseKey} => [";
    foreach ($subjects as $subject) {
        $lines[] = '        ' . var_export($subject, true) . ',';
    }
    $lines[] = "    ],";
}
$lines[] = "];";
$lines[] = "";

$outPath = __DIR__.'/Modules/QuestionBank/database/seeders/data/courses_data.php';
file_put_contents($outPath, implode("\n", $lines));
echo "Wrote: {$outPath}\n";