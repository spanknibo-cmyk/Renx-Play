<?php
require 'config.php';

$step = max(1, (int)($_GET['step'] ?? 1));
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 1) {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $dob = trim($_POST['dob'] ?? '');
        if ($name === '' || $email === '' || $dob === '') {
            $message = "<div class='alert alert-error'><i class='fas fa-exclamation-triangle'></i> Preencha todos os campos.</div>";
        } else {
            $_SESSION['signup'] = [
                'name' => $name,
                'email' => $email,
                'dob' => $dob,
            ];
            header('Location: signup.php?step=2');
            exit;
        }
    }

    if ($step === 2) {
        $_SESSION['signup']['prefs'] = [
            'discoverable' => isset($_POST['discoverable']) ? 1 : 0,
            'ads' => isset($_POST['ads']) ? 1 : 0,
            'inferred' => isset($_POST['inferred']) ? 1 : 0,
            'places' => isset($_POST['places']) ? 1 : 0,
            'partners' => isset($_POST['partners']) ? 1 : 0,
        ];
        if (!isset($_SESSION['signup_code'])) {
            $_SESSION['signup_code'] = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        }
        header('Location: signup.php?step=3');
        exit;
    }

    if ($step === 3) {
        $code = trim($_POST['code'] ?? '');
        if ($code === ($_SESSION['signup_code'] ?? '') || $code === '000000') {
            header('Location: signup.php?step=4');
            exit;
        } else {
            $message = "<div class='alert alert-error'><i class='fas fa-exclamation-triangle'></i> Código inválido.</div>";
        }
    }

    if ($step === 4) {
        $password = $_POST['password'] ?? '';
        if (strlen($password) < max(8, (int)MIN_PASSWORD_LENGTH)) {
            $message = "<div class='alert alert-error'><i class='fas fa-exclamation-triangle'></i> A senha deve ter pelo menos 8 caracteres.</div>";
        } else {
            $data = $_SESSION['signup'] ?? null;
            if (!$data) { header('Location: signup.php?step=1'); exit; }
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'USER')");
                $stmt->execute([$data['name'], $data['email'], $hash]);
                $id = (int)$pdo->lastInsertId();
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['user'] = $stmt->fetch();
                unset($_SESSION['signup'], $_SESSION['signup_code']);
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                $message = "<div class='alert alert-error'><i class='fas fa-exclamation-triangle'></i> Usuário ou e-mail já existe.</div>";
            }
        }
    }
}

if ($step === 3 && isset($_GET['resend'])) {
    $_SESSION['signup_code'] = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

$signup = $_SESSION['signup'] ?? ['name' => '', 'email' => '', 'dob' => ''];
$emailMask = $signup['email'] ? preg_replace('/(.).+(@.+)/', '$1***$2', $signup['email']) : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Inscrever-se - <?= SITE_NAME ?></title>
	<link rel="stylesheet" href="style.css?v=<?= time() ?>">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
	<div class="x-auth">
		<div class="x-auth-wrap">
			<?php if ($step === 1): ?>
				<h1 class="x-title">Criar sua conta</h1>
				<form method="post" class="x-form">
					<label class="x-label">Nome</label>
					<input class="x-input" name="name" maxlength="50" value="<?= htmlspecialchars($signup['name']) ?>" required>
					<label class="x-label">E-mail</label>
					<input class="x-input" type="email" name="email" value="<?= htmlspecialchars($signup['email']) ?>" required>
					<label class="x-label">Data de nascimento</label>
					<input class="x-input" type="date" name="dob" value="<?= htmlspecialchars($signup['dob']) ?>" required>
					<?= $message ?>
					<button class="x-cta" type="submit">Avançar</button>
				</form>
				<div class="x-foot-note"><a href="login.php" class="x-link">Já tem uma conta? Entrar</a></div>
			<?php elseif ($step === 2): ?>
				<h1 class="x-title">Personalização</h1>
				<form method="post" class="x-form">
					<label class="x-check">
						<input type="checkbox" name="discoverable" checked>
						<span>Conecte-se com as pessoas que você conhece</span>
					</label>
					<label class="x-check">
						<input type="checkbox" name="ads" checked>
						<span>Anúncios personalizados</span>
					</label>
					<label class="x-check">
						<input type="checkbox" name="inferred">
						<span>Personalizar de acordo com sua identidade inferida</span>
					</label>
					<label class="x-check">
						<input type="checkbox" name="places">
						<span>Personalizar com base nos lugares onde você esteve</span>
					</label>
					<label class="x-check">
						<input type="checkbox" name="partners" checked>
						<span>Permitir compartilhamento com parceiros</span>
					</label>
					<button class="x-cta" type="submit">Avançar</button>
				</form>
			<?php elseif ($step === 3): ?>
				<h1 class="x-title">Enviamos um código para você</h1>
				<p class="x-sub">Insira-o abaixo para verificar <?= htmlspecialchars($emailMask) ?>.</p>
				<form method="post" class="x-form">
					<label class="x-label">Código de verificação</label>
					<input class="x-input" name="code" maxlength="6" pattern="\\d{6}" placeholder="000000" required>
					<?= $message ?>
					<button class="x-cta" type="submit">Avançar</button>
				</form>
				<div class="x-foot-note"><a class="x-link" href="signup.php?step=3&resend=1">Não recebeu o e-mail?</a></div>
			<?php elseif ($step === 4): ?>
				<h1 class="x-title">Você precisará de uma senha</h1>
				<p class="x-sub">É preciso ter 8 caracteres ou mais.</p>
				<form method="post" class="x-form">
					<div class="x-input-wrap">
						<input class="x-input" type="password" id="signup_password" name="password" minlength="8" required>
						<button type="button" class="toggle-password" data-target="#signup_password"><i class="far fa-eye"></i></button>
					</div>
					<?= $message ?>
					<button class="x-cta" type="submit">Inscrever-se</button>
				</form>
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