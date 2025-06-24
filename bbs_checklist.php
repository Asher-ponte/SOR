<?php
session_start();
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header('Location: index.html');
    exit;
}
include 'db_config.php';
// Fetch active observation items
$items = $conn->query("SELECT id, label FROM bbs_observation_items WHERE is_active = 1 ORDER BY id ASC");
$items_arr = [];
while ($row = $items->fetch_assoc()) {
    $items_arr[] = $row;
}
// Fetch departments
$departments = $conn->query("SELECT id, name FROM departments ORDER BY name ASC");
$departments_arr = [];
while ($row = $departments->fetch_assoc()) {
    $departments_arr[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BBS Checklist</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    // AJAX for employee dropdown
    async function fetchEmployees(deptId, query = '') {
        const res = await fetch('bbs_employee_api.php?dept_id=' + deptId + '&q=' + encodeURIComponent(query));
        return res.json();
    }
    function onDepartmentChange() {
        const deptId = document.getElementById('department').value;
        const empInput = document.getElementById('employee');
        empInput.value = '';
        empInput.setAttribute('data-dept', deptId);
        document.getElementById('employee-suggestions').innerHTML = '';
    }
    async function onEmployeeInput(e) {
        const deptId = document.getElementById('department').value;
        const query = e.target.value;
        if (!deptId || !query) {
            document.getElementById('employee-suggestions').innerHTML = '';
            return;
        }
        const employees = await fetchEmployees(deptId, query);
        let html = '';
        employees.forEach(emp => {
            html += `<div class='cursor-pointer px-2 py-1 hover:bg-gray-200' onclick="selectEmployee('${emp.name}', '${emp.id}')">${emp.name}</div>`;
        });
        document.getElementById('employee-suggestions').innerHTML = html;
    }
    function selectEmployee(name, id) {
        document.getElementById('employee').value = name;
        document.getElementById('employee_id').value = id;
        document.getElementById('employee-suggestions').innerHTML = '';
    }
    async function submitBBSForm(e) {
        e.preventDefault();
        const form = document.getElementById('bbs-form');
        // Set hidden date field to current datetime-local value
        const picker = document.getElementById('date_of_observation_picker');
        const dt = picker.value;
        if (!dt) {
            alert('Please select date and time of observation.');
            return false;
        }
        // Format as 'YYYY-MM-DD HH:mm'
        const formatted = dt.replace('T', ' ').slice(0, 16);
        document.getElementById('date_of_observation').value = formatted;
        const formData = new FormData(form);
        // Collect dynamic answers
        const answers = [];
        <?php foreach($items_arr as $item): ?>
        const val_<?php echo $item['id']; ?> = form.querySelector('input[name="item_<?php echo $item['id']; ?>"]:checked');
        if (val_<?php echo $item['id']; ?>) {
            answers.push({item_id: <?php echo $item['id']; ?>, value: val_<?php echo $item['id']; ?>.value});
        }
        <?php endforeach; ?>
        formData.append('answers', JSON.stringify(answers));
        const res = await fetch('bbs_checklist_save.php', { method: 'POST', body: formData });
        const result = await res.json();
        alert(result.message);
        if (result.success) form.reset();
        return false;
    }
    // On page load, set datetime-local to now
    window.addEventListener('DOMContentLoaded', function() {
        const picker = document.getElementById('date_of_observation_picker');
        const now = new Date();
        const local = now.toISOString().slice(0,16);
        picker.value = local;
    });
    </script>
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

    <div class="max-w-xl mx-auto bg-white mt-8 p-6 rounded shadow">
        <h1 class="text-2xl font-bold mb-4 text-center">BBS Inspection Checklist</h1>
        <form id="bbs-form" method="POST" onsubmit="return submitBBSForm(event)">
            <div class="mb-4">
                <label class="block text-gray-700 font-medium">Observer Name</label>
                <input type="text" class="mt-1 block w-full border rounded px-3 py-2 bg-gray-100" value="<?php echo htmlspecialchars($_SESSION['username']); ?>" readonly>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 font-medium">Department</label>
                <select id="department" name="department_id" class="mt-1 block w-full border rounded px-3 py-2" onchange="onDepartmentChange()" required>
                    <option value="">Select Department</option>
                    <?php foreach($departments_arr as $dept): ?>
                        <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-4 relative">
                <label class="block text-gray-700 font-medium">Name of Employee/Observation</label>
                <input type="text" id="employee" name="employee" class="mt-1 block w-full border rounded px-3 py-2" autocomplete="off" oninput="onEmployeeInput(event)" data-dept="">
                <div id="employee-suggestions" class="absolute left-0 right-0 bg-white border rounded z-10"></div>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 font-medium">Date of Observation</label>
                <input type="datetime-local" id="date_of_observation_picker" class="mt-1 block w-full border rounded px-3 py-2" required>
                <input type="hidden" id="date_of_observation" name="date_of_observation">
            </div>
            <input type="hidden" id="employee_id" name="employee_id">
            <div class="mb-6">
                <label class="block text-gray-700 font-medium mb-2">Observation Items</label>
                <table class="w-full text-center border">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="py-2">Checkpoint</th>
                            <th class="py-2">Safe</th>
                            <th class="py-2">Unsafe</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($items_arr as $item): ?>
                        <tr class="<?php echo $item['id'] % 2 ? 'bg-gray-50' : 'bg-white'; ?>">
                            <td class="text-left px-2"><?php echo htmlspecialchars($item['label']); ?></td>
                            <td><input type="radio" name="item_<?php echo $item['id']; ?>" value="safe"></td>
                            <td><input type="radio" name="item_<?php echo $item['id']; ?>" value="unsafe"></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="flex justify-between">
                <a href="index.html" class="btn bg-gray-300 px-4 py-2 rounded hover:bg-gray-400">Back</a>
                <button type="submit" class="btn bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Submit</button>
            </div>
        </form>
    </div>
</body>
</html> 