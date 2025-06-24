<?php
session_start();
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header('Location: index.html');
    exit;
}
include 'db_config.php';
// Handle add/edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add']) && !empty($_POST['name']) && !empty($_POST['department_id'])) {
        $stmt = $conn->prepare("INSERT INTO employees (name, department_id) VALUES (?, ?)");
        $stmt->bind_param('si', $_POST['name'], $_POST['department_id']);
        $stmt->execute();
    }
    if (isset($_POST['edit']) && !empty($_POST['id']) && !empty($_POST['name']) && !empty($_POST['department_id'])) {
        $stmt = $conn->prepare("UPDATE employees SET name=?, department_id=? WHERE id=?");
        $stmt->bind_param('sii', $_POST['name'], $_POST['department_id'], $_POST['id']);
        $stmt->execute();
    }
    if (isset($_POST['delete']) && !empty($_POST['id'])) {
        $stmt = $conn->prepare("DELETE FROM employees WHERE id=?");
        $stmt->bind_param('i', $_POST['id']);
        $stmt->execute();
    }
    header('Location: admin_employees.php');
    exit;
}
// Fetch employees and departments
$employees = $conn->query("SELECT e.id, e.name, d.name AS department, e.department_id FROM employees e LEFT JOIN departments d ON e.department_id = d.id ORDER BY e.id DESC");
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
    <title>Manage Employees</title>
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
            <a href="admin.php" class="sidebar-icon text-blue-700 hover:text-orange-600" title="Admin Dashboard" aria-label="Admin Dashboard">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </a>
        </div>
        <button onclick="goToMainApp()" class="sidebar-icon text-blue-500" title="Main App">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
        </button>
    </nav>

    <div class="max-w-2xl mx-auto bg-white mt-8 p-6 rounded shadow">
        <h1 class="text-2xl font-bold mb-4 text-center">Manage Employees</h1>
        <a href="admin.php" class="inline-block mb-4 text-blue-600 hover:underline">&larr; Back to Admin Dashboard</a>
        <form method="POST" class="mb-6 flex flex-col md:flex-row md:items-end gap-2">
            <input type="text" name="name" placeholder="Employee Name" class="border rounded px-3 py-2 flex-1" required>
            <select name="department_id" class="border rounded px-3 py-2 flex-1" required>
                <option value="">Select Department</option>
                <?php foreach($departments_arr as $dept): ?>
                    <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" name="add" value="1" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Add</button>
        </form>
        <table class="w-full border text-center">
            <thead>
                <tr class="bg-gray-100">
                    <th class="py-2">ID</th>
                    <th class="py-2">Name</th>
                    <th class="py-2">Department</th>
                    <th class="py-2">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while($emp = $employees->fetch_assoc()): ?>
                <tr class="<?php echo $emp['id'] % 2 ? 'bg-gray-50' : 'bg-white'; ?>">
                    <form method="POST" class="flex items-center">
                        <td class="p-2"><?php echo $emp['id']; ?><input type="hidden" name="id" value="<?php echo $emp['id']; ?>"></td>
                        <td class="p-2"><input type="text" name="name" value="<?php echo htmlspecialchars($emp['name']); ?>" class="border rounded px-2 py-1 w-full"></td>
                        <td class="p-2">
                            <select name="department_id" class="border rounded px-2 py-1 w-full">
                                <?php foreach($departments_arr as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>" <?php if($dept['id'] == $emp['department_id']) echo 'selected'; ?>><?php echo htmlspecialchars($dept['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="p-2 flex gap-2 justify-center">
                            <button type="submit" name="edit" value="1" class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-700">Edit</button>
                            <button type="submit" name="delete" value="1" class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-700" onclick="return confirm('Delete this employee?')">Delete</button>
                        </td>
                    </form>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html> 