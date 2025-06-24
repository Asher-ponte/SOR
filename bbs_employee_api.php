<?php
include 'db_config.php';
header('Content-Type: application/json');
$dept_id = isset($_GET['dept_id']) ? intval($_GET['dept_id']) : 0;
$q = isset($_GET['q']) ? $_GET['q'] : '';
if ($dept_id <= 0) {
    echo json_encode([]);
    exit;
}
$sql = "SELECT id, name FROM employees WHERE department_id = ?";
$params = [$dept_id];
$types = 'i';
if ($q !== '') {
    $sql .= " AND name LIKE ?";
    $params[] = "%$q%";
    $types .= 's';
}
$sql .= " ORDER BY name ASC LIMIT 10";
$stmt = $conn->prepare($sql);
if ($q !== '') {
    $stmt->bind_param($types, $params[0], $params[1]);
} else {
    $stmt->bind_param($types, $params[0]);
}
$stmt->execute();
$result = $stmt->get_result();
$employees = [];
while ($row = $result->fetch_assoc()) {
    $employees[] = [
        'id' => $row['id'],
        'name' => $row['name']
    ];
}
echo json_encode($employees); 