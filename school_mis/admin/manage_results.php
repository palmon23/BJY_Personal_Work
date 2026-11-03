<?php
session_start();
include '../db.php';
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
  header("Location: ../login.php");
  exit;
}

// Add result
$message = '';
$students = $db->query("SELECT id, name, class FROM students ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $student_id = $_POST['student_id'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $score = $_POST['score'] ?? '';
    $term = $_POST['term'] ?? '1st';
    
    // Validation
    if(empty($student_id) || !is_numeric($student_id) || $student_id <= 0) {
        $message = '<p class="error">Please select a student</p>';
    } elseif(empty($subject)) {
        $message = '<p class="error">Subject is required</p>';
    } elseif(!is_numeric($score) || $score < 0 || $score > 100) {
        $message = '<p class="error">Score must be between 0 and 100</p>';
    } elseif(!in_array($term, ['1st', '2nd', '3rd'])) {
        $message = '<p class="error">Please select a valid term</p>';
    } else {
        // Check if student exists
        $student_check = $db->prepare("SELECT id FROM students WHERE id = ?");
        $student_check->execute([$student_id]);
        if($student_check->rowCount() === 0) {
            $message = '<p class="error">Student not found</p>';
        } else {
            try {
                try {
                  $db->exec("ALTER TABLE results ADD COLUMN IF NOT EXISTS created_by INT NULL");
                  $db->exec("ALTER TABLE results ADD COLUMN IF NOT EXISTS term ENUM('1st', '2nd', '3rd') DEFAULT '1st'");
                } catch(Exception $e) {}
                
                $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
                $stmt = $db->prepare("INSERT INTO results (student_id, subject, score, created_by, term) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$student_id, $subject, (int)$score, $user_id, $term]);
                $message = '<p class="success">Result added successfully</p>';
            } catch(Exception $e) {
                $message = '<p class="error">Error adding result: ' . htmlspecialchars($e->getMessage()) . '</p>';
            }
        }
    }
}

// Fetch all results with creator info
$results = $db->query("SELECT r.id, s.name AS student, r.subject, r.score, r.term, COALESCE(u.username, '-') AS added_by
                       FROM results r 
                       JOIN students s ON r.student_id = s.id
                       LEFT JOIN users u ON r.created_by = u.id
                       ORDER BY r.term, s.name")->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
  <title>Manage Results</title>
  <link rel="stylesheet" href="../css/dashboard.css">
</head>
<body>
  <header>Manage Results</header>
  <nav>
    <a href="admin.php">Back to Dashboard</a>
  </nav>

  <div class="container">
    <?php echo $message; ?>
    <div class="card">
      <h3>Add Result</h3>
      <form method="POST" style="display:grid;gap:12px;">
        <select name="student_id" required>
          <option value="">-- Select Student --</option>
          <?php foreach($students as $st): ?>
            <option value="<?php echo $st['id']; ?>" <?php echo (isset($_POST['student_id']) && $_POST['student_id']==$st['id'])? 'selected' : ''; ?>><?php echo htmlspecialchars($st['name'] . ' (' . $st['class'] . ')'); ?></option>
          <?php endforeach; ?>
        </select>
        <select name="term" required>
          <option value="1st" <?php echo (isset($_POST['term']) && $_POST['term']=='1st')? 'selected' : ''; ?>>1st Term</option>
          <option value="2nd" <?php echo (isset($_POST['term']) && $_POST['term']=='2nd')? 'selected' : ''; ?>>2nd Term</option>
          <option value="3rd" <?php echo (isset($_POST['term']) && $_POST['term']=='3rd')? 'selected' : ''; ?>>3rd Term</option>
        </select>
        <input type="text" name="subject" placeholder="Subject" value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>" required>
        <input type="number" name="score" placeholder="Score (0-100)" min="0" max="100" value="<?php echo htmlspecialchars($_POST['score'] ?? ''); ?>" required>
        <button type="submit" style="background:#7f5af0;color:#fff;border:none;padding:10px;border-radius:6px;cursor:pointer">Add Result</button>
      </form>
    </div>

    <div class="card">
      <h3>Results List</h3>
      <table style="width:100%;border-collapse:collapse;">
        <tr style="background:#f5f5f5;"><th style="padding:10px;text-align:left;border-bottom:1px solid #ddd;">ID</th><th style="padding:10px;text-align:left;border-bottom:1px solid #ddd;">Student</th><th style="padding:10px;text-align:left;border-bottom:1px solid #ddd;">Term</th><th style="padding:10px;text-align:left;border-bottom:1px solid #ddd;">Subject</th><th style="padding:10px;text-align:left;border-bottom:1px solid #ddd;">Score</th><th style="padding:10px;text-align:left;border-bottom:1px solid #ddd;">Added By</th><th style="padding:10px;text-align:left;border-bottom:1px solid #ddd;">Actions</th></tr>
        <?php foreach($results as $r): ?>
          <tr style="border-bottom:1px solid #eee;">
            <td style="padding:10px;"><?php echo $r['id']; ?></td>
            <td style="padding:10px;"><?php echo htmlspecialchars($r['student']); ?></td>
            <td style="padding:10px;"><strong><?php echo htmlspecialchars($r['term'] ?? '1st'); ?></strong></td>
            <td style="padding:10px;"><?php echo htmlspecialchars($r['subject']); ?></td>
            <td style="padding:10px;"><strong><?php echo $r['score']; ?></strong></td>
            <td style="padding:10px;"><?php echo htmlspecialchars($r['added_by']); ?></td>
            <td style="padding:10px;">
              <a href="edit_result.php?id=<?php echo $r['id']; ?>" style="color:#7f5af0;text-decoration:none;margin-right:8px;">Edit</a> |
              <a href="delete_result.php?id=<?php echo $r['id']; ?>" class="delete-btn" style="color:#dc3545;text-decoration:none;margin-left:8px;">Delete</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>

  <script src="../js/dashboard.js"></script>
</body>
</html>