<?php
session_start();
include '../db.php';

// Handle logout
if(isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: ../index.php");
    exit;
}

$search_query = $_GET['search'] ?? '';
$students = [];
$error = '';

try {
    if($search_query && strlen($search_query) >= 1) {
        // Search for students by name or class
        $stmt = $db->prepare("SELECT id, name, class FROM students WHERE LOWER(name) LIKE LOWER(?) OR LOWER(class) LIKE LOWER(?) ORDER BY name");
        $stmt->execute(['%' . $search_query . '%', '%' . $search_query . '%']);
    } else {
        // Fetch all students
        $stmt = $db->prepare("SELECT id, name, class FROM students ORDER BY name");
        $stmt->execute();
    }
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $error = 'Error fetching students';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Dashboard - Student Performance</title>
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
            padding: 40px 20px;
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
            font-size: 32px;
            font-weight: 700;
        }

        .header-content span {
            font-size: 15px;
            opacity: 0.9;
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
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .search-section h2 {
            font-size: 22px;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
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
            transition: border-color 0.3s;
        }

        .search-form input:focus {
            outline: none;
            border-color: #7f5af0;
            box-shadow: 0 0 5px rgba(127, 90, 240, 0.3);
        }

        .search-form button {
            padding: 12px 30px;
            background: linear-gradient(135deg, #7f5af0 0%, #00e7ff 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
        }

        .search-form button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(127, 90, 240, 0.3);
        }

        .clear-search {
            padding: 12px 20px;
            background: #f0f0f0;
            color: #666;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
        }

        .clear-search:hover {
            background: #e0e0e0;
        }

        .students-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .student-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            border-left: 5px solid #7f5af0;
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .student-card:hover {
            box-shadow: 0 8px 25px rgba(127, 90, 240, 0.2);
            transform: translateY(-5px);
        }

        .student-card-header {
            background: linear-gradient(135deg, #7f5af0 0%, #00e7ff 100%);
            color: white;
            padding: 20px;
        }

        .student-card-header h4 {
            font-size: 18px;
            font-weight: 700;
            margin: 0;
        }

        .student-card-body {
            padding: 20px;
        }

        .student-card-body p {
            margin: 10px 0;
            color: #666;
            font-size: 14px;
        }

        .student-card-body strong {
            color: #333;
        }

        .student-card-footer {
            padding: 0 20px 20px 20px;
        }

        .view-btn {
            display: inline-block;
            background: linear-gradient(135deg, #7f5af0 0%, #00e7ff 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            width: 100%;
            text-align: center;
        }

        .view-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(127, 90, 240, 0.3);
        }

        .error-box {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .info-box {
            background-color: #d1ecf1;
            border-left: 4px solid #17a2b8;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .no-results {
            text-align: center;
            padding: 50px;
            color: #999;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        .no-results h3 {
            color: #666;
            margin-bottom: 10px;
        }

        .about-section {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-top: 30px;
        }

        .about-section h3 {
            color: #333;
            font-size: 20px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .about-section p {
            color: #666;
            line-height: 1.6;
            margin: 10px 0;
        }

        .stats-section {
            background: linear-gradient(135deg, #7f5af0 0%, #00e7ff 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 20px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
        }

        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }

            .header-content h1 {
                font-size: 24px;
            }

            nav {
                flex-wrap: wrap;
                justify-content: center;
            }

            .search-form {
                flex-direction: column;
            }

            .students-grid {
                grid-template-columns: 1fr;
            }

            .stats-section {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <h1>👨‍👩‍👧‍👦 Parent Dashboard</h1>
            <span>View Students Performance & Results</span>
        </div>
    </header>
    
    <nav>
        <a href="../index.php">← Home</a>
        <a href="?logout=1">Logout</a>
    </nav>

    <div class="container">
        <?php if($error): ?>
            <div class="error-box">⚠️ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Search Section -->
        <div class="search-section">
            <h2>🔍 Search for Student Performance</h2>
            <form method="GET" class="search-form">
                <input type="text" name="search" placeholder="Search by student name or class..." value="<?php echo htmlspecialchars($search_query); ?>">
                <button type="submit">Search</button>
                <?php if($search_query): ?>
                    <a href="parent.php" class="clear-search">Clear Filter</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Statistics Section -->
        <?php if(!empty($students)): ?>
            <div class="stats-section">
                <div class="stat-item">
                    <div class="stat-number"><?php echo count($students); ?></div>
                    <div class="stat-label">Total Students</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo count(array_unique(array_column($students, 'class'))); ?></div>
                    <div class="stat-label">Classes</div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Students Grid -->
        <?php if(!empty($students)): ?>
            <div class="students-grid">
                <?php foreach($students as $student): ?>
                    <a href="view_performance.php?student_id=<?php echo urlencode($student['id']); ?>" class="student-card">
                        <div class="student-card-header">
                            <h4>📚 <?php echo htmlspecialchars($student['name']); ?></h4>
                        </div>
                        <div class="student-card-body">
                            <p><strong>Class:</strong> <?php echo htmlspecialchars($student['class']); ?></p>
                            <p style="margin-top: 15px; color: #7f5af0;"><strong>View Performance →</strong></p>
                        </div>
                        <div class="student-card-footer">
                            <button type="button" class="view-btn">Check Results</button>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-results">
                <h3>📭 No Students Found</h3>
                <p><?php echo $search_query ? "No students match your search query." : "No students available in the system."; ?></p>
                <?php if($search_query): ?>
                    <a href="parent.php" style="color: #7f5af0; text-decoration: none; font-weight: 600;">← Try a different search</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- About Section -->
        <div class="about-section">
            <h3>ℹ️ About This Portal</h3>
            <p>Welcome to the Student Performance Portal. Use this dashboard to:</p>
            <ul style="margin: 15px 0 0 20px; color: #666; line-height: 1.8;">
                <li>Search and view student information</li>
                <li>Check academic performance and results</li>
                <li>Monitor progress across all subjects</li>
                <li>Access real-time student data</li>
            </ul>
        </div>
    </div>

    <script src="../js/dashboard.js"></script>
</body>
</html>