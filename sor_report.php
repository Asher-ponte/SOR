<?php
session_start();

// Enhanced session validation
function validateAdminSession() {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        header('Location: index.html');
        exit;
    }

    // Check session expiration
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
        session_unset();
        session_destroy();
        header('Location: index.html');
        exit;
    }

    // Update last activity time
    $_SESSION['last_activity'] = time();
}

// Validate session before proceeding
validateAdminSession();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOR Report - Safety Observations</title>
    
    <!-- Core Dependencies -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.23/jspdf.plugin.autotable.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/exceljs/dist/exceljs.min.js"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
        }
        
        .input-field {
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 0.5rem 1rem;
            outline: none;
            transition: all 0.2s;
        }
        
        .input-field:focus {
            border-color: #0ea5e9;
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
        }
        
        .btn-primary {
            background-color: #0ea5e9;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            transition: background-color 0.2s;
        }
        
        .btn-primary:hover {
            background-color: #0284c7;
        }
        
        .btn-secondary {
            background-color: #e5e7eb;
            color: #374151;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            transition: background-color 0.2s;
        }
        
        .btn-secondary:hover {
            background-color: #d1d5db;
        }
        
        .table-container {
            overflow-x: auto;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            max-height: 600px;
            overflow-y: auto;
        }

        .table-container::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .table-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .table-container::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        .table-container::-webkit-scrollbar-thumb:hover {
            background: #666;
        }
    </style>
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

    <div class="min-h-screen bg-gray-50 p-2">
        <!-- Main Content -->
        <main class="flex-1 p-4 overflow-y-auto min-h-screen">
            <!-- Header -->
            <header class="flex items-center justify-between mb-4 bg-gray-50 py-2 px-4 rounded-lg shadow-sm">
                <div>
                    <h1 class="text-xl font-bold text-orange-700">Safety Observations Report</h1>
                    <p class="text-gray-600 text-sm">Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>!</p>
                </div>
                
                <div class="flex items-center space-x-2">
                    <div class="relative">
                        <input type="text" placeholder="Search..." class="input-field pr-10 text-sm">
                        <svg class="w-4 h-4 text-gray-400 absolute right-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                </div>
            </header>

            <!-- Filters and Table -->
            <div class="card mb-8">
                <div class="flex flex-wrap gap-4 mb-6 bg-white p-4">
                    <select id="filter-category" class="input-field">
                        <option value="">All Categories</option>
                        <option value="physical">Physical Hazard</option>
                        <option value="chemical">Chemical Hazard</option>
                        <option value="biological">Biological Hazard</option>
                        <option value="mechanical">Mechanical Hazard</option>
                        <option value="ergonomical">Ergonomical Hazard</option>
                        <option value="electrical">Electrical Hazard</option>
                    </select>
                    
                    <select id="filter-observation-type" class="input-field">
                        <option value="">All Types</option>
                        <option value="Unsafe Condition">Unsafe Condition</option>
                        <option value="Unsafe Act">Unsafe Act</option>
                    </select>
                    
                    <input type="text" id="filter-location" placeholder="Filter by location" class="input-field">
                    
                    <select id="filter-status" class="input-field">
                        <option value="">All Status</option>
                        <option value="Open">Open</option>
                        <option value="Closed">Closed</option>
                    </select>
                    
                    <div class="flex space-x-2">
                        <input type="text" id="date-range" placeholder="Select date range" class="input-field">
                    </div>
                    
                    <select id="filter-generated-by" class="input-field">
                        <option value="">All Users</option>
                    </select>
                    
                    <select id="filter-assign-to" class="input-field">
                        <option value="">All PIC</option>
                    </select>
                    
                    <select id="filter-description" class="filter-select">
                        <option value="">All Descriptions</option>
                    </select>
                    
                    <button onclick="applyFilters()" class="btn-primary">Apply Filters</button>
                    <button onclick="clearFilters()" class="btn-secondary">Clear</button>
                </div>

                <div id="all-observations-list" class="table-container">
                    <!-- Table will be dynamically populated -->
                </div>

                <!-- Pagination and Export Controls -->
                <div class="flex justify-between items-center mt-4 bg-white rounded-lg shadow p-4">
                    <!-- Export Buttons -->
                    <div class="flex items-center space-x-2">
                        <button onclick="generateAdminPDF()" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Export PDF
                        </button>
                        <button onclick="exportAdminCSV()" class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700 transition-colors flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            Export CSV
                        </button>
                        <button onclick="exportAdminXLSX()" class="px-4 py-2 bg-green-700 text-white rounded hover:bg-green-800 transition-colors flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            Export Excel
                        </button>
                    </div>

                    <!-- Pagination Controls -->
                    <div class="flex items-center space-x-4">
                        <select id="rows-per-page" class="form-select rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50" onchange="changeRowsPerPage()">
                            <option value="3">3 per page</option>
                            <option value="5">5 per page</option>
                            <option value="10">10 per page</option>
                            <option value="50">50 per page</option>
                        </select>

                        <div class="flex items-center space-x-2">
                            <button id="prev-page-btn" onclick="prevPage()" class="px-4 py-2 bg-white border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                </svg>
                                Previous
                            </button>
                            <span id="page-info" class="text-sm text-gray-700 bg-gray-100 px-4 py-2 rounded-md"></span>
                            <button id="next-page-btn" onclick="nextPage()" class="px-4 py-2 bg-white border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed flex items-center">
                                Next
                                <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
    // Add these constants at the top of your script
    const API_ENDPOINTS = {
        OBSERVATIONS: 'api.php?action=get_observations',
        UPDATE_OBSERVATION: 'api.php?action=update_observation',
        DELETE_OBSERVATION: 'api.php?action=delete_observation',
        LOGOUT: 'api.php?action=logout',
        FILTER_OPTIONS: 'api.php?action=get_filter_options'
    };

    // Initialize date picker
    flatpickr("#date-range", {
        mode: "range",
        dateFormat: "Y-m-d",
        onChange: function(selectedDates) {
            if (selectedDates.length === 2) {
                document.getElementById('filter-start-date').value = selectedDates[0].toISOString().split('T')[0];
                document.getElementById('filter-end-date').value = selectedDates[1].toISOString().split('T')[0];
            }
        }
    });

    // Set default rowsPerPage to 5
    let currentPage = 1;
    let rowsPerPage = 5;

    // Fetch and populate filter options from the backend
    async function fetchAndPopulateFilterOptions() {
        try {
            const response = await fetch(API_ENDPOINTS.FILTER_OPTIONS, { method: 'POST' });
            const result = await response.json();
            if (result.success) {
                // Populate location filter
                const locationSelect = document.getElementById('filter-location');
                const currentLocation = locationSelect.value;
                locationSelect.innerHTML = '<option value="">All Locations</option>';
                result.locations.forEach(loc => {
                    const option = document.createElement('option');
                    option.value = loc;
                    option.textContent = loc;
                    locationSelect.appendChild(option);
                });
                locationSelect.value = currentLocation;
                // Populate category filter
                const categorySelect = document.getElementById('filter-category');
                const currentCategory = categorySelect.value;
                categorySelect.innerHTML = '<option value="">All Categories</option>';
                result.categories.forEach(cat => {
                    const option = document.createElement('option');
                    option.value = cat;
                    option.textContent = cat;
                    categorySelect.appendChild(option);
                });
                categorySelect.value = currentCategory;
                // Populate assign_to filter if present
                const assignToSelect = document.getElementById('filter-assign-to');
                if (assignToSelect) {
                    const currentAssignee = assignToSelect.value;
                    assignToSelect.innerHTML = '<option value="">All PIC</option>';
                    result.assignees.forEach(assignee => {
                        const option = document.createElement('option');
                        option.value = assignee;
                        option.textContent = assignee;
                        assignToSelect.appendChild(option);
                    });
                    assignToSelect.value = currentAssignee;
                }
                // Populate description filter
                const descriptionSelect = document.getElementById('filter-description');
                const currentDescription = descriptionSelect.value;
                descriptionSelect.innerHTML = '<option value="">All Descriptions</option>' +
                    (result.descriptions || []).map(d => `<option value="${d}">${d}</option>`).join('');
                descriptionSelect.value = currentDescription;
            }
        } catch (error) {
            console.error('Failed to fetch filter options:', error);
        }
    }

    // Initialize with session check
    document.addEventListener('DOMContentLoaded', async () => {
        // Check session status first
        const formData = new FormData();
        formData.append('action', 'check_session');
        
        try {
            const response = await fetch('api.php', {
                method: 'POST',
                body: formData,
                credentials: 'include'  // Important for session cookies
            });
            const result = await response.json();

            if (!result.success || !result.loggedin) {
                window.location.href = 'index.html';
                return;
            }

            // If session is valid, proceed with initialization
            await fetchAndPopulateFilterOptions();
            loadAllObservations();

            // Set initial rows per page from select
            const rowsSelect = document.getElementById('rows-per-page');
            if (rowsSelect) {
                rowsSelect.value = '5'; // Set default to 5 per page
                rowsPerPage = 5;
            }
        } catch (error) {
            console.error('Error checking session:', error);
            window.location.href = 'index.html';
        }
    });

    // Table Functions
    async function loadAllObservations() {
        const filters = getFilterValues();
        const queryParams = new URLSearchParams({
            action: 'get_observations',
            ...filters,
            limit: rowsPerPage,
            offset: (currentPage - 1) * rowsPerPage
        });

        try {
            const response = await fetch(`api.php?${queryParams}`);
            const result = await response.json();

            if (result.success) {
                allObservations = result.observations;
                totalObservationsCount = (result.pagination && result.pagination.total) || (result.summary && result.summary.total) || 0;
                renderObservationsTable();
                updatePaginationControls();
            } else {
                showNotification(result.message, 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('Failed to load observations', 'error');
        }
    }

    function renderObservationsTable() {
        const container = document.getElementById('all-observations-list');
        
        if (allObservations.length === 0) {
            container.innerHTML = '<p class="text-center text-gray-500 py-4">No observations found</p>';
            return;
        }

        const table = `
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Corrective Action</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Before</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">After</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assign To</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Generated By</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    ${allObservations.map(obs => `
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${obs.id}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 w-32">${obs.timestamp}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${obs.category}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 w-32">${obs.observation_type}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${obs.location}</td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <div class="max-w-xs overflow-hidden text-ellipsis">
                                    ${obs.description}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <div class="max-w-xs overflow-hidden text-ellipsis">
                                    ${obs.corrective_actions || 'No corrective action specified'}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                ${obs.initial_image_data_url ? `<div class='cursor-pointer w-12 h-12 relative group' onclick=\"showImagePreview('${obs.initial_image_data_url}', 'Before Inspection')\"><img src='${obs.initial_image_data_url}' alt='Before photo' class='w-full h-full object-cover rounded-lg shadow-sm transition-transform duration-200 group-hover:scale-105'><div class='absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-10 rounded-lg transition-all duration-200'></div></div>` : 'No photo'}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                ${obs.corrective_photo_data_url ? `<div class='cursor-pointer w-12 h-12 relative group' onclick=\"showImagePreview('${obs.corrective_photo_data_url}', 'After Correction')\"><img src='${obs.corrective_photo_data_url}' alt='After photo' class='w-full h-full object-cover rounded-lg shadow-sm transition-transform duration-200 group-hover:scale-105'><div class='absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-10 rounded-lg transition-all duration-200'></div></div>` : 'No photo'}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${obs.assign_to || ''}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${obs.generated_by || ''}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    ${obs.status === 'Open' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800'}">
                                    ${obs.status}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button onclick="window.location.href='edit_observation.php?id=${obs.id}'" class="text-blue-600 hover:text-blue-900 mr-3">Edit</button>
                                <button onclick="deleteObservation(${obs.id})" class="text-red-600 hover:text-red-900">Delete</button>
                            </td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
        
        container.innerHTML = table;
    }

    // Utility Functions
    function getFilterValues() {
        return {
            location: document.getElementById('filter-location').value,
            category: document.getElementById('filter-category').value,
            observation_type: document.getElementById('filter-observation-type').value,
            status: document.getElementById('filter-status').value,
            start_date: document.getElementById('filter-start-date')?.value || '',
            end_date: document.getElementById('filter-end-date')?.value || '',
            generated_by: document.getElementById('filter-generated-by').value || '',
            assign_to: document.getElementById('filter-assign-to').value || '',
            description: document.getElementById('filter-description').value,
        };
    }

    function showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg ${
            type === 'success' ? 'bg-green-500' : 'bg-red-500'
        } text-white`;
        notification.textContent = message;
        document.body.appendChild(notification);

        setTimeout(() => {
            notification.remove();
        }, 3000);
    }

    // Pagination Functions
    function updatePaginationControls() {
        const totalPages = Math.ceil(totalObservationsCount / rowsPerPage);
        document.getElementById('page-info').textContent = `Page ${currentPage} of ${totalPages} (${totalObservationsCount} total)`;
        document.getElementById('prev-page-btn').disabled = currentPage === 1;
        document.getElementById('next-page-btn').disabled = currentPage === totalPages;
        
        // Update rows per page select
        const rowsSelect = document.getElementById('rows-per-page');
        if (rowsSelect) {
            rowsSelect.value = rowsPerPage.toString();
        }
    }

    function changeRowsPerPage() {
        const newRowsPerPage = parseInt(document.getElementById('rows-per-page').value);
        if (newRowsPerPage !== rowsPerPage) {
            rowsPerPage = newRowsPerPage;
            currentPage = 1; // Reset to first page when changing rows per page
            loadAllObservations();
        }
    }

    function prevPage() {
        if (currentPage > 1) {
            currentPage--;
            loadAllObservations();
        }
    }

    function nextPage() {
        const totalPages = Math.ceil(totalObservationsCount / rowsPerPage);
        if (currentPage < totalPages) {
            currentPage++;
            loadAllObservations();
        }
    }

    // Filter Functions
    function applyFilters() {
        currentPage = 1;
        loadAllObservations();
    }

    function clearFilters() {
        document.getElementById('filter-category').value = '';
        document.getElementById('filter-observation-type').value = '';
        document.getElementById('filter-location').value = '';
        document.getElementById('filter-status').value = '';
        document.getElementById('date-range').value = '';
        currentPage = 1;
        loadAllObservations();
    }

    // Export Functions
    async function generateAdminPDF() {
        if (allObservations.length === 0) {
            showNotification('No data to export', 'error');
            return;
        }
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('landscape');
        doc.setFontSize(6);
        doc.text('Safety Observations Report', 14, 15);

        // Set image size and row height
        const imgSize = 30; // px
        const rowHeight = imgSize + 8; // px

        // Preload all images as DataURLs
        const beforeImages = await Promise.all(allObservations.map(obs => toDataUrlOrNull(obs.initial_image_data_url)));
        const afterImages = await Promise.all(allObservations.map(obs => toDataUrlOrNull(obs.corrective_photo_data_url)));

        // Prepare table data
        const tableBody = allObservations.map((obs, idx) => [
            obs.id,
            obs.timestamp,
            obs.category,
            obs.observation_type,
            obs.location,
            obs.description,
            obs.corrective_actions || '',
            '', // Before image placeholder
            '', // After image placeholder
            obs.assign_to || '',
            obs.generated_by || '',
            obs.status
        ]);

        doc.autoTable({
            head: [['ID', 'Date', 'Category', 'Type', 'Location', 'Description', 'Corrective Action', 'Before', 'After', 'Assign To', 'Generated By', 'Status']],
            body: tableBody,
            startY: 25,
            theme: 'grid',
            styles: { fontSize: 6, cellPadding: 3, valign: 'middle', halign: 'center' },
            headStyles: { fillColor: [14, 165, 233], minCellHeight: 12, valign: 'middle', halign: 'center' },
            columnStyles: {
                0: { cellWidth: 15 },   // ID
                1: { cellWidth: 20 },   // Date
                2: { cellWidth: 22 },   // Category
                3: { cellWidth: 22 },   // Type
                4: { cellWidth: 22 },   // Location
                5: { cellWidth: 30 },   // Description
                6: { cellWidth: 30 },   // Corrective Action
                7: { cellWidth: imgSize + 6 }, // Before
                8: { cellWidth: imgSize + 6 }, // After
                9: { cellWidth: 15 },   // Assign To
                10: { cellWidth: 15 },  // Generated By
                11: { cellWidth: 15 }   // Status
            },
            didDrawCell: function (data) {
                // Only draw images in body rows, not header
                if (data.section === 'body') {
                    // Before photo (column 7)
                    if (data.column.index === 7 && beforeImages[data.row.index]) {
                        doc.addImage(
                            beforeImages[data.row.index],
                            'PNG',
                            data.cell.x + (data.cell.width - imgSize) / 2,
                            data.cell.y + (rowHeight - imgSize) / 2,
                            imgSize,
                            imgSize
                        );
                    }
                    // After photo (column 8)
                    if (data.column.index === 8 && afterImages[data.row.index]) {
                        doc.addImage(
                            afterImages[data.row.index],
                            'PNG',
                            data.cell.x + (data.cell.width - imgSize) / 2,
                            data.cell.y + (rowHeight - imgSize) / 2,
                            imgSize,
                            imgSize
                        );
                    }
                }
            },
            willDrawCell: function (data) {
                // Set row height for all body rows
                if (data.section === 'body') {
                    data.row.height = rowHeight;
                }
            }
        });

        doc.save('safety_observations_report.pdf');
        showNotification('PDF exported successfully');
    }

    // Helper: fetch image and convert to DataURL, or return null if not available
    async function toDataUrlOrNull(url) {
        if (!url) return null;
        try {
            const response = await fetch(url, { mode: 'cors' });
            const blob = await response.blob();
            return await new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onloadend = () => resolve(reader.result);
                reader.onerror = reject;
                reader.readAsDataURL(blob);
            });
        } catch (e) {
            return null;
        }
    }

    function exportAdminCSV() {
        if (allObservations.length === 0) {
            showNotification('No data to export', 'error');
            return;
        }
        const headers = ['ID', 'Date', 'Category', 'Type', 'Location', 'Description', 'Corrective Action', 'Before Photo URL', 'After Photo URL', 'Assign To', 'Generated By', 'Status'];
        const csvContent = [
            headers.join(','),
            ...allObservations.map(obs => [
                obs.id,
                obs.timestamp,
                obs.category,
                obs.observation_type,
                obs.location,
                `"${obs.description.replace(/"/g, '""')}"`,
                obs.corrective_actions || '',
                obs.initial_image_data_url || '',
                obs.corrective_photo_data_url || '',
                obs.assign_to || '',
                obs.generated_by || '',
                obs.status
            ].join(','))
        ].join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'safety_observations_report.csv';
        a.click();
        window.URL.revokeObjectURL(url);
        showNotification('CSV exported successfully');
    }

    async function exportAdminXLSX() {
        if (allObservations.length === 0) {
            showNotification('No data to export', 'error');
            return;
        }
        const workbook = new ExcelJS.Workbook();
        const sheet = workbook.addWorksheet('Safety Observations');
        // Add header
        sheet.addRow(['ID', 'Date', 'Category', 'Type', 'Location', 'Description', 'Corrective Action', 'Before', 'After', 'Assign To', 'Generated By', 'Status']);
        // Add data rows and images
        for (const obs of allObservations) {
            const row = sheet.addRow([
                obs.id,
                obs.timestamp,
                obs.category,
                obs.observation_type,
                obs.location,
                obs.description,
                obs.corrective_actions || '',
                '', // Before image
                '', // After image
                obs.assign_to || '',
                obs.generated_by || '',
                obs.status
            ]);
            // Add Before image
            if (obs.initial_image_data_url) {
                const beforeImg = await fetchImageAsBuffer(obs.initial_image_data_url);
                const beforeId = workbook.addImage({
                    buffer: beforeImg,
                    extension: 'png'
                });
                sheet.addImage(beforeId, {
                    tl: { col: 7, row: row.number - 1 },
                    ext: { width: 40, height: 40 }
                });
            }
            // Add After image
            if (obs.corrective_photo_data_url) {
                const afterImg = await fetchImageAsBuffer(obs.corrective_photo_data_url);
                const afterId = workbook.addImage({
                    buffer: afterImg,
                    extension: 'png'
                });
                sheet.addImage(afterId, {
                    tl: { col: 8, row: row.number - 1 },
                    ext: { width: 40, height: 40 }
                });
            }
        }
        // Download
        const buffer = await workbook.xlsx.writeBuffer();
        const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'safety_observations_report.xlsx';
        a.click();
        window.URL.revokeObjectURL(url);
        showNotification('Excel exported successfully');
    }

    // Helper to fetch image as ArrayBuffer for Excel
    async function fetchImageAsBuffer(url) {
        const res = await fetch(url);
        return await res.arrayBuffer();
    }

    // Add image preview modal
    function showImagePreview(imageUrl, title = 'Observation Photo') {
        const modal = document.createElement('div');
        modal.className = 'fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50';
        modal.innerHTML = `
            <div class="relative max-w-4xl mx-4 animate-fadeIn">
                <div class="bg-white rounded-t-lg p-4 flex justify-between items-center">
                    <h3 class="text-lg font-medium text-gray-900">${title}</h3>
                    <button onclick="this.closest('.fixed').remove()" class="text-gray-500 hover:text-gray-700 focus:outline-none">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="relative bg-gray-900 flex items-center justify-center">
                    <img src="${imageUrl}" alt="${title}" class="max-w-full max-h-[70vh] object-contain">
                </div>
                <div class="bg-white rounded-b-lg p-4 text-right">
                    <button onclick="this.closest('.fixed').remove()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                        Close
                    </button>
                </div>
            </div>
        `;

        // Add click outside to close
        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.remove();
        });

        // Add escape key to close
        const handleEscape = (e) => {
            if (e.key === 'Escape') {
                modal.remove();
                document.removeEventListener('keydown', handleEscape);
            }
        };
        document.addEventListener('keydown', handleEscape);

        document.body.appendChild(modal);
    }

    function goToMainApp() {
        window.location.href = 'index.html';
    }

    // Add JavaScript functions for edit and delete
    async function deleteObservation(id) {
        if (!confirm('Are you sure you want to delete this observation?')) {
            return;
        }

        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=delete_observation&id=' + id
            });
            
            const result = await response.json();
            
            if (result.success) {
                alert('Observation deleted successfully');
                location.reload();
            } else {
                alert('Failed to delete observation: ' + result.message);
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred while deleting the observation');
        }
    }
    </script>
</body>
</html> 