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
    // Proteção CSRF
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = "<div class='alert alert-error'><i class='fas fa-exclamation-triangle'></i> Sessão expirada. Atualize a página e tente novamente.</div>";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Segurança: renovar ID de sessão
            session_regenerate_id(true);
            $_SESSION['user'] = $user;

            $redirect = $_GET['redirect'] ?? ($_SESSION['redirect_after_login'] ?? 'index.php');
            unset($_SESSION['redirect_after_login']);
            header('Location: ' . $redirect);
            exit;
        } else {
            $message = "<div class='alert alert-error'><i class='fas fa-exclamation-triangle'></i> Credenciais inválidas!</div>";
        }
    }
}

// PROCESSAR REGISTRO
if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Proteção CSRF
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = "<div class='alert alert-error'><i class='fas fa-exclamation-triangle'></i> Sessão expirada. Atualize a página e tente novamente.</div>";
    } else {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = "<div class='alert alert-error'><i class='fas fa-exclamation-triangle'></i> Email inválido.</div>";
        } elseif (strlen($password) < max(8, MIN_PASSWORD_LENGTH)) { // exigir pelo menos 8
            $message = "<div class='alert alert-error'><i class='fas fa-exclamation-triangle'></i> A senha deve ter no mínimo 8 caracteres.</div>";
        } elseif ($password !== $confirmPassword) {
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
        <div class="auth-card auth-card-split">
            <div class="auth-grid">
                <aside class="auth-hero">
                    <div class="auth-hero-content">
                        <div class="brand-mark">
                            <i class="fas fa-play"></i>
                        </div>
                        <h1>Bem-vindo ao <?= SITE_NAME ?></h1>
                        <p>Entre e acompanhe os melhores jogos Ren'Py, com uma experiência limpa, moderna e rápida.</p>
                        <ul class="auth-bullets">
                            <li><i class="fas fa-check-circle"></i> Downloads seguros</li>
                            <li><i class="fas fa-check-circle"></i> Biblioteca sempre atualizada</li>
                            <li><i class="fas fa-check-circle"></i> Tema claro/escuro</li>
                        </ul>
                    </div>
                    <div class="auth-hero-ornaments"></div>
                </aside>

                <section class="auth-panel">
                    <!-- Logo e Header -->
                    <div class="auth-header">
                        <h2><?= $action === 'register' ? 'Criar conta' : 'Entrar' ?></h2>
                        <p style="color: hsl(var(--muted-foreground)); margin-bottom: 1.5rem; font-size: 0.875rem;">
                            <?= $action === 'register' ? 'Preencha seus dados para começar' : 'Entre com sua conta para continuar' ?>
                        </p>

                        <div class="auth-tabs">
                            <a href="?action=login" class="<?= $action === 'login' ? 'active' : '' ?>">Login</a>
                            <a href="?action=register" class="<?= $action === 'register' ? 'active' : '' ?>">Registrar</a>
                        </div>
                    </div>

                    <?= $message ?>

                    <?php if ($action === 'login'): ?>
                        <form method="POST" class="auth-form" autocomplete="on">
                            <input type="hidden" name="action" value="login">
                            <?= csrfField() ?>

                            <div class="form-group">
                                <label for="username">Usuário ou Email</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-user"></i>
                                    <input type="text" id="username" name="username" placeholder="Digite seu usuário ou email" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="password">Senha</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-lock"></i>
                                    <input type="password" id="password" name="password" placeholder="Digite sua senha" required>
                                    <button type="button" class="toggle-password" data-target="#password" aria-label="Mostrar senha"><i class="fas fa-eye"></i></button>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem;">
                                <i class="fas fa-sign-in-alt"></i>
                                Entrar
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="POST" class="auth-form" autocomplete="off">
                            <input type="hidden" name="action" value="register">
                            <?= csrfField() ?>

                            <div class="form-group">
                                <label for="username">Nome de Usuário</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-user"></i>
                                    <input type="text" id="username" name="username" placeholder="Escolha seu nome de usuário" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="email">Email</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-envelope"></i>
                                    <input type="email" id="email" name="email" placeholder="Digite seu email" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="password">Senha</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-lock"></i>
                                    <input type="password" id="reg_password" name="password" placeholder="Crie uma senha" required>
                                    <button type="button" class="toggle-password" data-target="#reg_password" aria-label="Mostrar senha"><i class="fas fa-eye"></i></button>
                                </div>
                                <div class="password-meter" aria-hidden="true">
                                    <div class="password-meter-bar" id="pwMeter"></div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="confirm_password">Confirmar Senha</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-lock"></i>
                                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirme sua senha" required>
                                    <button type="button" class="toggle-password" data-target="#confirm_password" aria-label="Mostrar senha"><i class="fas fa-eye"></i></button>
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
                </section>
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

        // Toggle de senha
        document.addEventListener('click', function(e) {
            const btn = e.target.closest('.toggle-password');
            if (!btn) return;
            const selector = btn.getAttribute('data-target');
            const input = document.querySelector(selector);
            if (!input) return;
            const isPwd = input.getAttribute('type') === 'password';
            input.setAttribute('type', isPwd ? 'text' : 'password');
            const icon = btn.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-eye');
                icon.classList.toggle('fa-eye-slash');
            }
        });

        // Medidor de força de senha (registro)
        const regPwd = document.getElementById('reg_password');
        const meter = document.getElementById('pwMeter');
        function score(pwd){
            if (!pwd) return 0;
            let s = 0;
            if (pwd.length >= 8) s += 1;
            if (/[A-Z]/.test(pwd)) s += 1;
            if (/[a-z]/.test(pwd)) s += 1;
            if (/[0-9]/.test(pwd)) s += 1;
            if (/[^A-Za-z0-9]/.test(pwd)) s += 1;
            return Math.min(s, 5);
        }
        function updateMeter(){
            if (!meter || !regPwd) return;
            const val = score(regPwd.value);
            const pct = (val/5)*100;
            meter.style.width = pct + '%';
            meter.dataset.level = String(val);
        }
        if (regPwd) regPwd.addEventListener('input', updateMeter);
        updateMeter();
    </script>
</body>
</html>
