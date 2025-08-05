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
        $message = "<div class='bg-red-500/10 border border-red-500/20 text-red-700 dark:text-red-400 px-4 py-3 rounded-md mb-4'>
                     <div class='flex items-center gap-2'>
                         <svg class='w-5 h-5' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                             <circle cx='12' cy='12' r='10'></circle>
                             <line x1='15' y1='9' x2='9' y2='15'></line>
                             <line x1='9' y1='9' x2='15' y2='15'></line>
                         </svg>
                         <span>Credenciais inválidas!</span>
                     </div>
                   </div>";
    }
}

// PROCESSAR REGISTRO
if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    
    if ($password !== $confirmPassword) {
        $message = "<div class='bg-red-500/10 border border-red-500/20 text-red-700 dark:text-red-400 px-4 py-3 rounded-md mb-4'>
                     <div class='flex items-center gap-2'>
                         <svg class='w-5 h-5' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                             <circle cx='12' cy='12' r='10'></circle>
                             <line x1='15' y1='9' x2='9' y2='15'></line>
                             <line x1='9' y1='9' x2='15' y2='15'></line>
                         </svg>
                         <span>Senhas não coincidem!</span>
                     </div>
                   </div>";
    } else {
        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $stmt->execute([$username, $email, $hashedPassword]);
            $message = "<div class='bg-green-500/10 border border-green-500/20 text-green-700 dark:text-green-400 px-4 py-3 rounded-md mb-4'>
                         <div class='flex items-center gap-2'>
                             <svg class='w-5 h-5' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                                 <path d='M9 12l2 2 4-4'></path>
                                 <circle cx='12' cy='12' r='10'></circle>
                             </svg>
                             <span>Conta criada com sucesso! Faça login.</span>
                         </div>
                       </div>";
            $action = 'login';
        } catch (PDOException $e) {
            $message = "<div class='bg-red-500/10 border border-red-500/20 text-red-700 dark:text-red-400 px-4 py-3 rounded-md mb-4'>
                         <div class='flex items-center gap-2'>
                             <svg class='w-5 h-5' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                                 <circle cx='12' cy='12' r='10'></circle>
                                 <line x1='15' y1='9' x2='9' y2='15'></line>
                                 <line x1='9' y1='9' x2='15' y2='15'></line>
                             </svg>
                             <span>Usuário ou email já existe!</span>
                         </div>
                       </div>";
        }
    }
}

renderHeader('Autenticação');
?>

<div class="min-h-screen bg-background text-foreground flex items-center justify-center py-8">
    <div class="w-full max-w-md">
        <div class="card">
            <div class="card-header text-center">
                <div class="flex items-center justify-center gap-3 mb-4">
                    <div class="w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center">
                        <svg class="w-6 h-6 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"></circle>
                            <path d="M12 16v-4"></path>
                            <path d="M12 8h.01"></path>
                        </svg>
                    </div>
                </div>
                <h2 class="card-title"><?= $action === 'register' ? 'Criar Conta' : 'Entrar' ?></h2>
                <p class="text-muted-foreground">
                    <?= $action === 'register' ? 'Crie sua conta para continuar' : 'Entre com sua conta para continuar' ?>
                </p>
                
                <!-- Tabs -->
                <div class="flex bg-muted rounded-lg p-1 mt-4">
                    <a href="?action=login" 
                       class="flex-1 text-center py-2 px-3 rounded-md text-sm font-medium transition-colors <?= $action === 'login' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground' ?>">
                        Login
                    </a>
                    <a href="?action=register" 
                       class="flex-1 text-center py-2 px-3 rounded-md text-sm font-medium transition-colors <?= $action === 'register' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground' ?>">
                        Registrar
                    </a>
                </div>
            </div>

            <div class="card-content">
                <?= $message ?>
                
                <?php if ($action === 'login'): ?>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="login">
                        
                        <div class="space-y-2">
                            <label class="text-sm font-medium">
                                <svg class="w-4 h-4 inline mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                                Usuário ou Email
                            </label>
                            <input 
                                type="text" 
                                name="username" 
                                required
                                class="w-full px-3 py-2 border border-input bg-background rounded-md focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent"
                                placeholder="Digite seu usuário ou email"
                            >
                        </div>
                        
                        <div class="space-y-2">
                            <label class="text-sm font-medium">
                                <svg class="w-4 h-4 inline mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                    <circle cx="12" cy="16" r="1"></circle>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                </svg>
                                Senha
                            </label>
                            <input 
                                type="password" 
                                name="password" 
                                required
                                class="w-full px-3 py-2 border border-input bg-background rounded-md focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent"
                                placeholder="Digite sua senha"
                            >
                        </div>
                        
                        <button 
                            type="submit" 
                            class="w-full bg-primary text-primary-foreground hover:bg-primary/90 px-4 py-2 rounded-md font-medium transition-colors flex items-center justify-center gap-2"
                        >
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                                <polyline points="10,17 15,12 10,7"></polyline>
                                <line x1="15" y1="12" x2="3" y2="12"></line>
                            </svg>
                            Entrar
                        </button>
                    </form>
                    
                <?php else: ?>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="register">
                        
                        <div class="space-y-2">
                            <label class="text-sm font-medium">
                                <svg class="w-4 h-4 inline mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                                Nome de Usuário
                            </label>
                            <input 
                                type="text" 
                                name="username" 
                                required
                                class="w-full px-3 py-2 border border-input bg-background rounded-md focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent"
                                placeholder="Escolha um nome de usuário"
                            >
                        </div>
                        
                        <div class="space-y-2">
                            <label class="text-sm font-medium">
                                <svg class="w-4 h-4 inline mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                    <polyline points="22,6 12,13 2,6"></polyline>
                                </svg>
                                Email
                            </label>
                            <input 
                                type="email" 
                                name="email" 
                                required
                                class="w-full px-3 py-2 border border-input bg-background rounded-md focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent"
                                placeholder="Digite seu email"
                            >
                        </div>
                        
                        <div class="space-y-2">
                            <label class="text-sm font-medium">
                                <svg class="w-4 h-4 inline mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                    <circle cx="12" cy="16" r="1"></circle>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                </svg>
                                Senha
                            </label>
                            <input 
                                type="password" 
                                name="password" 
                                required
                                class="w-full px-3 py-2 border border-input bg-background rounded-md focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent"
                                placeholder="Crie uma senha"
                            >
                        </div>
                        
                        <div class="space-y-2">
                            <label class="text-sm font-medium">
                                <svg class="w-4 h-4 inline mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                    <circle cx="12" cy="16" r="1"></circle>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                </svg>
                                Confirmar Senha
                            </label>
                            <input 
                                type="password" 
                                name="confirm_password" 
                                required
                                class="w-full px-3 py-2 border border-input bg-background rounded-md focus:outline-none focus:ring-2 focus:ring-ring focus:border-transparent"
                                placeholder="Confirme sua senha"
                            >
                        </div>
                        
                        <button 
                            type="submit" 
                            class="w-full bg-primary text-primary-foreground hover:bg-primary/90 px-4 py-2 rounded-md font-medium transition-colors flex items-center justify-center gap-2"
                        >
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                <circle cx="9" cy="7" r="4"></circle>
                                <line x1="20" y1="8" x2="20" y2="14"></line>
                                <line x1="23" y1="11" x2="17" y2="11"></line>
                            </svg>
                            Criar Conta
                        </button>
                    </form>
                <?php endif; ?>
                
                <div class="mt-6 pt-4 border-t border-border text-center">
                    <a href="index.php" class="text-sm text-muted-foreground hover:text-foreground transition-colors">
                        ← Voltar ao início
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php renderFooter(); ?>
