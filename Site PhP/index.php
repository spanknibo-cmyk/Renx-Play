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

    // BARRA DE PESQUISA INLINE
    ?>
    <section class="search-section">
    <div class="container">
        <div class="search-wrapper">
            <input type="text" id="searchInput" placeholder="Pesquisar jogos...">
            <i class="fas fa-search search-icon"></i>
            <div id="searchResults" class="search-results"></div>
        </div>
    </div>
</section>
    <?php

    $shots = json_decode($game['screenshots'] ?: '[]', true);
    $links = json_decode($game['download_links'] ?: '[]', true);

    echo "<div class='container main-content'>
    <section class='game-header'>
        <figure class='cover-box'>
            <img src='uploads/covers/{$game['cover_image']}' class='game-cover' alt='Capa {$game['title']}'>
        </figure>
        <header class='game-info'>
            <h1>{$game['title']}</h1>
            <ul class='game-meta'>
                <li><i class='fas fa-user'></i> {$game['author']}</li>
                <li><i class='fas fa-calendar'></i> ".date('d/m/Y',strtotime($game['created_at']))."</li>
                <li><i class='fas fa-download'></i> {$game['downloads_count']}</li>
                <li><i class='fas fa-tag'></i> {$game['category']}</li>
            </ul>
        </header>
    </section>";

    if ($shots) {
        echo "<section class='screenshots'><h2><i class='fas fa-images'></i> Screenshots</h2><div class='screenshot-gallery'>";
        foreach ($shots as $i => $s)
            echo "<img src='uploads/screenshots/{$s}' data-full='uploads/screenshots/{$s}' class='screenshot' alt='Screenshot' onclick='openModal(this, {$i})'>";
        echo "</div></section>";
    }

    echo "<section class='game-description'>
        <h2><i class='fas fa-info-circle'></i> Descrição</h2>
        <p>".nl2br(htmlspecialchars($game['description']))."</p>
    </section>

    <section class='game-specs'>
        <div class='spec-grid'>
            <div><strong>Idiomas:</strong> {$game['languages']}</div>
            <div><strong>Plataformas:</strong> {$game['platforms']}</div>
            <div><strong>Versão:</strong> {$game['version']}</div>
            <div><strong>Tradutor:</strong> {$game['translator']}</div>
        </div>
    </section>";

    if (isLoggedIn() && $links) {
        echo "<section class='download-section'>
            <h2><i class='fas fa-download'></i> Downloads</h2>
            <div class='download-buttons'>";
        foreach ($links as $l)
            echo "<a class='btn btn-download' href='{$l}' target='_blank' onclick='fetch(\"?download={$gameId}\")'><i class='fas fa-download'></i> Download</a>";
        echo "</div></section>";
    } elseif (!isLoggedIn()) {
        echo "<p class='login-required'><i class='fas fa-lock'></i><a href='auth.php'>Faça login</a> para baixar</p>";
    }

    echo "<section class='comments-section' id='comments'>
            <h2><i class='fas fa-comments'></i> Comentários ({$totalComments})</h2>";

    if (isLoggedIn()) {
        echo "<form class='comment-form' method='POST' id='commentForm'>
                <input type='hidden' name='action' value='comment'>
                <input type='hidden' name='game_id' value='{$gameId}'>
                <input type='hidden' name='parent_id' id='parentId' value=''>
                <div id='replyingToIndicator' style='display:none;' class='replying-indicator'>
                    Respondendo: <span id='replyingToUser'></span>
                </div>
                <textarea name='comment' placeholder='Escreva algo...' required></textarea>
                <div class='comment-form-actions'>
                    <button class='btn btn-primary'><i class='fas fa-paper-plane'></i> Enviar</button>
                    <button type='button' class='btn btn-secondary' id='cancelReply' style='display:none'>Cancelar</button>
                </div>
              </form>";
    }

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
            $ownerCls = ($c['user_id'] === $postOwner) ? 'comment-author-owner' : '';
            echo "<article class='comment {$ownerCls}' data-id='{$c['id']}' data-level='{$level}'>
                    <header>
                        <span class='comment-author'>{$c['username']}</span>";

            $dt = new DateTime($c['created_at'], new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone('America/Sao_Paulo'));
            echo "<time>" . $dt->format('d/m/Y H:i') . "</time>";

            echo ($c['edited_at'] ? " <em>(editado)</em>" : '') . "
                   </header>";

            if ($level === 1 && !empty($c['parent_id']) && isset($allUsers[$c['parent_id']])) {
                $replyTo = htmlspecialchars($allUsers[$c['parent_id']]['username']);
                echo "<div class='replying-to'>Respondendo: <span class='reply-user'>@{$replyTo}</span></div>";
            }

            echo "<p>" . nl2br(htmlspecialchars($c['comment'])) . "</p>
                   <div class='comment-actions'>";

            if (isLoggedIn()) {
                echo "<button class='reply-link' data-parent='{$c['id']}' data-username='{$c['username']}'><i class='fas fa-reply'></i> Responder</button>";
                if ($_SESSION['user']['id'] === $c['user_id']) {
                    echo "<button class='edit-link' data-id='{$c['id']}' data-text='" . htmlspecialchars($c['comment'], ENT_QUOTES) . "'>
                            <i class='fas fa-edit'></i> Editar</button>";
                }
                if ($_SESSION['user']['id'] === $c['user_id'] || canModerateComments($postOwner)) {
                    echo "<form method='POST' style='display:inline'>
                            <input type='hidden' name='action' value='delete_comment'>
                            <input type='hidden' name='comment_id' value='{$c['id']}'>
                            <input type='hidden' name='game_id' value='{$c['game_id']}'>
                            <button class='delete-link' onclick='return confirm(\"Apagar comentário?\")'>
                                <i class='fas fa-trash'></i> Excluir
                            </button>
                          </form>";
                }
            }
            echo "</div>";

            if (!empty($c['children'])) {
                $totalChildren = count($c['children']);
                echo "<div class='comment-children'>";
                foreach ($c['children'] as $i => $child) {
                    $style = $i < 5 ? '' : 'style="display:none;"';
                    echo "<div class='child-comment' {$style}>";
                    renderComments([$child], $postOwner, $allUsers, $level + 1);
                    echo "</div>";
                }
                echo "</div>";

                if ($totalChildren > 5) {
                    echo "<button class='show-more-btn' data-parent-id='{$c['id']}' data-shown='5' data-total='{$totalChildren}'>
                            Ver mais respostas ({$totalChildren} total)
                          </button>";
                }
            }
            echo "</article>";
        }
    }

    echo "<div class='comments-list'>";
    renderComments($tree, $game['posted_by'], $commentUsers);
    echo "</div></section></div>";

    renderFooter();
} else {
    // Página inicial - listagem de jogos
    renderHeader();

    // BARRA DE PESQUISA INLINE - PÁGINA INICIAL
    ?>
    <section class="search-section">
    <div class="container">
        <div class="search-wrapper">
            <input type="text" id="searchInput" placeholder="Pesquisar jogos...">
            <i class="fas fa-search search-icon"></i>
            <div id="searchResults" class="search-results"></div>
        </div>
    </div>
</section>
    <?php

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

    echo "<section class='hero-section'>
            <div class='container'>
                <h1><i class='fas fa-gamepad'></i> Jogos Traduzidos</h1>
                <p>Descubra os melhores jogos Ren'Py em português</p>
            </div>
          </section>

          <div class='container'>
            <div class='games-grid'>";

    foreach ($games as $g) {
        echo "<article class='game-card'>
                <a href='?game={$g['slug']}' class='game-link'>
                    <img src='uploads/covers/{$g['cover_image']}' class='game-card-image' alt='" . htmlspecialchars($g['title'], ENT_QUOTES) . "'>
                    <div class='game-card-content'>
                        <h3 class='game-title'>" . htmlspecialchars($g['title']) . "</h3>
                        <p class='game-excerpt'>" . substr(htmlspecialchars($g['description']), 0, 100) . "...</p>
                        <div class='game-stats'>
                            <span><i class='fas fa-download'></i> {$g['downloads_count']}</span>
                            <span><i class='fas fa-calendar'></i> " . date('d/m/Y', strtotime($g['created_at'])) . "</span>
                        </div>
                    </div>
                </a>
              </article>";
    }
    echo "  </div>";

    if ($pages > 1) {
        echo "<nav class='pagination'>";
        if ($page > 1)
            echo "<a href='?p=" . ($page - 1) . "' class='btn btn-secondary'><i class='fas fa-chevron-left'></i> Anterior</a>";

        for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++) {
            $cls = $i === $page ? 'active' : '';
            echo "<a href='?p={$i}' class='btn btn-secondary {$cls}'>{$i}</a>";
        }

        if ($page < $pages)
            echo "<a href='?p=" . ($page + 1) . "' class='btn btn-secondary'>Próxima <i class='fas fa-chevron-right'></i></a>";

        echo "</nav>";
    }

    echo "</div>";
    renderFooter();
}
?>

<script>
// BUSCA AUTOCOMPLETE - SIMPLES E GARANTIDA
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
        
        // Faz a busca
        fetch('search_games.php?q=' + encodeURIComponent(query))
            .then(response => response.json())
            .then(games => {
                if (games.length === 0) {
                    results.innerHTML = '<div style="padding:15px;color:#666;">Nenhum jogo encontrado</div>';
                } else {
                    results.innerHTML = games.map(game => 
                        `<a href="?game=${game.slug}" style="display:block;padding:10px 15px;text-decoration:none;color:#333;border-bottom:1px solid #eee;">
                            <strong>${game.title}</strong><br>
                            <small style="color:#666;">${game.category} • ${game.downloads_count} downloads</small>
                        </a>`
                    ).join('');
                }
                results.style.display = 'block';
            })
            .catch(() => {
                results.innerHTML = '<div style="padding:15px;color:#666;">Erro na busca</div>';
                results.style.display = 'block';
            });
    });
    
    // Esconder ao clicar fora
    document.addEventListener('click', function(e) {
        if (!e.target.closest('#searchInput') && !e.target.closest('#searchResults')) {
            results.style.display = 'none';
        }
    });
});

// Resto do JavaScript original
function openModal(img, idx=0) {
    const modal    = document.getElementById('imageModal');
    const modalImg = document.getElementById('modalImage');
    modalImg.src   = img.dataset.full || img.src;
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

document.addEventListener('click', e => {
    if (e.target.matches('.reply-link')) {
        const parentId = e.target.dataset.parent;
        const username = e.target.dataset.username;
        
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
    
    if (e.target.matches('.edit-link')) {
        const id = e.target.dataset.id;
        const text = e.target.dataset.text;
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
</script>

<style>
.comment { border: 1px solid #ccc; padding: 1em; margin-bottom: 1em; border-radius: 6px; }
.comment-author-owner { background: #fff8dc; border-color: #ffd700; color: #333333; }
.comment-children { margin-left: 2em; border-left: 2px solid #ccc; padding-left: 1em; }
.child-comment { margin-top: 0.5em; }
.comment-actions { margin-top: 0.5em; }
.comment-actions button, .comment-actions .delete-link {
    background: none; border: none; color: #007BFF; cursor: pointer; margin-right: 0.5em;
}
.comment-actions button:hover, .comment-actions .delete-link:hover { text-decoration: underline; }

.replying-to {
    font-size: 0.9em;
    color: #666;
    margin-bottom: 0.5em;
    padding: 0.25em 0.5em;
    background: #f8f9fa;
    border-left: 3px solid #007bff;
    border-radius: 3px;
}
.reply-user {
    font-weight: bold;
    color: #007bff;
}

.replying-indicator {
    font-size: 0.9em;
    color: #666;
    margin-bottom: 0.5em;
    padding: 0.5em;
    background: #e3f2fd;
    border-left: 3px solid #2196f3;
    border-radius: 3px;
}

.show-more-btn {
    background: none;
    border: none;
    color: #007bff;
    cursor: pointer;
    font-size: 0.9em;
    margin-top: 0.5em;
}
.show-more-btn:hover {
    text-decoration: underline;
}
.modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%;
       background:rgba(0,0,0,.9); align-items:center; justify-content:center; z-index:9999; }
.modal img { max-width:90%; max-height:90%; border:4px solid #fff; border-radius:8px; }
.modal-controls { position:absolute; top:50%; width:100%; display:flex; justify-content:space-between; }
.modal-controls button { background:none; border:none; font-size:2rem; color:#fff; cursor:pointer; }
.inline-form { display:inline; }


/* ====== SEARCH BAR (usa variáveis do tema) ====== */
.search-section {
    background: var(--bg-tertiary);
    padding: 2rem 0;
    margin-bottom: 2rem;
    border-bottom: 1px solid var(--border);
}

.search-wrapper {
    position: relative;
    max-width: 600px;
    margin: 0 auto;
}

.search-wrapper input {
    width: 100%;
    padding: 0.75rem 3rem 0.75rem 1rem;
    font-size: 1rem;
    border: 2px solid var(--border);
    border-radius: 25px;
    background: var(--bg-elevated);
    color: var(--text-primary);
    transition: all 0.3s ease;
    font-family: inherit;
}

.search-wrapper input::placeholder {
    color: var(--text-secondary);
}

.search-wrapper input:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(88, 166, 255, 0.2);
    outline: none;
    transform: translateY(-1px);
}

.search-icon {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    font-size: 1.1rem;
    color: var(--text-secondary);
    pointer-events: none;
    transition: color 0.3s ease;
}

.search-wrapper input:focus + .search-icon {
    color: var(--accent);
}

.search-results {
    display: none;
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: var(--bg-elevated);
    border: 1px solid var(--border);
    border-top: none;
    border-radius: 0 0 12px 12px;
    box-shadow: var(--shadow);
    max-height: 350px;
    overflow-y: auto;
    z-index: 100;
}

.search-results a {
    display: block;
    padding: 0.75rem 1rem;
    text-decoration: none;
    color: var(--text-primary);
    border-bottom: 1px solid var(--border);
    transition: background-color 0.2s ease;
}

.search-results a:last-child {
    border-bottom: none;
}

.search-results a:hover {
    background: var(--bg-tertiary);
}

.search-results strong {
    color: var(--accent);
}

.search-results small {
    color: var(--text-secondary);
}

.search-results div {
    padding: 1rem;
    text-align: center;
    color: var(--text-secondary);
    font-style: italic;
}

/* Responsivo */
@media (max-width: 576px) {
    .search-section {
        padding: 1.5rem 0;
    }
    
    .search-wrapper input {
        font-size: 0.9rem;
        padding: 0.65rem 2.5rem 0.65rem 0.85rem;
    }
    
    .search-icon {
        right: 0.75rem;
        font-size: 1rem;
    }
}


</style>

<div id='imageModal' class='modal' onclick='closeModal()' role='dialog' aria-hidden='true'>
    <button class='close' onclick='closeModal()' aria-label='Fechar' style='position:absolute;top:15px;right:25px;font-size:2.5rem'>&times;</button>
    <img class='modal-content' id='modalImage' alt='Imagem da screenshot'>
    <div class='modal-controls'>
        <button onclick='previousImage()' aria-label='Imagem anterior'>&lt;</button>
        <button onclick='nextImage()' aria-label='Próxima imagem'>&gt;</button>
    </div>
</div>
