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
$absence_id = $_GET['id'] ?? null;
$absence = null;
$student = null;

if(!$absence_id) {
    die("Invalid absence record.");
}

try {
    // Fetch the absence record
    $stmt = $db->prepare("SELECT a.id, a.student_id, a.date, s.name, s.class 
                         FROM absences a 
                         JOIN students s ON a.student_id = s.id 
                         WHERE a.id = ?");
    $stmt->execute([$absence_id]);
    $absence = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$absence) {
        die("Absence record not found.");
    }
} catch(Exception $e) {
    die("Error loading absence record.");
}

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $date = trim($_POST['date'] ?? '');
    
    if($date === '') {
        $error = 'Please provide a valid date.';
    } else {
        try {
            $stmt = $db->prepare("UPDATE absences SET date = ? WHERE id = ?");
            $stmt->execute([$date, $absence_id]);
            $success = 'Absence record updated successfully.';
            // Refresh the absence data
            $stmt = $db->prepare("SELECT a.id, a.student_id, a.date, s.name, s.class 
                                FROM absences a 
                                JOIN students s ON a.student_id = s.id 
                                WHERE a.id = ?");
            $stmt->execute([$absence_id]);
            $absence = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(Exception $e) {
            $error = 'Error updating absence record. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Edit Absence</title>
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
        max-width: 600px;
        margin: 40px auto;
        padding: 0 20px;
    }

    .card {
        background: white;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }

    .card h2 {
        color: #333;
        margin-bottom: 10px;
        font-size: 24px;
    }

    .card > p {
        color: #666;
        margin-bottom: 20px;
        font-size: 14px;
    }

    .form-group {
        margin-bottom: 18px;
    }

    label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #333;
        font-size: 14px;
    }

    input[type="date"],
    input[type="text"] {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
        transition: border-color 0.3s;
        background: #f9f9f9;
    }

    input[type="date"]:focus,
    input[type="text"]:focus {
        outline: none;
        border-color: #7f5af0;
        background: white;
        box-shadow: 0 0 5px rgba(127, 90, 240, 0.3);
    }

    input[type="text"][disabled],
    input[type="text"][readonly] {
        background: #f5f5f5;
        color: #666;
        cursor: not-allowed;
    }

    .button-group {
        display: flex;
        gap: 12px;
        margin-top: 25px;
    }

    .btn {
        padding: 12px 24px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        font-size: 14px;
        flex: 1;
    }

    .btn-primary {
        background: linear-gradient(135deg, #7f5af0 0%, #00e7ff 100%);
        color: white;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(127, 90, 240, 0.3);
    }

    .btn-secondary {
        background: #f0f0f0;
        color: #7f5af0;
    }

    .btn-secondary:hover {
        background: #e0e0e0;
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

    .info-box {
        background: #e7f3ff;
        border-left: 4px solid #007bff;
        padding: 12px;
        border-radius: 5px;
        margin-bottom: 15px;
        font-size: 13px;
        color: #004085;
    }
  </style>
</head>
<body>
  <header>
    <div class="header-content">
      <h1>Edit Absence Record</h1>
      <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
    </div>
  </header>
  
  <nav>
    <a href="admin.php">Dashboard</a>
    <a href="manage_absences.php">← Back to Absences</a>
    <a href="?logout=1">Logout</a>
  </nav>

  <div class="container">
    <?php if($error): ?>
      <div class="error-box">⚠️ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if($success): ?>
      <div class="success-box">✅ <?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="card">
      <h2>Edit Absence</h2>
      <p>Update the absence record details below.</p>

      <div class="info-box">
        <strong>Student:</strong> <?php echo htmlspecialchars($absence['name']); ?> 
        <strong style="margin-left: 15px;">Class:</strong> <?php echo htmlspecialchars($absence['class']); ?>
      </div>

      <form method="POST" novalidate>
        <div class="form-group">
          <label for="student_name">Student Name</label>
          <input type="text" id="student_name" value="<?php echo htmlspecialchars($absence['name']); ?>" disabled>
        </div>

        <div class="form-group">
          <label for="class">Class</label>
          <input type="text" id="class" value="<?php echo htmlspecialchars($absence['class']); ?>" disabled>
        </div>

        <div class="form-group">
          <label for="date">Absence Date</label>
          <input id="date" type="date" name="date" value="<?php echo htmlspecialchars($absence['date']); ?>" required>
        </div>

        <div class="button-group">
          <button class="btn btn-primary" type="submit">Update Absence</button>
          <a href="manage_absences.php" class="btn btn-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>

  <script src="../js/dashboard.js"></script>
</body>
</html>
