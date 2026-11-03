<?php
/**
 * Execute Database Migration
 * This script applies all migration changes defined in db_migration.sql
 */

require_once 'db.php';

$results = [];
$success = true;

try {
    $db->beginTransaction();
    
    // 1. Ensure teachers table exists, then add presence and tracking columns
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS `teachers` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(191) NOT NULL,
            `subject` VARCHAR(191) DEFAULT NULL,
            `present` TINYINT(1) DEFAULT 0,
            `last_updated` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $results[] = ['status' => 'success', 'message' => 'Ensured "teachers" table exists'];
    } catch (Exception $e) {
        $results[] = ['status' => 'error', 'message' => 'Could not create teachers table: ' . $e->getMessage()];
    }

    try {
        $db->exec("ALTER TABLE teachers ADD COLUMN IF NOT EXISTS `present` TINYINT(1) DEFAULT 0 COMMENT 'Teacher present (1) or absent (0)'");
        $results[] = ['status' => 'success', 'message' => 'Added "present" column to teachers table'];
    } catch (Exception $e) {
        $results[] = ['status' => 'info', 'message' => 'Column "present" already exists in teachers table'];
    }
    
    try {
        $db->exec("ALTER TABLE teachers ADD COLUMN IF NOT EXISTS `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'When presence was last updated'");
        $results[] = ['status' => 'success', 'message' => 'Added "last_updated" column to teachers table'];
    } catch (Exception $e) {
        $results[] = ['status' => 'info', 'message' => 'Column "last_updated" already exists in teachers table'];
    }
    
    // 2. Alter results table - add creator tracking and metadata
    try {
        $db->exec("ALTER TABLE results ADD COLUMN IF NOT EXISTS `created_by` INT NULL COMMENT 'User ID who created this result'");
        $results[] = ['status' => 'success', 'message' => 'Added "created_by" column to results table'];
    } catch (Exception $e) {
        $results[] = ['status' => 'info', 'message' => 'Column "created_by" already exists in results table'];
    }
    
    try {
        $db->exec("ALTER TABLE results ADD COLUMN IF NOT EXISTS `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When result was added'");
        $results[] = ['status' => 'success', 'message' => 'Added "created_at" column to results table'];
    } catch (Exception $e) {
        $results[] = ['status' => 'info', 'message' => 'Column "created_at" already exists in results table'];
    }
    
    try {
        $db->exec("ALTER TABLE results ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'When result was last updated'");
        $results[] = ['status' => 'success', 'message' => 'Added "updated_at" column to results table'];
    } catch (Exception $e) {
        $results[] = ['status' => 'info', 'message' => 'Column "updated_at" already exists in results table'];
    }
    
    // 3. Create teacher_attendance table
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS `teacher_attendance` (
              `id` INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Unique ID',
              `teacher_id` INT NOT NULL COMMENT 'Reference to teachers table',
              `attendance_date` DATE NOT NULL COMMENT 'Date of attendance',
              `present` TINYINT(1) DEFAULT 0 COMMENT 'Present (1) or Absent (0)',
              `marked_by` INT NULL COMMENT 'Admin user ID who marked the attendance',
              `remarks` TEXT NULL COMMENT 'Additional notes',
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              
              UNIQUE KEY `unique_attendance` (`teacher_id`, `attendance_date`),
              FOREIGN KEY (`teacher_id`) REFERENCES `teachers`(`id`) ON DELETE CASCADE,
              FOREIGN KEY (`marked_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
              
              INDEX `idx_teacher_id` (`teacher_id`),
              INDEX `idx_attendance_date` (`attendance_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Teacher daily attendance log'
        ");
        $results[] = ['status' => 'success', 'message' => 'Created "teacher_attendance" table'];
    } catch (Exception $e) {
        $results[] = ['status' => 'info', 'message' => 'Table "teacher_attendance" already exists'];
    }
    
    // 4. Create teacher_performance_summary view
    try {
        $db->exec("
            CREATE OR REPLACE VIEW `teacher_performance_summary` AS
            SELECT 
              t.id,
              t.name,
              t.subject,
              COUNT(DISTINCT r.id) AS total_results_added,
              AVG(r.score) AS average_student_score,
              MAX(r.score) AS highest_score,
              MIN(r.score) AS lowest_score,
              COUNT(CASE WHEN r.score >= 80 THEN 1 END) AS excellent_count,
              COUNT(CASE WHEN r.score >= 60 AND r.score < 80 THEN 1 END) AS good_count,
              COUNT(CASE WHEN r.score >= 40 AND r.score < 60 THEN 1 END) AS average_count,
              COUNT(CASE WHEN r.score < 40 THEN 1 END) AS needs_improvement_count,
              MAX(r.created_at) AS last_result_added,
              (SELECT COUNT(*) FROM teacher_attendance ta WHERE ta.teacher_id = t.id AND ta.present = 1) AS days_present,
              (SELECT COUNT(*) FROM teacher_attendance ta WHERE ta.teacher_id = t.id AND ta.present = 0) AS days_absent
            FROM 
              teachers t
            LEFT JOIN 
              results r ON r.created_by = t.id
            GROUP BY 
              t.id, t.name, t.subject
        ");
        $results[] = ['status' => 'success', 'message' => 'Created "teacher_performance_summary" view'];
    } catch (Exception $e) {
        $results[] = ['status' => 'info', 'message' => 'View "teacher_performance_summary" already exists or error: ' . $e->getMessage()];
    }
    
    // 5. Add indexes for optimization
    try {
        $db->exec("ALTER TABLE results ADD INDEX IF NOT EXISTS `idx_created_by` (`created_by`)");
        $results[] = ['status' => 'success', 'message' => 'Added index on results.created_by'];
    } catch (Exception $e) {
        $results[] = ['status' => 'info', 'message' => 'Index on results.created_by already exists'];
    }
    
    try {
        $db->exec("ALTER TABLE results ADD INDEX IF NOT EXISTS `idx_created_at` (`created_at`)");
        $results[] = ['status' => 'success', 'message' => 'Added index on results.created_at'];
    } catch (Exception $e) {
        $results[] = ['status' => 'info', 'message' => 'Index on results.created_at already exists'];
    }
    
    try {
        $db->exec("ALTER TABLE teachers ADD INDEX IF NOT EXISTS `idx_present` (`present`)");
        $results[] = ['status' => 'success', 'message' => 'Added index on teachers.present'];
    } catch (Exception $e) {
        $results[] = ['status' => 'info', 'message' => 'Index on teachers.present already exists'];
    }
    
    $db->commit();
    
} catch (Exception $e) {
    if (method_exists($db, 'inTransaction') && $db->inTransaction()) {
        $db->rollBack();
    }
    $success = false;
    $results[] = ['status' => 'error', 'message' => 'Migration failed: ' . $e->getMessage()];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Migration Results</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #7f5af0 0%, #00e7ff 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 800px;
            width: 100%;
            padding: 40px;
        }
        
        h1 {
            color: #7f5af0;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .status-badge.success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-badge.error {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .results-list {
            list-style: none;
            margin: 30px 0;
        }
        
        .result-item {
            padding: 15px;
            margin-bottom: 12px;
            border-radius: 8px;
            border-left: 4px solid;
            background-color: #f8f9fa;
        }
        
        .result-item.success {
            border-left-color: #28a745;
            background-color: #f0f9f6;
        }
        
        .result-item.success .icon {
            color: #28a745;
        }
        
        .result-item.error {
            border-left-color: #dc3545;
            background-color: #fdf5f6;
        }
        
        .result-item.error .icon {
            color: #dc3545;
        }
        
        .result-item.info {
            border-left-color: #17a2b8;
            background-color: #f0f8fa;
        }
        
        .result-item.info .icon {
            color: #17a2b8;
        }
        
        .result-item strong {
            color: #333;
            display: block;
            margin-bottom: 5px;
        }
        
        .result-item span {
            color: #666;
            font-size: 14px;
        }
        
        .icon {
            margin-right: 10px;
            font-weight: bold;
        }
        
        .action-buttons {
            margin-top: 40px;
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #7f5af0 0%, #00e7ff 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(127, 90, 240, 0.3);
        }
        
        .btn-secondary {
            background-color: #e9ecef;
            color: #333;
        }
        
        .btn-secondary:hover {
            background-color: #dee2e6;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Database Migration</h1>
        <div class="status-badge <?php echo $success ? 'success' : 'error'; ?>">
            <?php echo $success ? '✓ Migration Completed Successfully' : '✗ Migration Failed'; ?>
        </div>
        
        <ul class="results-list">
            <?php foreach ($results as $result): ?>
                <li class="result-item <?php echo $result['status']; ?>">
                    <span class="icon">
                        <?php 
                            if ($result['status'] === 'success') echo '✓';
                            elseif ($result['status'] === 'error') echo '✗';
                            else echo 'ℹ';
                        ?>
                    </span>
                    <strong><?php echo ucfirst($result['status']); ?></strong>
                    <span><?php echo htmlspecialchars($result['message']); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        
        <div class="action-buttons">
            <a href="admin/manage_teachers.php" class="btn btn-primary">Manage Teachers</a>
            <a href="admin/admin.php" class="btn btn-secondary">Back to Admin</a>
        </div>
    </div>
</body>
</html>
