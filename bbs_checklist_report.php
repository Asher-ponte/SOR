<?php
session_start();
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header('Location: index.html');
    exit;
}
include 'db_config.php';
// Fetch filters
$departments = $conn->query("SELECT id, name FROM departments ORDER BY name ASC");
$employees = $conn->query("SELECT id, name FROM employees ORDER BY name ASC");
$items = $conn->query("SELECT id, label FROM bbs_observation_items WHERE is_active = 1 ORDER BY id ASC");
$items_arr = [];
while ($row = $items->fetch_assoc()) {
    $items_arr[] = $row;
}
$where = [];
$params = [];
$types = '';
if (!empty($_GET['department_id'])) {
    $where[] = 'b.department_id = ?';
    $params[] = $_GET['department_id'];
    $types .= 'i';
}
if (!empty($_GET['employee_id'])) {
    $where[] = 'b.employee_id = ?';
    $params[] = $_GET['employee_id'];
    $types .= 'i';
}
if (!empty($_GET['observer'])) {
    $where[] = 'b.observer = ?';
    $params[] = $_GET['observer'];
    $types .= 's';
}
if (!empty($_GET['date_from'])) {
    $where[] = 'b.date_of_observation >= ?';
    $params[] = $_GET['date_from'];
    $types .= 's';
}
if (!empty($_GET['date_to'])) {
    $where[] = 'b.date_of_observation <= ?';
    $params[] = $_GET['date_to'];
    $types .= 's';
}
$sql = "SELECT b.*, d.name as department_name, e.name as employee_name FROM bbs_checklists b JOIN departments d ON b.department_id = d.id JOIN employees e ON b.employee_id = e.id";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY b.date_of_observation DESC';
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$rows = $result->fetch_all(MYSQLI_ASSOC);
// Fetch all answers for all checklists
$answers = [];
if (count($rows) > 0) {
    $ids = array_column($rows, 'id');
    $in = implode(',', array_fill(0, count($ids), '?'));
    $ans_stmt = $conn->prepare("SELECT * FROM bbs_checklist_answers WHERE checklist_id IN ($in)");
    $ans_stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
    $ans_stmt->execute();
    $ans_result = $ans_stmt->get_result();
    while ($a = $ans_result->fetch_assoc()) {
        $answers[$a['checklist_id']][$a['item_id']] = $a['value'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BBS Checklist Report</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Top Navbar -->
    <nav class="w-full bg-white shadow-lg flex items-center justify-between px-8 py-3 space-x-4">
        <div class="flex items-center space-x-4">
            <div class="sidebar-avatar group relative w-12 h-12 flex items-center justify-center rounded-full bg-gradient-to-br from-cyan-500 to-blue-500 shadow-lg border-4 border-white transition-all duration-200 hover:scale-105 hover:shadow-2xl cursor-pointer" title="<?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>">
                <?php if (!empty($_SESSION['profile_image_url'])): ?>
                    <img src="<?php echo htmlspecialchars($_SESSION['profile_image_url']); ?>" 
                         alt="Profile" 
                         class="w-full h-full object-cover rounded-full" />
                <?php else: ?>
                    <span class="text-white font-bold text-2xl select-none">
                        <?php echo strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)); ?>
                    </span>
                <?php endif; ?>
                <span class="absolute bottom-1 right-1 w-4 h-4 bg-green-400 border-2 border-white rounded-full shadow"></span>
            </div>
            <a href="admin.php" class="sidebar-icon text-blue-700 hover:text-orange-600" title="Dashboard" aria-label="Dashboard">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
                <span class="sidebar-label">Dashboard</span>
            </a>
            <a href="admin_departments.php" class="sidebar-icon text-cyan-600" title="Manage Departments">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h18M3 17h18"/>
                </svg>
                <span class="sidebar-label">Departments</span>
            </a>
            <a href="admin_employees.php" class="sidebar-icon text-cyan-600" title="Manage Employees">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 15c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span class="sidebar-label">Employees</span>
            </a>
            <a href="bbs_checklist_report.php" class="sidebar-icon text-cyan-600" title="BBS Checklist Report">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span class="sidebar-label">BBS Report</span>
            </a>
            <a href="admin_bbs_items.php" class="sidebar-icon text-cyan-600" title="Manage BBS Checkpoints">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                <span class="sidebar-label">BBS Items</span>
            </a>
            <a href="bbs_dashboard.php" class="sidebar-icon text-blue-700 hover:text-orange-600" title="BBS Checklist Dashboard" aria-label="BBS Checklist Dashboard">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <rect x="3" y="13" width="4" height="8" fill="#f59e0b" stroke="#f59e0b" stroke-width="1.5"/>
                    <rect x="9" y="9" width="4" height="12" fill="#0ea5e9" stroke="#0ea5e9" stroke-width="1.5"/>
                    <rect x="15" y="5" width="4" height="16" fill="#f59e0b" stroke="#f59e0b" stroke-width="1.5"/>
                </svg>
                <span class="sidebar-label">BBS Dashboard</span>
            </a>
            <a href="sor_report.php" class="sidebar-icon text-blue-700 hover:text-orange-600" title="SOR Report" aria-label="SOR Report">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span class="sidebar-label">SOR Report</span>
            </a>
        </div>
        <button onclick="goToMainApp()" class="sidebar-icon text-blue-500" title="Main App">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
        </button>
    </nav>

    <div class="max-w-6xl mx-auto bg-white mt-8 p-6 rounded shadow">
        <h1 class="text-2xl font-bold mb-4 text-center">BBS Checklist Report</h1>
        <form method="GET" class="mb-6 flex flex-wrap gap-2 items-end">
            <select name="department_id" class="border rounded px-3 py-2">
                <option value="">All Departments</option>
                <?php foreach($departments as $dept): ?>
                    <option value="<?php echo $dept['id']; ?>" <?php if(isset($_GET['department_id']) && $_GET['department_id']==$dept['id']) echo 'selected'; ?>><?php echo htmlspecialchars($dept['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="employee_id" class="border rounded px-3 py-2">
                <option value="">All Employees</option>
                <?php foreach($employees as $emp): ?>
                    <option value="<?php echo $emp['id']; ?>" <?php if(isset($_GET['employee_id']) && $_GET['employee_id']==$emp['id']) echo 'selected'; ?>><?php echo htmlspecialchars($emp['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="observer" placeholder="Observer" class="border rounded px-3 py-2" value="<?php echo htmlspecialchars($_GET['observer'] ?? ''); ?>">
            <input type="date" name="date_from" class="border rounded px-3 py-2" value="<?php echo htmlspecialchars($_GET['date_from'] ?? ''); ?>">
            <input type="date" name="date_to" class="border rounded px-3 py-2" value="<?php echo htmlspecialchars($_GET['date_to'] ?? ''); ?>">
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Filter</button>
            <button type="button" onclick="exportCSV()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">Export CSV</button>
        </form>
        <div class="overflow-x-auto">
        <table class="w-full border text-sm">
            <thead class="bg-gray-100">
                <tr>
                    <th class="p-2">Date</th>
                    <th class="p-2">Observer</th>
                    <th class="p-2">Department</th>
                    <th class="p-2">Employee</th>
                    <?php foreach($items_arr as $item): ?>
                        <th class="p-2"><?php echo htmlspecialchars($item['label']); ?></th>
                    <?php endforeach; ?>
                    <th class="p-2">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($rows as $row): ?>
                <tr class="border-t">
                    <td class="p-2"><?php echo htmlspecialchars(date('d M Y, H:i', strtotime($row['date_of_observation']))); ?></td>
                    <td class="p-2"><?php echo htmlspecialchars($row['observer']); ?></td>
                    <td class="p-2"><?php echo htmlspecialchars($row['department_name']); ?></td>
                    <td class="p-2"><?php echo htmlspecialchars($row['employee_name']); ?></td>
                    <?php foreach($items_arr as $item): ?>
                        <td class="p-2"><?php echo isset($answers[$row['id']][$item['id']]) ? htmlspecialchars(ucfirst($answers[$row['id']][$item['id']])) : '-'; ?></td>
                    <?php endforeach; ?>
                    <td class="p-2">
                        <button onclick="openEditModal(<?php echo $row['id']; ?>)" class="bg-yellow-500 text-white px-2 py-1 rounded hover:bg-yellow-600">Edit</button>
                        <button onclick="deleteChecklist(<?php echo $row['id']; ?>)" class="bg-red-600 text-white px-2 py-1 rounded hover:bg-red-700 ml-2">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <div class="mt-4 text-center">
            <a href="admin.php" class="text-blue-600 hover:underline">Back to Admin Dashboard</a>
        </div>
    </div>
    <!-- Edit Modal -->
    <div id="edit-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 max-w-lg w-full mx-auto">
            <h2 class="text-xl font-bold mb-4">Edit Checklist Answers</h2>
            <form id="edit-form" class="space-y-4">
                <input type="hidden" name="checklist_id" id="edit-checklist-id">
                <div id="edit-items"></div>
                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" onclick="closeEditModal()" class="bg-gray-300 px-4 py-2 rounded hover:bg-gray-400">Cancel</button>
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Save</button>
                </div>
            </form>
        </div>
    </div>
    <script>
    function exportCSV() {
        let csv = 'Date,Observer,Department,Employee<?php foreach($items_arr as $item): ?>,<?php echo addslashes($item['label']); ?><?php endforeach; ?>\n';
        document.querySelectorAll('table tbody tr').forEach(row => {
            let rowData = [];
            row.querySelectorAll('td').forEach((cell, idx) => {
                if(idx < 4 + <?php echo count($items_arr); ?>) rowData.push('"'+cell.textContent.replace(/"/g,'""')+'"');
            });
            csv += rowData.join(',') + '\n';
        });
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'bbs_checklist_report.csv';
        a.click();
        window.URL.revokeObjectURL(url);
    }
    function openEditModal(id) {
        // Fetch answers for checklist id
        fetch('bbs_checklist_report.php?action=get_answers&id=' + id)
            .then(res => res.json())
            .then(data => {
                document.getElementById('edit-checklist-id').value = id;
                let html = '';
                <?php foreach($items_arr as $item): ?>
                html += `<div><label class='block mb-1'><?php echo addslashes($item['label']); ?></label>
                    <label class='mr-4'><input type='radio' name='item_<?php echo $item['id']; ?>' value='safe' ${data.answers && data.answers[<?php echo $item['id']; ?>]==='safe' ? 'checked' : ''}> Safe</label>
                    <label><input type='radio' name='item_<?php echo $item['id']; ?>' value='unsafe' ${data.answers && data.answers[<?php echo $item['id']; ?>]==='unsafe' ? 'checked' : ''}> Unsafe</label>
                </div>`;
                <?php endforeach; ?>
                document.getElementById('edit-items').innerHTML = html;
                document.getElementById('edit-modal').classList.remove('hidden');
            });
    }
    function closeEditModal() {
        document.getElementById('edit-modal').classList.add('hidden');
    }
    document.getElementById('edit-form').onsubmit = async function(e) {
        e.preventDefault();
        const form = e.target;
        const checklist_id = form.checklist_id.value;
        const answers = [];
        <?php foreach($items_arr as $item): ?>
        const val_<?php echo $item['id']; ?> = form.querySelector('input[name="item_<?php echo $item['id']; ?>"]:checked');
        if (val_<?php echo $item['id']; ?>) {
            answers.push({item_id: <?php echo $item['id']; ?>, value: val_<?php echo $item['id']; ?>.value});
        }
        <?php endforeach; ?>
        const res = await fetch('bbs_checklist_report.php?action=update_answers', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({id: checklist_id, answers: answers})
        });
        const result = await res.json();
        alert(result.message);
        if (result.success) location.reload();
    };
    async function deleteChecklist(id) {
        if (!confirm('Delete this checklist and all answers?')) return;
        const res = await fetch('api.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=delete_bbs_checklist&id=' + encodeURIComponent(id),
            credentials: 'include'
        });
        const result = await res.json();
        alert(result.message);
        if (result.success) location.reload();
    }
    </script>
    <?php
    // Handle AJAX for edit/delete
    if (isset($_GET['action']) && $_GET['action'] === 'get_answers' && isset($_GET['id'])) {
        $cid = intval($_GET['id']);
        $ans = $conn->query("SELECT item_id, value FROM bbs_checklist_answers WHERE checklist_id = $cid");
        $arr = [];
        while ($a = $ans->fetch_assoc()) $arr[$a['item_id']] = $a['value'];
        echo json_encode(['answers' => $arr]);
        exit;
    }
    if (isset($_GET['action']) && $_GET['action'] === 'update_answers') {
        $data = json_decode(file_get_contents('php://input'), true);
        $cid = intval($data['id']);
        $conn->query("DELETE FROM bbs_checklist_answers WHERE checklist_id = $cid");
        $stmt = $conn->prepare("INSERT INTO bbs_checklist_answers (checklist_id, item_id, value) VALUES (?, ?, ?)");
        foreach ($data['answers'] as $ans) {
            $item_id = intval($ans['item_id']);
            $value = $ans['value'] === 'safe' ? 'safe' : 'unsafe';
            $stmt->bind_param('iis', $cid, $item_id, $value);
            $stmt->execute();
        }
        echo json_encode(['success' => true, 'message' => 'Answers updated.']);
        exit;
    }
    ?>
</body>
</html> 