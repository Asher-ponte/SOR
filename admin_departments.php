<?php
session_start();
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header('Location: index.html');
    exit;
}
include 'db_config.php';
// Handle add/edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add']) && !empty($_POST['name'])) {
        $stmt = $conn->prepare("INSERT INTO departments (name) VALUES (?)");
        $stmt->bind_param('s', $_POST['name']);
        $stmt->execute();
    }
    if (isset($_POST['edit']) && !empty($_POST['id']) && !empty($_POST['name'])) {
        $stmt = $conn->prepare("UPDATE departments SET name=? WHERE id=?");
        $stmt->bind_param('si', $_POST['name'], $_POST['id']);
        $stmt->execute();
    }
    if (isset($_POST['delete']) && !empty($_POST['id'])) {
        $stmt = $conn->prepare("DELETE FROM departments WHERE id=?");
        $stmt->bind_param('i', $_POST['id']);
        $stmt->execute();
    }
    header('Location: admin_departments.php');
    exit;
}
$departments = $conn->query("SELECT * FROM departments ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Departments</title>
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
        <h1 class="text-2xl font-bold mb-4 text-center">Manage Departments</h1>
        <form method="POST" class="mb-6 flex gap-2">
            <input type="text" name="name" placeholder="New Department Name" class="border rounded px-3 py-2 flex-1" required>
            <button type="submit" name="add" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Add</button>
        </form>
        <table class="w-full border text-left">
            <thead class="bg-gray-100">
                <tr><th class="p-2">Name</th><th class="p-2">Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach($departments as $dept): ?>
                <tr class="border-t">
                    <form method="POST" class="flex items-center">
                        <td class="p-2"><input type="text" name="name" value="<?php echo htmlspecialchars($dept['name']); ?>" class="border rounded px-2 py-1 w-full"></td>
                        <td class="p-2 flex gap-2">
                            <input type="hidden" name="id" value="<?php echo $dept['id']; ?>">
                            <button type="submit" name="edit" class="bg-green-600 text-white px-2 py-1 rounded hover:bg-green-700">Save</button>
                            <button type="submit" name="delete" class="bg-red-600 text-white px-2 py-1 rounded hover:bg-red-700" onclick="return confirm('Delete this department?')">Delete</button>
                        </td>
                    </form>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="mt-4 text-center">
            <a href="admin.php" class="text-blue-600 hover:underline">Back to Admin Dashboard</a>
        </div>
    </div>
</body>
</html> 