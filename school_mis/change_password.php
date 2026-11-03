<?php
session_start();
include 'db.php';

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $username = $_POST['username'] ?? '';
    $oldPassword = $_POST['old_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $newUsername = trim($_POST['new_username'] ?? '');

    // Validation
    if(empty($username) || empty($oldPassword)) {
        $error = 'Username and current password are required';
    } elseif(empty($newPassword) && empty($newUsername)) {
        $error = 'Please provide at least a new password or new username';
    } elseif(!empty($newPassword) && $newPassword !== $confirmPassword) {
        $error = 'New passwords do not match';
    } elseif(!empty($newPassword) && strlen($newPassword) < 6) {
        $error = 'Password must be at least 6 characters';
    } else {
        try {
            // Fetch current user
            $stmt = $db->prepare("SELECT * FROM users WHERE username=?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if($user && password_verify($oldPassword, $user['password'])) {
                $updateFields = [];
                $updateValues = [];
                
                // Prepare password update
                if(!empty($newPassword)) {
                    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $updateFields[] = "password=?";
                    $updateValues[] = $newHash;
                }
                
                // Prepare username update
                if(!empty($newUsername)) {
                    // Check if new username already exists
                    $checkUsername = $db->prepare("SELECT id FROM users WHERE username=? AND id!=?");
                    $checkUsername->execute([$newUsername, $user['id']]);
                    if($checkUsername->fetch()) {
                        $error = 'Username already exists. Please choose a different username.';
                    } else {
                        $updateFields[] = "username=?";
                        $updateValues[] = $newUsername;
                    }
                }
                
                // Perform update if no error
                if(empty($error) && !empty($updateFields)) {
                    $updateValues[] = $user['id'];
                    $updateQuery = "UPDATE users SET " . implode(", ", $updateFields) . " WHERE id=?";
                    $update = $db->prepare($updateQuery);
                    $update->execute($updateValues);
                    
                    $messages = [];
                    if(!empty($newPassword)) {
                        $messages[] = 'password';
                    }
                    if(!empty($newUsername)) {
                        $messages[] = 'username';
                    }
                    
                    $success = 'Your ' . implode(' and ', $messages) . ' updated successfully!';
                    $_POST = [];
                }
            } else {
                $error = 'Username or old password is incorrect';
            }
        } catch(Exception $e) {
            $error = 'Error updating account. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #7f5af0 0%, #00e7ff 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 450px;
            height: 300;
        }

        .container h1 {
            text-align: center;
            color: #7f5af0;
            margin-bottom: 5px;
            font-size: 28px;
        }

        .container p {
            text-align: center;
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
        }

        .form-group {
            margin-bottom: 12px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #7f5af0;
            box-shadow: 0 0 5px rgba(127, 90, 240, 0.3);
        }

        .change-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #7f5af0 0%, #00e7ff 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-weight: bold;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 5px;
        }

        .change-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(127, 90, 240, 0.4);
        }

        .change-btn:active {
            transform: translateY(0);
        }

        .error-box {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .success-box {
            background-color: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .back-link {
            text-align: center;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #eee;
        }

        .back-link a {
            color: #7f5af0;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: color 0.3s;
        }

        .back-link a:hover {
            color: #00e7ff;
        }

        .help-text {
            font-size: 12px;
            color: #999;
            margin-top: 2px;
        }

        @media (max-width: 480px) {
            .container {
                padding: 30px 20px;
            }

            .container h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Account Settings</h1>
        <p>Update your username and password securely</p>

        <?php if($error): ?>
            <div class="error-box">⚠️ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if($success): ?>
            <div class="success-box">✓ <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="username">Current Username</label>
                <input type="text" id="username" name="username" placeholder="Enter your current username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required autofocus>
                <span class="help-text">Your current login username</span>
            </div>

            <div class="form-group">
                <label for="old_password">Current Password</label>
                <input type="password" id="old_password" name="old_password" placeholder="Enter your current password" required>
                <span class="help-text">Required for verification</span>
            </div>

            <div class="form-group">
                <label for="new_username">New Username (Optional)</label>
                <input type="text" id="new_username" name="new_username" placeholder="Leave blank to keep current username">
                <span class="help-text">Leave blank if you don't want to change it</span>
            </div>

            <div class="form-group">
                <label for="new_password">New Password (Optional)</label>
                <input type="password" id="new_password" name="new_password" placeholder="Leave blank to keep current password">
                <span class="help-text">Minimum 6 characters. Leave blank if you don't want to change it</span>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your new password">
                <span class="help-text">Must match the new password above</span>
            </div>

            <button type="submit" class="change-btn">Update Account</button>
        </form>

        <div class="back-link">
            <a href="index.php">← Back to Home</a>
        </div>
    </div>
</body>
</html>
