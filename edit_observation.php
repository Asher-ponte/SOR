<?php
session_start();
if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
    header('Location: index.html');
    exit;
}
include 'db_config.php';
$id = $_GET['id'] ?? '';
if (!$id) {
    echo '<div class="p-8 text-red-600">No observation ID provided.</div>';
    exit;
}
// Fetch observation
$stmt = $conn->prepare("SELECT * FROM observations WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$obs = $result->fetch_assoc();
$stmt->close();
if (!$obs) {
    echo '<div class="p-8 text-red-600">Observation not found.</div>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Observation</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="max-w-2xl mx-auto mt-10 bg-white rounded-xl shadow-lg p-8">
        <h1 class="text-2xl font-bold mb-6 text-blue-700">Edit Safety Observation</h1>
        <form id="edit-observation-form" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($obs['id']); ?>">
            <input type="hidden" id="edit-from" name="from" value="<?php echo isset($_GET['from']) && $_GET['from'] === 'index' ? 'index' : ''; ?>">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date/Time</label>
                    <input type="datetime-local" name="timestamp" id="edit-date" class="input-field w-full" value="<?php echo htmlspecialchars(str_replace(' ', 'T', $obs['timestamp'])); ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <select name="category" id="edit-category" class="input-field w-full">
                        <option value="">Select Category</option>
                        <option value="physical" <?php if($obs['category']==='physical') echo 'selected'; ?>>Physical Hazard</option>
                        <option value="chemical" <?php if($obs['category']==='chemical') echo 'selected'; ?>>Chemical Hazard</option>
                        <option value="biological" <?php if($obs['category']==='biological') echo 'selected'; ?>>Biological Hazard</option>
                        <option value="mechanical" <?php if($obs['category']==='mechanical') echo 'selected'; ?>>Mechanical Hazard</option>
                        <option value="ergonomical" <?php if($obs['category']==='ergonomical') echo 'selected'; ?>>Ergonomical Hazard</option>
                        <option value="electrical" <?php if($obs['category']==='electrical') echo 'selected'; ?>>Electrical Hazard</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                    <select name="observation_type" id="edit-type" class="input-field w-full">
                        <option value="">Select Type</option>
                        <option value="Unsafe Condition" <?php if($obs['observation_type']==='Unsafe Condition') echo 'selected'; ?>>Unsafe Condition</option>
                        <option value="Unsafe Act" <?php if($obs['observation_type']==='Unsafe Act') echo 'selected'; ?>>Unsafe Act</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                    <input type="text" name="location" id="edit-location" class="input-field w-full" value="<?php echo htmlspecialchars($obs['location']); ?>" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Assign To</label>
                    <input type="text" name="assign_to" id="edit-assign-to" class="input-field w-full" value="<?php echo htmlspecialchars($obs['assign_to']); ?>" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
                    <input type="date" name="due_date" id="edit-due-date" class="input-field w-full" value="<?php echo htmlspecialchars($obs['due_date']); ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" id="edit-status" class="input-field w-full">
                        <option value="Open" <?php if($obs['status']==='Open') echo 'selected'; ?>>Open</option>
                        <option value="Closed" <?php if($obs['status']==='Closed') echo 'selected'; ?>>Closed</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea name="description" id="edit-description" class="input-field w-full"><?php echo htmlspecialchars($obs['description']); ?></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Corrective Actions</label>
                <textarea name="corrective_actions" id="edit-corrective" class="input-field w-full"><?php echo htmlspecialchars($obs['corrective_actions']); ?></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Preventive Actions</label>
                <textarea name="preventive_actions" id="edit-preventive" class="input-field w-full"><?php echo htmlspecialchars($obs['preventive_actions']); ?></textarea>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Before Image</label>
                    <div class="mb-2">
                        <img id="edit-before-preview" class="w-32 h-32 object-cover rounded border mb-2" src="<?php echo htmlspecialchars($obs['initial_image_data_url']); ?>" alt="Before Preview">
                    </div>
                    <input type="file" name="initial_image" id="edit-before-input" accept="image/*">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">After Image</label>
                    <div class="mb-2">
                        <img id="edit-after-preview" class="w-32 h-32 object-cover rounded border mb-2" src="<?php echo htmlspecialchars($obs['corrective_photo_data_url']); ?>" alt="After Preview">
                    </div>
                    <input type="file" name="corrective_image" id="edit-after-input" accept="image/*">
                </div>
            </div>
            <div class="flex justify-end gap-2 mt-4">
                <a href="sor_report.php" class="btn-secondary px-4 py-2">Cancel</a>
                <button type="submit" class="btn-primary px-4 py-2">Save Changes</button>
            </div>
        </form>
        <div id="edit-message" class="mt-4"></div>
    </div>
    <script>
    document.getElementById('edit-before-input').addEventListener('change', function(e) {
        if (e.target.files && e.target.files[0]) {
            document.getElementById('edit-before-preview').src = URL.createObjectURL(e.target.files[0]);
        } else {
            document.getElementById('edit-before-preview').src = '<?php echo htmlspecialchars($obs['initial_image_data_url']); ?>';
        }
    });
    document.getElementById('edit-after-input').addEventListener('change', function(e) {
        if (e.target.files && e.target.files[0]) {
            document.getElementById('edit-after-preview').src = URL.createObjectURL(e.target.files[0]);
        } else {
            document.getElementById('edit-after-preview').src = '<?php echo htmlspecialchars($obs['corrective_photo_data_url']); ?>';
        }
    });
    document.getElementById('edit-observation-form').addEventListener('submit', async function(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        formData.append('action', 'update_observation');
        // Prevent multiple submissions
        const submitBtn = form.querySelector('button[type="submit"]') || form.querySelector('input[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Updating...';
        }
        // Validate required fields
        const location = document.getElementById('edit-location').value.trim();
        const assignTo = document.getElementById('edit-assign-to').value.trim();
        if (!location || !assignTo) {
            const msg = document.getElementById('edit-message');
            msg.textContent = 'Location and Assign To are required.';
            msg.className = 'mt-4 text-red-600';
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Save Changes';
            }
            return;
        }
        try {
            const response = await fetch('api.php', {
                method: 'POST',
                body: formData,
                credentials: 'include'
            });
            const result = await response.json();
            const msg = document.getElementById('edit-message');
            if (result.success) {
                msg.textContent = 'Observation updated successfully!';
                msg.className = 'mt-4 text-green-600';
                // Determine where to redirect based on the 'from' parameter
                const from = document.getElementById('edit-from').value;
                setTimeout(() => {
                    if (from === 'index') {
                        window.location.href = 'index.html#report-table-page';
                    } else {
                        window.location.href = 'sor_report.php';
                    }
                }, 1200);
            } else {
                msg.textContent = result.message || 'Failed to update observation.';
                msg.className = 'mt-4 text-red-600';
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Save Changes';
                }
            }
        } catch (error) {
            document.getElementById('edit-message').textContent = 'Error updating observation.';
            document.getElementById('edit-message').className = 'mt-4 text-red-600';
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Save Changes';
            }
        }
    });
    </script>
</body>
</html> 