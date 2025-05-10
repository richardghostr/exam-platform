<?php
require_once '../includes/config.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

$dailyRegistrations = $conn->query("
    SELECT 
        DATE(created_at) as registration_date,
        COUNT(*) as user_count,
        SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) as student_count,
        SUM(CASE WHEN role = 'teacher' THEN 1 ELSE 0 END) as teacher_count
    FROM users
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY registration_date ASC
");

$data = [
    'labels' => [],
    'total' => [],
    'students' => [],
    'teachers' => []
];

while ($row = $dailyRegistrations->fetch_assoc()) {
    $data['labels'][] = date('d M', strtotime($row['registration_date']));
    $data['total'][] = $row['user_count'];
    $data['students'][] = $row['student_count'];
    $data['teachers'][] = $row['teacher_count'];
}

echo json_encode($data);