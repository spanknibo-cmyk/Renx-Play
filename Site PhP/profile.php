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

<div class="min-h-screen bg-background text-foreground flex items-center justify-center py-8">
    <div class="w-full max-w-md">
        <div class="card">
            <div class="card-header text-center">
                <div class="flex items-center justify-center gap-3 mb-2">
                    <div class="w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </div>
                </div>
                <h2 class="card-title">Meu Perfil</h2>
                <p class="text-muted-foreground">Gerencie suas informações pessoais</p>
            </div>

            <div class="card-content space-y-6">
                <?php if (!empty($errors)): ?>
                    <div class="bg-red-500/10 border border-red-500/20 text-red-700 dark:text-red-400 px-4 py-3 rounded-md">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="15" y1="9" x2="9" y2="15"></line>
                                <line x1="9" y1="9" x2="15" y2="15"></line>
                            </svg>
                            <div><?= implode('<br>', $errors) ?></div>
                        </div>
                    </div>
                <?php elseif (!empty($success)): ?>
                    <div class="bg-green-500/10 border border-green-500/20 text-green-700 dark:text-green-400 px-4 py-3 rounded-md">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 12l2 2 4-4"></path>
                                <circle cx="12" cy="12" r="10"></circle>
                            </svg>
                            <div><?= $success ?></div>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-4">
                    <?= csrfField(); ?>
                    
                    <div class="space-y-2">
                        <label class="text-sm font-medium">
                            <svg class="w-4 h-4 inline mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            Nome de usuário
                        </label>
                        <input 
                            type="text" 
                            name="username" 
                            value="<?= sanitize($user['username']); ?>" 
                            required
                            class="w-full px-3 py-2 border border-input bg-background rounded-md focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent"
                        >
                    </div>

                    <div class="space-y-2">
                        <label class="text-sm font-medium">
                            <svg class="w-4 h-4 inline mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <circle cx="12" cy="16" r="1"></circle>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                            Nova senha
                            <span class="text-muted-foreground text-xs ml-1">(opcional)</span>
                        </label>
                        <input 
                            type="password" 
                            name="password" 
                            placeholder="Deixe em branco para manter"
                            class="w-full px-3 py-2 border border-input bg-background rounded-md focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent"
                        >
                    </div>

                    <button 
                        type="submit" 
                        class="w-full bg-primary text-primary-foreground hover:bg-primary/90 px-4 py-2 rounded-md font-medium transition-colors flex items-center justify-center gap-2"
                    >
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                            <polyline points="17,21 17,13 7,13 7,21"></polyline>
                            <polyline points="7,3 7,8 15,8"></polyline>
                        </svg>
                        Salvar alterações
                    </button>
                </form>

                <div class="pt-4 border-t border-border">
                    <div class="flex items-center justify-between text-sm text-muted-foreground">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
                                <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
                            </svg>
                            <span>Função: <?= $user['role'] ?></span>
                        </div>
                        <a href="dashboard.php" class="text-primary hover:underline">
                            Ir para Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php renderFooter(); ?>
