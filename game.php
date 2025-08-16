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

$gameSlug = $_GET['slug'] ?? ($_GET['game'] ?? null);
if (!$gameSlug) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT g.*, u.username AS author FROM games g JOIN users u ON g.posted_by=u.id WHERE g.slug = ?");
$stmt->execute([$gameSlug]);
$game = $stmt->fetch();

if (!$game) {
    header('Location: index.php');
    exit;
}

$gameId = (int)$game['id'];
$postOwner = (int)$game['posted_by'];

if (isLoggedIn() && isset($_POST['action'])) {
    $gameIdPost = (int)($_POST['game_id'] ?? 0);
    if ($gameIdPost < 1) {
        header('Location: index.php');
        exit;
    }

    $owner = $pdo->prepare("SELECT posted_by FROM games WHERE id = ?");
    $owner->execute([$gameIdPost]);
    $postOwner = (int)$owner->fetchColumn();

    if (!$postOwner) {
        header('Location: index.php');
        exit;
    }

    if ($_POST['action'] === 'comment' && !empty($_POST['comment'])) {
        $pdo->prepare("INSERT INTO comments (game_id, user_id, comment, parent_id, created_at) VALUES (?,?,?,?,NOW())")
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

    header("Location: game.php?slug={$gameSlug}#comments");
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
$totalComments = (int)$cTotal->fetchColumn();

renderHeader($game['title']);
?>

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
            <?= renderImageTag('uploads/covers/' . $game['cover_image'], $game['title']) ?>
        </div>

        <div class="game-info">
            <h1><?= htmlspecialchars($game['title']) ?></h1>

            <div class="game-badges">
                <span class="badge"><?= htmlspecialchars(html_entity_decode($game['engine'] ?? "REN'PY")) ?></span>
                <span class="badge"><?= htmlspecialchars($game['version'] ?? 'v1.0') ?></span>
                <span class="badge"><?= htmlspecialchars($game['author']) ?></span>
            </div>

            <div class="card-rating" style="margin-bottom: .75rem;">
                <i class="fas fa-star"></i>
                <span>4.5</span>
            </div>

            <div style="margin-bottom: .75rem;">
                <?php $langs = !empty($game['languages_multi']) ? json_decode($game['languages_multi'], true) : []; ?>
                <p><strong>Idiomas:</strong>
                    <?php if ($langs): ?>
                        <?= htmlspecialchars(implode(', ', $langs)) ?>
                    <?php else: ?>
                        <?= htmlspecialchars($game['language'] ?? 'English') ?>
                    <?php endif; ?>
                </p>
                <?php if (!empty($game['developer_name'])): ?>
                    <p><strong>Desenvolvedor:</strong> <?= htmlspecialchars($game['developer_name']) ?></p>
                <?php endif; ?>
                <p><strong>Censurado:</strong> <?= !empty($game['censored']) ? 'Sim' : 'Não' ?></p>
                <?php if (!empty($game['released_at_custom'])): ?>
                    <p><strong>Lançamento:</strong> <?= date('d/m/Y', strtotime($game['released_at_custom'])) ?></p>
                <?php else: ?>
                    <p><strong>Lançamento:</strong> <?= date('d/m/Y', strtotime($game['created_at'])) ?></p>
                <?php endif; ?>
                <?php if (!empty($game['updated_at_custom'])): ?>
                    <p><strong>Atualização:</strong> <?= date('d/m/Y', strtotime($game['updated_at_custom'])) ?></p>
                <?php endif; ?>
            </div>

            <?php if (!empty($game['patreon_url']) || !empty($game['discord_url']) || !empty($game['subscribestar_url']) || !empty($game['itch_url']) || !empty($game['kofi_url']) || !empty($game['bmc_url']) || !empty($game['steam_url'])): ?>
            <div class="download-section" style="margin-top:.5rem;">
                <div class="creator-links">
                    <?php if (!empty($game['patreon_url'])): ?><a class="btn btn-outline" target="_blank" href="<?= htmlspecialchars($game['patreon_url']) ?>"><i class="fab fa-patreon"></i> Patreon</a><?php endif; ?>
                    <?php if (!empty($game['discord_url'])): ?><a class="btn btn-outline" target="_blank" href="<?= htmlspecialchars($game['discord_url']) ?>"><i class="fab fa-discord"></i> Discord</a><?php endif; ?>
                    <?php if (!empty($game['subscribestar_url'])): ?><a class="btn btn-outline" target="_blank" href="<?= htmlspecialchars($game['subscribestar_url']) ?>"><i class="fas fa-star"></i> SubscribeStar</a><?php endif; ?>
                    <?php if (!empty($game['itch_url'])): ?><a class="btn btn-outline" target="_blank" href="<?= htmlspecialchars($game['itch_url']) ?>"><i class="fas fa-gamepad"></i> itch.io</a><?php endif; ?>
                    <?php if (!empty($game['kofi_url'])): ?><a class="btn btn-outline" target="_blank" href="<?= htmlspecialchars($game['kofi_url']) ?>"><i class="fas fa-mug-hot"></i> Ko-fi</a><?php endif; ?>
                    <?php if (!empty($game['bmc_url'])): ?><a class="btn btn-outline" target="_blank" href="<?= htmlspecialchars($game['bmc_url']) ?>"><i class="fas fa-coffee"></i> Buy Me a Coffee</a><?php endif; ?>
                    <?php if (!empty($game['steam_url'])): ?><a class="btn btn-outline" target="_blank" href="<?= htmlspecialchars($game['steam_url']) ?>"><i class="fab fa-steam"></i> Steam</a><?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isLoggedIn()): ?>
        <div class="download-section">
            <h3><i class="fas fa-download"></i> Selecionar Plataforma:</h3>
            <div class="download-buttons">
                <?php if (!empty($game['download_url_windows'])): ?>
                    <a href="<?= htmlspecialchars($game['download_url_windows']) ?>" target="_blank" class="btn btn-download">
                        <i class="fab fa-windows"></i>
                        Windows
                    </a>
                <?php endif; ?>

                <?php if (!empty($game['download_url_android'])): ?>
                    <a href="<?= htmlspecialchars($game['download_url_android']) ?>" target="_blank" class="btn btn-download">
                        <i class="fab fa-android"></i>
                        Android
                    </a>
                <?php endif; ?>

                <?php if (!empty($game['download_url'])): ?>
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

    <?php if (!empty($game['screenshots'])): ?>
        <div class="card" style="margin: 1rem 0;">
            <div class="card-content">
                <?= displayScreenshots($game['screenshots'], $game['title'], $game['id'], false) ?>
            </div>
        </div>
    <?php endif; ?>

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
</div>

<?php renderFooter(); ?>