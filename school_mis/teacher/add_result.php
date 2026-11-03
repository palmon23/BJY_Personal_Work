<?php
session_start();
include '../db.php';

$error = '';
$success = '';

// load students for dropdown
$students_list = [];
try {
  $students_list = $db->query("SELECT id, name, class FROM students ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
  // ignore - form will still work with manual ID
}

if($_SERVER['REQUEST_METHOD'] == 'POST'){
  $student_id = (int)($_POST['student_id'] ?? 0);
  $subject = trim($_POST['subject'] ?? '');
  $score = $_POST['score'] ?? '';
  $term = trim($_POST['term'] ?? '1st');

  if($student_id <= 0 || $subject === '' || $score === ''){
    $error = 'Please provide student, subject and score.';
  } elseif(!is_numeric($score) || $score < 0 || $score > 100) {
    $error = 'Score must be a number between 0 and 100.';
  } elseif(!in_array($term, ['1st', '2nd', '3rd'])) {
    $error = 'Please select a valid term.';
  } else {
    try {
      // verify student exists
      $chk = $db->prepare("SELECT id FROM students WHERE id = ?");
      $chk->execute([$student_id]);
      if(!$chk->fetch()){
        $error = 'Student not found.';
      } else {
        try {
          $db->exec("ALTER TABLE results ADD COLUMN IF NOT EXISTS created_by INT NULL");
          $db->exec("ALTER TABLE results ADD COLUMN IF NOT EXISTS term ENUM('1st', '2nd', '3rd') DEFAULT '1st'");
        } catch(Exception $e) {}
        
        $user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
        $stmt = $db->prepare("INSERT INTO results (student_id, subject, score, created_by, term) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$student_id, $subject, (int)$score, $user_id, $term]);
        $success = 'Result added successfully.';
        $_POST = [];
      }
    } catch(Exception $e) {
      $error = 'Error adding result. Please try again.';
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Add Result</title>
  <link rel="stylesheet" href="../css/dashboard.css">
  <style>
    .form-card { max-width:620px;margin:40px auto;padding:24px;background:#fff;border-radius:10px;box-shadow:0 6px 18px rgba(0,0,0,0.06); }
    .form-group { margin-bottom:14px; }
    label{display:block;margin-bottom:6px;font-weight:600;color:#333}
    input, select { width:100%; padding:10px 12px; border:1px solid #e0e0e0; border-radius:8px; }
    .btn{ padding:10px 18px; background:linear-gradient(135deg,#7f5af0,#00e7ff); color:#fff; border-radius:8px; border:none; cursor:pointer }
  </style>
</head>
<body>
  <div class="form-card">
    <h2>Add Result</h2>
    <p class="muted">Record a student's subject score.</p>

    <?php if($error): ?>
      <div style="background:#f8d7da;padding:10px;border-left:4px solid #dc3545;border-radius:6px;margin:12px 0;color:#721c24;"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if($success): ?>
      <div style="background:#d4edda;padding:10px;border-left:4px solid #28a745;border-radius:6px;margin:12px 0;color:#155724;"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="POST" novalidate>

      <div class="form-group">
        <label for="student_id">Student</label>
        <select id="student_id" name="student_id" required>
          <option value="">-- Select student --</option>
          <?php foreach($students_list as $st): ?>
            <option value="<?php echo $st['id']; ?>" <?php echo (isset($_POST['student_id']) && $_POST['student_id']==$st['id'])? 'selected' : ''; ?> ><?php echo htmlspecialchars($st['name'].' ('.$st['class'].')'); ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="term">Term</label>
        <select id="term" name="term" required>
          <option value="1st" <?php echo (isset($_POST['term']) && $_POST['term']=='1st')? 'selected' : ''; ?>>1st Term</option>
          <option value="2nd" <?php echo (isset($_POST['term']) && $_POST['term']=='2nd')? 'selected' : ''; ?>>2nd Term</option>
          <option value="3rd" <?php echo (isset($_POST['term']) && $_POST['term']=='3rd')? 'selected' : ''; ?>>3rd Term</option>
        </select>
      </div>

      <div class="form-group">
        <label for="subject">Subject</label>
        <input id="subject" type="text" name="subject" value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>" required>
      </div>

      <div class="form-group">
        <label for="score">Score (0-100)</label>
        <input id="score" type="number" name="score" min="0" max="100" value="<?php echo htmlspecialchars($_POST['score'] ?? ''); ?>" required>
      </div>

      <div style="display:flex;gap:12px;align-items:center;">
        <button class="btn" type="submit">Add Result</button>
        <a href="teacher.php" style="color:#7f5af0;text-decoration:none;font-weight:600;">← Back</a>
      </div>
    </form>
  </div>
</body>
</html>
