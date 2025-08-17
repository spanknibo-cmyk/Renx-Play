<?php
require 'config.php';

$step = max(1, (int)($_GET['step'] ?? 1));
$message = '';

if ($step === 1 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    if ($identifier === '') {
        $message = "<div class='alert alert-error'><i class='fas fa-exclamation-triangle'></i> Informe seu e-mail ou usuário.</div>";
    } else {
        $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();
        if ($user) {
            $_SESSION['pending_login_user_id'] = $user['id'];
            header('Location: login.php?step=2');
            exit;
        } else {
            $message = "<div class='alert alert-error'><i class='fas fa-exclamation-triangle'></i> Conta não encontrada.</div>";
        }
    }
}

if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $userId = $_SESSION['pending_login_user_id'] ?? null;
    if (!$userId) {
        header('Location: login.php?step=1');
        exit;
    }
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        unset($_SESSION['pending_login_user_id']);
        $_SESSION['user'] = $user;
        $redirect = $_SESSION['redirect_after_login'] ?? 'index.php';
        unset($_SESSION['redirect_after_login']);
        header('Location: ' . $redirect);
        exit;
    } else {
        $message = "<div class='alert alert-error'><i class='fas fa-exclamation-triangle'></i> Senha incorreta.</div>";
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Entrar - <?= SITE_NAME ?></title>
	<link rel="stylesheet" href="style.css?v=<?= time() ?>">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
	<div class="x-auth">
		<div class="x-auth-wrap">
			<h1 class="x-title">Entrar</h1>
			
			<div class="x-social">
				<a href="#" class="btn btn-social btn-google"><i class="fab fa-google"></i> Sign in with Google</a>
				<a href="#" class="btn btn-social btn-apple"><i class="fab fa-apple"></i> Entrar com Apple</a>
			</div>
			<div class="auth-divider"><span>ou</span></div>

			<?= $message ?>

			<?php if ($step === 1): ?>
				<form method="post" class="x-form">
					<label class="x-label">Celular, e-mail ou nome de usuário</label>
					<input class="x-input" type="text" name="identifier" placeholder="seu@email.com" required>
					<button class="x-cta" type="submit">Avançar</button>
				</form>
				<div class="x-foot-note"><a href="signup.php" class="x-link">Não tem uma conta? Inscreva-se</a></div>
			<?php else: ?>
				<form method="post" class="x-form">
					<label class="x-label">Senha</label>
					<div class="x-input-wrap">
						<input class="x-input" type="password" id="login_password" name="password" placeholder="Sua senha" required>
						<button type="button" class="toggle-password" data-target="#login_password"><i class="far fa-eye"></i></button>
					</div>
					<button class="x-cta" type="submit">Entrar</button>
				</form>
				<div class="x-foot-row">
					<a href="#" class="x-link">Esqueceu sua senha?</a>
					<a href="login.php?step=1" class="x-link">Voltar</a>
				</div>
			<?php endif; ?>
		</div>
	</div>
	<script>
		document.querySelectorAll('.toggle-password').forEach(function(btn){
			var sel = btn.getAttribute('data-target');
			var input = document.querySelector(sel);
			btn.addEventListener('click', function(){
				if (!input) return;
				var isPwd = input.type === 'password';
				input.type = isPwd ? 'text' : 'password';
				btn.querySelector('i').className = isPwd ? 'far fa-eye-slash' : 'far fa-eye';
			});
		});
	</script>
</body>
</html>