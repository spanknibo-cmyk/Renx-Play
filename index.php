<?php
require 'config.php';

date_default_timezone_set('America/Sao_Paulo');

// Redireciona antigos links ?game=... para a nova rota dedicada
$gameSlug = $_GET['game'] ?? null;
if ($gameSlug) {
    header('Location: game.php?slug=' . urlencode($gameSlug));
    exit;
}

renderHeader('', 'Lista de jogos', 'jogos, renpy, visual novel');
?>

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
                <a href="game.php?slug=<?= urlencode($g['slug']) ?>" style="text-decoration: none; color: inherit;">
                    <div class="card">
                        <div class="card-image">
                            <?= renderImageTag('uploads/covers/' . $g['cover_image'], $g['title'], ['loading' => 'lazy']) ?>
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
        <?= renderPagination($page, $pages, '', [], 'p', PAGINATION_RANGE) ?>
    <?php endif; ?>
</div>

<script>
// Busca
(function() {
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
            `<a href="game.php?slug=${game.slug}" style="display: block; padding: 0.75rem 1rem; text-decoration: none; color: hsl(var(--foreground)); border-bottom: 1px solid hsl(var(--border)); transition: background 0.2s;" onmouseover="this.style.background='hsl(var(--accent))'" onmouseout="this.style.background='transparent'">
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

  document.addEventListener('click', function(e) {
    if (!e.target.closest('.search-wrapper')) {
      results.style.display = 'none';
    }
  });
})();
</script>

<?php renderFooter(); ?>
