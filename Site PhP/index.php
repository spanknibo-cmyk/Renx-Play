<?php
require 'config.php';

date_default_timezone_set('America/Sao_Paulo');

function canModerateComments(int $postOwnerId): bool {
    return isLoggedIn() && (
        $_SESSION['user']['role'] === 'DEV'
        || $_SESSION['user']['role'] === 'SUPER_ADMIN'
        || $_SESSION['user']['id'] === $postOwnerId
    );
}

$gameSlug = $_GET['game'] ?? null;

if ($gameSlug) {
    // PÁGINA DO JOGO INDIVIDUAL
    $stmt = $pdo->prepare("
        SELECT g.*, u.username AS author
          FROM games g
          JOIN users u ON g.posted_by=u.id
         WHERE g.slug = ?");
    $stmt->execute([$gameSlug]);
    $game = $stmt->fetch();

    if (!$game) {
        header('Location: index.php');
        exit;
    }

    $gameId = $game['id'];
    $postOwner = $game['posted_by'];

    if (isLoggedIn() && isset($_POST['action'])) {
        $gameIdPost = (int)($_POST['game_id'] ?? 0);

        if ($gameIdPost < 1) {
            header('Location: index.php');
            exit;
        }

        $owner = $pdo->prepare("SELECT posted_by FROM games WHERE id = ?");
        $owner->execute([$gameIdPost]);
        $postOwner = $owner->fetchColumn();

        if (!$postOwner) {
            header('Location: index.php');
            exit;
        }

        if ($_POST['action'] === 'comment' && !empty($_POST['comment'])) {
            $pdo->prepare("INSERT INTO comments (game_id, user_id, comment, parent_id, created_at)
                           VALUES (?,?,?,?,NOW())")
                ->execute([
                    $gameIdPost,
                    $_SESSION['user']['id'],
                    trim($_POST['comment']),
                    !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null
                ]);
        } elseif ($_POST['action'] === 'edit_comment' && !empty($_POST['comment_id']) && !empty($_POST['comment'])) {
            $c = $pdo->prepare("SELECT * FROM comments WHERE id=?");
            $c->execute([(int)$_POST['comment_id']]);
            $comment = $c->fetch();
            if ($comment && ($comment['user_id'] === $_SESSION['user']['id'] || canModerateComments($postOwner))) {
                $pdo->prepare("UPDATE comments SET comment=?, edited_at=NOW() WHERE id=?")
                    ->execute([trim($_POST['comment']), (int)$_POST['comment_id']]);
            }
        } elseif ($_POST['action'] === 'delete_comment' && !empty($_POST['comment_id'])) {
            $c = $pdo->prepare("SELECT * FROM comments WHERE id=?");
            $c->execute([(int)$_POST['comment_id']]);
            $comment = $c->fetch();
            if ($comment && ($comment['user_id'] === $_SESSION['user']['id'] || canModerateComments($postOwner))) {
                $pdo->prepare("UPDATE comments SET deleted_at=NOW() WHERE id=?")
                    ->execute([(int)$_POST['comment_id']]);
            }
        }

        header("Location: index.php?game={$gameSlug}#comments");
        exit;
    }

    if (isset($_GET['download']) && isLoggedIn()) {
        $pdo->prepare("UPDATE games SET downloads_count = downloads_count + 1 WHERE id = ?")
            ->execute([ $_GET['download'] ]);
        echo "<script>window.close();</script>";
        exit;
    }

    $cTotal = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE game_id=? AND deleted_at IS NULL");
    $cTotal->execute([$gameId]);
    $totalComments = $cTotal->fetchColumn();

    renderHeader($game['title']);
    ?>
    
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($game['title']) ?> - <?= SITE_NAME ?></title>
        <link rel="stylesheet" href="style.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    </head>
    <body>
        <!-- Header moderno -->
        <header class="header">
            <div class="header-content">
                <a href="index.php" class="logo">
                    <i class="fas fa-play" style="margin-right: 0.5rem;"></i>
                    Renx-Play
                </a>
                
                <nav class="nav">
                    <a href="index.php" class="nav-link">
                        <i class="fas fa-home"></i>
                        Games
                    </a>
                    
                    <?php if (hasRole(['ADMIN', 'SUPER_ADMIN', 'DEV'])): ?>
                        <a href="dashboard.php" class="nav-link">
                            <i class="fas fa-cog"></i>
                            Admin
                        </a>
                    <?php endif; ?>
                    
                    <?php if (isLoggedIn()): ?>
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
                                <a href="auth.php?action=logout" class="dropdown-item">
                                    <i class="fas fa-sign-out-alt"></i>
                                    Sair
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="auth.php" class="nav-link">
                            <i class="fas fa-sign-in-alt"></i>
                            Entrar
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        </header>

        <!-- Botão voltar -->
        <div class="container" style="padding-top: 1rem;">
            <a href="index.php" class="btn btn-outline btn-sm">
                <i class="fas fa-arrow-left"></i>
                Voltar para a lista
            </a>
        </div>

        <!-- Detalhes do jogo -->
        <div class="game-detail">
            <div class="game-header">
                <div class="game-image">
                    <img src="uploads/covers/<?= htmlspecialchars($game['cover_image']) ?>" alt="<?= htmlspecialchars($game['title']) ?>">
                </div>
                
                <div class="game-info">
                    <h1><?= htmlspecialchars($game['title']) ?></h1>
                    
                    <div class="game-badges">
                        <span class="badge">REN'PY</span>
                        <span class="badge">v1.0</span>
                        <span class="badge"><?= htmlspecialchars($game['author']) ?></span>
                    </div>
                    
                    <div class="card-rating" style="margin-bottom: 1rem;">
                        <i class="fas fa-star"></i>
                        <span>4.5</span>
                    </div>
                    
                    <div style="margin-bottom: 1rem;">
                        <p><strong>Idioma:</strong> <?= htmlspecialchars($game['language'] ?? 'English') ?></p>
                        <p><strong>Censurado:</strong> <?= $game['censored'] ? 'Sim' : 'Não' ?></p>
                        <p><strong>Lançamento:</strong> <?= date('d/m/Y', strtotime($game['created_at'])) ?></p>
                    </div>
                    
                    <div style="margin-bottom: 1rem;">
                        <p><strong>Plataformas:</strong></p>
                        <div class="game-badges">
                            <?php if ($game['os_windows'] ?? true): ?>
                                <span class="badge">Windows</span>
                            <?php endif; ?>
                            <?php if ($game['os_android'] ?? true): ?>
                                <span class="badge">Android</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (isLoggedIn()): ?>
                <div class="download-section">
                    <h3><i class="fas fa-download"></i> Selecionar Plataforma:</h3>
                    <div class="download-buttons">
                        <?php if ($game['download_url_windows']): ?>
                            <a href="<?= htmlspecialchars($game['download_url_windows']) ?>" target="_blank" class="btn btn-primary">
                                <i class="fab fa-windows"></i>
                                Windows
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($game['download_url_android']): ?>
                            <a href="<?= htmlspecialchars($game['download_url_android']) ?>" target="_blank" class="btn btn-primary">
                                <i class="fab fa-android"></i>
                                Android
                            </a>
                        <?php endif; ?>
                        
                        <?php if ($game['download_url']): ?>
                            <a href="<?= htmlspecialchars($game['download_url']) ?>" target="_blank" class="btn btn-secondary">
                                <i class="fas fa-download"></i>
                                Download Geral
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="download-section">
                    <p style="text-align: center; color: hsl(var(--muted-foreground));">
                        <i class="fas fa-lock"></i>
                        <a href="auth.php" style="color: hsl(var(--primary));">Faça login</a> para fazer download
                    </p>
                </div>
            <?php endif; ?>

            <!-- Descrição -->
            <div class="card" style="margin-bottom: 1rem;">
                <div class="card-content">
                    <h3 style="margin-bottom: 0.75rem;">
                        <i class="fas fa-info-circle"></i>
                        Descrição
                    </h3>
                    <p><?= nl2br(htmlspecialchars($game['description'])) ?></p>
                </div>
            </div>

            <!-- Tags -->
            <?php if (!empty($game['tags'])): ?>
                <div class="card" style="margin-bottom: 1rem;">
                    <div class="card-content">
                        <h3 style="margin-bottom: 0.75rem;">
                            <i class="fas fa-tags"></i>
                            Tags
                        </h3>
                        <div class="card-tags">
                            <?php 
                            $tags = explode(',', $game['tags']);
                            foreach ($tags as $tag): 
                                $tag = trim($tag);
                                if ($tag):
                            ?>
                                <span class="tag"><?= htmlspecialchars($tag) ?></span>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <script>
            function toggleDropdown() {
                const dropdown = document.getElementById('userDropdown');
                dropdown.classList.toggle('show');
            }

            // Fechar dropdown ao clicar fora
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.user-dropdown')) {
                    document.getElementById('userDropdown').classList.remove('show');
                }
            });
        </script>
    </body>
    </html>

    <?php
} else {
    // PÁGINA INICIAL - LISTAGEM DE JOGOS
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= SITE_NAME ?> - Jogos Traduzidos</title>
        <link rel="stylesheet" href="style.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <link rel="icon" href="https://i.imgur.com/QyZKduC.png" type="image/png">
    </head>
    <body>
        <!-- Header moderno -->
        <header class="header">
            <div class="header-content">
                <a href="index.php" class="logo">
                    Renxplay Teste
                </a>
                
                <nav class="nav">
                    <a href="index.php" class="nav-link active">
                        <i class="fas fa-home"></i>
                        Jogos
                    </a>
                    
                    <?php if (isLoggedIn() && hasRole(['ADMIN', 'SUPER_ADMIN', 'DEV'])): ?>
                        <a href="dashboard.php" class="nav-link">
                            <i class="fas fa-cog"></i>
                            Admin
                        </a>
                    <?php endif; ?>
                    
                    <?php if (isLoggedIn()): ?>
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
                                <a href="auth.php?action=logout" class="dropdown-item">
                                    <i class="fas fa-sign-out-alt"></i>
                                    Sair
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="auth.php" class="btn btn-outline btn-sm">
                            <i class="fas fa-sign-in-alt"></i>
                            Entrar
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        </header>

        <!-- Seção de busca -->
        <section class="search-section">
            <div class="container">
                <div class="search-wrapper">
                    <input type="text" class="search-input" id="searchInput" placeholder="Pesquisar jogos...">
                    <i class="fas fa-search search-icon"></i>
                    <div id="searchResults" class="search-results" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: hsl(var(--card)); border: 1px solid hsl(var(--border)); border-radius: var(--radius); margin-top: 0.25rem; box-shadow: 0 4px 12px hsl(var(--foreground) / 0.1); z-index: 1000;"></div>
                </div>
            </div>
        </section>

        <!-- Área principal com logo central -->
        <div style="text-align: center; padding: 2rem 0; background: hsl(var(--background));">
            <div style="width: 150px; height: 150px; margin: 0 auto 1rem; background: hsl(var(--muted)); border-radius: 50%; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden;">
                <div style="width: 120px; height: 120px; border: 4px solid hsl(var(--border)); border-radius: 50%; background: hsl(var(--background)); display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-play" style="font-size: 3rem; color: hsl(var(--primary)); margin-left: 0.5rem;"></i>
                </div>
                <!-- Ícone de busca sobreposto -->
                <div style="position: absolute; top: -15px; right: -15px; width: 60px; height: 60px; background: hsl(var(--muted)); border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 4px solid hsl(var(--background));">
                    <i class="fas fa-search" style="font-size: 1.25rem; color: hsl(var(--muted-foreground));"></i>
                </div>
            </div>
            
            <h1 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem;">Jogos Traduzidos</h1>
            <p style="color: hsl(var(--muted-foreground)); font-size: 0.875rem;">Descubra os melhores jogos Ren'Py em português</p>
        </div>

        <!-- Grid de jogos -->
        <div class="container">
            <?php
            $page = max(1, (int)($_GET['p'] ?? 1));
            $offset = ($page - 1) * POSTS_PER_PAGE;

            $sql = "SELECT * FROM games ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(1, POSTS_PER_PAGE, PDO::PARAM_INT);
            $stmt->bindValue(2, $offset, PDO::PARAM_INT);
            $stmt->execute();
            $games = $stmt->fetchAll();

            $total = $pdo->query("SELECT COUNT(*) FROM games")->fetchColumn();
            $pages = ceil($total / POSTS_PER_PAGE);

            if (empty($games)): ?>
                <div style="text-align: center; padding: 3rem; color: hsl(var(--muted-foreground));">
                    <p>Nenhum jogo encontrado.</p>
                </div>
            <?php else: ?>
                <div class="games-grid">
                    <?php foreach ($games as $g): ?>
                        <a href="?game=<?= urlencode($g['slug']) ?>" style="text-decoration: none; color: inherit;">
                            <div class="card">
                                <div class="card-image">
                                    <img src="uploads/covers/<?= htmlspecialchars($g['cover_image']) ?>" 
                                         alt="<?= htmlspecialchars($g['title']) ?>" 
                                         loading="lazy">
                                </div>
                                
                                <div class="card-content">
                                    <h3 class="card-title"><?= htmlspecialchars($g['title']) ?></h3>
                                    
                                    <div class="card-meta">
                                        <span class="badge">REN'PY</span>
                                        <span>v1.0</span>
                                        
                                        <div class="card-rating">
                                            <i class="fas fa-star"></i>
                                            <span>4.5</span>
                                        </div>
                                    </div>
                                    
                                    <p class="card-description">
                                        <?= htmlspecialchars(substr($g['description'], 0, 100)) ?>...
                                    </p>
                                    
                                    <?php if (!empty($g['tags'])): ?>
                                        <div class="card-tags">
                                            <?php 
                                            $tags = array_slice(explode(',', $g['tags']), 0, 3);
                                            foreach ($tags as $tag): 
                                                $tag = trim($tag);
                                                if ($tag):
                                            ?>
                                                <span class="tag"><?= htmlspecialchars($tag) ?></span>
                                            <?php 
                                                endif;
                                            endforeach; 
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="card-footer">
                                        <div style="display: flex; align-items: center; gap: 0.25rem;">
                                            <i class="fas fa-calendar"></i>
                                            <span><?= date('d/m/Y', strtotime($g['created_at'])) ?></span>
                                        </div>
                                        
                                        <div class="btn btn-sm btn-outline">
                                            <i class="fas fa-download"></i>
                                            Download
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Paginação -->
            <?php if ($pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?p=<?= $page - 1 ?>">Previous</a>
                    <?php endif; ?>
                    
                    <span class="current">1</span>
                    
                    <?php if ($pages > 1): ?>
                        <a href="?p=2">2</a>
                    <?php endif; ?>
                    
                    <?php if ($page < $pages): ?>
                        <a href="?p=<?= $page + 1 ?>">Next</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <script>
            function toggleDropdown() {
                const dropdown = document.getElementById('userDropdown');
                dropdown.classList.toggle('show');
            }

            // Fechar dropdown ao clicar fora
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.user-dropdown')) {
                    const dropdown = document.getElementById('userDropdown');
                    if (dropdown) dropdown.classList.remove('show');
                }
            });

            // Busca
            document.addEventListener('DOMContentLoaded', function() {
                const input = document.getElementById('searchInput');
                const results = document.getElementById('searchResults');
                
                if (!input || !results) return;
                
                input.addEventListener('input', function() {
                    const query = this.value.trim();
                    
                    if (query.length < 2) {
                        results.style.display = 'none';
                        return;
                    }
                    
                    fetch('search_games.php?q=' + encodeURIComponent(query))
                        .then(response => response.json())
                        .then(games => {
                            if (games.length === 0) {
                                results.innerHTML = '<div style="padding: 1rem; color: hsl(var(--muted-foreground));">Nenhum jogo encontrado</div>';
                            } else {
                                results.innerHTML = games.map(game => 
                                    `<a href="?game=${game.slug}" style="display: block; padding: 0.75rem 1rem; text-decoration: none; color: hsl(var(--foreground)); border-bottom: 1px solid hsl(var(--border)); transition: background 0.2s;" onmouseover="this.style.background='hsl(var(--accent))'" onmouseout="this.style.background='transparent'">
                                        <strong>${game.title}</strong><br>
                                        <small style="color: hsl(var(--muted-foreground));">${game.category || 'Visual Novel'} • ${game.downloads_count || 0} downloads</small>
                                    </a>`
                                ).join('');
                            }
                            results.style.display = 'block';
                        })
                        .catch(() => {
                            results.innerHTML = '<div style="padding: 1rem; color: hsl(var(--muted-foreground));">Erro na busca</div>';
                            results.style.display = 'block';
                        });
                });
                
                // Esconder ao clicar fora
                document.addEventListener('click', function(e) {
                    if (!e.target.closest('.search-wrapper')) {
                        results.style.display = 'none';
                    }
                });
            });
        </script>
    </body>
    </html>
    <?php
}
?>
