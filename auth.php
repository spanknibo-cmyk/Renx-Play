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

// Não renderizar header/footer para página de auth standalone
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $action === 'register' ? 'Registrar' : 'Entrar' ?> - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" href="https://i.imgur.com/QyZKduC.png" type="image/png">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <!-- Logo e Header -->
            <div class="auth-header">
                <div style="text-align: center; margin-bottom: 2rem;">
                    <div style="width: 120px; height: 120px; margin: 0 auto 1rem; background: hsl(var(--muted)); border-radius: 50%; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden;">
                        <div style="width: 80px; height: 80px; border: 4px solid hsl(var(--border)); border-radius: 50%; background: hsl(var(--background)); display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-play" style="font-size: 2rem; color: hsl(var(--primary)); margin-left: 0.25rem;"></i>
                        </div>
                        <!-- Ícone de busca sobreposto -->
                        <div style="position: absolute; top: -10px; right: -10px; width: 50px; height: 50px; background: hsl(var(--muted)); border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 3px solid hsl(var(--background));">
                            <i class="fas fa-search" style="font-size: 1rem; color: hsl(var(--muted-foreground));"></i>
                        </div>
                    </div>
                </div>
                
                <h2><?= $action === 'register' ? 'Entrar' : 'Entrar' ?></h2>
                <p style="color: hsl(var(--muted-foreground)); margin-bottom: 1.5rem; font-size: 0.875rem;">
                    Entre com sua conta para continuar
                </p>
                
                <div class="auth-tabs">
                    <a href="?action=login" class="<?= $action === 'login' ? 'active' : '' ?>">Login</a>
                    <a href="?action=register" class="<?= $action === 'register' ? 'active' : '' ?>">Registrar</a>
                </div>
            </div>
            
            <?= $message ?>
            
            <?php if ($action === 'login'): ?>
                <form method="POST" class="auth-form">
                    <input type="hidden" name="action" value="login">
                    
                    <div class="form-group">
                        <label for="username">Usuário ou Email</label>
                        <div style="position: relative;">
                            <input type="text" id="username" name="username" placeholder="Digite seu usuário ou email" required style="padding-left: 2.5rem;">
                            <i class="fas fa-user" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: hsl(var(--muted-foreground)); font-size: 0.875rem;"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Senha</label>
                        <div style="position: relative;">
                            <input type="password" id="password" name="password" placeholder="Digite sua senha" required style="padding-left: 2.5rem;">
                            <i class="fas fa-lock" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: hsl(var(--muted-foreground)); font-size: 0.875rem;"></i>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">
                        <i class="fas fa-sign-in-alt"></i>
                        Entrar
                    </button>
                </form>
            <?php else: ?>
                <form method="POST" class="auth-form">
                    <input type="hidden" name="action" value="register">
                    
                    <div class="form-group">
                        <label for="username">Nome de Usuário</label>
                        <div style="position: relative;">
                            <input type="text" id="username" name="username" placeholder="Escolha seu nome de usuário" required style="padding-left: 2.5rem;">
                            <i class="fas fa-user" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: hsl(var(--muted-foreground)); font-size: 0.875rem;"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email</label>
                        <div style="position: relative;">
                            <input type="email" id="email" name="email" placeholder="Digite seu email" required style="padding-left: 2.5rem;">
                            <i class="fas fa-envelope" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: hsl(var(--muted-foreground)); font-size: 0.875rem;"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Senha</label>
                        <div style="position: relative;">
                            <input type="password" id="password" name="password" placeholder="Crie uma senha" required style="padding-left: 2.5rem;">
                            <i class="fas fa-lock" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: hsl(var(--muted-foreground)); font-size: 0.875rem;"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirmar Senha</label>
                        <div style="position: relative;">
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirme sua senha" required style="padding-left: 2.5rem;">
                            <i class="fas fa-lock" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); color: hsl(var(--muted-foreground)); font-size: 0.875rem;"></i>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">
                        <i class="fas fa-user-plus"></i>
                        Criar Conta
                    </button>
                </form>
            <?php endif; ?>
            
            <div style="text-align: center; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid hsl(var(--border));">
                <a href="index.php" style="color: hsl(var(--muted-foreground)); text-decoration: none; font-size: 0.875rem; display: inline-flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-arrow-left"></i>
                    Voltar para o site
                </a>
            </div>
        </div>
    </div>

    <script>
        // Auto focus no primeiro input
        document.addEventListener('DOMContentLoaded', function() {
            const firstInput = document.querySelector('input[type="text"], input[type="email"]');
            if (firstInput) {
                firstInput.focus();
            }
        });
    </script>
</body>
</html>
