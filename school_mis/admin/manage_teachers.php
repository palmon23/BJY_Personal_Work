<?php
session_start();
include '../db.php';
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
  header("Location: ../login.php");
  exit;
}

$message = '';

// Add teacher
if($_SERVER['REQUEST_METHOD'] == 'POST'){
  // handle presence toggle separately
  if(isset($_POST['action']) && $_POST['action'] === 'toggle_presence' && isset($_POST['teacher_id'])){
    $tid = (int)$_POST['teacher_id'];
    try {
      // best-effort: ensure 'present' column exists
      try { $db->exec("ALTER TABLE teachers ADD COLUMN present TINYINT(1) DEFAULT 0"); } catch(Exception $e) {}
      // flip value
      $cur = $db->prepare("SELECT present FROM teachers WHERE id = ?");
      $cur->execute([$tid]);
      $row = $cur->fetch(PDO::FETCH_ASSOC);
      $new = ($row && $row['present']) ? 0 : 1;
      $upd = $db->prepare("UPDATE teachers SET present = ? WHERE id = ?");
      $upd->execute([$new, $tid]);
      $message = 'Teacher presence updated.';
    } catch(Exception $e) {
      $message = 'Error updating presence.';
    }
  }

    $name = trim($_POST['name'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    
    if(empty($name) || empty($subject)) {
        $message = "Name and subject are required";
    } else {
        try {
            $stmt = $db->prepare("INSERT INTO teachers (name, subject) VALUES (?, ?)");
            $stmt->execute([$name, $subject]);
            $message = "Teacher added successfully!";
        } catch(Exception $e) {
            $message = "Error adding teacher: " . $e->getMessage();
        }
    }
}

// Fetch all teachers (handle case where `teachers` table doesn't exist yet)
try {
  $teachers = $db->query("SELECT * FROM teachers")->fetchAll();
} catch (Exception $e) {
  $teachers = [];
  $migrationLink = '../run_migration.php';
  if (empty($message)) {
    $message = 'Teachers table not found. Please <a href="' . $migrationLink . '">run the migration</a> to create required tables.';
  } else {
    $message .= ' <a href="' . $migrationLink . '">Run migration</a>.';
  }
}
?>

<!DOCTYPE html>
<html>
<head>
  <title>Manage Teachers</title>
  <link rel="stylesheet" href="../css/dashboard.css">
  <style>
    .card form input, .card form select { display:block; width:100%; padding:10px; margin:8px 0; border-radius:8px; border:1px solid #e0e0e0 }
    .actions button { background:#7f5af0;color:#fff;border:none;padding:8px 10px;border-radius:6px;cursor:pointer }
    .present-badge { display:inline-block;padding:6px 10px;border-radius:20px;background:#d4edda;color:#155724;font-weight:700 }
    .absent-badge { display:inline-block;padding:6px 10px;border-radius:20px;background:#f8d7da;color:#721c24;font-weight:700 }
    table th, table td { padding:10px; }
  </style>
</head>
<body>
  <header>Manage Teachers</header>
  <nav>
    <a href="admin.php">Back to Dashboard</a>
  </nav>

  <div class="container">
    <div class="card">
      <h3>Add Teacher</h3>
      <?php if($message): ?>
        <p class="message"><?php echo htmlspecialchars($message); ?></p>
      <?php endif; ?>
      <form method="POST">
        <input type="text" name="name" placeholder="Teacher Name" required>
        <input type="text" name="subject" placeholder="Subject" required>
        <button type="submit">Add Teacher</button>
      </form>
    </div>

    <div class="card">
      <h3>Teacher List</h3>
      <table>
        <tr><th>ID</th><th>Name</th><th>Subject</th><th>Status</th><th>Actions</th></tr>
        <?php foreach($teachers as $t): ?>
          <tr>
            <td><?php echo htmlspecialchars($t['id']); ?></td>
            <td><?php echo htmlspecialchars($t['name']); ?></td>
            <td><?php echo htmlspecialchars($t['subject']); ?></td>
            <td>
              <?php if(isset($t['present']) && $t['present']): ?>
                <span class="present-badge">Present</span>
              <?php else: ?>
                <span class="absent-badge">Absent</span>
              <?php endif; ?>
            </td>
            <td class="actions">
              <form method="POST" style="display:inline-block;margin-right:8px;">
                <input type="hidden" name="action" value="toggle_presence">
                <input type="hidden" name="teacher_id" value="<?php echo htmlspecialchars($t['id']); ?>">
                <button type="submit"><?php echo (isset($t['present']) && $t['present'])? 'Mark Absent' : 'Mark Present'; ?></button>
              </form>
              <a href="edit_teacher.php?id=<?php echo urlencode($t['id']); ?>">Edit</a> |
              <a href="delete_teacher.php?id=<?php echo urlencode($t['id']); ?>" class="delete-btn" onclick="return confirm('Delete this teacher?')">Delete</a> |
              <a href="teacher_performance.php?id=<?php echo urlencode($t['id']); ?>">View Performance</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>

  <script src="../js/dashboard.js"></script>
</body>
</html>