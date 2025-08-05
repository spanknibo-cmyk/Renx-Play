<?php
require 'config.php';

$action = $_GET['action'] ?? $_POST['action'] ?? 'login';
$message = '';




// LOGOUT
if ($action === 'logout') {
    session_destroy();
    header('Location: index.php');
    exit;
}

// PROCESSAR LOGIN
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user'] = $user;
        header('Location: ' . ($_GET['redirect'] ?? 'index.php'));
        exit;
    } else {
        $message = "<div class='alert alert-error'><i class='fas fa-exclamation-triangle'></i> Credenciais inválidas!</div>";
    }
}

// PROCESSAR REGISTRO
if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    
    if ($password !== $confirmPassword) {
        $message = "<div class='alert alert-error'><i class='fas fa-exclamation-triangle'></i> Senhas não coincidem!</div>";
    } else {
        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $stmt->execute([$username, $email, $hashedPassword]);
            $message = "<div class='alert alert-success'><i class='fas fa-check'></i> Conta criada com sucesso! Faça login.</div>";
            $action = 'login';
        } catch (PDOException $e) {
            $message = "<div class='alert alert-error'><i class='fas fa-exclamation-triangle'></i> Usuário ou email já existe!</div>";
        }
    }
}

renderHeader('Autenticação');

echo "<div class='auth-container'>
    <div class='auth-card'>
        <div class='auth-header'>
            <h2><i class='fas fa-user-circle'></i> " . ($action === 'register' ? 'Criar Conta' : 'Login') . "</h2>
            <div class='auth-tabs'>
                <a href='?action=login' class='" . ($action === 'login' ? 'active' : '') . "'>Login</a>
                <a href='?action=register' class='" . ($action === 'register' ? 'active' : '') . "'>Registrar</a>
            </div>
        </div>
        
        {$message}";

if ($action === 'login') {
    echo "<form method='POST' class='auth-form'>
        <input type='hidden' name='action' value='login'>
        <div class='form-group'>
            <label><i class='fas fa-user'></i> Usuário ou Email</label>
            <input type='text' name='username' required>
        </div>
        <div class='form-group'>
            <label><i class='fas fa-lock'></i> Senha</label>
            <input type='password' name='password' required>
        </div>
        <button type='submit' class='btn btn-primary btn-full'><i class='fas fa-sign-in-alt'></i> Entrar</button>
    </form>";
} else {
    echo "<form method='POST' class='auth-form'>
        <input type='hidden' name='action' value='register'>
        <div class='form-group'>
            <label><i class='fas fa-user'></i> Nome de Usuário</label>
            <input type='text' name='username' required>
        </div>
        <div class='form-group'>
            <label><i class='fas fa-envelope'></i> Email</label>
            <input type='email' name='email' required>
        </div>
        <div class='form-group'>
            <label><i class='fas fa-lock'></i> Senha</label>
            <input type='password' name='password' required>
        </div>
        <div class='form-group'>
            <label><i class='fas fa-lock'></i> Confirmar Senha</label>
            <input type='password' name='confirm_password' required>
        </div>
        <button type='submit' class='btn btn-primary btn-full'><i class='fas fa-user-plus'></i> Criar Conta</button>
    </form>";
}

echo "</div></div>";

renderFooter();
?>
