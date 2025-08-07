<?php
require 'config.php';
requireLogin();
requireRole(['ADMIN', 'SUPER_ADMIN', 'DEV']);

date_default_timezone_set('America/Sao_Paulo');

$ME = $_SESSION['user']['id'];
$MYROLE = $_SESSION['user']['role'];

function isDev() { return $_SESSION['user']['role'] === 'DEV'; }
function isSuperAdmin() { return $_SESSION['user']['role'] === 'SUPER_ADMIN'; }
function isAdmin() { return $_SESSION['user']['role'] === 'ADMIN'; }

function managedUserIds(PDO $pdo): array {
    $me = $_SESSION['user']['id'];
    if (isDev()) {
        return array_column($pdo->query("SELECT id FROM users")->fetchAll(), 'id');
    }
    if (isSuperAdmin()) {
        $ids = [$me];
        $query = $pdo->prepare("SELECT id FROM users WHERE role='ADMIN' AND created_by=?");
        $query->execute([$me]);
        return array_merge($ids, array_column($query->fetchAll(), 'id'));
    }
    return [$me];
}

function canManageGame(int $ownerId, PDO $pdo): bool {
    if (isDev()) return true;
    return in_array($ownerId, managedUserIds($pdo));
}

$action = $_POST['action'] ?? $_GET['action'] ?? 'home';
$message = '';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// PROCESSAMENTO DE AÇÕES CRUD
if ($action === 'create_game' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isAdmin() && !isSuperAdmin() && !isDev()) die('403');

    $errors = [];
    $required = ['title', 'description', 'language', 'version', 'engine', 'tags'];
    
    foreach ($required as $field) {
        if (empty(trim($_POST[$field] ?? ''))) {
            $errors[] = "Campo $field é obrigatório";
        }
    }

    $cover = null;
    if (!empty($_FILES['cover_image']['name'])) {
        $cover = uploadFile($_FILES['cover_image'], 'uploads/covers/');
        if (!$cover) $errors[] = 'Falha no upload da capa';
    } else {
        $errors[] = 'Capa é obrigatória';
    }

    if (!$errors) {
        // Criar slug único
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $_POST['title'])));
        
        $stmt = $pdo->prepare("
            INSERT INTO games (
                title, slug, description, cover_image, language, version, engine, 
                tags, download_url, download_url_windows, download_url_android, 
                download_url_linux, download_url_mac, censored, os_windows, 
                os_android, os_linux, os_mac, posted_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            sanitize($_POST['title']),
            $slug,
            sanitize($_POST['description']),
            $cover,
            sanitize($_POST['language']),
            sanitize($_POST['version']),
            sanitize($_POST['engine']),
            sanitize($_POST['tags']),
            sanitize($_POST['download_url'] ?? ''),
            sanitize($_POST['download_url_windows'] ?? ''),
            sanitize($_POST['download_url_android'] ?? ''),
            sanitize($_POST['download_url_linux'] ?? ''),
            sanitize($_POST['download_url_mac'] ?? ''),
            isset($_POST['censored']) ? 1 : 0,
            isset($_POST['os_windows']) ? 1 : 0,
            isset($_POST['os_android']) ? 1 : 0,
            isset($_POST['os_linux']) ? 1 : 0,
            isset($_POST['os_mac']) ? 1 : 0,
            $ME
        ]);

        $message = $result 
            ? "<div class='alert alert-success'><i class='fas fa-check'></i> Jogo criado com sucesso!</div>"
            : "<div class='alert alert-error'>Erro ao criar jogo.</div>";
        $action = 'home';
    } else {
        $message = "<div class='alert alert-error'><i class='fas fa-times'></i> " . implode('<br>', $errors) . "</div>";
    }
}

if ($action === 'delete_game' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['game_id'];
    $game = $pdo->prepare("SELECT * FROM games WHERE id=?")->execute([$id]);
    $game = $pdo->prepare("SELECT * FROM games WHERE id=?")->fetch();
    
    if ($game && canManageGame($game['posted_by'], $pdo)) {
        $pdo->prepare("DELETE FROM games WHERE id=?")->execute([$id]);
        // Limpar arquivos
        if (file_exists('uploads/covers/' . $game['cover_image'])) {
            unlink('uploads/covers/' . $game['cover_image']);
        }
        $message = "<div class='alert alert-success'><i class='fas fa-check'></i> Jogo excluído!</div>";
    }
    $action = 'home';
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" href="https://i.imgur.com/QyZKduC.png" type="image/png">
</head>
<body>
    <!-- Header moderno -->
    <header class="header">
        <div class="header-content">
            <a href="index.php" class="logo">
                Renx-Play
            </a>
            
            <nav class="nav">
                <a href="index.php" class="nav-link">
                    <i class="fas fa-home"></i>
                    Games
                </a>
                
                <a href="dashboard.php" class="nav-link active">
                    <i class="fas fa-cog"></i>
                    Admin
                </a>
                
                <div class="user-dropdown">
                    <button class="user-dropdown-toggle" onclick="toggleUserDropdown()" aria-expanded="false">
                        <i class="fas fa-user-circle"></i>
                        <span><?= $_SESSION['user']['username'] ?></span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="user-dropdown-menu" id="userDropdownMenu">
                        <a href="profile.php" class="dropdown-item">
                            <i class="fas fa-user-cog"></i> <span>Perfil</span>
                        </a>
                        <button onclick="toggleTheme()" class="dropdown-item theme-toggle-btn">
                            <i class="fas fa-moon theme-icon"></i> <span>Tema</span>
                        </button>
                        <a href="auth.php?action=logout" class="dropdown-item" onclick="return confirm('Deseja realmente sair?')">
                            <i class="fas fa-sign-out-alt"></i> <span>Sair</span>
                        </a>
                    </div>
                </div>
            </nav>
        </div>
    </header>

    <!-- Conteúdo principal -->
    <div class="container">
        <div class="admin-header">
            <h1>Admin Dashboard</h1>
            
            <?php if ($action === 'home'): ?>
                <a href="?action=create_game" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Add Game
                </a>
            <?php endif; ?>
        </div>

        <?= $message ?>

        <?php if ($action === 'home'): ?>
            <!-- Lista de jogos -->
            <?php
            $ids = managedUserIds($pdo);
            $ph = str_repeat('?,', count($ids) - 1) . '?';
            
            $stmt = $pdo->prepare("
                SELECT g.*, u.username AS author 
                FROM games g 
                JOIN users u ON g.posted_by = u.id 
                WHERE g.posted_by IN ($ph) 
                ORDER BY g.created_at DESC
            ");
            $stmt->execute($ids);
            $games = $stmt->fetchAll();
            
            if ($games): ?>
                <div class="admin-games-grid">
                    <?php foreach ($games as $game): ?>
                        <div class="admin-card">
                            <div class="admin-card-image">
                                <img src="uploads/covers/<?= htmlspecialchars($game['cover_image']) ?>" 
                                     alt="<?= htmlspecialchars($game['title']) ?>"
                                     loading="lazy">
                            </div>
                            
                            <div class="admin-card-content">
                                <h3 class="card-title"><?= htmlspecialchars($game['title']) ?></h3>
                                
                                <p class="text-sm" style="color: hsl(var(--muted-foreground)); margin-bottom: 0.5rem;">
                                    <?= htmlspecialchars($game['author']) ?>
                                </p>
                                
                                <p class="card-description">
                                    <?= htmlspecialchars(substr($game['description'], 0, 80)) ?>...
                                </p>
                                
                                <div style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.75rem; font-size: 0.75rem; color: hsl(var(--muted-foreground));">
                                    <span>
                                        <i class="fas fa-download"></i>
                                        <?= $game['downloads_count'] ?? 0 ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-calendar"></i>
                                        <?= date('d/m/Y', strtotime($game['created_at'])) ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="admin-card-footer">
                                <a href="?action=edit_game&id=<?= $game['id'] ?>" class="btn btn-sm btn-outline">
                                    <i class="fas fa-edit"></i>
                                    Edit
                                </a>
                                
                                <a href="../uploads/covers/<?= $game['cover_image'] ?>" target="_blank" class="btn btn-sm btn-outline">
                                    <i class="fas fa-image"></i>
                                    Imagens
                                </a>
                                
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Tem certeza que deseja excluir este jogo?')">
                                    <input type="hidden" name="action" value="delete_game">
                                    <input type="hidden" name="game_id" value="<?= $game['id'] ?>">
                                    <button type="submit" class="btn btn-sm" style="background: hsl(0 84% 60%); color: white; border-color: hsl(0 84% 60%);">
                                        <i class="fas fa-trash"></i>
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 3rem; color: hsl(var(--muted-foreground));">
                    <p>Nenhum jogo encontrado.</p>
                    <a href="?action=create_game" class="btn btn-primary" style="margin-top: 1rem;">
                        <i class="fas fa-plus"></i>
                        Criar primeiro jogo
                    </a>
                </div>
            <?php endif; ?>

        <?php elseif ($action === 'create_game'): ?>
            <!-- Formulário de criação -->
            <div class="card">
                <div class="card-content">
                    <h2 style="margin-bottom: 1.5rem;">
                        <i class="fas fa-plus"></i>
                        Create New Game
                    </h2>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="create_game">
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                            <div class="form-group">
                                <label for="title">Título *</label>
                                <input type="text" id="title" name="title" required 
                                       value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="engine">Engine</label>
                                <select id="engine" name="engine" required>
                                    <option value="REN'PY" <?= ($_POST['engine'] ?? '') === "REN'PY" ? 'selected' : '' ?>>REN'PY</option>
                                    <option value="UNITY" <?= ($_POST['engine'] ?? '') === 'UNITY' ? 'selected' : '' ?>>Unity</option>
                                    <option value="RPG_MAKER" <?= ($_POST['engine'] ?? '') === 'RPG_MAKER' ? 'selected' : '' ?>>RPG Maker</option>
                                    <option value="OTHER" <?= ($_POST['engine'] ?? '') === 'OTHER' ? 'selected' : '' ?>>Outro</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="description">Descrição *</label>
                            <textarea id="description" name="description" rows="4" required 
                                      placeholder="Descreva o jogo..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="cover_image">Imagem URL *</label>
                            <input type="file" id="cover_image" name="cover_image" accept="image/*" required>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                            <div class="form-group">
                                <label for="version">Versão</label>
                                <input type="text" id="version" name="version" value="<?= htmlspecialchars($_POST['version'] ?? 'v1.0') ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="language">Idioma</label>
                                <select id="language" name="language" required>
                                    <option value="English" <?= ($_POST['language'] ?? '') === 'English' ? 'selected' : '' ?>>English</option>
                                    <option value="Portuguese" <?= ($_POST['language'] ?? '') === 'Portuguese' ? 'selected' : '' ?>>Português</option>
                                    <option value="Spanish" <?= ($_POST['language'] ?? '') === 'Spanish' ? 'selected' : '' ?>>Español</option>
                                    <option value="Other" <?= ($_POST['language'] ?? '') === 'Other' ? 'selected' : '' ?>>Outro</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Sistemas Operacionais</label>
                            <div style="display: flex; gap: 1rem; margin-top: 0.5rem;">
                                <label style="display: flex; align-items: center; gap: 0.5rem;">
                                    <input type="checkbox" name="os_windows" value="1" <?= isset($_POST['os_windows']) ? 'checked' : 'checked' ?>>
                                    <i class="fab fa-windows"></i> Windows
                                </label>
                                <label style="display: flex; align-items: center; gap: 0.5rem;">
                                    <input type="checkbox" name="os_android" value="1" <?= isset($_POST['os_android']) ? 'checked' : '' ?>>
                                    <i class="fab fa-android"></i> Android
                                </label>
                                <label style="display: flex; align-items: center; gap: 0.5rem;">
                                    <input type="checkbox" name="os_linux" value="1" <?= isset($_POST['os_linux']) ? 'checked' : '' ?>>
                                    <i class="fab fa-linux"></i> Linux
                                </label>
                                <label style="display: flex; align-items: center; gap: 0.5rem;">
                                    <input type="checkbox" name="os_mac" value="1" <?= isset($_POST['os_mac']) ? 'checked' : '' ?>>
                                    <i class="fab fa-apple"></i> Mac
                                </label>
                            </div>
                        </div>
                        
                        <div style="border: 1px solid hsl(var(--border)); border-radius: var(--radius); padding: 1rem; margin-bottom: 1rem;">
                            <h4 style="margin-bottom: 1rem;">Links de Download por Plataforma</h4>
                            
                            <div class="form-group">
                                <label for="download_url_windows">
                                    <i class="fab fa-windows"></i> Link para download Windows
                                </label>
                                <input type="url" id="download_url_windows" name="download_url_windows" 
                                       placeholder="https://..." value="<?= htmlspecialchars($_POST['download_url_windows'] ?? '') ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="download_url_android">
                                    <i class="fab fa-android"></i> Link para download Android
                                </label>
                                <input type="url" id="download_url_android" name="download_url_android" 
                                       placeholder="https://..." value="<?= htmlspecialchars($_POST['download_url_android'] ?? '') ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="download_url_linux">
                                    <i class="fab fa-linux"></i> Link para download Linux
                                </label>
                                <input type="url" id="download_url_linux" name="download_url_linux" 
                                       placeholder="https://..." value="<?= htmlspecialchars($_POST['download_url_linux'] ?? '') ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="download_url_mac">
                                    <i class="fab fa-apple"></i> Link para download Mac
                                </label>
                                <input type="url" id="download_url_mac" name="download_url_mac" 
                                       placeholder="https://..." value="<?= htmlspecialchars($_POST['download_url_mac'] ?? '') ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="download_url">
                                    <i class="fas fa-download"></i> Link Genérico (opcional)
                                </label>
                                <input type="url" id="download_url" name="download_url" 
                                       placeholder="https://..." value="<?= htmlspecialchars($_POST['download_url'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="tags">Tags (separadas por vírgula)</label>
                            <input type="text" id="tags" name="tags" 
                                   placeholder="Adult,Visual Novel" 
                                   value="<?= htmlspecialchars($_POST['tags'] ?? 'Adult,Visual Novel') ?>">
                        </div>
                        
                        <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                Criar Jogo
                            </button>
                            
                            <a href="?action=home" class="btn btn-secondary">
                                Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // As funções toggleUserDropdown e toggleTheme estão no script.js
    </script>
    <script src="script.js?v=<?= time() ?>"></script>
</body>
</html>
