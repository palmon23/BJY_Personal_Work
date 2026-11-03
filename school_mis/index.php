<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Management System - Home</title>
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
        
        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 500px;
        }
        
        .container h1 {
            text-align: center;
            color: #7f5af0;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .container p {
            text-align: center;
            color: #666;
            font-size: 14px;
            margin-bottom: 30px;
        }

        .logo-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo-section img {
            height: 80px;
            width: 80px;
            display: block;
            margin: 0 auto;
            border-radius: 50%;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .login-options {
            display: grid;
            gap: 15px;
        }

        .login-btn {
            display: inline-block;
            padding: 12px 20px;
            background: linear-gradient(135deg, #7f5af0 0%, #00e7ff 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            text-align: center;
            width: 100%;
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(127, 90, 240, 0.4);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #a78bfa 0%, #38bdf8 100%);
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

        .success-box {
            background-color: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .divider {
            text-align: center;
            margin: 20px 0;
            color: #999;
            font-size: 14px;
        }

        .divider:before {
            content: "";
            display: block;
            border-top: 1px solid #ddd;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📚 School Management System</h1>
        <p>Welcome to the Student Information Portal</p>
        
        <div class="logo-section">
            <img src="logo.jpg" alt="School Logo" title="School Logo">
        </div>

        <div class="login-options">
            <a href="login.php" class="login-btn">Staff/Admin Login</a>
            <a href="parent/parent.php" class="login-btn btn-secondary">Parent Login</a>
            <a href="change_password.php" class="login-btn btn-secondary">Forgot Password?</a>
        </div>
    </div>

    <script src="js/script.js"></script>
</body>
</html>
