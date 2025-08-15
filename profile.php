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

// ----- INTERFACE -----
renderHeader('Meu perfil', 'Gerencie seus dados de usuário');
?>

<div class="container profile-container">
    <div class="auth-card profile-card">
        <div class="auth-header">
            <h2><i class="fas fa-user-cog"></i> Meu perfil</h2>
        </div>

        <div class="alert" style="display:flex;align-items:center;gap:.5rem;margin:0 0 1rem 0;color:hsl(var(--muted-foreground));">
            <i class="fas fa-envelope"></i>
            <span><?= htmlspecialchars($user['email'] ?? '') ?></span>
            <span style="margin:0 .5rem;opacity:.5;">•</span>
            <span class="role role-<?= htmlspecialchars($user['role']) ?>" style="font-weight:600;"><?= htmlspecialchars($user['role']) ?></span>
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
</div>

<?php renderFooter(); ?>
