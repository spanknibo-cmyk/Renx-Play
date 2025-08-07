<?php
require_once 'config.php';   // onde estão as funções/constantes
requireLogin();              // bloqueia não-logados

$user = $_SESSION['user'];   // dados em sessão

// ----- PROCESSA ATUALIZAÇÃO -----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken($_POST['csrf_token'])) {
    $newName = sanitize($_POST['username']);
    $newPass = $_POST['password'];
    
    // validações simples
    if (strlen($newName) < 3)   $errors[] = "Nome de usuário muito curto.";
    if ($newPass && strlen($newPass) < MIN_PASSWORD_LENGTH) $errors[] = "Senha muito curta.";
    
    if (empty($errors)) {
        try {
            // atualiza nome
            $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
            $stmt->execute([$newName, $user['id']]);
            
            // atualiza senha se enviada
            if ($newPass) {
                $hash = password_hash($newPass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hash, $user['id']]);
            }
            
            // reflete na sessão
            $_SESSION['user']['username'] = $newName;
            $success = "Perfil atualizado com sucesso!";
        } catch (PDOException $e) {
            $errors[] = "Erro ao salvar: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu perfil - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Header do perfil com navegação simples -->
    <header class="header">
        <div class="header-content">
            <a href="index.php" class="logo">
                Renxplay Teste
            </a>
            
            <nav class="nav">
                <a href="index.php" class="nav-link">
                    <i class="fas fa-home"></i>
                    Início
                </a>
                
                <?php if (isLoggedIn()): ?>
                    <div class="user-dropdown">
                        <span class="nav-link user-info">
                            <i class="fas fa-user"></i>
                            <?= htmlspecialchars($user['username']) ?> ADMIN
                        </span>
                    </div>
                    
                    <a href="dashboard.php" class="nav-link">
                        <i class="fas fa-cog"></i>
                        Painel
                    </a>
                    
                    <a href="profile.php" class="nav-link active">
                        <i class="fas fa-user-cog"></i>
                        Perfil
                    </a>
                    
                    <a href="auth.php?action=logout" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i>
                        Sair
                    </a>
                    
                    <button onclick="toggleTheme()" class="nav-link theme-toggle">
                        <i class="fas fa-moon"></i>
                        Tema
                    </button>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <div class="container" style="max-width: 600px; padding-top: 2rem;">
        <div class="profile-card">
            <div class="profile-header">
                <h2><i class="fas fa-user-cog"></i> Meu perfil</h2>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-times-circle"></i> <?= implode('<br>', $errors) ?>
                </div>
            <?php elseif (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= $success ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <?= csrfField(); ?>
                
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Nome de usuário</label>
                    <input type="text" name="username" value="<?= sanitize($user['username']); ?>" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Nova senha <small>(opcional)</small></label>
                    <input type="password" name="password" placeholder="Deixe em branco para manter">
                </div>

                <button type="submit" class="btn btn-primary btn-full">
                    <i class="fas fa-save"></i> Salvar alterações
                </button>
            </form>
        </div>

        <!-- Informações do site -->
        <div class="site-info">
            <div class="site-logo">
                <i class="fas fa-gamepad"></i>
                <span>Renxplay Teste</span>
            </div>
            <p>A melhor plataforma para jogos Ren'Py.</p>
            
            <div class="site-links">
                <h4><i class="fas fa-info-circle"></i> Sobre</h4>
                <ul>
                    <li><a href="#">Contato</a></li>
                </ul>
            </div>
            
            <div class="site-links">
                <h4><i class="fas fa-shield-alt"></i> Legal</h4>
                <ul>
                    <li><a href="#">Termos de Uso</a></li>
                    <li><a href="#">Política de Privacidade</a></li>
                    <li><a href="#">Sobre</a></li>
                </ul>
            </div>
            
            <div class="site-footer">
                <p>&copy; <?= date('Y') ?> Renxplay Teste. Desenvolvido com <i class="fas fa-heart" style="color: #e74c3c;"></i> para a comunidade.</p>
                <p class="disclaimer">
                    <i class="fas fa-exclamation-triangle"></i> 
                    Conteúdo adulto. Proibido para menores de 18 anos.
                </p>
            </div>
        </div>
    </div>

    <script>
        function toggleTheme() {
            document.body.classList.toggle('dark');
        }
    </script>
</body>
</html>
