<?php
session_start();
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header('HTTP/1.1 401 Unauthorized');
    exit('Not authorized');
}

include 'db_config.php';

header('Content-Type: application/json');

try {
    $query = "SELECT id, name FROM departments ORDER BY name ASC";
    $result = $conn->query($query);

    if (!$result) {
        throw new Exception($conn->error);
    }

    $departments = [];
    while ($row = $result->fetch_assoc()) {
        $departments[] = [
            'id' => $row['id'],
            'name' => $row['name']
        ];
    }

    echo json_encode($departments);
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close();
?> 