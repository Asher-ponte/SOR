<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
include 'db_config.php';
header('Content-Type: application/json');
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header('HTTP/1.1 401 Unauthorized');
    exit('Not authorized');
}

function handleQueryError($conn, $query, $error) {
    return [
        'error' => $error,
        'query' => $query,
        'mysql_error' => $conn->error
    ];
}

// Get filter parameters
$filter = $_GET['filter'] ?? 'daily';
$department = $_GET['department'] ?? 'all';
$week_start = $_GET['week_start'] ?? date('Y-m-d');

// Validate and ensure week_start is a Monday for weekly filter
if ($filter === 'weekly') {
    $week_start_date = new DateTime($week_start);
    $day_of_week = $week_start_date->format('N'); // 1 (Monday) through 7 (Sunday)
    
    if ($day_of_week !== '1') {
        // Adjust to Monday
        $days_to_subtract = $day_of_week - 1;
        $week_start_date->modify("-{$days_to_subtract} days");
        $week_start = $week_start_date->format('Y-m-d');
    }
}

// Prepare date range based on filter
$today = date('Y-m-d');
switch ($filter) {
    case 'weekly':
        $start_date = $week_start;
        $end_date = date('Y-m-d', strtotime($week_start . ' +5 days')); // Monday to Saturday (6 days)
        break;
    case 'monthly':
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $end_date = $today;
        break;
    case 'quarterly':
        $start_date = date('Y-m-d', strtotime('-90 days'));
        $end_date = $today;
        break;
    case 'yearly':
        $start_date = date('Y-m-d', strtotime('-365 days'));
        $end_date = $today;
        break;
    default: // daily
        $start_date = $today;
        $end_date = $today;
        break;
}

// Department condition
$dept_condition = $department !== 'all' ? "AND c.department_id = " . intval($department) : "";

try {
    // Total BBS Observations
    $query = "SELECT COUNT(*) as cnt FROM bbs_checklists c 
              WHERE date_of_observation BETWEEN ? AND ? {$dept_condition}";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception(json_encode(handleQueryError($conn, $query, "Failed to prepare total observations query")));
    }
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();

    // Safe/Unsafe counts
    $query = "SELECT 
                SUM(CASE WHEN a.value = 'safe' THEN 1 ELSE 0 END) as safe_cnt,
                SUM(CASE WHEN a.value = 'unsafe' THEN 1 ELSE 0 END) as unsafe_cnt
              FROM bbs_checklist_answers a
              JOIN bbs_checklists c ON a.checklist_id = c.id
              WHERE c.date_of_observation BETWEEN ? AND ? {$dept_condition}";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception(json_encode(handleQueryError($conn, $query, "Failed to prepare safe/unsafe counts query")));
    }
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $safe = $result['safe_cnt'] ?? 0;
    $unsafe = $result['unsafe_cnt'] ?? 0;
    $stmt->close();

    // Daily Submissions per Observer for the Week (Monday to Saturday)
    $query = "SELECT 
                observer,
                DATE(date_of_observation) as obs_date,
                COUNT(*) as submission_count
              FROM bbs_checklists c
              WHERE date_of_observation BETWEEN ? AND ? {$dept_condition}
              GROUP BY observer, DATE(date_of_observation)
              ORDER BY observer, obs_date";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception(json_encode(handleQueryError($conn, $query, "Failed to prepare daily submissions query")));
    }
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $daily_submissions = [];
    while ($row = $result->fetch_assoc()) {
        if (!isset($daily_submissions[$row['observer']])) {
            $daily_submissions[$row['observer']] = [
                'days' => array_fill(0, 6, ['hit' => false, 'count' => 0]), // Monday to Saturday (6 days)
                'compliance' => 0
            ];
        }
        
        // Calculate day difference from Monday (0 = Monday, 1 = Tuesday, ..., 5 = Saturday)
        $day_diff = (strtotime($row['obs_date']) - strtotime($start_date)) / (60 * 60 * 24);
        if ($day_diff >= 0 && $day_diff < 6) {
            $daily_submissions[$row['observer']]['days'][$day_diff] = [
                'hit' => true,
                'count' => $row['submission_count']
            ];
        }
    }
    $stmt->close();

    // Calculate compliance percentage for each observer (6 days: Monday to Saturday)
    foreach ($daily_submissions as $observer => &$data) {
        $hits = count(array_filter($data['days'], function($day) { return $day['hit']; }));
        $data['compliance'] = round(($hits / 6) * 100);
    }

    // Top 5 most frequent unsafe items
    $query = "SELECT i.label, COUNT(*) as cnt 
              FROM bbs_checklist_answers a 
              JOIN bbs_observation_items i ON a.item_id = i.id
              JOIN bbs_checklists c ON a.checklist_id = c.id
              WHERE a.value = 'unsafe' 
              AND c.date_of_observation BETWEEN ? AND ? {$dept_condition}
              GROUP BY a.item_id 
              ORDER BY cnt DESC 
              LIMIT 5";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception(json_encode(handleQueryError($conn, $query, "Failed to prepare unsafe items query")));
    }
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $violated_items = ['labels' => [], 'counts' => []];
    while ($row = $result->fetch_assoc()) {
        $violated_items['labels'][] = $row['label'];
        $violated_items['counts'][] = $row['cnt'];
    }
    $stmt->close();

    // Top 5 employees with most unsafe observations
    $query = "SELECT e.name, COUNT(*) as cnt 
              FROM bbs_checklists c 
              JOIN bbs_checklist_answers a ON c.id = a.checklist_id 
              JOIN employees e ON c.employee_id = e.id
              WHERE a.value = 'unsafe' 
              AND c.date_of_observation BETWEEN ? AND ? {$dept_condition}
              GROUP BY c.employee_id 
              ORDER BY cnt DESC 
              LIMIT 5";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception(json_encode(handleQueryError($conn, $query, "Failed to prepare unsafe employees query")));
    }
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $unsafe_emps = ['labels' => [], 'counts' => []];
    $top_employees = [];
    while ($row = $result->fetch_assoc()) {
        $unsafe_emps['labels'][] = $row['name'];
        $unsafe_emps['counts'][] = $row['cnt'];
        $top_employees[] = $row['name'];
    }
    $stmt->close();

    // Get violation items for each top employee (for note)
    $unsafe_emp_items = [];
    foreach ($top_employees as $employee) {
        $query = "SELECT i.label, COUNT(*) as cnt 
                  FROM bbs_checklist_answers a 
                  JOIN bbs_observation_items i ON a.item_id = i.id
                  JOIN bbs_checklists c ON a.checklist_id = c.id
                  JOIN employees e ON c.employee_id = e.id
                  WHERE a.value = 'unsafe' 
                  AND e.name = ?
                  AND c.date_of_observation BETWEEN ? AND ? {$dept_condition}
                  GROUP BY i.label 
                  ORDER BY cnt DESC 
                  LIMIT 5";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception(json_encode(handleQueryError($conn, $query, "Failed to prepare employee violation items query for note")));
        }
        $stmt->bind_param('sss', $employee, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();
        $items = [];
        while ($row = $result->fetch_assoc()) {
            // For each item, fetch the occurrences (date and time)
            $item_label = $row['label'];
            $occ_query = "SELECT c.date_of_observation, d.name AS department
                          FROM bbs_checklist_answers a
                          JOIN bbs_checklists c ON a.checklist_id = c.id
                          JOIN employees e ON c.employee_id = e.id
                          JOIN bbs_observation_items i ON a.item_id = i.id
                          JOIN departments d ON c.department_id = d.id
                          WHERE a.value = 'unsafe'
                          AND e.name = ?
                          AND i.label = ?
                          AND c.date_of_observation BETWEEN ? AND ? {$dept_condition}
                          ORDER BY c.date_of_observation";
            $occ_stmt = $conn->prepare($occ_query);
            if (!$occ_stmt) {
                throw new Exception(json_encode(handleQueryError($conn, $occ_query, "Failed to prepare occurrences query for note")));
            }
            $occ_stmt->bind_param('ssss', $employee, $item_label, $start_date, $end_date);
            $occ_stmt->execute();
            $occ_result = $occ_stmt->get_result();
            $occurrences = [];
            while ($occ = $occ_result->fetch_assoc()) {
                $occurrences[] = [
                    'date' => $occ['date_of_observation'],
                    'department' => $occ['department']
                ];
            }
            $occ_stmt->close();
            $items[] = ['label' => $item_label, 'count' => $row['cnt'], 'occurrences' => $occurrences];
        }
        $unsafe_emp_items[$employee] = $items;
        $stmt->close();
    }

    // Top 5 employees with highest safe rate
    $query = "SELECT 
                e.name, 
                COUNT(*) as total_observations,
                SUM(CASE WHEN a.value = 'safe' THEN 1 ELSE 0 END) as safe_cnt,
                (SUM(CASE WHEN a.value = 'safe' THEN 1 ELSE 0 END) / COUNT(*)) * 100 as safe_rate
              FROM bbs_checklists c 
              JOIN bbs_checklist_answers a ON c.id = a.checklist_id 
              JOIN employees e ON c.employee_id = e.id
              WHERE c.date_of_observation BETWEEN ? AND ? {$dept_condition}
              GROUP BY c.employee_id, e.name
              HAVING total_observations > 0
              ORDER BY safe_rate DESC, total_observations DESC
              LIMIT 5";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception(json_encode(handleQueryError($conn, $query, "Failed to prepare safe employees query")));
    }
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $safe_emps = ['labels' => [], 'counts' => [], 'rates' => []];
    while ($row = $result->fetch_assoc()) {
        $safe_emps['labels'][] = $row['name'];
        $safe_emps['counts'][] = $row['safe_cnt'];
        $safe_emps['rates'][] = round($row['safe_rate'], 1);
    }
    $stmt->close();

    // Top 5 Unsafe Locations
    $query = "SELECT 
                d.name AS department,
                COUNT(*) as cnt
              FROM bbs_checklist_answers a
              JOIN bbs_checklists c ON a.checklist_id = c.id
              JOIN departments d ON c.department_id = d.id
              WHERE a.value = 'unsafe'
              AND c.date_of_observation BETWEEN ? AND ? {$dept_condition}
              GROUP BY d.id
              ORDER BY cnt DESC
              LIMIT 5";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception(json_encode(handleQueryError($conn, $query, "Failed to prepare unsafe locations query")));
    }
    $stmt->bind_param('ss', $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $unsafe_locations = ['labels' => [], 'counts' => []];
    while ($row = $result->fetch_assoc()) {
        $unsafe_locations['labels'][] = $row['department'];
        $unsafe_locations['counts'][] = $row['cnt'];
    }
    $stmt->close();

    // Calculate percentages
    $total_observations = $safe + $unsafe;
    $safe_pct = $total_observations > 0 ? round(($safe / $total_observations) * 100, 1) : 0;
    $unsafe_pct = $total_observations > 0 ? round(($unsafe / $total_observations) * 100, 1) : 0;

    // Prepare response
    $response = [
    'kpi' => [
        'total_reports' => $total,
        'safe_pct' => $safe_pct,
            'unsafe_pct' => $unsafe_pct
        ],
        'safe_emps' => $safe_emps,
        'unsafe_emps' => $unsafe_emps,
        'violated_items' => $violated_items,
        'daily_submissions' => $daily_submissions,
        'unsafe_locations' => $unsafe_locations,
        'unsafe_emp_items' => $unsafe_emp_items
    ];

    echo json_encode($response);

} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close();
?> 