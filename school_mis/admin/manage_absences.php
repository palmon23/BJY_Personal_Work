<?php
session_start();
include '../db.php';

if(!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    die("Access denied");
}

// Handle logout
if(isset($_GET['logout'])) {
    session_destroy();
    header("Location: ../index.php");
    exit;
}

$error = '';
$success = '';
$absences = [];
$search_query = $_GET['search'] ?? '';

// Handle query parameters for messages
if(isset($_GET['error'])) {
    $error = $_GET['error'];
}
if(isset($_GET['success'])) {
    $success = $_GET['success'];
}

try {
    if($search_query && strlen($search_query) >= 1) {
        // Search for absences by student name or date
        $stmt = $db->prepare("SELECT a.id, a.student_id, a.date, s.name, s.class 
                             FROM absences a 
                             JOIN students s ON a.student_id = s.id 
                             WHERE LOWER(s.name) LIKE LOWER(?) OR a.date LIKE ? 
                             ORDER BY a.date DESC");
        $stmt->execute(['%' . $search_query . '%', '%' . $search_query . '%']);
    } else {
        // Fetch all absences
        $stmt = $db->prepare("SELECT a.id, a.student_id, a.date, s.name, s.class 
                             FROM absences a 
                             JOIN students s ON a.student_id = s.id 
                             ORDER BY a.date DESC");
        $stmt->execute();
    }
    $all_absences = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Filter to only working days (Monday-Friday)
    $absences = [];
    foreach($all_absences as $absence) {
        $dayOfWeek = date('N', strtotime($absence['date'])); // 1=Monday, 7=Sunday
        if($dayOfWeek >= 1 && $dayOfWeek <= 5) { // Monday to Friday
            $absences[] = $absence;
        }
    }
} catch(Exception $e) {
    $error = 'Error fetching absences';
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Manage Absences</title>
  <link rel="stylesheet" href="../css/dashboard.css">
  <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Arial, sans-serif;
        background: #f5f5f5;
    }

    header {
        background: linear-gradient(135deg, #7f5af0 0%, #00e7ff 100%);
        color: white;
        padding: 30px 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    .header-content {
        max-width: 1200px;
        margin: 0 auto;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .header-content h1 {
        font-size: 28px;
        font-weight: 700;
    }

    nav {
        background: white;
        padding: 15px 20px;
        border-bottom: 1px solid #eee;
        display: flex;
        gap: 20px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }

    nav a {
        color: #7f5af0;
        text-decoration: none;
        font-weight: 600;
        transition: color 0.3s;
        font-size: 14px;
    }

    nav a:hover {
        color: #00e7ff;
    }

    .container {
        max-width: 1200px;
        margin: 30px auto;
        padding: 0 20px;
    }

    .search-section {
        background: white;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        margin-bottom: 30px;
    }

    .search-section h2 {
        font-size: 20px;
        color: #333;
        margin-bottom: 20px;
    }

    .search-form {
        display: flex;
        gap: 10px;
    }

    .search-form input {
        flex: 1;
        padding: 12px 15px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
    }

    .search-form button {
        padding: 12px 30px;
        background: linear-gradient(135deg, #7f5af0 0%, #00e7ff 100%);
        color: white;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
    }

    .card {
        background: white;
        padding: 25px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        margin-bottom: 20px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }

    table thead tr {
        background: linear-gradient(135deg, #7f5af0 0%, #5f3db0 100%);
        color: white;
    }

    table thead th {
        padding: 12px;
        text-align: left;
        font-weight: 600;
        font-size: 14px;
    }

    table tbody tr {
        border-bottom: 1px solid #e0e0e0;
        transition: background-color 0.3s ease;
    }

    table tbody tr:hover {
        background-color: #f9f9f9;
    }

    table tbody td {
        padding: 12px;
        color: #333;
        font-size: 14px;
    }

    .action-btns {
        display: flex;
        gap: 8px;
    }

    .btn-edit, .btn-delete {
        padding: 6px 12px;
        border: none;
        border-radius: 5px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
    }

    .btn-edit {
        background: #007bff;
        color: white;
    }

    .btn-edit:hover {
        background: #0056b3;
    }

    .btn-delete {
        background: #dc3545;
        color: white;
    }

    .btn-delete:hover {
        background: #c82333;
    }

    .error-box {
        background-color: #f8d7da;
        border-left: 4px solid #dc3545;
        color: #721c24;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }

    .success-box {
        background-color: #d4edda;
        border-left: 4px solid #28a745;
        color: #155724;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }

    .no-results {
        text-align: center;
        padding: 30px;
        color: #999;
    }

    .add-btn {
        padding: 10px 20px;
        background: linear-gradient(135deg, #7f5af0 0%, #00e7ff 100%);
        color: white;
        text-decoration: none;
        border-radius: 5px;
        font-weight: 600;
        display: inline-block;
        margin-bottom: 15px;
    }

    .add-btn:hover {
        box-shadow: 0 4px 12px rgba(127, 90, 240, 0.3);
    }

    .back-btn {
        display: inline-block;
        padding: 10px 20px;
        background: #f0f0f0;
        color: #7f5af0;
        text-decoration: none;
        border-radius: 5px;
        font-weight: 600;
        transition: all 0.3s;
    }

    .back-btn:hover {
        background: #e0e0e0;
    }
  </style>
</head>
<body>
  <header>
    <div class="header-content">
      <h1>🛑 Manage Student Absences</h1>
      <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
    </div>
  </header>
  
  <nav>
    <a href="admin.php">Dashboard</a>
    <a href="manage_students.php">Manage Students</a>
    <a href="manage_teachers.php">Manage Teachers</a>
    <a href="manage_results.php">Manage Results</a>
    <a href="manage_absences.php">Manage Absences</a>
    <a href="../change_password.php">Change Password</a>
    <a href="?logout=1">Logout</a>
  </nav>

  <div class="container">
    <?php if($error): ?>
      <div class="error-box">⚠️ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if($success): ?>
      <div class="success-box">✅ <?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="search-section">
      <h2>🔍 Search Absences</h2>
      <form method="GET" class="search-form">
        <input type="text" name="search" placeholder="Search by student name or date (YYYY-MM-DD)..." value="<?php echo htmlspecialchars($search_query); ?>">
        <button type="submit">Search</button>
        <?php if($search_query): ?>
          <a href="manage_absences.php" class="back-btn">Clear</a>
        <?php endif; ?>
      </form>
    </div>

    <div class="card">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3>📋 All Absences</h3>
        <a href="../teacher/add_absence.php" class="add-btn">+ Record New Absence</a>
      </div>

      <?php if(empty($absences)): ?>
        <div class="no-results">
          <p>No absence records found.</p>
        </div>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>Student Name</th>
              <th>Class</th>
              <th>Date</th>
              <th style="text-align: center;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($absences as $absence): 
              $date = date('M d, Y', strtotime($absence['date']));
              $dayName = date('l', strtotime($absence['date'])); // Get day name (Monday, Tuesday, etc.)
            ?>
              <tr>
                <td><?php echo htmlspecialchars($absence['name']); ?></td>
                <td><?php echo htmlspecialchars($absence['class']); ?></td>
                <td><?php echo htmlspecialchars($dayName . ', ' . $date); ?></td>
                <td style="text-align: center;">
                  <div class="action-btns">
                    <a href="edit_absence.php?id=<?php echo urlencode($absence['id']); ?>" class="btn-edit">Edit</a>
                    <a href="delete_absence.php?id=<?php echo urlencode($absence['id']); ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this absence record?');">Delete</a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <script src="../js/dashboard.js"></script>
</body>
</html>
