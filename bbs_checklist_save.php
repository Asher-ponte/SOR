<?php
session_start();
header('Content-Type: application/json');
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}
include 'db_config.php';
$observer = $_SESSION['username'];
$department_id = intval($_POST['department_id'] ?? 0);
$employee_id = intval($_POST['employee_id'] ?? 0);
$date_of_observation = $_POST['date_of_observation'] ?? '';
$answers = json_decode($_POST['answers'] ?? '[]', true);
if (!$department_id || !$employee_id || !$date_of_observation || !is_array($answers) || count($answers) === 0) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}
// Save checklist meta info
$stmt = $conn->prepare("INSERT INTO bbs_checklists (observer, department_id, employee_id, date_of_observation, ppe, body_mechanics, tools) VALUES (?, ?, ?, ?, '', '', '')");
$stmt->bind_param('siis', $observer, $department_id, $employee_id, $date_of_observation);
if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Failed to save checklist.']);
    exit;
}
$checklist_id = $conn->insert_id;
// Save answers
$ans_stmt = $conn->prepare("INSERT INTO bbs_checklist_answers (checklist_id, item_id, value) VALUES (?, ?, ?)");
foreach ($answers as $ans) {
    $item_id = intval($ans['item_id']);
    $value = $ans['value'] === 'safe' ? 'safe' : 'unsafe';
    $ans_stmt->bind_param('iis', $checklist_id, $item_id, $value);
    $ans_stmt->execute();
}
echo json_encode(['success' => true, 'message' => 'Checklist saved successfully.']); 