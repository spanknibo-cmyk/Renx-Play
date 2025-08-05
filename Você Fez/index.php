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

    // Página do jogo individual com design moderno
    ?>
    <div class="min-h-screen bg-background text-foreground">
        <!-- Search Section -->
        <section class="search-section">
            <div class="container">
                <div class="search-wrapper">
                    <input type="text" id="searchInput" placeholder="Pesquisar jogos..." class="input">
                    <svg class="search-icon w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                    <div id="searchResults" class="search-results"></div>
                </div>
            </div>
        </section>

        <div class="container py-8">
            <!-- Back Button -->
            <div class="mb-6">
                <a href="index.php" class="btn btn-outline btn-sm">
                    <svg class="w-4 h-4 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="m12 19-7-7 7-7"></path>
                        <path d="m19 12H5"></path>
                    </svg>
                    Voltar para a lista
                </a>
            </div>

            <!-- Game Header -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
                <div class="aspect-[3/2] overflow-hidden rounded-lg shadow-lg">
                    <img src="uploads/covers/<?= $game['cover_image'] ?>" 
                         alt="<?= htmlspecialchars($game['title']) ?>" 
                         class="w-full h-full object-cover hover:scale-105 transition-transform duration-300 cursor-pointer"
                         onclick="window.open('uploads/covers/<?= $game['cover_image'] ?>', '_blank')">
                </div>

                <div class="space-y-4">
                    <div>
                        <h1 class="text-3xl font-bold mb-2"><?= htmlspecialchars($game['title']) ?></h1>
                        <div class="flex items-center gap-2 text-sm text-muted-foreground mb-4">
                            <span class="badge badge-secondary"><?= htmlspecialchars($game['category']) ?></span>
                            <span><?= htmlspecialchars($game['version']) ?></span>
                            <span>•</span>
                            <span><?= htmlspecialchars($game['author']) ?></span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 mb-4">
                        <svg class="w-5 h-5 fill-yellow-400 text-yellow-400" viewBox="0 0 24 24">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                        </svg>
                        <span class="text-lg font-medium">4.5</span>
                    </div>

                    <p class="text-muted-foreground mb-4"><?= nl2br(htmlspecialchars($game['description'])) ?></p>

                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <strong>Idiomas:</strong> <?= htmlspecialchars($game['languages']) ?>
                        </div>
                        <div>
                            <strong>Plataformas:</strong> <?= htmlspecialchars($game['platforms']) ?>
                        </div>
                        <div>
                            <strong>Lançamento:</strong> <?= date('d/m/Y', strtotime($game['created_at'])) ?>
                        </div>
                        <div>
                            <strong>Tradutor:</strong> <?= htmlspecialchars($game['translator']) ?>
                        </div>
                    </div>

                    <!-- Download Button -->
                    <?php if (isLoggedIn()): 
                        $links = json_decode($game['download_links'] ?: '[]', true);
                        if ($links):
                    ?>
                        <a href="<?= $links[0] ?>" 
                           target="_blank" 
                           onclick="fetch('?download=<?= $gameId ?>')"
                           class="btn btn-primary w-full">
                            <svg class="w-4 h-4 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="7,10 12,15 17,10"></polyline>
                                <line x1="12" y1="15" x2="12" y2="3"></line>
                            </svg>
                            Download
                        </a>
                    <?php else: ?>
                        <button disabled class="btn btn-primary w-full">
                            <svg class="w-4 h-4 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="7,10 12,15 17,10"></polyline>
                                <line x1="12" y1="15" x2="12" y2="3"></line>
                            </svg>
                            Nenhum link disponível
                        </button>
                    <?php endif; ?>
                    <?php else: ?>
                        <p class="text-center">
                            <svg class="w-5 h-5 inline mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <circle cx="12" cy="16" r="1"></circle>
                                <path d="m7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                            <a href="auth.php" class="text-primary hover:underline">Faça login</a> para baixar
                        </p>
                    <?php endif; ?>

                    <!-- Screenshots Gallery -->
                    <?php 
                    $shots = json_decode($game['screenshots'] ?: '[]', true);
                    if ($shots): 
                    ?>
                        <div class="mt-6">
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3 md:gap-4">
                                <?php foreach ($shots as $index => $shot): ?>
                                    <div class="group relative">
                                        <div class="aspect-[4/3] overflow-hidden rounded-lg shadow-md border border-border bg-muted">
                                            <img src="uploads/screenshots/<?= $shot ?>" 
                                                 alt="Screenshot <?= $index + 1 ?>" 
                                                 class="w-full h-full object-cover cursor-pointer hover:scale-105 transition-transform duration-300"
                                                 onclick="openModal(this, <?= $index ?>)"
                                                 loading="lazy">
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Comments Section -->
            <section id="comments" class="mt-8">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <svg class="w-5 h-5 inline mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="m3 21 1.9-5.7a8.5 8.5 0 1 1 3.8 3.8z"></path>
                            </svg>
                            Comentários (<?= $totalComments ?>)
                        </h2>
                    </div>
                    <div class="card-content">
                        <?php if (isLoggedIn()): ?>
                            <form method="POST" id="commentForm" class="mb-6">
                                <input type="hidden" name="action" value="comment">
                                <input type="hidden" name="game_id" value="<?= $gameId ?>">
                                <input type="hidden" name="parent_id" id="parentId" value="">
                                
                                <div id="replyingToIndicator" style="display:none;" class="mb-3 p-3 bg-muted rounded-md">
                                    Respondendo: <span id="replyingToUser" class="font-medium"></span>
                                </div>
                                
                                <textarea name="comment" 
                                          placeholder="Escreva algo..." 
                                          required 
                                          class="textarea w-full mb-3"></textarea>
                                
                                <div class="flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <svg class="w-4 h-4 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="m22 2-7 20-4-9-9-4Z"></path>
                                            <path d="M22 2 11 13"></path>
                                        </svg>
                                        Enviar
                                    </button>
                                    <button type="button" id="cancelReply" style="display:none" class="btn btn-secondary">
                                        Cancelar
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>

                        <!-- Comments List -->
                        <div class="space-y-4">
                            <?php
                            $stmt = $pdo->prepare("
                                SELECT c.*, u.username
                                  FROM comments c
                                  JOIN users u ON u.id = c.user_id
                                 WHERE c.game_id=? AND c.deleted_at IS NULL
                                 ORDER BY c.created_at ASC
                            ");
                            $stmt->execute([$gameId]);
                            $comments = $stmt->fetchAll();

                            $tree = [];
                            foreach ($comments as $c) {
                                if ($c['parent_id']) {
                                    $tree[$c['parent_id']]['children'][] = $c;
                                } else {
                                    $tree[$c['id']] = $c;
                                    $tree[$c['id']]['children'] = [];
                                }
                            }

                            $commentUsers = [];
                            foreach ($comments as $c) {
                                $commentUsers[$c['id']] = $c;
                            }

                            function renderComments($nodes, $postOwner, $allUsers = [], $level = 0) {
                                foreach ($nodes as $c) {
                                    $ownerClass = ($c['user_id'] === $postOwner) ? 'border-l-4 border-primary bg-muted/20' : '';
                                    echo "<article class='border rounded-lg p-4 {$ownerClass}' data-id='{$c['id']}' data-level='{$level}'>
                                            <header class='flex items-center justify-between mb-2'>
                                                <div class='flex items-center gap-2'>
                                                    <span class='font-medium'>{$c['username']}</span>";

                                    $dt = new DateTime($c['created_at'], new DateTimeZone('UTC'));
                                    $dt->setTimezone(new DateTimeZone('America/Sao_Paulo'));
                                    echo "<time class='text-sm text-muted-foreground'>" . $dt->format('d/m/Y H:i') . "</time>";

                                    echo ($c['edited_at'] ? " <em class='text-sm text-muted-foreground'>(editado)</em>" : '') . "
                                                </div>
                                           </header>";

                                    if ($level === 1 && !empty($c['parent_id']) && isset($allUsers[$c['parent_id']])) {
                                        $replyTo = htmlspecialchars($allUsers[$c['parent_id']]['username']);
                                        echo "<div class='text-sm text-muted-foreground mb-2'>Respondendo: <span class='font-medium'>@{$replyTo}</span></div>";
                                    }

                                    echo "<p class='mb-3'>" . nl2br(htmlspecialchars($c['comment'])) . "</p>
                                           <div class='flex gap-2'>";

                                    if (isLoggedIn()) {
                                        echo "<button class='reply-link btn btn-ghost btn-sm' data-parent='{$c['id']}' data-username='{$c['username']}'>
                                                <svg class='w-4 h-4 mr-1' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                                                    <polyline points='9,17 4,12 9,7'></polyline>
                                                    <path d='M20 18v-2a4 4 0 0 0-4-4H4'></path>
                                                </svg>
                                                Responder
                                              </button>";
                                        
                                        if ($_SESSION['user']['id'] === $c['user_id']) {
                                            echo "<button class='edit-link btn btn-ghost btn-sm' data-id='{$c['id']}' data-text='" . htmlspecialchars($c['comment'], ENT_QUOTES) . "'>
                                                    <svg class='w-4 h-4 mr-1' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                                                        <path d='M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7'></path>
                                                        <path d='M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z'></path>
                                                    </svg>
                                                    Editar
                                                  </button>";
                                        }
                                        
                                        if ($_SESSION['user']['id'] === $c['user_id'] || canModerateComments($postOwner)) {
                                            echo "<form method='POST' style='display:inline'>
                                                    <input type='hidden' name='action' value='delete_comment'>
                                                    <input type='hidden' name='comment_id' value='{$c['id']}'>
                                                    <input type='hidden' name='game_id' value='{$c['game_id']}'>
                                                    <button class='btn btn-ghost btn-sm text-destructive' onclick='return confirm(\"Apagar comentário?\")'>
                                                        <svg class='w-4 h-4 mr-1' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                                                            <polyline points='3,6 5,6 21,6'></polyline>
                                                            <path d='m19,6v14a2,2 0 0,1 -2,2H7a2,2 0 0,1 -2,-2V6m3,0V4a2,2 0 0,1 2,-2h4a2,2 0 0,1 2,2v2'></path>
                                                        </svg>
                                                        Excluir
                                                    </button>
                                                  </form>";
                                        }
                                    }
                                    echo "</div>";

                                    if (!empty($c['children'])) {
                                        $totalChildren = count($c['children']);
                                        echo "<div class='ml-6 mt-4 space-y-4'>";
                                        foreach ($c['children'] as $i => $child) {
                                            $style = $i < 5 ? '' : 'style="display:none;"';
                                            echo "<div class='child-comment' {$style}>";
                                            renderComments([$child], $postOwner, $allUsers, $level + 1);
                                            echo "</div>";
                                        }
                                        echo "</div>";

                                        if ($totalChildren > 5) {
                                            echo "<button class='show-more-btn btn btn-ghost btn-sm mt-2' data-parent-id='{$c['id']}' data-shown='5' data-total='{$totalChildren}'>
                                                    Ver mais respostas ({$totalChildren} total)
                                                  </button>";
                                        }
                                    }
                                    echo "</article>";
                                }
                            }

                            renderComments($tree, $game['posted_by'], $commentUsers);
                            ?>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <script>
    // Busca autocomplete
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
                        results.innerHTML = '<div class="p-4 text-center text-muted-foreground">Nenhum jogo encontrado</div>';
                    } else {
                        results.innerHTML = games.map(game => 
                            `<a href="?game=${game.slug}" class="block">
                                <strong>${game.title}</strong><br>
                                <small class="text-muted-foreground">${game.category} • ${game.downloads_count} downloads</small>
                            </a>`
                        ).join('');
                    }
                    results.style.display = 'block';
                })
                .catch(() => {
                    results.innerHTML = '<div class="p-4 text-center text-muted-foreground">Erro na busca</div>';
                    results.style.display = 'block';
                });
        });
        
        document.addEventListener('click', function(e) {
            if (!e.target.closest('#searchInput') && !e.target.closest('#searchResults')) {
                results.style.display = 'none';
            }
        });
    });

    // Comentários
    document.addEventListener('click', e => {
        if (e.target.matches('.reply-link') || e.target.closest('.reply-link')) {
            const btn = e.target.matches('.reply-link') ? e.target : e.target.closest('.reply-link');
            const parentId = btn.dataset.parent;
            const username = btn.dataset.username;
            
            document.getElementById('parentId').value = parentId;
            document.getElementById('cancelReply').style.display = 'inline-block';
            
            const indicator = document.getElementById('replyingToIndicator');
            const userSpan = document.getElementById('replyingToUser');
            userSpan.textContent = '@' + username;
            indicator.style.display = 'block';
            
            document.getElementById('commentForm').scrollIntoView({behavior: 'smooth'});
        }
        
        if (e.target.matches('#cancelReply')) {
            document.getElementById('parentId').value = '';
            document.getElementById('replyingToIndicator').style.display = 'none';
            e.target.style.display = 'none';
            
            const form = document.getElementById('commentForm');
            form.action.value = 'comment';
            if (form.comment_id) {
                form.removeChild(form.comment_id);
            }
            form.comment.value = '';
        }
        
        if (e.target.matches('.edit-link') || e.target.closest('.edit-link')) {
            const btn = e.target.matches('.edit-link') ? e.target : e.target.closest('.edit-link');
            const id = btn.dataset.id;
            const text = btn.dataset.text;
            const form = document.getElementById('commentForm');
            
            document.getElementById('replyingToIndicator').style.display = 'none';
            document.getElementById('parentId').value = '';
            
            form.comment.value = text;
            form.action.value = 'edit_comment';

            if (!form.comment_id) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'comment_id';
                input.value = id;
                form.appendChild(input);
            } else {
                form.comment_id.value = id;
            }

            document.getElementById('cancelReply').style.display = 'inline-block';
            form.scrollIntoView({behavior: 'smooth'});
        }
        
        if (e.target.classList.contains('show-more-btn')) {
            const btn = e.target;
            const parentId = btn.getAttribute('data-parent-id');
            const total = parseInt(btn.getAttribute('data-total'));
            let shown = parseInt(btn.getAttribute('data-shown'));

            const parentDiv = btn.previousElementSibling;

            let count = 0;
            const children = parentDiv.querySelectorAll('.child-comment[style*="display: none"]');
            for (let i = 0; i < children.length && count < 5; i++, count++) {
                children[i].style.display = '';
            }

            shown += count;
            btn.setAttribute('data-shown', shown);

            if (shown >= total) {
                btn.remove();
            }
        }
    });

    // Modal para screenshots
    function openModal(img, idx=0) {
        const modal = document.getElementById('imageModal');
        const modalImg = document.getElementById('modalImage');
        modalImg.src = img.dataset.full || img.src;
        modal.dataset.index = idx;
        modal.style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('imageModal').style.display = 'none';
    }

    function nextImage() { navigateImage(1); }
    function previousImage() { navigateImage(-1); }
    
    function navigateImage(step) {
        const modal = document.getElementById('imageModal');
        const gallery = document.querySelectorAll('.screenshot-gallery img');
        let idx = parseInt(modal.dataset.index,10) + step;
        if (idx < 0) idx = gallery.length-1;
        if (idx >= gallery.length) idx = 0;
        gallery[idx].click();
    }
    </script>

    <!-- Modal para screenshots -->
    <div id='imageModal' class='fixed inset-0 bg-black bg-opacity-90 flex items-center justify-center z-50' style='display:none' onclick='closeModal()'>
        <button class='absolute top-4 right-6 text-white text-4xl hover:text-gray-300' onclick='closeModal()'>&times;</button>
        <img class='max-w-[90%] max-h-[90%] border-4 border-white rounded-lg' id='modalImage' alt='Screenshot'>
        <div class='absolute top-1/2 left-4 right-4 flex justify-between pointer-events-none'>
            <button onclick='previousImage()' class='text-white text-3xl hover:text-gray-300 pointer-events-auto'>&lt;</button>
            <button onclick='nextImage()' class='text-white text-3xl hover:text-gray-300 pointer-events-auto'>&gt;</button>
        </div>
    </div>

    <?php
    renderFooter();
} else {
    // Página inicial - listagem de jogos
    renderHeader();

    $page   = max(1, (int)($_GET['p'] ?? 1));
    $offset = ($page - 1) * POSTS_PER_PAGE;

    $sql = "SELECT * FROM games ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, POSTS_PER_PAGE, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $games = $stmt->fetchAll();

    $total = $pdo->query("SELECT COUNT(*) FROM games")->fetchColumn();
    $pages = ceil($total / POSTS_PER_PAGE);

    ?>
    <div class="min-h-screen bg-background text-foreground">
        <!-- Search Section -->
        <section class="search-section">
            <div class="container">
                <div class="search-wrapper">
                    <input type="text" id="searchInput" placeholder="Pesquisar jogos..." class="input">
                    <svg class="search-icon w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <path d="m21 21-4.35-4.35"></path>
                    </svg>
                    <div id="searchResults" class="search-results"></div>
                </div>
            </div>
        </section>

        <div class="container py-8">
            <!-- Hero Section -->
            <div class="text-center mb-8">
                <h1 class="text-4xl font-bold mb-4">
                    <svg class="w-8 h-8 inline mr-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polygon points="10,8 16,12 10,16 10,8"></polygon>
                    </svg>
                    Jogos Traduzidos
                </h1>
                <p class="text-muted-foreground text-lg">Descubra os melhores jogos Ren'Py em português</p>
            </div>

            <!-- Games Grid -->
            <?php if (empty($games)): ?>
                <div class="text-center py-12">
                    <p class="text-muted-foreground">Nenhum jogo encontrado.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <?php foreach ($games as $g): ?>
                        <a href="?game=<?= $g['slug'] ?>" class="block">
                            <div class="card game-card">
                                <div class="game-card-image">
                                    <img src="uploads/covers/<?= $g['cover_image'] ?>" 
                                         alt="<?= htmlspecialchars($g['title']) ?>" 
                                         loading="lazy">
                                </div>
                                <div class="card-header pb-2">
                                    <h3 class="card-title text-lg line-clamp-2"><?= htmlspecialchars($g['title']) ?></h3>
                                    <div class="flex items-center gap-2 text-sm text-muted-foreground">
                                        <span class="badge badge-secondary"><?= htmlspecialchars($g['category']) ?></span>
                                        <span><?= htmlspecialchars($g['version']) ?></span>
                                    </div>
                                </div>
                                <div class="card-content pb-2">
                                    <p class="text-sm text-muted-foreground line-clamp-3 mb-3">
                                        <?= substr(htmlspecialchars($g['description']), 0, 100) ?>...
                                    </p>
                                    <div class="flex items-center gap-2 mb-2">
                                        <svg class="w-4 h-4 fill-yellow-400 text-yellow-400" viewBox="0 0 24 24">
                                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                        </svg>
                                        <span class="text-sm font-medium">4.5</span>
                                    </div>
                                </div>
                                <div class="card-footer pt-2">
                                    <div class="flex items-center justify-between w-full text-xs text-muted-foreground">
                                        <div class="flex items-center gap-1">
                                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                                <line x1="3" y1="10" x2="21" y2="10"></line>
                                            </svg>
                                            <span><?= date('d/m/Y', strtotime($g['created_at'])) ?></span>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                                <polyline points="7,10 12,15 17,10"></polyline>
                                                <line x1="12" y1="15" x2="12" y2="3"></line>
                                            </svg>
                                            <span><?= $g['downloads_count'] ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?p=<?= $page - 1 ?>" class="btn btn-outline btn-sm">
                                <svg class="w-4 h-4 mr-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="15,18 9,12 15,6"></polyline>
                                </svg>
                                Anterior
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
                            <a href="?p=<?= $i ?>" 
                               class="btn <?= $i === $page ? 'btn-primary' : 'btn-outline' ?> btn-sm">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $pages): ?>
                            <a href="?p=<?= $page + 1 ?>" class="btn btn-outline btn-sm">
                                Próxima
                                <svg class="w-4 h-4 ml-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="9,18 15,12 9,6"></polyline>
                                </svg>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
    // Busca autocomplete - página inicial
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
                        results.innerHTML = '<div class="p-4 text-center text-muted-foreground">Nenhum jogo encontrado</div>';
                    } else {
                        results.innerHTML = games.map(game => 
                            `<a href="?game=${game.slug}" class="block">
                                <strong>${game.title}</strong><br>
                                <small class="text-muted-foreground">${game.category} • ${game.downloads_count} downloads</small>
                            </a>`
                        ).join('');
                    }
                    results.style.display = 'block';
                })
                .catch(() => {
                    results.innerHTML = '<div class="p-4 text-center text-muted-foreground">Erro na busca</div>';
                    results.style.display = 'block';
                });
        });
        
        document.addEventListener('click', function(e) {
            if (!e.target.closest('#searchInput') && !e.target.closest('#searchResults')) {
                results.style.display = 'none';
            }
        });
    });
    </script>

    <?php
    renderFooter();
}
?>
