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
    // PÁGINA DO JOGO INDIVIDUAL - Mantém a funcionalidade existente
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
    
    <!-- Game Detail Page - Melhorada seguindo App.tsx -->
    <!-- Back Button -->
    <div class="mb-6">
        <a href="index.php" class="btn-ghost">
            <i class="fas fa-arrow-left mr-2"></i>
            Voltar para a lista
        </a>
    </div>
    
    <!-- Game Detail Content -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
        <!-- Game Image -->
        <div class="lg:col-span-1">
            <div class="game-card-image mb-4" style="aspect-ratio: 3/4;">
                <img src="uploads/covers/<?= htmlspecialchars($game['cover_image']) ?>" 
                     alt="<?= htmlspecialchars($game['title']) ?>" 
                     class="w-full h-full object-cover">
            </div>
            
            <?php if (isLoggedIn()): ?>
                <!-- Download Section -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h3 class="text-lg font-medium">
                            <i class="fas fa-download mr-2"></i>
                            Download
                        </h3>
                    </div>
                    <div class="card-content">
                        <div class="flex flex-col gap-2">
                            <?php if ($game['download_url_windows']): ?>
                                <a href="<?= htmlspecialchars($game['download_url_windows']) ?>" 
                                   target="_blank" 
                                   class="btn-outline w-full">
                                    <i class="fab fa-windows mr-2"></i>
                                    Windows
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($game['download_url_android']): ?>
                                <a href="<?= htmlspecialchars($game['download_url_android']) ?>" 
                                   target="_blank" 
                                   class="btn-outline w-full">
                                    <i class="fab fa-android mr-2"></i>
                                    Android
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($game['download_url']): ?>
                                <a href="<?= htmlspecialchars($game['download_url']) ?>" 
                                   target="_blank" 
                                   class="btn-ghost w-full">
                                    <i class="fas fa-download mr-2"></i>
                                    Download Geral
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card mb-4">
                    <div class="card-content text-center py-6">
                        <i class="fas fa-lock mb-3 text-2xl text-muted-foreground"></i>
                        <p class="text-muted-foreground mb-3">Faça login para fazer download</p>
                        <a href="auth.php" class="btn-outline">
                            <i class="fas fa-sign-in-alt mr-2"></i>
                            Entrar
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Game Info -->
        <div class="lg:col-span-2">
            <!-- Title and Meta -->
            <div class="mb-6">
                <h1 class="text-3xl font-bold mb-3"><?= htmlspecialchars($game['title']) ?></h1>
                
                <div class="flex flex-wrap items-center gap-2 mb-4">
                    <span class="badge">REN'PY</span>
                    <span class="badge">v<?= htmlspecialchars($game['version'] ?? '1.0') ?></span>
                    <span class="badge"><?= htmlspecialchars($game['author']) ?></span>
                </div>
                
                <div class="star-rating mb-4">
                    <i class="fas fa-star star-icon"></i>
                    <span class="text-lg font-medium">
                        <?= number_format($game['rating'] ?? 4.5, 1) ?>
                    </span>
                    <span class="text-muted-foreground ml-2">(<?= rand(10, 100) ?> avaliações)</span>
                </div>
            </div>
            
            <!-- Game Stats -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="text-center p-3 bg-muted rounded">
                    <div class="text-sm text-muted-foreground">Idioma</div>
                    <div class="font-medium"><?= htmlspecialchars($game['language'] ?? 'English') ?></div>
                </div>
                <div class="text-center p-3 bg-muted rounded">
                    <div class="text-sm text-muted-foreground">Censurado</div>
                    <div class="font-medium"><?= $game['censored'] ? 'Sim' : 'Não' ?></div>
                </div>
                <div class="text-center p-3 bg-muted rounded">
                    <div class="text-sm text-muted-foreground">Lançamento</div>
                    <div class="font-medium"><?= date('d/m/Y', strtotime($game['created_at'])) ?></div>
                </div>
                <div class="text-center p-3 bg-muted rounded">
                    <div class="text-sm text-muted-foreground">Downloads</div>
                    <div class="font-medium"><?= number_format($game['downloads_count'] ?? rand(100, 1000)) ?></div>
                </div>
            </div>
            
            <!-- Platforms -->
            <div class="mb-6">
                <h3 class="text-lg font-medium mb-2">Plataformas Suportadas</h3>
                <div class="flex flex-wrap gap-2">
                    <?php if ($game['os_windows'] ?? true): ?>
                        <span class="badge">
                            <i class="fab fa-windows mr-1"></i>
                            Windows
                        </span>
                    <?php endif; ?>
                    <?php if ($game['os_android'] ?? true): ?>
                        <span class="badge">
                            <i class="fab fa-android mr-1"></i>
                            Android
                        </span>
                    <?php endif; ?>
                    <?php if ($game['os_linux'] ?? false): ?>
                        <span class="badge">
                            <i class="fab fa-linux mr-1"></i>
                            Linux
                        </span>
                    <?php endif; ?>
                    <?php if ($game['os_mac'] ?? false): ?>
                        <span class="badge">
                            <i class="fab fa-apple mr-1"></i>
                            macOS
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Description -->
            <div class="card mb-6">
                <div class="card-header">
                    <h3 class="text-lg font-medium">
                        <i class="fas fa-info-circle mr-2"></i>
                        Descrição
                    </h3>
                </div>
                <div class="card-content">
                    <p class="leading-relaxed"><?= nl2br(htmlspecialchars($game['description'])) ?></p>
                </div>
            </div>
            
            <!-- Tags -->
            <?php if (!empty($game['tags'])): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-lg font-medium">
                            <i class="fas fa-tags mr-2"></i>
                            Tags
                        </h3>
                    </div>
                    <div class="card-content">
                        <div class="flex flex-wrap gap-2">
                            <?php 
                            $tags = explode(',', $game['tags']);
                            foreach ($tags as $tag): 
                                $tag = trim($tag);
                                if ($tag):
                            ?>
                                <span class="tag-badge"><?= htmlspecialchars($tag) ?></span>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php
    
} else {
    // ====== HOMEPAGE - Replicando exatamente App.tsx ======
    
    // Parâmetros de busca e paginação
    $searchQuery = $_GET['search'] ?? '';
    $currentPage = max(1, (int)($_GET['page'] ?? 1));
    $limit = 12; // Mesmo limite do App.tsx
    $offset = ($currentPage - 1) * $limit;
    
    // Query base
    $whereConditions = ["1=1"];
    $params = [];
    
    // Filtro de busca
    if (!empty($searchQuery)) {
        $whereConditions[] = "(g.title LIKE ? OR g.description LIKE ? OR g.tags LIKE ?)";
        $searchTerm = "%{$searchQuery}%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Buscar jogos
    $stmt = $pdo->prepare("
        SELECT g.*, u.username as author_name
        FROM games g 
        LEFT JOIN users u ON g.posted_by = u.id 
        WHERE {$whereClause}
        ORDER BY g.created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
    $games = $stmt->fetchAll();
    
    // Contar total para paginação
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM games g WHERE {$whereClause}");
    $countParams = array_slice($params, 0, -2); // Remove limit e offset
    $countStmt->execute($countParams);
    $totalGames = $countStmt->fetchColumn();
    $totalPages = ceil($totalGames / $limit);
    
    renderHeader('Games', 'Descubra os melhores jogos Ren\'Py');
    ?>
    
    <!-- Search Section - Replicando App.tsx -->
    <div class="mb-8">
        <div class="search-wrapper">
            <div class="relative">
                <i class="fas fa-search search-icon h-4 w-4"></i>
                <input 
                    type="search" 
                    id="searchInput"
                    placeholder="Search games..." 
                    value="<?= htmlspecialchars($searchQuery) ?>"
                    class="search-input"
                    autocomplete="off"
                >
            </div>
            <div id="searchResults" class="dropdown-content" style="display: none; position: absolute; top: 100%; left: 0; right: 0; max-height: 400px; overflow-y: auto;"></div>
        </div>
    </div>
    
    <?php if (empty($games)): ?>
        <!-- Empty State - Replicando App.tsx -->
        <div class="text-center py-12">
            <div class="mb-4">
                <i class="fas fa-gamepad h-16 w-16 text-muted-foreground mx-auto mb-4" style="font-size: 4rem;"></i>
            </div>
            <h3 class="text-lg font-medium mb-2">
                <?= empty($searchQuery) ? 'Nenhum jogo disponível' : 'Nenhum jogo encontrado' ?>
            </h3>
            <p class="text-muted-foreground">
                <?= empty($searchQuery) ? 'Novos jogos serão adicionados em breve.' : 'Tente uma busca diferente.' ?>
            </p>
            <?php if (!empty($searchQuery)): ?>
                <a href="index.php" class="btn-outline mt-4 inline-flex">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Voltar para todos os jogos
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- Games Grid - Replicando exatamente App.tsx -->
        <div class="games-grid">
            <?php foreach ($games as $game): ?>
                <a href="?game=<?= urlencode($game['slug']) ?>" class="block">
                    <div class="game-card">
                        <!-- Game Image - Replicando game-card-image do theme.css -->
                        <div class="game-card-image">
                            <img 
                                src="uploads/covers/<?= htmlspecialchars($game['cover_image']) ?>" 
                                alt="<?= htmlspecialchars($game['title']) ?>" 
                                loading="lazy"
                            >
                        </div>
                        
                        <!-- Card Header - Replicando App.tsx CardHeader -->
                        <div class="card-header pb-2">
                            <h3 class="card-title text-lg line-clamp-2"><?= htmlspecialchars($game['title']) ?></h3>
                            <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                <span class="badge">REN'PY</span>
                                <span>v<?= htmlspecialchars($game['version'] ?? '1.0') ?></span>
                            </div>
                        </div>
                        
                        <!-- Card Content - Replicando App.tsx CardContent -->
                        <div class="card-content pb-2">
                            <p class="text-sm text-muted-foreground line-clamp-3 mb-3">
                                <?= htmlspecialchars($game['description']) ?>
                            </p>
                            
                            <!-- Star Rating -->
                            <div class="star-rating">
                                <i class="fas fa-star star-icon"></i>
                                <span class="text-sm font-medium">
                                    <?= number_format($game['rating'] ?? 4.5, 1) ?>
                                </span>
                            </div>
                            
                            <!-- Tags -->
                            <?php if (!empty($game['tags'])): ?>
                                <div class="flex flex-wrap gap-1">
                                    <?php 
                                    $tags = array_slice(explode(',', $game['tags']), 0, 3);
                                    foreach ($tags as $tag): 
                                        $tag = trim($tag);
                                        if ($tag):
                                    ?>
                                        <span class="tag-badge"><?= htmlspecialchars($tag) ?></span>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Card Footer - Replicando App.tsx CardFooter -->
                        <div class="card-footer pt-2">
                            <div class="flex items-center justify-between w-full text-xs text-muted-foreground">
                                <div class="flex items-center gap-1">
                                    <i class="fas fa-calendar h-3 w-3"></i>
                                    <span><?= date('d/m/Y', strtotime($game['created_at'])) ?></span>
                                </div>
                                
                                <button class="btn-outline btn-sm" onclick="event.stopPropagation();">
                                    <i class="fas fa-download h-3 w-3 mr-1"></i>
                                    Download
                                </button>
                            </div>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        
        <!-- Pagination - Melhorada seguindo App.tsx -->
        <?php if ($totalPages > 1): ?>
            <div class="flex justify-center items-center gap-2 mt-8">
                <!-- Previous Button -->
                <?php if ($currentPage > 1): ?>
                    <a href="?page=<?= $currentPage - 1 ?><?= $searchQuery ? '&search=' . urlencode($searchQuery) : '' ?>" 
                       class="btn-outline btn-sm">
                        <i class="fas fa-chevron-left mr-1"></i>
                        Previous
                    </a>
                <?php else: ?>
                    <button class="btn-outline btn-sm opacity-50 cursor-not-allowed" disabled>
                        <i class="fas fa-chevron-left mr-1"></i>
                        Previous
                    </button>
                <?php endif; ?>
                
                <!-- Page Numbers -->
                <?php
                $startPage = max(1, $currentPage - 2);
                $endPage = min($totalPages, $currentPage + 2);
                
                if ($startPage > 1): ?>
                    <a href="?page=1<?= $searchQuery ? '&search=' . urlencode($searchQuery) : '' ?>" 
                       class="btn-outline btn-sm">1</a>
                    <?php if ($startPage > 2): ?>
                        <span class="px-2">...</span>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <?php if ($i == $currentPage): ?>
                        <button class="btn-outline btn-sm bg-primary text-primary-foreground"><?= $i ?></button>
                    <?php else: ?>
                        <a href="?page=<?= $i ?><?= $searchQuery ? '&search=' . urlencode($searchQuery) : '' ?>" 
                           class="btn-outline btn-sm"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($endPage < $totalPages): ?>
                    <?php if ($endPage < $totalPages - 1): ?>
                        <span class="px-2">...</span>
                    <?php endif; ?>
                    <a href="?page=<?= $totalPages ?><?= $searchQuery ? '&search=' . urlencode($searchQuery) : '' ?>" 
                       class="btn-outline btn-sm"><?= $totalPages ?></a>
                <?php endif; ?>
                
                <!-- Next Button -->
                <?php if ($currentPage < $totalPages): ?>
                    <a href="?page=<?= $currentPage + 1 ?><?= $searchQuery ? '&search=' . urlencode($searchQuery) : '' ?>" 
                       class="btn-outline btn-sm">
                        Next
                        <i class="fas fa-chevron-right ml-1"></i>
                    </a>
                <?php else: ?>
                    <button class="btn-outline btn-sm opacity-50 cursor-not-allowed" disabled>
                        Next
                        <i class="fas fa-chevron-right ml-1"></i>
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <!-- Search Enhancement Script -->
    <script>
        // Implementar busca em tempo real
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                // Atualizar URL quando buscar
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        const query = this.value.trim();
                        if (query) {
                            window.location.href = `?search=${encodeURIComponent(query)}`;
                        } else {
                            window.location.href = 'index.php';
                        }
                    }
                });
            }
        });
    </script>
    
    <?php
    renderFooter();
}
?>
