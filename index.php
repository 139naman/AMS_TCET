<?php
// index.php: login page start session and handle login as you currently do
session_start();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require 'config.php'; // DB connection

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $stmt = $pdo->prepare("SELECT id, fullname, role, password, division_id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Assuming password is hashed - adjust if plain text
        if ($user && $password === $user['password']){
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['division_id'] = $user['division_id'];

            switch ($user['role']) {
                case 'admin':
                    header('Location: admin.php');
                    break;
                case 'teacher':
                    header('Location: teacher.php');
                    break;
                case 'student':
                    header('Location: student.php');
                    break;
                default:
                    $error = "Unknown user role.";
            }
            exit();
        } else {
            $error = "Invalid username or password.";
        }
    } else {
        $error = "Please enter username and password.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Login - Attendance Management System</title>
<style>
  /* Reset */
  * {
    box-sizing: border-box;
  }
  body, html {
    height: 100%;
    margin: 0;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #f9f6ee, #fffefa);
    display: flex;
    justify-content: center;
    align-items: center;
  }
  .login-container {
    background: rgba(255, 255, 255, 0.85);
    border-radius: 20px;
    box-shadow: 0 15px 30px rgba(0,0,0,0.15);
    padding: 3rem 3.5rem;
    width: 360px;
    text-align: center;
  }
  .login-logo {
    margin-bottom: 1.8rem;
  }
  .login-logo img {
    width: 120px;
    height: auto;
    user-select: none;
  }
  .login-container h2 {
    margin-bottom: 2rem;
    color: #4a4a4a;
    font-weight: 600;
  }
  .login-input {
    position: relative;
    margin-bottom: 1.6rem;
  }
  .login-input input {
    width: 100%;
    padding: 12px 40px 12px 15px;
    border-radius: 25px;
    border: 1.5px solid #dcd5c7;
    font-size: 1rem;
    color: #3b3b3b;
    box-shadow: inset 0 2px 5px #eee9dc;
    outline: none;
    transition: border-color 0.3s ease;
  }
  .login-input input::placeholder {
    color: #a59985;
  }
  .login-input input:focus {
    border-color: #a9987c;
    box-shadow: 0 0 8px 1px rgba(169,152,124,0.6);
  }
  .login-input .icon {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    width: 18px;
    height: 18px;
    fill: #a9987c;
    pointer-events: none;
    user-select: none;
  }
  .login-btn {
    background: linear-gradient(135deg, #d0ba77 0%, #a9987c 100%);
    border: none;
    color: #3b3b3b;
    font-weight: 700;
    font-size: 1.1rem;
    width: 100%;
    padding: 12px 0;
    border-radius: 25px;
    cursor: pointer;
    box-shadow: 0 6px 10px rgba(154,138,110,0.4);
    transition: background 0.3s ease;
  }
  .login-btn:hover {
    background: linear-gradient(135deg, #a9987c 0%, #d0ba77 100%);
  }
  .error-message {
    background-color: #f9d5d3;
    color: #7f2a27;
    font-weight: 700;
    padding: 1rem;
    border-radius: 10px;
    margin-bottom: 1rem;
  }
  .forgot-link {
    display: block;
    margin-top: 1rem;
    font-size: 0.9rem;
    color: #a9987c;
    text-decoration: none;
  }
  .forgot-link:hover {
    color: #7e6b4f;
    text-decoration: underline;
  }
</style>
</head>
<body>
  <div class="login-container" role="main" aria-label="Login Form">
    <div class="login-logo">
      <!-- Replace 'college-logo.png' with your actual logo PNG -->
      <img src="assets/logo.png" alt="TCET College Logo" />
    </div>
    <h2>AMS TCET</h2>
    <?php if ($error): ?>
      <div class="error-message" role="alert"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="POST" action="" novalidate>
      <div class="login-input">
        <input type="text" name="username" placeholder="Username" aria-label="Username" required />
        <svg class="icon" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
        </svg>
      </div>
      <div class="login-input">
        <input type="password" name="password" placeholder="Password" aria-label="Password" required />
        <svg class="icon" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M12 17a2 2 0 1 0 0-4 2 2 0 0 0 0 4zm6-6h-1V7a5 5 0 0 0-10 0v4H6a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2zM8 7a4 4 0 0 1 8 0v4H8V7z"/>
        </svg>
      </div>
      <button type="submit" class="login-btn" aria-label="Log in">LOGIN</button>
    </form>
    <a href="#" class="forgot-link">Forgot Username / Password?</a>
  </div>
</body>
</html>
