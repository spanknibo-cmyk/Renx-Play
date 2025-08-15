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
    $required = ['title', 'description', 'version', 'engine'];
    
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

    // Idiomas múltiplos
    $languages = isset($_POST['languages']) && is_array($_POST['languages']) ? array_values(array_unique($_POST['languages'])) : [];
    $languagesJson = json_encode($languages, JSON_UNESCAPED_UNICODE);

    // Upload múltiplo de imagens extras
    $screenshots = [];
    if (!empty($_FILES['screenshots']['name'][0])) {
        $screenshots = uploadMultipleFiles($_FILES['screenshots'], 'uploads/screenshots/', MAX_SCREENSHOTS);
    }

    if (!$errors) {
        // Criar slug único
        $slugBase = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $_POST['title'])));
        $slug = $slugBase;
        // garantir unicidade
        $i = 1;
        while (true) {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM games WHERE slug=?");
            $chk->execute([$slug]);
            if ($chk->fetchColumn() == 0) break;
            $slug = $slugBase . '-' . (++$i);
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO games (
                title, slug, description, cover_image, language, version, engine,
                tags, download_url, download_url_windows, download_url_android,
                download_url_linux, download_url_mac, censored, os_windows,
                os_android, os_linux, os_mac, posted_by,
                developer_name, languages_multi, updated_at_custom, released_at_custom,
                patreon_url, discord_url, subscribestar_url, itch_url, kofi_url, bmc_url, steam_url,
                screenshots
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            sanitize($_POST['title']),
            $slug,
            sanitize($_POST['description']),
            $cover,
            sanitize($_POST['language'] ?? ''), // legacy single
            sanitize($_POST['version']),
            sanitize($_POST['engine']),
            sanitize($_POST['tags'] ?? ''),
            sanitize($_POST['download_url'] ?? ''),
            sanitize($_POST['download_url_windows'] ?? ''),
            sanitize($_POST['download_url_android'] ?? ''),
            sanitize($_POST['download_url_linux'] ?? ''),
            sanitize($_POST['download_url_mac'] ?? ''),
            ($_POST['censored'] ?? 'no') === 'yes' ? 1 : 0,
            isset($_POST['os_windows']) ? 1 : 0,
            isset($_POST['os_android']) ? 1 : 0,
            isset($_POST['os_linux']) ? 1 : 0,
            isset($_POST['os_mac']) ? 1 : 0,
            $ME,
            sanitize($_POST['developer'] ?? ''),
            $languagesJson,
            !empty($_POST['updated_at']) ? $_POST['updated_at'] : null,
            !empty($_POST['released_at']) ? $_POST['released_at'] : null,
            sanitize($_POST['patreon'] ?? ''),
            sanitize($_POST['discord'] ?? ''),
            sanitize($_POST['subscribestar'] ?? ''),
            sanitize($_POST['itch'] ?? ''),
            sanitize($_POST['kofi'] ?? ''),
            sanitize($_POST['bmc'] ?? ''),
            sanitize($_POST['steam'] ?? ''),
            $screenshots ? json_encode($screenshots) : null
        ]);

        $message = $result 
            ? "<div class='alert alert-success'><i class='fas fa-check'></i> Jogo criado com sucesso!</div>"
            : "<div class='alert alert-error'>Erro ao criar jogo.</div>";
        $action = 'home';
    } else {
        $message = "<div class='alert alert-error'><i class='fas fa-times'></i> " . implode('<br>', $errors) . "</div>";
    }
}

if ($action === 'update_game' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['game_id'];
    $gstmt = $pdo->prepare("SELECT * FROM games WHERE id=?");
    $gstmt->execute([$id]);
    $g = $gstmt->fetch();
    if (!$g || !canManageGame($g['posted_by'], $pdo)) { die('403'); }

    $cover = $g['cover_image'];
    if (!empty($_FILES['cover_image']['name'])) {
        $new = uploadFile($_FILES['cover_image'], 'uploads/covers/');
        if ($new) {
            if ($cover && file_exists('uploads/covers/' . $cover)) @unlink('uploads/covers/' . $cover);
            $cover = $new;
        }
    }

    $languages = isset($_POST['languages']) && is_array($_POST['languages']) ? array_values(array_unique($_POST['languages'])) : [];
    $languagesJson = json_encode($languages, JSON_UNESCAPED_UNICODE);

    // new screenshots appended
    $screenshotsExisting = !empty($g['screenshots']) ? json_decode($g['screenshots'], true) : []; if (isset($_POST['remove_screenshots']) && is_array($_POST['remove_screenshots'])) { $screenshotsExisting = array_values(array_diff($screenshotsExisting, $_POST['remove_screenshots'])); }
    $screenshotsNew = [];
    if (!empty($_FILES['screenshots']['name'][0])) {
        $remaining = MAX_SCREENSHOTS - count($screenshotsExisting);
        if ($remaining > 0) {
            $screenshotsNew = uploadMultipleFiles($_FILES['screenshots'], 'uploads/screenshots/', $remaining);
        }
    }
    $screenshotsAll = (isset($_POST['replace_all']) && $_POST['replace_all']) ? ($screenshotsNew ?: []) : array_values(array_filter(array_merge($screenshotsExisting ?: [], $screenshotsNew ?: [])));

    $stmt = $pdo->prepare("UPDATE games SET title=?, description=?, cover_image=?, language=?, version=?, engine=?, tags=?, download_url=?, download_url_windows=?, download_url_android=?, download_url_linux=?, download_url_mac=?, censored=?, os_windows=?, os_android=?, os_linux=?, os_mac=?, developer_name=?, languages_multi=?, updated_at_custom=?, released_at_custom=?, patreon_url=?, discord_url=?, subscribestar_url=?, itch_url=?, kofi_url=?, bmc_url=?, steam_url=?, screenshots=? WHERE id=?");

    // apagar arquivos removidos
    $existingFiles = !empty($g['screenshots']) ? (json_decode($g['screenshots'], true) ?: []) : [];
    $toDelete = array_diff($existingFiles, $screenshotsAll);
    foreach ($toDelete as $del) {
        $p = 'uploads/screenshots/' . $del;
        if (file_exists($p)) @unlink($p);
        $t = 'uploads/screenshots/thumb_' . $del;
        if (file_exists($t)) @unlink($t);
    }

    $ok = $stmt->execute([
        sanitize($_POST['title']),
        sanitize($_POST['description']),
        $cover,
        sanitize($_POST['language'] ?? ''),
        sanitize($_POST['version']),
        sanitize($_POST['engine']),
        sanitize($_POST['tags'] ?? ''),
        sanitize($_POST['download_url'] ?? ''),
        sanitize($_POST['download_url_windows'] ?? ''),
        sanitize($_POST['download_url_android'] ?? ''),
        sanitize($_POST['download_url_linux'] ?? ''),
        sanitize($_POST['download_url_mac'] ?? ''),
        ($_POST['censored'] ?? 'no') === 'yes' ? 1 : 0,
        isset($_POST['os_windows']) ? 1 : 0,
        isset($_POST['os_android']) ? 1 : 0,
        isset($_POST['os_linux']) ? 1 : 0,
        isset($_POST['os_mac']) ? 1 : 0,
        sanitize($_POST['developer'] ?? ''),
        $languagesJson,
        !empty($_POST['updated_at']) ? $_POST['updated_at'] : null,
        !empty($_POST['released_at']) ? $_POST['released_at'] : null,
        sanitize($_POST['patreon'] ?? ''),
        sanitize($_POST['discord'] ?? ''),
        sanitize($_POST['subscribestar'] ?? ''),
        sanitize($_POST['itch'] ?? ''),
        sanitize($_POST['kofi'] ?? ''),
        sanitize($_POST['bmc'] ?? ''),
        sanitize($_POST['steam'] ?? ''),
        $screenshotsAll ? json_encode($screenshotsAll) : null,
        $id
    ]);
    
    $message = $ok ? "<div class='alert alert-success'><i class='fas fa-check'></i> Jogo atualizado!</div>" : "<div class='alert alert-error'>Erro ao atualizar</div>";
    $action = 'home';
}

if ($action === 'delete_game' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['game_id'];
    $stmtG = $pdo->prepare("SELECT * FROM games WHERE id=?");
    $stmtG->execute([$id]);
    $game = $stmtG->fetch();
    
    if ($game && canManageGame($game['posted_by'], $pdo)) {
        $pdo->prepare("DELETE FROM games WHERE id=?")->execute([$id]);
        // Limpar arquivos
        if (!empty($game['cover_image']) && file_exists('uploads/covers/' . $game['cover_image'])) {
            @unlink('uploads/covers/' . $game['cover_image']);
        }
        if (!empty($game['screenshots'])) {
            $arr = json_decode($game['screenshots'], true) ?: [];
            foreach ($arr as $sc) {
                $p = 'uploads/screenshots/' . $sc;
                if (file_exists($p)) @unlink($p);
                $t = 'uploads/screenshots/thumb_' . $sc;
                if (file_exists($t)) @unlink($t);
            }
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
                    Jogos
                </a>
                
                <a href="dashboard.php" class="nav-link active">
                    <i class="fas fa-cog"></i>
                    Admin
                </a>
                
                <div class="user-dropdown">
                    <button class="nav-link" onclick="toggleDropdown()">
                        <i class="fas fa-user"></i>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="dropdown-content" id="userDropdown">
                        <a href="profile.php" class="dropdown-item">
                            <i class="fas fa-user-cog"></i>
                            Perfil
                        </a>
                        <a href="javascript:void(0)" class="dropdown-item theme-toggle" onclick="toggleTheme()">
                            <i class="fas fa-moon"></i>
                            Tema
                        </a>
                        <a href="auth.php?action=logout" class="dropdown-item">
                            <i class="fas fa-sign-out-alt"></i>
                            Sair
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
                                <?= renderImageTag('uploads/covers/' . $game['cover_image'], $game['title'], ['loading' => 'lazy']) ?>
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
                            <input type="file" id="coverInput" name="cover_image" accept=".jpg,.jpeg,.png,.webp,.gif,.svg,.avif" required>
                            <div id="coverPreview" style="margin-top:.5rem"></div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                            <div class="form-group">
                                <label for="version">Versão</label>
                                <input type="text" id="version" name="version" value="<?= htmlspecialchars($_POST['version'] ?? 'v1.0') ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>Idiomas</label>
                                <div style="display:flex; flex-wrap:wrap; gap:.75rem; margin-top:.5rem;">
                                    <?php $opts=['English'=>'Inglês','Portuguese'=>'Português','Russian'=>'Russo','Spanish'=>'Espanhol','All'=>'Todos']; foreach($opts as $val=>$lab): ?>
                                        <label style="display:flex; align-items:center; gap:.5rem;">
                                            <input type="checkbox" name="languages[]" value="<?= $val ?>"> <?= $lab ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <input type="hidden" name="language" value="<?= htmlspecialchars($_POST['language'] ?? 'English') ?>">
                            </div>
                        </div>
                        
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem; margin-bottom:1rem;">
                            <div class="form-group">
                                <label>Desenvolvedor</label>
                                <input type="text" name="developer" placeholder="Nome do criador">
                            </div>
                            <div class="form-group">
                                <label>Censura</label>
                                <div style="display:flex; gap:1rem; margin-top:.5rem;">
                                    <label><input type="radio" name="censored" value="no" checked> Não</label>
                                    <label><input type="radio" name="censored" value="yes"> Sim</label>
                                </div>
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem; margin-bottom:1rem;">
                            <div class="form-group">
                                <label>Atualização</label>
                                <input type="date" name="updated_at">
                            </div>
                            <div class="form-group">
                                <label>Lançamento</label>
                                <input type="date" name="released_at">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Imagens Extras (até 30)</label>
                            <input id="screenshotsInput" type="file" name="screenshots[]" accept=".jpg,.jpeg,.png,.webp,.gif,.svg,.avif" multiple>
                            <div id="screenshotsPreview" style="margin-top:.5rem"></div>
                        </div>

                        <div style="border: 1px solid hsl(var(--border)); border-radius: var(--radius); padding: 1rem; margin-bottom: 1rem;">
                            <h4 style="margin-bottom: 1rem;">Links do Criador</h4>
                            <div style="display:grid; grid-template-columns: repeat(2,1fr); gap:1rem;">
                                <div class="form-group"><label>Patreon</label><input type="url" name="patreon" placeholder="https://patreon.com/..."/></div>
                                <div class="form-group"><label>Discord</label><input type="url" name="discord" placeholder="https://discord.gg/..."/></div>
                                <div class="form-group"><label>SubscribeStar</label><input type="url" name="subscribestar" placeholder="https://subscribestar.adult/..."/></div>
                                <div class="form-group"><label>itch.io</label><input type="url" name="itch" placeholder="https://username.itch.io/game"/></div>
                                <div class="form-group"><label>Ko-fi</label><input type="url" name="kofi" placeholder="https://ko-fi.com/..."/></div>
                                <div class="form-group"><label>Buy Me a Coffee</label><input type="url" name="bmc" placeholder="https://www.buymeacoffee.com/..."/></div>
                                <div class="form-group"><label>Steam</label><input type="url" name="steam" placeholder="https://store.steampowered.com/app/..."/></div>
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
        <?php elseif ($action === 'edit_game' && isset($_GET['id'])): ?>
            <?php $eid=(int)$_GET['id']; $st=$pdo->prepare("SELECT * FROM games WHERE id=?"); $st->execute([$eid]); $eg=$st->fetch(); if(!$eg || !canManageGame($eg['posted_by'],$pdo)) { echo '<div class="alert alert-error">Jogo não encontrado</div>'; } else { ?>
            <div class="card">
                <div class="card-content">
                    <h2 style="margin-bottom: 1.5rem;"><i class="fas fa-edit"></i> Editar Jogo</h2>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_game">
                        <input type="hidden" name="game_id" value="<?= $eg['id'] ?>">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                            <div class="form-group"><label>Título</label><input type="text" name="title" value="<?= htmlspecialchars($eg['title']) ?>"/></div>
                            <div class="form-group"><label>Engine</label>
                                <select name="engine">
                                    <option value="REN'PY" <?= $eg['engine']==="REN'PY"?'selected':'' ?>>REN'PY</option>
                                    <option value="UNITY" <?= $eg['engine']==='UNITY'?'selected':'' ?>>UNITY</option>
                                    <option value="RPG_MAKER" <?= $eg['engine']==='RPG_MAKER'?'selected':'' ?>>RPG_MAKER</option>
                                    <option value="OTHER" <?= $eg['engine']==='OTHER'?'selected':'' ?>>OTHER</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group"><label>Descrição</label><textarea name="description" rows="4"><?= htmlspecialchars($eg['description']) ?></textarea></div>
                        <div class="form-group"><label>Capa</label><input id="editCoverInput" type="file" name="cover_image" accept=".jpg,.jpeg,.png,.webp,.gif,.svg,.avif"><div id="editCoverPreview" style="margin-top:.5rem"></div></div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                            <div class="form-group"><label>Versão</label><input type="text" name="version" value="<?= htmlspecialchars($eg['version']) ?>"></div>
                            <div class="form-group"><label>Idiomas</label>
                                <?php $langs = $eg['languages_multi']? json_decode($eg['languages_multi'], true):[]; $opts=['English'=>'Inglês','Portuguese'=>'Português','Russian'=>'Russo','Spanish'=>'Espanhol','All'=>'Todos']; ?>
                                <div style="display:grid; grid-template-columns: repeat(3, minmax(120px,1fr)); gap:.5rem; margin-top:.5rem;">
                                    <?php foreach($opts as $val=>$lab): ?>
                                        <label style="display:flex; align-items:center; gap:.4rem; padding:.25rem .5rem; border:1px solid hsl(var(--border)); border-radius:8px;">
                                            <input type="checkbox" name="languages[]" value="<?= $val ?>" <?= in_array($val,$langs??[])?'checked':''; ?>> <span><?= $lab ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <input type="hidden" name="language" value="<?= htmlspecialchars($eg['language'] ?? '') ?>">
                            </div>
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                            <div class="form-group"><label>Desenvolvedor</label><input type="text" name="developer" value="<?= htmlspecialchars($eg['developer_name'] ?? '') ?>"></div>
                            <div class="form-group"><label>Censura</label>
                                <div style="display:flex; gap:1rem; margin-top:.5rem;">
                                    <label><input type="radio" name="censored" value="no" <?= empty($eg['censored'])?'checked':''; ?>> Não</label>
                                    <label><input type="radio" name="censored" value="yes" <?= !empty($eg['censored'])?'checked':''; ?>> Sim</label>
                                </div>
                            </div>
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                            <div class="form-group"><label>Atualização</label><input type="date" name="updated_at" value="<?= htmlspecialchars($eg['updated_at_custom'] ?? '') ?>"></div>
                            <div class="form-group"><label>Lançamento</label><input type="date" name="released_at" value="<?= htmlspecialchars($eg['released_at_custom'] ?? '') ?>"></div>
                        </div>

                        <?php $existing = $eg['screenshots']? json_decode($eg['screenshots'], true):[]; ?>
                        <?php if ($existing): ?>
                        <div class="form-group">
                            <label>Imagens extras atuais</label>
                            <div class="screenshot-gallery" style="margin-top:.5rem;">
                                <?php foreach ($existing as $i => $sc): $path = 'uploads/screenshots/' . $sc; ?>
                                    <div class="screenshot-item" style="position:relative;">
                                        <?= renderImageTag($path, '', ['class' => 'screenshot']) ?>
                                        <label style="position:absolute;top:6px;right:6px;background:rgba(0,0,0,.6);color:#fff;padding:.25rem .5rem;border-radius:6px;font-size:.75rem;">
                                            <input type="checkbox" name="remove_screenshots[]" value="<?= htmlspecialchars($sc) ?>"> remover
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="form-group"><label>Imagens extras (adicionar novas)</label><input id="editScreenshotsInput" type="file" name="screenshots[]" multiple accept=".jpg,.jpeg,.png,.webp,.gif,.svg,.avif"><div id="editScreenshotsPreview" style="margin-top:.5rem"></div></div>
                        <div class="form-group" style="margin-top:.25rem">
                            <label style="display:flex;align-items:center;gap:.5rem;"><input type="checkbox" name="replace_all" value="1"> Substituir todas as imagens existentes por estas novas</label>
                        </div>

                        <div style="border:1px solid hsl(var(--border)); border-radius:var(--radius); padding:1rem; margin-bottom:1rem;">
                            <h4 style="margin-bottom:1rem;">Links do Criador</h4>
                            <div style="display:grid; grid-template-columns: repeat(2,1fr); gap:1rem;">
                                <div class="form-group"><label>Patreon</label><input type="url" name="patreon" value="<?= htmlspecialchars($eg['patreon_url'] ?? '') ?>"></div>
                                <div class="form-group"><label>Discord</label><input type="url" name="discord" value="<?= htmlspecialchars($eg['discord_url'] ?? '') ?>"></div>
                                <div class="form-group"><label>SubscribeStar</label><input type="url" name="subscribestar" value="<?= htmlspecialchars($eg['subscribestar_url'] ?? '') ?>"></div>
                                <div class="form-group"><label>itch.io</label><input type="url" name="itch" value="<?= htmlspecialchars($eg['itch_url'] ?? '') ?>"></div>
                                <div class="form-group"><label>Ko-fi</label><input type="url" name="kofi" value="<?= htmlspecialchars($eg['kofi_url'] ?? '') ?>"></div>
                                <div class="form-group"><label>Buy Me a Coffee</label><input type="url" name="bmc" value="<?= htmlspecialchars($eg['bmc_url'] ?? '') ?>"></div>
                                <div class="form-group"><label>Steam</label><input type="url" name="steam" value="<?= htmlspecialchars($eg['steam_url'] ?? '') ?>"></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Sistemas Operacionais</label>
                            <div style="display: flex; gap: 1rem; margin-top: 0.5rem;">
                                <label style="display: flex; align-items: center; gap: 0.5rem;">
                                    <input type="checkbox" name="os_windows" value="1" <?= !empty($eg['os_windows']) ? 'checked' : '' ?>>
                                    <i class="fab fa-windows"></i> Windows
                                </label>
                                <label style="display: flex; align-items: center; gap: 0.5rem;">
                                    <input type="checkbox" name="os_android" value="1" <?= !empty($eg['os_android']) ? 'checked' : '' ?>>
                                    <i class="fab fa-android"></i> Android
                                </label>
                                <label style="display: flex; align-items: center; gap: 0.5rem;">
                                    <input type="checkbox" name="os_linux" value="1" <?= !empty($eg['os_linux']) ? 'checked' : '' ?>>
                                    <i class="fab fa-linux"></i> Linux
                                </label>
                                <label style="display: flex; align-items: center; gap: 0.5rem;">
                                    <input type="checkbox" name="os_mac" value="1" <?= !empty($eg['os_mac']) ? 'checked' : '' ?>>
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
                                       placeholder="https://..." value="<?= htmlspecialchars($eg['download_url_windows'] ?? '') ?>">
                            </div>

                            <div class="form-group">
                                <label for="download_url_android">
                                    <i class="fab fa-android"></i> Link para download Android
                                </label>
                                <input type="url" id="download_url_android" name="download_url_android"
                                       placeholder="https://..." value="<?= htmlspecialchars($eg['download_url_android'] ?? '') ?>">
                            </div>

                            <div class="form-group">
                                <label for="download_url_linux">
                                    <i class="fab fa-linux"></i> Link para download Linux
                                </label>
                                <input type="url" id="download_url_linux" name="download_url_linux"
                                       placeholder="https://..." value="<?= htmlspecialchars($eg['download_url_linux'] ?? '') ?>">
                            </div>

                            <div class="form-group">
                                <label for="download_url_mac">
                                    <i class="fab fa-apple"></i> Link para download Mac
                                </label>
                                <input type="url" id="download_url_mac" name="download_url_mac"
                                       placeholder="https://..." value="<?= htmlspecialchars($eg['download_url_mac'] ?? '') ?>">
                            </div>

                            <div class="form-group">
                                <label for="download_url">
                                    <i class="fas fa-download"></i> Link Genérico (opcional)
                                </label>
                                <input type="url" id="download_url" name="download_url"
                                       placeholder="https://..." value="<?= htmlspecialchars($eg['download_url'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="tags">Tags (separadas por vírgula)</label>
                            <input type="text" id="tags" name="tags"
                                   placeholder="Adult,Visual Novel"
                                   value="<?= htmlspecialchars($eg['tags'] ?? '') ?>">
                        </div>

                        <div style="display:flex; gap:1rem;">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
                            <a href="?action=home" class="btn btn-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
            <?php } ?>
        <?php endif; ?>
    </div>

    <script>
        // Dropdown handlers moved to global script.js
    </script>
<?php include 'footer_partial.php'; ?>
<script src="script.js"></script>
</body>
</html>
