<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

require_login();

$from = (string) ($_GET['from'] ?? date('Y-m-01'));
$to = (string) ($_GET['to'] ?? date('Y-m-d'));

$fromDate = DateTime::createFromFormat('Y-m-d', $from);
$toDate = DateTime::createFromFormat('Y-m-d', $to);

if (!$fromDate || !$toDate) {
    $from = date('Y-m-01');
    $to = date('Y-m-d');
}

if ($from > $to) {
    $temp = $from;
    $from = $to;
    $to = $temp;
}

$statement = db()->prepare(
    "SELECT e.employee_code, CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
            a.clock_in_at, a.clock_out_at, a.clock_in_method, a.clock_out_method, a.is_late
     FROM attendance_logs a
     INNER JOIN employees e ON e.id = a.employee_id
     WHERE DATE(a.clock_in_at) BETWEEN :from_date AND :to_date
     ORDER BY a.clock_in_at DESC"
);
$statement->execute(array('from_date' => $from, 'to_date' => $to));
$records = $statement->fetchAll();

$fileName = 'attendance_report_' . $from . '_to_' . $to . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $fileName . '"');

$output = fopen('php://output', 'w');
fputcsv($output, array('Employee Code', 'Employee Name', 'Clock In', 'Clock Out', 'Clock In Method', 'Clock Out Method', 'Late'));

foreach ($records as $record) {
    fputcsv(
        $output,
        array(
            $record['employee_code'],
            $record['employee_name'],
            $record['clock_in_at'],
            $record['clock_out_at'],
            $record['clock_in_method'],
            $record['clock_out_method'],
            ((int) $record['is_late'] === 1) ? 'Yes' : 'No',
        )
    );
}

fclose($output);
exit;
