<?php
session_start();
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header('Location: index.html');
    exit;
}
include 'db_config.php';
// Total BBS Observations
$total = $conn->query("SELECT COUNT(*) as cnt FROM bbs_checklists")->fetch_assoc()['cnt'];
// Safe/Unsafe counts
$safe = $conn->query("SELECT COUNT(*) as cnt FROM bbs_checklist_answers WHERE value='safe'")->fetch_assoc()['cnt'];
$unsafe = $conn->query("SELECT COUNT(*) as cnt FROM bbs_checklist_answers WHERE value='unsafe'")->fetch_assoc()['cnt'];
// Top 5 most frequent unsafe items
$top_unsafe_items = $conn->query("SELECT i.label, COUNT(*) as cnt FROM bbs_checklist_answers a JOIN bbs_observation_items i ON a.item_id=i.id WHERE a.value='unsafe' GROUP BY a.item_id ORDER BY cnt DESC LIMIT 5");
$unsafe_items_labels = [];
$unsafe_items_counts = [];
while ($row = $top_unsafe_items->fetch_assoc()) {
    $unsafe_items_labels[] = $row['label'];
    $unsafe_items_counts[] = $row['cnt'];
}
// Top 5 employees with most unsafe
$top_unsafe_emps = $conn->query("SELECT e.name, COUNT(*) as cnt FROM bbs_checklists c JOIN bbs_checklist_answers a ON c.id=a.checklist_id JOIN employees e ON c.employee_id=e.id WHERE a.value='unsafe' GROUP BY c.employee_id ORDER BY cnt DESC LIMIT 5");
$unsafe_emps_labels = [];
$unsafe_emps_counts = [];
while ($row = $top_unsafe_emps->fetch_assoc()) {
    $unsafe_emps_labels[] = $row['name'];
    $unsafe_emps_counts[] = $row['cnt'];
}
// Top 5 employees with highest safe rate (min 1 answer)
$top_safe_emps = $conn->query("SELECT e.name, SUM(a.value='safe') as safe_cnt FROM bbs_checklists c JOIN bbs_checklist_answers a ON c.id=a.checklist_id JOIN employees e ON c.employee_id=e.id GROUP BY c.employee_id ORDER BY safe_cnt DESC LIMIT 5");
$safe_emps_labels = [];
$safe_emps_counts = [];
while ($row = $top_safe_emps->fetch_assoc()) {
    $safe_emps_labels[] = $row['name'];
    $safe_emps_counts[] = $row['safe_cnt'];
}
// BBS Daily Submission (today, per observer)
$today = date('Y-m-d');
$daily_submissions = [];
$observers = [];
$res = $conn->query("SELECT DISTINCT observer FROM bbs_checklists WHERE date_of_observation = '$today'");
if (!$res) {
    die("Query Error (observer fetch): " . $conn->error);
}
while ($row = $res->fetch_assoc()) {
    $observers[] = $row['observer'];
}
foreach ($observers as $observer) {
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM bbs_checklists WHERE observer = ? AND date_of_observation = ?");
    $stmt->bind_param('ss', $observer, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $daily_submissions[] = $result->fetch_assoc()['cnt'] ?? 0;
    $stmt->close();
}
// Top 5 Unsafe Locations (by department)
$top_unsafe_locations = $conn->query("
    SELECT d.name AS department, COUNT(*) as cnt
    FROM bbs_checklist_answers a
    JOIN bbs_checklists c ON a.checklist_id = c.id
    JOIN departments d ON c.department_id = d.id
    WHERE a.value = 'unsafe'
    GROUP BY d.name
    ORDER BY cnt DESC
    LIMIT 5
");
if (!$top_unsafe_locations) {
    die('Query Error (unsafe locations): ' . $conn->error);
}
$unsafe_locations_labels = [];
$unsafe_locations_counts = [];
while ($row = $top_unsafe_locations->fetch_assoc()) {
    $unsafe_locations_labels[] = $row['department'];
    $unsafe_locations_counts[] = $row['cnt'];
}
// --- KPI Calculations ---
$total_reports = $total;
$total_safe = $safe;
$total_unsafe = $unsafe;
$unique_observers = $conn->query("SELECT COUNT(DISTINCT observer) as cnt FROM bbs_checklists")->fetch_assoc()['cnt'];
$avg_per_user = $unique_observers ? round($total_reports / $unique_observers, 2) : 0;
$safe_pct = ($total_safe + $total_unsafe) > 0 ? round(($total_safe / ($total_safe + $total_unsafe)) * 100, 1) : 0;
$unsafe_pct = ($total_safe + $total_unsafe) > 0 ? round(($total_unsafe / ($total_safe + $total_unsafe)) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BBS Checklist Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gradient-to-br from-blue-50 to-orange-50 min-h-screen">
    <!-- Top Navbar (copied from admin.php) -->
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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
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
    <div class="max-w-7xl mx-auto py-8">
        <!-- Filter System -->
        <div class="flex justify-end mb-6 gap-4">
            <div class="flex items-center">
                <label class="mr-2 font-semibold text-gray-700">Time Period:</label>
                <input id="weekPicker" class="border rounded px-3 py-2 bg-white" placeholder="Pick a week" readonly>
            </div>
            <div class="flex items-center">
                <label class="mr-2 font-semibold text-gray-700">Department:</label>
                <select id="departmentFilter" class="border rounded px-3 py-2 bg-white">
                    <option value="all">All Departments</option>
            </select>
            </div>
            <button id="addObserverBtn" class="btn-primary bg-cyan-600 text-white px-4 py-2 rounded">Add Observer</button>
        </div>
        <div class="mb-4 flex items-center gap-2">
            <label for="dailyTarget" class="font-semibold text-gray-700">Daily Target:</label>
            <input type="number" id="dailyTarget" min="1" value="3" class="border rounded px-2 py-1 w-20">
        </div>
        <!-- KPI Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <div class="bg-green-50 rounded-xl p-6 text-center border border-green-100 shadow">
                <div class="text-sm text-green-700 font-semibold mb-1">Total Reports</div>
                <div class="text-3xl font-bold text-green-700" id="kpi-total"></div>
            </div>
            <div class="bg-green-50 rounded-xl p-6 text-center border border-green-100 shadow">
                <div class="text-sm text-green-700 font-semibold mb-1">Safe %</div>
                <div class="text-2xl font-bold text-green-700" id="kpi-safe"></div>
            </div>
            <div class="bg-red-50 rounded-xl p-6 text-center border border-red-100 shadow">
                <div class="text-sm text-red-700 font-semibold mb-1">Unsafe %</div>
                <div class="text-2xl font-bold text-red-700" id="kpi-unsafe"></div>
            </div>
        </div>
        <!-- BBS Submission Chart -->
        <div class="bg-white rounded shadow p-4 mb-6">
            <canvas id="bbsSubmissionChart" height="120"></canvas>
            </div>
        <!-- BBS Compliance Table -->
        <div class="table-container bg-white rounded shadow p-2 overflow-x-auto mb-6">
            <table class="min-w-full text-center border">
                <thead>
                    <tr class="bg-cyan-100">
                        <th class="px-2 py-1 border">No.</th>
                        <th class="px-2 py-1 border">Observer</th>
                        <th class="px-2 py-1 border">Monday<br><span id="date-mon"></span></th>
                        <th class="px-2 py-1 border">Tuesday<br><span id="date-tue"></span></th>
                        <th class="px-2 py-1 border">Wednesday<br><span id="date-wed"></span></th>
                        <th class="px-2 py-1 border">Thursday<br><span id="date-thu"></span></th>
                        <th class="px-2 py-1 border">Friday<br><span id="date-fri"></span></th>
                        <th class="px-2 py-1 border">Saturday<br><span id="date-sat"></span></th>
                        <th class="px-2 py-1 border">Individual % Compliance</th>
                        <th class="px-2 py-1 border">Actions</th>
                    </tr>
                </thead>
                <tbody id="bbs-table-body">
                    <!-- Data will be injected here -->
                </tbody>
            </table>
            </div>
        <div class="mt-4 flex flex-col md:flex-row md:items-center md:justify-between gap-2 mb-8">
            <div class="font-semibold text-lg text-cyan-700">Observer BBS Compliance: <span id="observer-compliance" class="text-red-500">0%</span></div>
        </div>
        <!-- Chart Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8 w-full">
            <div class="bg-white rounded-2xl border border-green-100 p-6 shadow flex flex-col items-center min-h-[400px]">
                <h3 class="text-base font-bold text-green-700 mb-3">Top 5 Employees (Safe Records)</h3>
                <canvas id="safeEmpsChart"></canvas>
            </div>
            <div class="bg-white rounded-2xl border border-red-100 p-6 shadow flex flex-col items-center min-h-[400px] w-full">
                <h3 class="text-base font-bold text-red-700 mb-3">Top 5 Employees (Unsafe Records)</h3>
                <canvas id="unsafeEmpsChart"></canvas>
            </div>
        </div>
        <div id="unsafe-emp-items-table-container" class="bg-white rounded-2xl border border-red-100 p-6 shadow min-h-[200px] mb-8"></div>
        <div class="grid grid-cols-1 gap-8 mb-8 w-full">
            <div class="bg-white rounded-2xl border border-red-100 p-6 shadow flex flex-col items-center min-h-[400px]">
            <h3 class="text-base font-bold text-red-700 mb-3">Top 5 Most Violated Items</h3>
            <canvas id="violatedItemsChart"></canvas>
        </div>
            </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8 w-full">
            <div class="bg-white rounded-2xl border border-orange-100 p-6 shadow flex flex-col items-center min-h-[400px]">
                <h3 class="text-base font-bold text-orange-700 mb-3">Top 5 Locations (Unsafe Observations)</h3>
                <canvas id="unsafeLocationsChart"></canvas>
            </div>
            <div class="bg-white rounded-2xl border border-blue-100 p-6 shadow flex flex-col items-center min-h-[400px]">
                <h3 class="text-base font-bold text-blue-700 mb-3">BBS Submission Trend</h3>
                <canvas id="bbsSubmissionChart"></canvas>
            </div>
        </div>
    </div>
    <!-- Observer Edit Modal (hidden by default) -->
    <div id="observerModal" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg p-6 w-full max-w-md">
            <h3 class="text-lg font-bold mb-4" id="observerModalTitle">Edit Observer</h3>
            <form id="observerForm" class="space-y-4">
                <input type="hidden" id="observerId">
                <div>
                    <label class="block text-gray-700">Observer Name</label>
                    <input type="text" id="observerInput" class="input-field w-full border rounded px-3 py-2" required>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeObserverModal()" class="btn-secondary px-4 py-2 rounded">Cancel</button>
                    <button type="submit" class="btn-primary bg-cyan-600 text-white px-4 py-2 rounded">Save</button>
                </div>
            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script>
    let safeEmpsChart, unsafeEmpsChart, violatedItemsChart, employeeViolationItemsChart, unsafeLocationsChart, bbsSubmissionChart;
    
    // Flatpickr week picker
    flatpickr("#weekPicker", {
        weekNumbers: true,
        dateFormat: "Y-m-d",
        defaultDate: new Date(),
        onChange: function(selectedDates, dateStr, instance) {
            if (selectedDates.length > 0) {
                const monday = getMonday(selectedDates[0]);
                document.getElementById('weekPicker').value = monday.toISOString().slice(0,10);
                loadBbsDashboardData(monday);
            }
        },
        onReady: function(selectedDates, dateStr, instance) {
            // Set initial date to current week's Monday
            const monday = getMonday(new Date());
            instance.setDate(monday);
        }
    });

    function getMonday(d) {
        d = new Date(d);
        const day = d.getDay();
        // If Sunday (0), subtract 6 days; else subtract (day-1)
        if (day === 0) {
            d.setDate(d.getDate() - 6);
        } else {
            d.setDate(d.getDate() - (day - 1));
        }
        d.setHours(0,0,0,0);
        return d;
    }

    // Set week dates in header with validation
    function setWeekDates(monday) {
        // Validate that we have a Monday
        if (monday.getDay() !== 1) {
            console.warn('setWeekDates: Input is not a Monday, correcting...');
            monday = getMonday(monday);
        }
        
        const days = [0,1,2,3,4,5];
        const ids = ["date-mon","date-tue","date-wed","date-thu","date-fri","date-sat"];
        const dayNames = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
        
        for (let i = 0; i < 6; i++) {
            const date = new Date(monday);
            date.setDate(date.getDate() + i);
            const dateElement = document.getElementById(ids[i]);
            if (dateElement) {
                const dateString = date.toISOString().slice(5,10);
                dateElement.textContent = dateString;
                dateElement.title = `${dayNames[i]} ${date.toISOString().slice(0,10)}`;
            }
        }
        
        // Update week picker display to show the week range
        const saturday = new Date(monday);
        saturday.setDate(saturday.getDate() + 5);
        const weekRange = `${monday.toISOString().slice(0,10)} to ${saturday.toISOString().slice(0,10)}`;
        document.getElementById('weekPicker').title = `Week: ${weekRange}`;
    }

    async function loadBbsDashboardData(monday) {
        // Validate input
        if (!monday || !(monday instanceof Date)) {
            console.error('loadBbsDashboardData: Invalid date input');
            return;
        }
        
        setWeekDates(monday);
        const weekStart = monday.toISOString().slice(0,10);
        const department = document.getElementById('departmentFilter').value;
        
        try {
            const res = await fetch(`bbs_dashboard_data.php?filter=weekly&week_start=${weekStart}&department=${department}`);
            const text = await res.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (e) {
                console.error('Failed to parse JSON response:', text);
                throw new Error('Server did not return valid JSON. See console for details.');
            }
            renderAllCharts(data);
            renderBBSComplianceTable(data.daily_submissions, weekStart);
        } catch (err) {
            console.error('Failed to load dashboard data:', err);
            alert('Failed to load dashboard data: ' + err.message);
        }
    }

    function renderBBSComplianceTable(submissions, weekStart) {
        const tbody = document.getElementById('bbs-table-body');
        tbody.innerHTML = '';
        
        let totalCompliance = 0;
        let observerCount = 0;

        Object.entries(submissions).forEach(([observer, data], idx) => {
            const row = document.createElement('tr');
            const days = data.days || Array(6).fill({ hit: false, count: 0 });
            const compliance = data.compliance || 0;
            
            row.innerHTML = `
                <td class="border px-2 py-1">${idx+1}</td>
                <td class="border px-2 py-1">${observer}</td>
                ${days.map(day => `
                    <td class="border px-2 py-1 ${day.count >= dailyTarget ? 'bg-green-200 text-green-700' : 'bg-red-200 text-red-700'}">
                        ${day.count}
                    </td>
                `).join('')}
                <td class="border px-2 py-1 font-bold">${compliance}%</td>
                <td class="border px-2 py-1">
                    <button onclick="editObserver('${observer}')" class="text-blue-600 hover:underline">Edit</button>
                    <button onclick="deleteObserver('${observer}')" class="text-red-600 hover:underline ml-2">Delete</button>
                </td>
            `;
            tbody.appendChild(row);

            totalCompliance += compliance;
            observerCount++;
        });

        const averageCompliance = observerCount ? Math.round(totalCompliance / observerCount) : 0;
        document.getElementById('observer-compliance').textContent = averageCompliance + '%';
        document.getElementById('observer-compliance').className = averageCompliance >= 80 ? 'text-green-600' : 'text-red-500';
    }

    // Add this function to render the BBS submission chart
    function renderBBSSubmissionChart(data) {
        if (bbsSubmissionChart) bbsSubmissionChart.destroy();
        
        const ctx = document.getElementById('bbsSubmissionChart').getContext('2d');
        bbsSubmissionChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
                datasets: Object.entries(data.daily_submissions).map(([observer, data]) => ({
                    label: observer,
                    data: data.days.map(day => day.count),
                    backgroundColor: getUserColor(observer),
                    borderRadius: 4
                }))
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'top' },
                    title: { display: true, text: 'Daily BBS Submissions per Observer' }
                },
                scales: {
                    y: { 
                        beginAtZero: true,
                        ticks: { precision: 0 }
                    }
                }
            }
        });
    }

    // Helper to assign a color per user
    function getUserColor(username) {
        const colors = [
            '#0ea5e9','#f59e0b','#10b981','#6366f1','#ec4899','#f43f5e',
            '#a3e635','#f87171','#fbbf24','#818cf8','#f472b6','#34d399'
        ];
        let hash = 0;
        for (let i = 0; i < username.length; i++) {
            hash = username.charCodeAt(i) + ((hash << 5) - hash);
        }
        return colors[Math.abs(hash) % colors.length];
    }

    function renderAllCharts(data) {
        // Defensive check for KPI data
        if (!data.kpi) {
            alert('Dashboard data error: ' + (data.error || 'No KPI data returned'));
            return;
        }
        // KPIs
        document.getElementById('kpi-total').textContent = data.kpi.total_reports;
        document.getElementById('kpi-safe').textContent = data.kpi.safe_pct + '%';
        document.getElementById('kpi-unsafe').textContent = data.kpi.unsafe_pct + '%';

        const chartConfig = {
            indexAxis: 'x',
            plugins: { 
                legend: { 
                    display: false 
                } 
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { 
                        font: { 
                            size: 12, 
                            weight: 'bold' 
                        }
                    }
                },
                x: {
                    beginAtZero: true,
                    ticks: { 
                        font: { 
                            size: 12, 
                            weight: 'bold' 
                        },
                        maxRotation: 0,
                        minRotation: 0,
                        callback: function(value) {
                            const label = this.getLabelForValue(value);
                            const words = label.split(' ');
                            const lines = [];
                            let currentLine = '';
                            
                            words.forEach(word => {
                                if (currentLine.length + word.length > 15) {
                                    lines.push(currentLine);
                                    currentLine = word;
                                } else {
                                    currentLine += (currentLine.length ? ' ' : '') + word;
                                }
                            });
                            if (currentLine) {
                                lines.push(currentLine);
                            }
                            return lines;
                        }
                    }
                }
            }
        };

        // Safe Employees
        if (safeEmpsChart) safeEmpsChart.destroy();
        safeEmpsChart = new Chart(document.getElementById('safeEmpsChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: data.safe_emps.labels,
                datasets: [{
                    label: 'Safe Records',
                    data: data.safe_emps.counts,
                    backgroundColor: '#22c55e',
                    borderRadius: 8,
                    barThickness: 32
                }]
            },
            options: {
                ...chartConfig,
                scales: {
                    ...chartConfig.scales,
                    x: {
                        ...chartConfig.scales.x,
                        ticks: {
                            ...chartConfig.scales.x.ticks,
                            maxRotation: 45,
                            minRotation: 45
                        }
                    }
                }
            }
        });

        // Unsafe Employees
        if (unsafeEmpsChart) unsafeEmpsChart.destroy();
        unsafeEmpsChart = new Chart(document.getElementById('unsafeEmpsChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: data.unsafe_emps.labels,
                datasets: [{
                    label: 'Unsafe Records',
                    data: data.unsafe_emps.counts,
                    backgroundColor: '#ef4444',
                    borderRadius: 8,
                    barThickness: 32
                }]
            },
            options: {
                ...chartConfig,
                scales: {
                    ...chartConfig.scales,
                    x: {
                        ...chartConfig.scales.x,
                        ticks: {
                            ...chartConfig.scales.x.ticks,
                            maxRotation: 45,
                            minRotation: 45
                        }
                    }
                }
            }
        });

        // Violated Items
        if (violatedItemsChart) violatedItemsChart.destroy();
        violatedItemsChart = new Chart(document.getElementById('violatedItemsChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: data.violated_items.labels,
                datasets: [{
                    label: 'Violations',
                    data: data.violated_items.counts,
                    backgroundColor: '#ef4444',
                    borderRadius: 8,
                    barThickness: 32
                }]
            },
            options: {
                ...chartConfig,
                scales: {
                    ...chartConfig.scales,
                    x: {
                        ...chartConfig.scales.x,
                        ticks: {
                            ...chartConfig.scales.x.ticks,
                            align: 'center',
                            maxWidth: 120,
                            font: {
                                size: 13,
                                weight: 'bold'
                            },
                            callback: function(value) {
                                const label = this.getLabelForValue(value);
                                return label.match(/.{1,20}(\s|$)/g);
                            },
                            maxRotation: 0,
                            minRotation: 0
                        }
                    }
                }
            }
        });

        // Unsafe Locations
        if (unsafeLocationsChart) unsafeLocationsChart.destroy();
        unsafeLocationsChart = new Chart(document.getElementById('unsafeLocationsChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: data.unsafe_locations.labels,
                datasets: [{
                    label: 'Unsafe Observations',
                    data: data.unsafe_locations.counts,
                    backgroundColor: '#f59e0b',
                    borderRadius: 8,
                    barThickness: 32
                }]
            },
            options: chartConfig
        });

        // BBS Submission Chart
        renderBBSSubmissionChart(data);

        // Render unsafe items per employee table
        renderUnsafeEmpItemsTable(data.unsafe_emp_items);
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        const monday = getMonday(new Date());
        document.getElementById('weekPicker').value = monday.toISOString().slice(0,10);
        loadBbsDashboardData(monday);
    });

    // Load departments for filter
    async function loadDepartments() {
        try {
            const res = await fetch('get_departments.php');
            const departments = await res.json();
            const select = document.getElementById('departmentFilter');
            departments.forEach(dept => {
                const option = document.createElement('option');
                option.value = dept.id;
                option.textContent = dept.name;
                select.appendChild(option);
            });
        } catch (err) {
            console.error('Failed to load departments:', err);
        }
    }

    // Event Listeners
    document.getElementById('departmentFilter').addEventListener('change', function() {
        const monday = getMonday(new Date(document.getElementById('weekPicker').value));
        loadBbsDashboardData(monday);
    });

    // Initialize
    window.addEventListener('DOMContentLoaded', function() {
        loadDepartments();
        const monday = getMonday(new Date());
        loadBbsDashboardData(monday);
    });

    // Observer management functions
    function editObserver(observer) {
        document.getElementById('observerId').value = observer;
        document.getElementById('observerInput').value = observer;
        document.getElementById('observerModalTitle').textContent = 'Edit Observer';
        document.getElementById('observerModal').classList.remove('hidden');
    }

    function closeObserverModal() {
        document.getElementById('observerModal').classList.add('hidden');
    }

    async function deleteObserver(observer) {
        if (!confirm('Delete this observer?')) return;
        try {
            const res = await fetch('api.php?action=delete_observer', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `observer=${encodeURIComponent(observer)}`,
                credentials: 'include'
            });
            const data = await res.json();
            if (data.success) {
                const monday = getMonday(new Date(document.getElementById('weekPicker').value));
                loadBbsDashboardData(monday);
            } else {
                alert(data.message || 'Failed to delete observer');
            }
        } catch (err) {
            alert('Failed to delete observer: ' + err.message);
        }
    }

    document.getElementById('observerForm').onsubmit = async function(e) {
        e.preventDefault();
        const observer = document.getElementById('observerId').value;
        const newObserver = document.getElementById('observerInput').value;
        
        if (!newObserver) {
            alert('Observer name is required');
            return;
        }

        let url, body;
        if (observer) {
            url = 'api.php?action=edit_observer';
            body = `observer=${encodeURIComponent(observer)}&new_observer=${encodeURIComponent(newObserver)}`;
        } else {
            url = 'api.php?action=add_observer';
            body = `observer=${encodeURIComponent(newObserver)}`;
        }

        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: body,
                credentials: 'include'
            });
            const data = await res.json();
            if (data.success) {
                closeObserverModal();
                const monday = getMonday(new Date(document.getElementById('weekPicker').value));
                loadBbsDashboardData(monday);
            } else {
                alert(data.message || 'Failed to save observer');
            }
        } catch (err) {
            alert('Failed to save observer: ' + err.message);
        }
    };

    document.getElementById('addObserverBtn').onclick = function() {
        document.getElementById('observerId').value = '';
        document.getElementById('observerInput').value = '';
        document.getElementById('observerModalTitle').textContent = 'Add Observer';
        document.getElementById('observerModal').classList.remove('hidden');
    };

    // Render unsafe items per employee as a table
    function renderUnsafeEmpItemsTable(unsafeEmpItems) {
        const container = document.getElementById('unsafe-emp-items-table-container');
        if (!unsafeEmpItems || Object.keys(unsafeEmpItems).length === 0) {
            container.innerHTML = '';
            return;
        }
        let html = `<div class='overflow-x-auto'><table class='min-w-full text-xs text-left border border-gray-200'>`;
        html += `<thead><tr class='bg-red-100 text-red-700'>
            <th class='px-2 py-1 border'>Employee</th>
            <th class='px-2 py-1 border'>Violated Item</th>
            <th class='px-2 py-1 border'>Count</th>
            <th class='px-2 py-1 border'>Department</th>
            <th class='px-2 py-1 border'>Dates</th>
        </tr></thead><tbody>`;
        for (const [emp, items] of Object.entries(unsafeEmpItems)) {
            if (items.length === 0) {
                html += `<tr><td class='border px-2 py-1'>${emp}</td><td class='border px-2 py-1' colspan='4'>No violations</td></tr>`;
            } else {
                for (const item of items) {
                    // Get unique departments for this item
                    const departments = [...new Set(item.occurrences.map(o => o.department))];
                    html += `<tr>`;
                    html += `<td class='border px-2 py-1'>${emp}</td>`;
                    html += `<td class='border px-2 py-1'>${item.label}</td>`;
                    html += `<td class='border px-2 py-1'>${item.count}</td>`;
                    html += `<td class='border px-2 py-1'>[${departments.map(d => `'${d}'`).join(', ')}]</td>`;
                    html += `<td class='border px-2 py-1'>[${item.occurrences.map(o => `'${o.date}'`).join(', ')}]</td>`;
                    html += `</tr>`;
                }
            }
        }
        html += `</tbody></table></div>`;
        container.innerHTML = html;
    }

    // Add daily target logic
    let dailyTarget = parseInt(localStorage.getItem('bbsDailyTarget')) || 3;
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('dailyTarget').value = dailyTarget;
        document.getElementById('dailyTarget').addEventListener('input', function() {
            dailyTarget = parseInt(this.value) || 1;
            localStorage.setItem('bbsDailyTarget', dailyTarget);
            const monday = getMonday(new Date(document.getElementById('weekPicker').value));
            loadBbsDashboardData(monday);
        });
    });
    </script>
</body>
</html> 