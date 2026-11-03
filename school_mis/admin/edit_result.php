<?php
session_start();
include '../db.php';
if($_SESSION['role'] != 'admin') die("Access denied");

$id = $_GET['id'] ?? null;
if(!$id) die("Invalid result ID");

// Fetch result details
$stmt = $db->prepare("SELECT r.*, s.name AS student FROM results r JOIN students s ON r.student_id = s.id WHERE r.id = ?");
$stmt->execute([$id]);
$result = $stmt->fetch();

if(!$result) die("Result not found");

$message = '';

// Update result
if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $subject = $_POST['subject'];
    $score = $_POST['score'];
    $term = $_POST['term'] ?? '1st';
    
    // Validation
    if(empty($subject)) {
        $message = '<p class="error">Subject is required</p>';
    } elseif(!is_numeric($score) || $score < 0 || $score > 100) {
        $message = '<p class="error">Score must be between 0 and 100</p>';
    } elseif(!in_array($term, ['1st', '2nd', '3rd'])) {
        $message = '<p class="error">Please select a valid term</p>';
    } else {
        try {
            try {
              $db->exec("ALTER TABLE results ADD COLUMN IF NOT EXISTS term ENUM('1st', '2nd', '3rd') DEFAULT '1st'");
            } catch(Exception $e) {}
            
            $update = $db->prepare("UPDATE results SET subject = ?, score = ?, term = ? WHERE id = ?");
            $update->execute([$subject, $score, $term, $id]);
            $message = '<p class="success">Result updated successfully</p>';
            // Refresh result data
            $stmt = $db->prepare("SELECT r.*, s.name AS student FROM results r JOIN students s ON r.student_id = s.id WHERE r.id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch();
        } catch(Exception $e) {
            $message = '<p class="error">Error updating result: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit Result</title>
  <link rel="stylesheet" href="../css/dashboard.css">
  <style>
    .form-card{max-width:720px;margin:40px auto;padding:24px;background:#fff;border-radius:10px;box-shadow:0 6px 18px rgba(0,0,0,0.06)}
    .form-group{margin-bottom:14px}
    label{display:block;margin-bottom:6px;font-weight:600;color:#333}
    input[type=text],input[type=number],select{width:100%;padding:10px 12px;border:1px solid #e0e0e0;border-radius:8px}
    .btn{padding:10px 18px;background:linear-gradient(135deg,#7f5af0,#00e7ff);color:#fff;border-radius:8px;border:none;cursor:pointer}
    .muted{color:#666;font-size:13px}
  </style>
</head>
<body>
  <header style="background:linear-gradient(135deg,#7f5af0,#00e7ff);color:white;padding:20px 0;text-align:center;box-shadow:0 2px 8px rgba(0,0,0,0.06);">Edit Result</header>
  <nav style="padding:12px 20px;background:#fff;border-bottom:1px solid #eee;">
    <a href="manage_results.php">← Back to Results</a>
  </nav>

  <div class="container">
    <?php echo $message; ?>
    <div class="form-card">
      <h3 style="margin-top:0;">Edit Result</h3>
      <p class="muted">Update the subject and score for the selected student.</p>
      <form method="POST" novalidate>
        <div class="form-group">
            <label>Student</label>
            <div style="padding:10px 12px;background:#fafafa;border:1px solid #eee;border-radius:6px;"><?php echo htmlspecialchars($result['student']); ?></div>
        </div>

        <div class="form-group">
            <label for="subject">Subject</label>
            <input id="subject" type="text" name="subject" placeholder="Subject" value="<?php echo htmlspecialchars($result['subject']); ?>" required>
        </div>

        <div class="form-group">
            <label for="term">Term</label>
            <select id="term" name="term" required>
              <option value="1st" <?php echo (isset($result['term']) && $result['term']=='1st')? 'selected' : ''; ?>>1st Term</option>
              <option value="2nd" <?php echo (isset($result['term']) && $result['term']=='2nd')? 'selected' : ''; ?>>2nd Term</option>
              <option value="3rd" <?php echo (isset($result['term']) && $result['term']=='3rd')? 'selected' : ''; ?>>3rd Term</option>
            </select>
        </div>

        <div class="form-group">
            <label for="score">Score (0-100)</label>
            <input id="score" type="number" name="score" placeholder="Score (0-100)" value="<?php echo htmlspecialchars($result['score']); ?>" min="0" max="100" required>
        </div>

        <div style="display:flex;gap:12px;align-items:center;">
            <button class="btn" type="submit">Update Result</button>
            <a href="manage_results.php" style="color:#7f5af0;text-decoration:none;font-weight:600;">← Cancel</a>
        </div>
      </form>
    </div>
  </div>

  <script src="../js/dashboard.js"></script>
</body>
</html>
