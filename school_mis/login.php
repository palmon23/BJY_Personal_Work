<?php
session_start();
include 'db.php';

$error = '';

// Handle form submission
if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if(empty($username) || empty($password)) {
        $error = 'Username and password are required';
    } else {
        $query = $db->prepare("SELECT * FROM users WHERE username=?");
        $query->execute([$username]);
        $user = $query->fetch();
        
        if($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['username'] = $user['username'];
            
            if($user['role'] == 'admin') {
                header("Location: admin/admin.php");
            } elseif($user['role'] == 'teacher') {
                header("Location: teacher/teacher.php");
            } elseif($user['role'] == 'parent') {
                header("Location: parent/parent.php");
            }
            exit;
        } else {
            $error = 'Invalid username or password';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Management System - Login</title>
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
            margin: 0;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
        }
        
        .login-container h1 {
            text-align: center;
            color: #7f5af0;
            margin-top: 0;
            margin-bottom: 10px;
        }
        
        .login-container p {
            text-align: center;
            color: #666;
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #7f5af0;
            box-shadow: 0 0 5px rgba(127, 90, 240, 0.3);
        }
        
        .login-btn {
            width: 100%;
            padding: 11px;
            background: linear-gradient(135deg, #7f5af0 0%, #00e7ff 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(127, 90, 240, 0.4);
        }

        .login-btn:active {
            transform: translateY(0);
        }
        
        .error-box {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .links {
            margin-top: 20px;
            text-align: center;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
        
        .links a {
            display: block;
            color: #7f5af0;
            text-decoration: none;
            font-size: 14px;
            margin: 8px 0;
            font-weight: 600;
            transition: color 0.3s;
        }
        
        .links a:hover {
            color: #00e7ff;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>📚 School Management System</h1>
        <p>Login to your account</p>
        
        <?php if($error): ?>
            <div class="error-box">⚠️ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Enter your username" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
            </div>
            
            <button type="submit" class="login-btn">Login</button>
        </form>
        
        <div class="links">
            <a href="index.php">← Back to Home</a>
            <a href="change_password.php">Forgot Password?</a>
        </div>
    </div>
</body>
</html>
