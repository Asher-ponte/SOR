<?php
// api.php
// Handles all API requests from the frontend (both main app and admin dashboard)

// 1. Error Reporting and Output Buffering:
// Prevent PHP errors from being displayed directly in the output, which can break JSON responses.
// Errors will still be logged to the server's error log.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 2. Session Configuration:
// Set session cookie parameters before starting the session
ini_set('session.cookie_httponly', 1); // Prevent JavaScript access to session cookie
ini_set('session.use_only_cookies', 1); // Forces sessions to only use cookies
ini_set('session.cookie_samesite', 'Strict'); // Protects against CSRF
ini_set('session.gc_maxlifetime', 3600); // Session timeout after 1 hour
ini_set('session.cookie_lifetime', 3600); // Cookie lifetime 1 hour

// Start output buffering to capture any unintended output before sending JSON.
ob_start();

include 'db_config.php'; // Include the database connection file

// Start session to manage user login state
// Ensure this is at the very top before any output
session_start();

// Set CORS headers to allow same-origin requests
header('Access-Control-Allow-Credentials: true');
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*';
header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json'); // Set response header to JSON

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Define the upload directory
$uploadDir = 'uploads/';

// Create the uploads directory if it doesn't exist
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true); // Create recursively and set permissions
}

// --- DEBUGGING: Log incoming request data ---
error_log("--- API Request Start ---");
error_log("Method: " . $_SERVER['REQUEST_METHOD']);
error_log("GET parameters: " . print_r($_GET, true));
error_log("POST parameters: " . print_r($_POST, true));
error_log("Files: " . print_r($_FILES, true));
// --- END DEBUGGING ---

// Get the requested action from GET or POST parameters
// Prioritize POST, then GET. This is crucial for FormData.
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// --- DEBUGGING: Log determined action ---
error_log("Determined action: " . ($action ?: 'NULL/Empty'));
// --- END DEBUGGING ---

// Function to validate session status
function validateSession() {
    // Check if session exists and is valid
    if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
        return false;
    }

    // Check if session has expired (optional additional check)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
        session_unset();
        session_destroy();
        return false;
    }

    // Update last activity time
    $_SESSION['last_activity'] = time();
    return true;
}

// Handle session check action
function checkSession() {
    $isValid = validateSession();
    
    if ($isValid) {
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'loggedin' => true,
            'username' => $_SESSION['username'] ?? null,
            'message' => 'Session is valid'
        ]);
    } else {
        ob_end_clean();
        echo json_encode([
            'success' => false,
            'loggedin' => false,
            'message' => 'Session is invalid or expired'
        ]);
    }
}

// Use a switch statement to route actions to specific functions
switch ($action) {
    case 'login':
        loginUser($conn);
        break;
    case 'register':
        registerUser($conn);
        break;
    case 'logout':
        logoutUser();
        break;
    case 'check_session':
        checkSession();
        break;
    case 'save_observation':
        saveObservation($conn, $uploadDir);
        break;
    case 'get_observation':
        getObservation($conn);
        break;
    case 'get_observations':
        getObservations($conn);
        break;
    case 'update_observation':
        updateObservation($conn);
        break;
    case 'delete_observation':
        deleteObservation($conn);
        break;
    case 'get_dashboard_data':
        getDashboardData($conn);
        break;
    case 'save_comment':
        saveComment($conn);
        break;
    case 'get_comments':
        getComments($conn);
        break;
    case 'update_observation_status':
        updateObservationStatus($conn, $uploadDir);
        break;
    case 'delete_bbs_checklist':
        deleteBbsChecklist($conn);
        break;
    case 'get_sor_compliance_tracker':
        getSORComplianceTracker($conn);
        break;
    case 'edit_user':
        editUser($conn);
        break;
    case 'delete_user':
        deleteUser($conn);
        break;
    case 'add_user':
        addUser($conn);
        break;
    case 'delete_observer':
        if (!isset($_POST['observer'])) {
            echo json_encode(['success' => false, 'message' => 'Observer is required']);
            exit;
        }
        $observer = $_POST['observer'];
        
        // Start transaction
        $conn->begin_transaction();
        try {
            // Delete observer's checklist answers first
            $stmt = $conn->prepare("DELETE a FROM bbs_checklist_answers a 
                                  JOIN bbs_checklists c ON a.checklist_id = c.id 
                                  WHERE c.observer = ?");
            $stmt->bind_param('s', $observer);
            $stmt->execute();
            $stmt->close();

            // Then delete observer's checklists
            $stmt = $conn->prepare("DELETE FROM bbs_checklists WHERE observer = ?");
            $stmt->bind_param('s', $observer);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'edit_observer':
        if (!isset($_POST['observer']) || !isset($_POST['new_observer'])) {
            echo json_encode(['success' => false, 'message' => 'Observer and new observer name are required']);
            exit;
        }
        $observer = $_POST['observer'];
        $new_observer = $_POST['new_observer'];
        
        // Check if new observer name already exists
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM bbs_checklists WHERE observer = ? AND observer != ?");
        $stmt->bind_param('ss', $new_observer, $observer);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        if ($result['cnt'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Observer name already exists']);
            exit;
        }
        $stmt->close();

        // Update observer name
        $stmt = $conn->prepare("UPDATE bbs_checklists SET observer = ? WHERE observer = ?");
        $stmt->bind_param('ss', $new_observer, $observer);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update observer']);
        }
        $stmt->close();
        break;

    case 'add_observer':
        if (!isset($_POST['observer'])) {
            echo json_encode(['success' => false, 'message' => 'Observer name is required']);
            exit;
        }
        $observer = $_POST['observer'];
        
        // Check if observer name already exists
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM bbs_checklists WHERE observer = ?");
        $stmt->bind_param('s', $observer);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        if ($result['cnt'] > 0) {
            echo json_encode(['success' => false, 'message' => 'Observer name already exists']);
            exit;
        }
        $stmt->close();

        // Since observers are only stored in bbs_checklists, we'll consider them added
        // when they submit their first checklist. For now, just return success.
        echo json_encode(['success' => true]);
        break;

    case 'get_filter_options':
        $locations = [];
        $categories = [];
        $assignees = [];
        $descriptions = [];
        $result = $conn->query("SELECT DISTINCT location FROM observations WHERE location IS NOT NULL AND location != ''");
        while ($row = $result->fetch_assoc()) $locations[] = $row['location'];
        $result = $conn->query("SELECT DISTINCT category FROM observations WHERE category IS NOT NULL AND category != ''");
        while ($row = $result->fetch_assoc()) $categories[] = $row['category'];
        $result = $conn->query("SELECT DISTINCT assign_to FROM observations WHERE assign_to IS NOT NULL AND assign_to != ''");
        while ($row = $result->fetch_assoc()) $assignees[] = $row['assign_to'];
        $result = $conn->query("SELECT DISTINCT description FROM observations WHERE description IS NOT NULL AND description != ''");
        while ($row = $result->fetch_assoc()) $descriptions[] = $row['description'];
        ob_end_clean();
        echo json_encode([
            'success' => true,
            'locations' => $locations,
            'categories' => $categories,
            'assignees' => $assignees,
            'descriptions' => $descriptions
        ]);
        exit;

    default:
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

// --- Helper function for photo uploads ---
/**
 * Handles photo upload for both initial inspection and corrective action photos
 * @param string $fileInputName The form field name ('initial_image' or 'corrective_image')
 * @param string $uploadDir Directory where photos will be stored
 * @return string|null Returns the relative path to the saved file or null if upload fails
 */
function uploadImage($fileInputName, $uploadDir) {
    if (isset($_FILES[$fileInputName]) && $_FILES[$fileInputName]['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES[$fileInputName]['tmp_name'];
        $fileName = basename($_FILES[$fileInputName]['name']);
        $fileSize = $_FILES[$fileInputName]['size'];
        $fileType = $_FILES[$fileInputName]['type'];

        // Sanitize filename and make it unique
        $fileName = preg_replace("/[^a-zA-Z0-9.\-_]/", "", $fileName);
        $newFileName = uniqid() . '_' . $fileName;
        $destPath = $uploadDir . $newFileName;

        if (move_uploaded_file($fileTmpPath, $destPath)) {
            return $destPath;
        } else {
            error_log("Error moving uploaded file from $fileTmpPath to $destPath");
            return null;
        }
    } else if (isset($_FILES[$fileInputName])) {
        error_log("File upload error for $fileInputName: " . $_FILES[$fileInputName]['error']);
    }
    return null;
}


// --- API Action Functions ---

// Handles user registration
function registerUser($conn) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Username and password are required for registration.']);
        return;
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
    $stmt->bind_param("ss", $username, $hashed_password);

    if ($stmt->execute()) {
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Registration successful! You can now log in.']);
    } else {
        if ($conn->errno == 1062) {
             ob_end_clean();
             echo json_encode(['success' => false, 'message' => 'Registration failed. Username already exists.']);
        } else {
            error_log("Error during user registration: " . $stmt->error);
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
        }
    }
    $stmt->close();
}

// Handles user login
function loginUser($conn) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Username and password are required for login.']);
        return;
    }

    $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['loggedin'] = true;
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_id'] = $user['id'];

            ob_end_clean();
            echo json_encode(['success' => true, 'message' => 'Login successful!', 'username' => $user['username']]);
        } else {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Incorrect password.']);
        }
    } else {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'User not found.']);
    }
    $stmt->close();
}

// Handles saving a new safety observation report
/**
 * Saves a new observation with initial inspection photo
 * Photo handling:
 * - Accepts 'initial_image' in form data
 * - Stores photo path in 'initial_image_data_url'
 * - Stores original filename in 'initial_image_filename'
 */
function saveObservation($conn, $uploadDir) {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login to submit observations.']);
        return;
    }

    $generated_by = $_SESSION['username'] ?? 'Anonymous';
    $timestamp = $_POST['timestamp'] ?? '';
    $category = $_POST['category'] ?? '';
    $observation_type = $_POST['observation_type'] ?? '';
    $description = $_POST['description'] ?? '';
    $corrective_actions = $_POST['corrective_actions'] ?? '';
    $preventive_actions = $_POST['preventive_actions'] ?? '';
    $location = $_POST['location'] ?? '';
    $assign_to = $_POST['assign_to'] ?? '';
    $due_date = $_POST['due_date'] ?? null;
    $status = $_POST['status'] ?? 'Open';

    // Handle initial image upload
    $initial_image_path = uploadImage('initial_image', $uploadDir);
    $initial_image_filename = $_FILES['initial_image']['name'] ?? null; // Get original filename if uploaded

    if (empty($timestamp) || empty($category) || empty($description)) {
         ob_end_clean();
         echo json_encode(['success' => false, 'message' => 'Timestamp, Category, and Description are required fields.']);
         return;
    }

    $stmt = $conn->prepare("INSERT INTO observations (timestamp, initial_image_data_url, initial_image_filename, category, observation_type, description, corrective_actions, preventive_actions, location, assign_to, due_date, status, generated_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("sssssssssssss",
        $timestamp,
        $initial_image_path, // Store the path
        $initial_image_filename,
        $category,
        $observation_type,
        $description,
        $corrective_actions,
        $preventive_actions,
        $location,
        $assign_to,
        $due_date,
        $status,
        $generated_by
    );

    if ($stmt->execute()) {
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Observation saved successfully.']);
    } else {
        error_log("Error saving observation: " . $stmt->error . " | SQLSTATE: " . $stmt->sqlstate);
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Failed to save observation. Please try again.']);
    }
    $stmt->close();
}

// Handles fetching all observations (used for the report table and potentially admin view)
/**
 * Retrieves observations with both initial and corrective photos
 * Returns:
 * - initial_image_data_url: Path to the "Before" photo
 * - initial_image_filename: Original filename of "Before" photo
 * - corrective_photo_data_url: Path to the "After" photo
 * - corrective_photo_filename: Original filename of "After" photo
 */
function getObservations($conn) {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login to view observations.']);
        return;
    }

    // Initialize filter and pagination variables
    $category = $_POST['category'] ?? $_GET['category'] ?? '';
    $observation_type = $_POST['observation_type'] ?? $_GET['observation_type'] ?? '';
    $location = $_POST['location'] ?? $_GET['location'] ?? '';
    $status = $_POST['status'] ?? $_GET['status'] ?? '';
    $start_date = $_POST['start_date'] ?? $_GET['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? $_GET['end_date'] ?? '';
    $assign_to = $_POST['assign_to'] ?? $_GET['assign_to'] ?? '';
    
    // Pagination parameters (use frontend values if provided)
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : (isset($_POST['limit']) ? intval($_POST['limit']) : 16);
    $offset = isset($_GET['offset']) ? intval($_GET['offset']) : (isset($_POST['offset']) ? intval($_POST['offset']) : 0);
    $page = ($limit > 0) ? (floor($offset / $limit) + 1) : 1;

    // Build the WHERE clause for filters
    $where_clause = "WHERE 1=1";
    $params = [];
    $types = "";

    if (!empty($category)) {
        $where_clause .= " AND category = ?";
        $params[] = $category;
        $types .= "s";
    }
    if (!empty($observation_type)) {
        $where_clause .= " AND observation_type = ?";
        $params[] = $observation_type;
        $types .= "s";
    }
    if (!empty($location)) {
        $where_clause .= " AND location = ?";
        $params[] = $location;
        $types .= "s";
    }
    if (!empty($status)) {
        $where_clause .= " AND status = ?";
        $params[] = $status;
        $types .= "s";
    }
    if (!empty($assign_to)) {
        $where_clause .= " AND assign_to = ?";
        $params[] = $assign_to;
        $types .= "s";
    }
    if (!empty($start_date)) {
        $where_clause .= " AND timestamp >= ?";
        $params[] = $start_date . " 00:00:00";
        $types .= "s";
    }
    if (!empty($end_date)) {
        $where_clause .= " AND timestamp <= ?";
        $params[] = $end_date . " 23:59:59";
        $types .= "s";
    }

    // Get summary statistics
    $summary = [];
    
    // Total observations
    $total_sql = "SELECT COUNT(*) as total FROM observations " . $where_clause;
    $total_stmt = $conn->prepare($total_sql);
    if (!empty($params)) {
        $total_stmt->bind_param($types, ...$params);
    }
    $total_stmt->execute();
    $summary['total'] = $total_stmt->get_result()->fetch_assoc()['total'] ?? 0;
    $total_stmt->close();

    // Open observations
    $open_sql = "SELECT COUNT(*) as open FROM observations " . $where_clause . " AND status = 'Open'";
    $open_stmt = $conn->prepare($open_sql);
    if (!empty($params)) {
        $open_stmt->bind_param($types, ...$params);
    }
    $open_stmt->execute();
    $summary['open'] = $open_stmt->get_result()->fetch_assoc()['open'] ?? 0;
    $open_stmt->close();

    // Closed observations
    $closed_sql = "SELECT COUNT(*) as closed FROM observations " . $where_clause . " AND status = 'Closed'";
    $closed_stmt = $conn->prepare($closed_sql);
    if (!empty($params)) {
        $closed_stmt->bind_param($types, ...$params);
    }
    $closed_stmt->execute();
    $summary['closed'] = $closed_stmt->get_result()->fetch_assoc()['closed'] ?? 0;
    $closed_stmt->close();

    // Calculate closure rate
    $summary['closure_rate'] = ($summary['total'] > 0) ? round(($summary['closed'] / $summary['total']) * 100, 2) : 0;

    // Add back unsafe acts and conditions counts
    $unsafe_acts_sql = "SELECT COUNT(*) as unsafe_acts FROM observations " . $where_clause . " AND observation_type = 'Unsafe Act'";
    $unsafe_acts_stmt = $conn->prepare($unsafe_acts_sql);
    if (!empty($params)) {
        $unsafe_acts_stmt->bind_param($types, ...$params);
    }
    $unsafe_acts_stmt->execute();
    $summary['unsafe_acts'] = $unsafe_acts_stmt->get_result()->fetch_assoc()['unsafe_acts'] ?? 0;
    $unsafe_acts_stmt->close();

    // Unsafe Conditions count
    $unsafe_conditions_sql = "SELECT COUNT(*) as unsafe_conditions FROM observations " . $where_clause . " AND observation_type = 'Unsafe Condition'";
    $unsafe_conditions_stmt = $conn->prepare($unsafe_conditions_sql);
    if (!empty($params)) {
        $unsafe_conditions_stmt->bind_param($types, ...$params);
    }
    $unsafe_conditions_stmt->execute();
    $summary['unsafe_conditions'] = $unsafe_conditions_stmt->get_result()->fetch_assoc()['unsafe_conditions'] ?? 0;
    $unsafe_conditions_stmt->close();

    // Get paginated observations
    $sql = "SELECT id, timestamp, initial_image_data_url, initial_image_filename, category, observation_type, 
            description, corrective_actions, preventive_actions, location, assign_to, due_date, status, 
            generated_by, corrective_photo_data_url, corrective_photo_filename 
            FROM observations " . $where_clause . " 
            ORDER BY timestamp DESC 
            LIMIT ? OFFSET ?";

    // Add limit and offset to params
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        $observations = [];
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                // Only get comment count for visible observations
                $row['comment_count'] = (int)($conn->query("SELECT COUNT(*) FROM comments WHERE observation_id = " . (int)$row['id'])->fetch_row()[0]);
                $observations[] = $row;
            }
        }
        $stmt->close();

        ob_end_clean();
        echo json_encode([
            'success' => true,
            'summary' => $summary,
            'observations' => $observations,
            'pagination' => [
                'total' => $summary['total'],
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($summary['total'] / $limit)
            ]
        ]);
    } else {
        error_log("Error preparing statement for getObservations: " . $conn->error);
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Database error preparing statement.']);
    }
}

// Handles updating the status of an observation and adding a corrective photo
/**
 * Updates an observation with corrective action photo
 * Photo handling:
 * - Accepts 'corrective_image' in form data
 * - Stores photo path in 'corrective_photo_data_url'
 * - Stores original filename in 'corrective_photo_filename'
 */
function updateObservationStatus($conn, $uploadDir) {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login to update observations.']);
        return;
    }

    // --- DEBUGGING: Log POST data specifically for updateObservationStatus ---
    error_log("Inside updateObservationStatus function. Received POST data: " . print_r($_POST, true));
    error_log("Received FILES data: " . print_r($_FILES, true));
    // --- END DEBUGGING ---

    $id = $_POST['id'] ?? '';
    $status = $_POST['status'] ?? '';

    // Handle corrective image upload
    $corrective_photo_path = uploadImage('corrective_image', $uploadDir);
    $corrective_photo_filename = $_FILES['corrective_image']['name'] ?? null; // Get original filename if uploaded

    if (empty($id) || empty($status)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Observation ID and status are required for update.']);
        return;
    }

    $stmt = $conn->prepare("UPDATE observations SET status = ?, corrective_photo_data_url = ?, corrective_photo_filename = ? WHERE id = ?");
    $stmt->bind_param("sssi", $status, $corrective_photo_path, $corrective_photo_filename, $id);

    if ($stmt->execute()) {
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Observation updated successfully.']);
    } else {
        error_log("Error updating observation status: " . $stmt->error);
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Failed to update observation. Please try again.']);
    }
    $stmt->close();
}

// Handles updating an observation from the admin dashboard (full edit)
function updateObservation($conn) {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login to update observations.']);
        return;
    }

    $id = $_POST['id'] ?? '';
    $timestamp = $_POST['timestamp'] ?? '';
    $category = $_POST['category'] ?? '';
    $observation_type = $_POST['observation_type'] ?? '';
    $description = $_POST['description'] ?? '';
    $corrective_actions = $_POST['corrective_actions'] ?? '';
    $preventive_actions = $_POST['preventive_actions'] ?? '';
    $location = $_POST['location'] ?? '';
    $assign_to = $_POST['assign_to'] ?? '';
    $due_date = $_POST['due_date'] ?? null;
    $status = $_POST['status'] ?? 'Open';

    // Fetch current observation for old image paths
    $stmt = $conn->prepare("SELECT initial_image_data_url, corrective_photo_data_url FROM observations WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current = $result->fetch_assoc();
    $stmt->close();

    // Handle new image uploads
    global $uploadDir;
    $initial_image_path = $current['initial_image_data_url'];
    $corrective_photo_path = $current['corrective_photo_data_url'];
    if (isset($_FILES['initial_image']) && $_FILES['initial_image']['error'] === UPLOAD_ERR_OK) {
        if ($initial_image_path && file_exists($initial_image_path)) unlink($initial_image_path);
        $initial_image_path = uploadImage('initial_image', $uploadDir);
    }
    if (isset($_FILES['corrective_image']) && $_FILES['corrective_image']['error'] === UPLOAD_ERR_OK) {
        if ($corrective_photo_path && file_exists($corrective_photo_path)) unlink($corrective_photo_path);
        $corrective_photo_path = uploadImage('corrective_image', $uploadDir);
    }

    $sql = "UPDATE observations SET timestamp = ?, category = ?, observation_type = ?, description = ?, corrective_actions = ?, preventive_actions = ?, location = ?, assign_to = ?, due_date = ?, status = ?, initial_image_data_url = ?, corrective_photo_data_url = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssssssssi",
        $timestamp,
        $category,
        $observation_type,
        $description,
        $corrective_actions,
        $preventive_actions,
        $location,
        $assign_to,
        $due_date,
        $status,
        $initial_image_path,
        $corrective_photo_path,
        $id
    );
    if ($stmt->execute()) {
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Observation updated successfully.', 'initial_image_data_url' => $initial_image_path, 'corrective_photo_data_url' => $corrective_photo_path]);
    } else {
        error_log("Error updating observation: " . $stmt->error);
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Failed to update observation.']);
    }
    $stmt->close();
}

// Handles deleting an observation from the admin dashboard
/**
 * Deletes an observation and its associated photos
 * - Removes both initial and corrective photos from the uploads directory
 * - Deletes the database record
 */
function deleteObservation($conn) {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login to delete observations.']);
        return;
    }

    $id = $_POST['id'] ?? '';

    if (empty($id)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Observation ID is required for deletion.']);
        return;
    }

    try {
        // Start transaction
        $conn->begin_transaction();

        // Get file paths before deletion
        $stmt = $conn->prepare("SELECT initial_image_data_url, corrective_photo_data_url FROM observations WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Failed to prepare statement for getting file paths");
        }
        
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute statement for getting file paths");
        }
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        // Delete associated comments first (due to foreign key constraint)
        $stmt = $conn->prepare("DELETE FROM comments WHERE observation_id = ?");
        if (!$stmt) {
            throw new Exception("Failed to prepare statement for deleting comments");
        }
        
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to delete associated comments");
        }
        $stmt->close();

        // Delete the observation record
        $stmt = $conn->prepare("DELETE FROM observations WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Failed to prepare statement for deleting observation");
        }
        
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to delete observation record");
        }
        $stmt->close();

        // Delete associated files if they exist
        if ($row) {
            if ($row['initial_image_data_url'] && file_exists($row['initial_image_data_url'])) {
                unlink($row['initial_image_data_url']);
            }
            if ($row['corrective_photo_data_url'] && file_exists($row['corrective_photo_data_url'])) {
                unlink($row['corrective_photo_data_url']);
            }
        }

        // Commit transaction
        $conn->commit();

        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Observation deleted successfully.']);
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        error_log("Error deleting observation: " . $e->getMessage());
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Failed to delete observation: ' . $e->getMessage()]);
    }
}


// Handles fetching data specifically for the admin dashboard charts and stats
function getDashboardData($conn) {
     if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
         ob_end_clean();
         echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login to view the dashboard.']);
         return;
     }

     $data = [];

     // 1. Total observations count
     $total_query = $conn->query("SELECT COUNT(*) as total FROM observations");
     $data['total'] = $total_query->fetch_assoc()['total'] ?? 0;

     // 2. Open observations count
     $open_query = $conn->query("SELECT COUNT(*) as open FROM observations WHERE status = 'Open'");
     $data['open'] = $open_query->fetch_assoc()['open'] ?? 0;

     // 3. Closed observations count
     $closed_query = $conn->query("SELECT COUNT(*) as closed FROM observations WHERE status = 'Closed'");
     $data['closed'] = $closed_query->fetch_assoc()['closed'] ?? 0;

     // Calculate Total Closure Rate
     $data['total_closure_rate'] = ($data['total'] > 0) ? round(($data['closed'] / $data['total']) * 100, 2) : 0;

     // Calculate On-time Closure Rate
     $ontime_closed_query = $conn->query("SELECT COUNT(*) as ontime_closed FROM observations WHERE status = 'Closed' AND DATE(timestamp) <= due_date");
     $ontime_closed_count = $ontime_closed_query->fetch_assoc()['ontime_closed'] ?? 0;
     $data['ontime_closure_rate'] = ($data['closed'] > 0) ? round(($ontime_closed_count / $data['closed']) * 100, 2) : 0;


     // 4. Observations by Category
     $category_query = $conn->query("SELECT category, COUNT(*) as count FROM observations GROUP BY category ORDER BY count DESC");
     $data['categories'] = [];
     while($row = $category_query->fetch_assoc()) {
         $data['categories'][] = $row;
     }

     // 5. Observations by Location (NEW)
     $location_query = $conn->query("SELECT location, COUNT(*) as count FROM observations GROUP BY location ORDER BY count DESC");
     $data['locations'] = [];
     while($row = $location_query->fetch_assoc()) {
         $data['locations'][] = $row;
     }

     // 5b. Open/Closed per Location (for chart)
     $locations_status_query = $conn->query("SELECT location,
         SUM(CASE WHEN status = 'Open' THEN 1 ELSE 0 END) as open,
         SUM(CASE WHEN status = 'Closed' THEN 1 ELSE 0 END) as closed
         FROM observations GROUP BY location ORDER BY location");
     $data['locations_status'] = [];
     while($row = $locations_status_query->fetch_assoc()) {
         $data['locations_status'][] = $row;
     }

     // 6. Observations by User (generated_by) (NEW)
     $username_query = $conn->query("SELECT generated_by, COUNT(*) as count FROM observations GROUP BY generated_by ORDER BY count DESC");
     $data['usernames'] = [];
     while($row = $username_query->fetch_assoc()) {
         $data['usernames'][] = $row;
     }

     // 7. Observations by Type (Unsafe Act vs Unsafe Condition) (NEW)
     $observation_type_query = $conn->query("SELECT observation_type, COUNT(*) as count FROM observations GROUP BY observation_type ORDER BY count DESC");
     $data['observation_types'] = [];
     while($row = $observation_type_query->fetch_assoc()) {
         $data['observation_types'][] = $row;
     }

     ob_end_clean();
     echo json_encode(['success' => true, 'data' => $data]);
}

// Handles user logout
function logoutUser() {
    // Clear all session data
    session_unset();
    session_destroy();
    
    // Clear session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    ob_end_clean();
    echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
}

// Handles saving a new comment
function saveComment($conn) {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login to comment.']);
        return;
    }
    $observation_id = $_POST['observation_id'] ?? '';
    $comment_text = trim($_POST['comment_text'] ?? '');
    $user = $_SESSION['username'] ?? 'Anonymous';
    if (empty($observation_id) || empty($comment_text)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Observation ID and comment text are required.']);
        return;
    }
    $stmt = $conn->prepare("INSERT INTO comments (observation_id, user, comment_text) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $observation_id, $user, $comment_text);
    if ($stmt->execute()) {
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Comment posted.']);
    } else {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Failed to post comment.']);
    }
    $stmt->close();
}

// Handles fetching comments for an observation
function getComments($conn) {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login to view comments.']);
        return;
    }
    $observation_id = $_POST['observation_id'] ?? '';
    if (empty($observation_id)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Observation ID is required.']);
        return;
    }
    $stmt = $conn->prepare("SELECT user, comment_text, created_at FROM comments WHERE observation_id = ? ORDER BY created_at ASC");
    $stmt->bind_param("i", $observation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $comments = [];
    while ($row = $result->fetch_assoc()) {
        $comments[] = $row;
    }
    $stmt->close();
    ob_end_clean();
    echo json_encode(['success' => true, 'comments' => $comments]);
}

// Add this function before the switch statement
function getObservation($conn) {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login to view observation details.']);
        return;
    }

    $id = $_POST['id'] ?? '';

    if (empty($id)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Observation ID is required.']);
        return;
    }

    try {
        $stmt = $conn->prepare("SELECT * FROM observations WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Failed to prepare statement");
        }
        
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute statement");
        }
        
        $result = $stmt->get_result();
        $observation = $result->fetch_assoc();
        $stmt->close();

        if (!$observation) {
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Observation not found.']);
            return;
        }

        ob_end_clean();
        echo json_encode(['success' => true, 'observation' => $observation]);
    } catch (Exception $e) {
        error_log("Error getting observation: " . $e->getMessage());
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Failed to get observation details: ' . $e->getMessage()]);
    }
}

// Add this function after the switch
function deleteBbsChecklist($conn) {
    if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login to delete checklists.']);
        return;
    }
    $id = $_POST['id'] ?? '';
    if (empty($id)) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Checklist ID is required.']);
        return;
    }
    $id = intval($id);
    // Delete answers first (due to FK constraint)
    $conn->query("DELETE FROM bbs_checklist_answers WHERE checklist_id = $id");
    $conn->query("DELETE FROM bbs_checklists WHERE id = $id");
    ob_end_clean();
    echo json_encode(['success' => true, 'message' => 'Checklist deleted.']);
}

// Add SOR Compliance Tracker API
function getSORComplianceTracker($conn) {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login to view SOR Compliance.']);
        return;
    }
    $week_start = $_GET['week_start'] ?? $_POST['week_start'] ?? null;
    if (!$week_start) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Missing week_start parameter.']);
        return;
    }
    
    // Validate and ensure week_start is a Monday
    $start = new DateTime($week_start);
    $day_of_week = $start->format('N'); // 1 (Monday) through 7 (Sunday)
    
    if ($day_of_week !== '1') {
        // Adjust to Monday
        $days_to_subtract = $day_of_week - 1;
        $start->modify("-{$days_to_subtract} days");
        $week_start = $start->format('Y-m-d');
    }
    
    // Generate 6 days: Monday to Saturday
    $days = [];
    for ($i = 0; $i < 6; $i++) {
        $d = clone $start;
        $d->modify("+{$i} days");
        $days[] = $d->format('Y-m-d');
    }
    
    // Get all users
    $users = [];
    $user_result = $conn->query("SELECT id, username FROM users ORDER BY username ASC");
    while ($row = $user_result->fetch_assoc()) {
        $users[] = $row;
    }
    
    // For each user, for each day, count observations
    $compliance = [];
    $total_hits = 0;
    foreach ($users as $user) {
        $hits = 0;
        $user_days = [];
        foreach ($days as $day) {
            $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM observations WHERE generated_by = ? AND DATE(timestamp) = ?");
            $stmt->bind_param("ss", $user['username'], $day);
            $stmt->execute();
            $cnt = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
            $hit = $cnt >= 3;
            if ($hit) $hits++;
            $user_days[] = [ 'date' => $day, 'count' => $cnt, 'hit' => $hit ];
            $stmt->close();
        }
        $percent = round(($hits / 6) * 100);
        $compliance[$user['username']] = [ 'days' => $user_days, 'percent' => $percent ];
        $total_hits += $hits;
    }
    $leadership_compliance = count($users) > 0 ? round(($total_hits / (count($users)*6)) * 100) : 0;
    
    ob_end_clean();
    echo json_encode([
        'success' => true,
        'users' => $users,
        'compliance' => $compliance,
        'leadership_compliance' => $leadership_compliance,
        'week_start' => $week_start,
        'days' => $days
    ]);
}

// Edit user
function editUser($conn) {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
        return;
    }
    $id = $_POST['id'] ?? '';
    $username = $_POST['username'] ?? '';
    if (!$id || !$username) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Missing user id or username.']);
        return;
    }
    $stmt = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
    $stmt->bind_param("si", $username, $id);
    $ok = $stmt->execute();
    $stmt->close();
    ob_end_clean();
    echo json_encode(['success' => $ok, 'message' => $ok ? 'User updated.' : 'Failed to update user.']);
}

// Delete user
function deleteUser($conn) {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
        return;
    }
    $id = $_POST['id'] ?? '';
    if (!$id) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Missing user id.']);
        return;
    }
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $id);
    $ok = $stmt->execute();
    $stmt->close();
    ob_end_clean();
    echo json_encode(['success' => $ok, 'message' => $ok ? 'User deleted.' : 'Failed to delete user.']);
}

// Add user
function addUser($conn) {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
        return;
    }
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '123456';
    if (!$username) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Username is required.']);
        return;
    }
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
    $stmt->bind_param("ss", $username, $hashed_password);
    $ok = $stmt->execute();
    $stmt->close();
    ob_end_clean();
    echo json_encode(['success' => $ok, 'message' => $ok ? 'User added.' : 'Failed to add user. Username may already exist.']);
}

// Close the database connection when the script finishes
$conn->close();
?>
