<?php
require 'config.php';
requireLogin();

date_default_timezone_set('America/Sao_Paulo');

$ME      = $_SESSION['user']['id'];
$MYROLE  = $_SESSION['user']['role'];

/* ───── Helpers de papel ─────────────────────────────────────────────── */
function isDev()        { return $_SESSION['user']['role'] === 'DEV'; }
function isSuperAdmin() { return $_SESSION['user']['role'] === 'SUPER_ADMIN'; }
function isAdmin()      { return $_SESSION['user']['role'] === 'ADMIN'; }

function managedUserIds(PDO $pdo): array
{
    $me = $_SESSION['user']['id'];

    if (isDev()) {
        return array_column($pdo->query("SELECT id FROM users")->fetchAll(), 'id');
    }

    if (isSuperAdmin()) {
        $ids   = [$me];
        $query = $pdo->prepare("SELECT id FROM users WHERE role='ADMIN' AND created_by=?");
        $query->execute([$me]);
        return array_merge($ids, array_column($query->fetchAll(), 'id'));
    }
    return [$me]; // ADMIN
}

function canManageGame(int $ownerId, PDO $pdo): bool
{
    if (isDev()) return true;
    return in_array($ownerId, managedUserIds($pdo));
}

/* ───── Roteamento ───────────────────────────────────────────────────── */
$action  = $_POST['action'] ?? $_GET['action'] ?? 'home';
$message = '';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ──────────────────────────────────────────────────────────────────────
| 1. BACK-END (CRUD)
|───────────────────────────────────────────────────────────────────────*/

/* ► Criar Jogo */
if ($action === 'create_game' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isAdmin() && !isSuperAdmin() && !isDev()) die('403');

    $errors = [];
    foreach (['title','description','languages','platforms','version','category',
              'translator','download_links'] as $fld) {
        if (empty(trim($_POST[$fld] ?? '')))
            $errors[] = "Campo <strong>$fld</strong> é obrigatório";
    }

    /* capa obrigatória */
    $cover = null;
    if (!empty($_FILES['cover_image']['name'])) {
        $cover = uploadFile($_FILES['cover_image'], 'uploads/covers/');
        if (!$cover) $errors[] = 'Falha no upload da capa';
    } else {
        $errors[] = 'Capa é obrigatória';
    }

    /* screenshots (opcional) */
    $shots = [];
    if (!empty($_FILES['screenshots']['name'][0])) {
        $shots = uploadMultipleFiles($_FILES['screenshots'], 'uploads/screenshots/');
    }

    $links = array_filter(array_map('trim', explode("\n", $_POST['download_links'])));

    if (!$errors) {
        $stmt = $pdo->prepare("
            INSERT INTO games
              (title, description, cover_image, screenshots, languages, platforms,
               version, category, translator, posted_by, download_links, created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())");
        $ok   = $stmt->execute([
            sanitize($_POST['title']),
            sanitize($_POST['description']),
            $cover,
            json_encode($shots),
            sanitize($_POST['languages']),
            sanitize($_POST['platforms']),
            sanitize($_POST['version']),
            sanitize($_POST['category']),
            sanitize($_POST['translator']),
            $ME,
            json_encode($links)
        ]);

        $message = $ok
            ? "<div class='bg-green-500/10 border border-green-500/20 text-green-700 dark:text-green-400 px-4 py-3 rounded-md mb-4'>
                <svg class='w-5 h-5 inline mr-2' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                    <path d='M9 12l2 2 4-4'></path>
                    <circle cx='12' cy='12' r='10'></circle>
                </svg>
                Jogo criado com sucesso!
               </div>"
            : "<div class='bg-red-500/10 border border-red-500/20 text-red-700 dark:text-red-400 px-4 py-3 rounded-md mb-4'>
                Erro ao salvar jogo.
               </div>";
        $action  = 'my_games';
    } else {
        $message = "<div class='bg-red-500/10 border border-red-500/20 text-red-700 dark:text-red-400 px-4 py-3 rounded-md mb-4'>
                     <svg class='w-5 h-5 inline mr-2' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                         <circle cx='12' cy='12' r='10'></circle>
                         <line x1='15' y1='9' x2='9' y2='15'></line>
                         <line x1='9' y1='9' x2='15' y2='15'></line>
                     </svg>
                     " . implode('<br>', $errors) . "
                   </div>";
    }
}

/* ► Atualizar Jogo */
if ($action === 'update_game' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id   = (int)$_POST['game_id'];
    $game = $pdo->query("SELECT * FROM games WHERE id=$id")->fetch();

    if (!$game || !canManageGame($game['posted_by'],$pdo)) {
        $message = "<div class='bg-red-500/10 border border-red-500/20 text-red-700 dark:text-red-400 px-4 py-3 rounded-md mb-4'>Acesso negado!</div>";
        $action  = 'my_games';
    } else {
        /* capa */
        $cover = $game['cover_image'];
        if (!empty($_FILES['cover_image']['name'])) {
            $new = uploadFile($_FILES['cover_image'],'uploads/covers/');
            if ($new) {
                if (file_exists('uploads/covers/'.$cover)) unlink('uploads/covers/'.$cover);
                $cover = $new;
            }
        }
        /* screenshots */
        $shots = json_decode($game['screenshots'],true) ?: [];
        if (!empty($_FILES['screenshots']['name'][0])) {
            foreach ($shots as $old)
                if (file_exists('uploads/screenshots/'.$old)) unlink('uploads/screenshots/'.$old);
            $shots = uploadMultipleFiles($_FILES['screenshots'],'uploads/screenshots/');
        }
        $links = array_filter(array_map('trim', explode("\n", $_POST['download_links'])));

        $upd = $pdo->prepare("
            UPDATE games SET
              title=?, description=?, cover_image=?, screenshots=?, languages=?,
              platforms=?, version=?, category=?, translator=?, download_links=?
            WHERE id=?");
        $upd->execute([
            sanitize($_POST['title']),
            sanitize($_POST['description']),
            $cover,
            json_encode($shots),
            sanitize($_POST['languages']),
            sanitize($_POST['platforms']),
            sanitize($_POST['version']),
            sanitize($_POST['category']),
            sanitize($_POST['translator']),
            json_encode($links),
            $id
        ]);

        $message = "<div class='bg-green-500/10 border border-green-500/20 text-green-700 dark:text-green-400 px-4 py-3 rounded-md mb-4'>
                     <svg class='w-5 h-5 inline mr-2' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                         <path d='M9 12l2 2 4-4'></path>
                         <circle cx='12' cy='12' r='10'></circle>
                     </svg>
                     Jogo atualizado com sucesso!
                   </div>";
        $action  = 'my_games';
    }
}

/* ► Deletar Jogo */
if ($action === 'delete_game' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $g  = $pdo->query("SELECT * FROM games WHERE id=$id")->fetch();

    if ($g && canManageGame($g['posted_by'],$pdo)) {
        if ($g['cover_image'] && file_exists('uploads/covers/'.$g['cover_image']))
            unlink('uploads/covers/'.$g['cover_image']);

        foreach (json_decode($g['screenshots'],true) ?: [] as $img) {
            if (file_exists('uploads/screenshots/'.$img)) unlink('uploads/screenshots/'.$img);
        }
        $pdo->prepare("DELETE FROM games WHERE id=?")->execute([$id]);
        $message = "<div class='bg-green-500/10 border border-green-500/20 text-green-700 dark:text-green-400 px-4 py-3 rounded-md mb-4'>
                     <svg class='w-5 h-5 inline mr-2' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                         <polyline points='3,6 5,6 21,6'></polyline>
                         <path d='m19,6v14a2,2 0 0,1 -2,2H7a2,2 0 0,1 -2,-2V6m3,0V4a2,2 0 0,1 2,-2h4a2,2 0 0,1 2,2v2'></path>
                     </svg>
                     Jogo removido com sucesso!
                   </div>";
    }
    $action = 'my_games';
}

/* ► Criar Usuário */
if ($action === 'create_user' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isDev() && !isSuperAdmin()) die('403');

    $roleAllowed = isDev()
        ? ['DEV','SUPER_ADMIN','ADMIN','USER']
        : ['ADMIN','USER'];                   // SUPER_ADMIN cria apenas ADMIN/USER

    if (!in_array($_POST['role'],$roleAllowed)) die('Role não permitido');

    try {
        $pdo->prepare("
            INSERT INTO users(username,email,password,role,created_by,created_at)
            VALUES(?,?,?,?,?,NOW())")
            ->execute([
                sanitize($_POST['username']),
                sanitize($_POST['email']),
                password_hash($_POST['password'],PASSWORD_DEFAULT),
                $_POST['role'],
                $ME
            ]);
        $message = "<div class='bg-green-500/10 border border-green-500/20 text-green-700 dark:text-green-400 px-4 py-3 rounded-md mb-4'>
                     <svg class='w-5 h-5 inline mr-2' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                         <path d='M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2'></path>
                         <circle cx='9' cy='7' r='4'></circle>
                         <path d='M22 21v-2a4 4 0 0 0-3-3.87'></path>
                         <path d='M16 3.13a4 4 0 0 1 0 7.75'></path>
                     </svg>
                     Usuário criado com sucesso!
                   </div>";
        $action  = 'users';
    } catch (PDOException $e) {
        $message = "<div class='bg-red-500/10 border border-red-500/20 text-red-700 dark:text-red-400 px-4 py-3 rounded-md mb-4'>".$e->getMessage()."</div>";
    }
}

/* ► Trocar Cargo (DEV) */
if ($action === 'update_user_role' && $_SERVER['REQUEST_METHOD'] === 'POST' && isDev()) {
    $uid = (int)$_POST['user_id'];
    $new = $_POST['role'];
    $pdo->prepare("UPDATE users SET role=? WHERE id=? AND role<>'DEV'")->execute([$new,$uid]);
    $message = "<div class='bg-green-500/10 border border-green-500/20 text-green-700 dark:text-green-400 px-4 py-3 rounded-md mb-4'>Cargo atualizado!</div>";
    $action  = 'users';
}

/* ► Deletar Usuário */
if ($action === 'delete_user' && isset($_GET['id'])) {
    $uid = (int)$_GET['id'];
    $usr = $pdo->prepare("SELECT role,created_by FROM users WHERE id=?");
    $usr->execute([$uid]);
    $usr = $usr->fetch();

    $canDelete = false;
    if (isDev() && $usr && $usr['role']!=='DEV' && $uid!=$ME) $canDelete = true;
    if (isSuperAdmin() && $usr && $usr['role']==='ADMIN' && $usr['created_by']==$ME) $canDelete = true;

    if ($canDelete) {
        $pdo->prepare("UPDATE games SET posted_by=? WHERE posted_by=?")->execute([$ME,$uid]);
        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
        $message = "<div class='bg-green-500/10 border border-green-500/20 text-green-700 dark:text-green-400 px-4 py-3 rounded-md mb-4'>
                     <svg class='w-5 h-5 inline mr-2' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                         <polyline points='3,6 5,6 21,6'></polyline>
                         <path d='m19,6v14a2,2 0 0,1 -2,2H7a2,2 0 0,1 -2,-2V6m3,0V4a2,2 0 0,1 2,-2h4a2,2 0 0,1 2,2v2'></path>
                     </svg>
                     Usuário removido com sucesso!
                   </div>";
    } else {
        $message = "<div class='bg-red-500/10 border border-red-500/20 text-red-700 dark:text-red-400 px-4 py-3 rounded-md mb-4'>Não autorizado.</div>";
    }
    $action = 'users';
}

/* ──────────────────────────────────────────────────────────────────────
| 2. FRONT-END
|───────────────────────────────────────────────────────────────────────*/
renderHeader('Painel de Controle');
?>

<div class="min-h-screen bg-background text-foreground">
    <!-- Hero Section -->
    <section class="py-8 bg-muted/30 border-b border-border">
        <div class="container">
            <h1 class="text-3xl font-bold mb-2">
                <svg class="w-8 h-8 inline mr-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                </svg>
                Painel de Controle
            </h1>
            <p class="text-muted-foreground">Administre seus jogos e usuários.</p>
        </div>
    </section>

    <?= $message ?>

    <div class="container py-8">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Sidebar -->
            <aside class="lg:col-span-1">
                <div class="card">
                    <div class="card-header">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center">
                                <svg class="w-6 h-6 text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="12" cy="7" r="4"></circle>
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-semibold"><?= $_SESSION['user']['username'] ?></h3>
                                <span class="text-sm text-muted-foreground bg-secondary px-2 py-1 rounded-md"><?= $MYROLE ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="card-content">
                        <nav class="space-y-2">
                            <?php
                            $menu = [
                                'home'        => ['Início','home'],
                                'create_game' => ['Novo Jogo','plus'],
                                'my_games'    => ['Meus Jogos','gamepad-2'],
                                'create_user' => ['Novo Usuário','user-plus'],
                                'users'       => ['Usuários','users'],
                                'all_games'   => ['Todos os Jogos','globe']
                            ];
                            
                            $icons = [
                                'home' => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9,22 9,12 15,12 15,22"></polyline>',
                                'plus' => '<circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line>',
                                'gamepad-2' => '<line x1="6" y1="11" x2="10" y2="11"></line><line x1="8" y1="9" x2="8" y2="13"></line><line x1="15" y1="12" x2="15.01" y2="12"></line><line x1="18" y1="10" x2="18.01" y2="10"></line><rect x="2" y="6" width="20" height="12" rx="2"></rect>',
                                'user-plus' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line>',
                                'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
                                'globe' => '<circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>'
                            ];
                            
                            foreach ($menu as $key => [$lbl,$ico]) {
                                if (in_array($key,['create_game','my_games']) && !isAdmin() && !isSuperAdmin() && !isDev()) continue;
                                if (in_array($key,['create_user','users'])   && !isDev() && !isSuperAdmin()) continue;
                                if ($key==='all_games' && !isDev()) continue;

                                $isActive = $action===$key;
                                $activeClass = $isActive ? 'bg-primary text-primary-foreground' : 'hover:bg-muted';
                                echo "<a href='?action={$key}' class='flex items-center gap-3 px-3 py-2 rounded-md transition-colors {$activeClass}'>
                                        <svg class='w-4 h-4' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                                            {$icons[$ico]}
                                        </svg>
                                        {$lbl}
                                      </a>";
                            }
                            ?>
                        </nav>
                    </div>
                </div>
            </aside>

            <!-- Main Content -->
            <main class="lg:col-span-3">
                <?php
                switch ($action) {

                /* ╭─────────── HOME ────────────╮ */
                case 'home':
                    $ids         = managedUserIds($pdo);
                    $ph          = implode(',',array_fill(0,count($ids),'?'));

                    $gamesCount  = $pdo->prepare("SELECT COUNT(*) FROM games WHERE posted_by IN ($ph)");
                    $downloads   = $pdo->prepare("SELECT COALESCE(SUM(downloads_count),0) FROM games WHERE posted_by IN ($ph)");
                    $gamesCount->execute($ids);
                    $downloads->execute($ids);

                    $stats = [
                        'games'     => $gamesCount->fetchColumn(),
                        'downloads' => $downloads->fetchColumn()
                    ];

                    if (isDev()) {
                        $stats['users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
                    } elseif (isSuperAdmin()) {
                        $a = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role='ADMIN' AND created_by=?");
                        $a->execute([$ME]);
                        $stats['admins'] = $a->fetchColumn();
                    }

                    echo "<div class='grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8'>";
                    echo "<div class='card'>
                            <div class='card-content pt-6'>
                                <div class='flex items-center gap-4'>
                                    <div class='w-12 h-12 bg-blue-500/10 rounded-full flex items-center justify-center'>
                                        <svg class='w-6 h-6 text-blue-500' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                                            <line x1='6' y1="11" x2="10" y2="11"></line>
                                            <line x1='8' y1="9" x2="8" y2="13"></line>
                                            <line x1='15' y1="12" x2="15.01" y2="12"></line>
                                            <line x1='18' y1="10" x2="18.01" y2="10"></line>
                                            <rect x='2' y='6' width='20' height='12' rx='2'></rect>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class='text-2xl font-bold'>{$stats['games']}</h3>
                                        <p class='text-muted-foreground'>Jogos</p>
                                    </div>
                                </div>
                            </div>
                          </div>";
                    
                    if (isset($stats['admins'])) {
                        echo "<div class='card'>
                                <div class='card-content pt-6'>
                                    <div class='flex items-center gap-4'>
                                        <div class='w-12 h-12 bg-green-500/10 rounded-full flex items-center justify-center'>
                                            <svg class='w-6 h-6 text-green-500' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                                                <path d='M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2'></path>
                                                <circle cx='9' cy='7' r='4'></circle>
                                                <path d='M22 21v-2a4 4 0 0 0-3-3.87'></path>
                                                <path d='M16 3.13a4 4 0 0 1 0 7.75'></path>
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 class='text-2xl font-bold'>{$stats['admins']}</h3>
                                            <p class='text-muted-foreground'>Admins</p>
                                        </div>
                                    </div>
                                </div>
                              </div>";
                    }
                    
                    if (isset($stats['users'])) {
                        echo "<div class='card'>
                                <div class='card-content pt-6'>
                                    <div class='flex items-center gap-4'>
                                        <div class='w-12 h-12 bg-purple-500/10 rounded-full flex items-center justify-center'>
                                            <svg class='w-6 h-6 text-purple-500' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                                                <path d='M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2'></path>
                                                <circle cx='9' cy='7' r='4'></circle>
                                                <path d='M22 21v-2a4 4 0 0 0-3-3.87'></path>
                                                <path d='M16 3.13a4 4 0 0 1 0 7.75'></path>
                                            </svg>
                                        </div>
                                        <div>
                                            <h3 class='text-2xl font-bold'>{$stats['users']}</h3>
                                            <p class='text-muted-foreground'>Usuários</p>
                                        </div>
                                    </div>
                                </div>
                              </div>";
                    }
                    
                    echo "<div class='card'>
                            <div class='card-content pt-6'>
                                <div class='flex items-center gap-4'>
                                    <div class='w-12 h-12 bg-orange-500/10 rounded-full flex items-center justify-center'>
                                        <svg class='w-6 h-6 text-orange-500' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                                            <path d='M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4'></path>
                                            <polyline points='7,10 12,15 17,10'></polyline>
                                            <line x1='12' y1='15' x2='12' y2='3'></line>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class='text-2xl font-bold'>{$stats['downloads']}</h3>
                                        <p class='text-muted-foreground'>Downloads</p>
                                    </div>
                                </div>
                            </div>
                          </div>";
                    echo "</div>";

                    /* últimos jogos */
                    $recent = $pdo->prepare("SELECT id,title,description,cover_image,downloads_count,created_at,slug
                                         FROM games
                                         WHERE posted_by IN ($ph)
                                         ORDER BY created_at DESC LIMIT 6");
                    $recent->execute($ids);
                    $recent = $recent->fetchAll();

                    if ($recent) {
                        echo "<div class='card'>
                                <div class='card-header'>
                                    <h2 class='card-title'>
                                        <svg class='w-5 h-5 inline mr-2' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                                            <circle cx='12' cy='12' r='10'></circle>
                                            <polyline points='12,6 12,12 16,14'></polyline>
                                        </svg>
                                        Últimos jogos
                                    </h2>
                                </div>
                                <div class='card-content'>
                                    <div class='grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4'>";
                        foreach ($recent as $g) {
                            echo "<a href='./index.php?game={$g['slug']}' target='_blank' class='block'>
                                    <div class='card hover:shadow-md transition-shadow'>
                                        <div class='game-card-image'>
                                            <img src='uploads/covers/{$g['cover_image']}' alt='{$g['title']}' class='w-full h-32 object-cover'>
                                        </div>
                                        <div class='card-content'>
                                            <h3 class='font-semibold text-sm line-clamp-1'>{$g['title']}</h3>
                                            <p class='text-xs text-muted-foreground line-clamp-2 mt-1'>".substr($g['description'],0,100)."...</p>
                                            <div class='flex items-center justify-between mt-2 text-xs text-muted-foreground'>
                                                <div class='flex items-center gap-1'>
                                                    <svg class='w-3 h-3' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                                                        <path d='M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4'></path>
                                                        <polyline points='7,10 12,15 17,10'></polyline>
                                                        <line x1='12' y1='15' x2='12' y2='3'></line>
                                                    </svg>
                                                    <span>{$g['downloads_count']}</span>
                                                </div>
                                                <div class='flex items-center gap-1'>
                                                    <svg class='w-3 h-3' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                                                        <rect x='3' y='4' width='18' height='18' rx='2' ry='2'></rect>
                                                        <line x1='16' y1='2' x2='16' y2='6'></line>
                                                        <line x1='8' y1='2' x2='8' y2='6'></line>
                                                        <line x1='3' y1='10' x2='21' y2='10'></line>
                                                    </svg>
                                                    <span>".date('d/m/Y',strtotime($g['created_at']))."</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                  </a>";
                        }
                        echo "    </div>
                                </div>
                              </div>";
                    }
                break;

                /* ╭───────── FORM NOVO JOGO ─────────╮ */
                case 'create_game':
                    echo "<h1><i class='fas fa-plus'></i> Novo Jogo</h1>
                          <form method='POST' enctype='multipart/form-data' class='form-grid'>
                            <input type='hidden' name='action' value='create_game'>

                            <div class='form-group'>
                                <label><i class='fas fa-heading'></i> Título *</label>
                                <input type='text' name='title' required value='".($_POST['title']??'')."'>
                            </div>

                            <div class='form-group'>
                                <label><i class='fas fa-image'></i> Capa *</label>
                                <input type='file' name='cover_image' accept='image/*' required id='coverInput'>
                                <div id='coverPreview'></div>
                            </div>

                            <div class='form-group full-width'>
                                <label><i class='fas fa-align-left'></i> Descrição *</label>
                                <textarea name='description' rows='4' required>".($_POST['description']??'')."</textarea>
                            </div>

                            <div class='form-group'>
                                <label><i class='fas fa-language'></i> Idiomas *</label>
                                <input type='text' name='languages' required value='".($_POST['languages']??'')."'>
                            </div>

                            <div class='form-group'>
                                <label><i class='fas fa-desktop'></i> Plataformas *</label>
                                <input type='text' name='platforms' required value='".($_POST['platforms']??'')."'>
                            </div>

                            <div class='form-group'>
                                <label><i class='fas fa-tag'></i> Versão *</label>
                                <input type='text' name='version' required value='".($_POST['version']??'')."'>
                            </div>

                            <div class='form-group'>
                                <label><i class='fas fa-folder'></i> Categoria *</label>
                                <input type='text' name='category' required value='".($_POST['category']??'')."'>
                            </div>

                            <div class='form-group'>
                                <label><i class='fas fa-user'></i> Tradutor *</label>
                                <input type='text' name='translator' required value='".($_POST['translator']??'')."'>
                            </div>

                            <div class='form-group'>
                                <label><i class='fas fa-images'></i> Screenshots (opcional)</label>
                                <input type='file' name='screenshots[]' accept='image/*' multiple id='screenshotsInput'>
                                <div id='screenshotsPreview'></div>
                            </div>

                            <div class='form-group full-width'>
                                <label><i class='fas fa-download'></i> Links de Download *</label>
                                <textarea name='download_links' rows='3' required placeholder='Um link por linha'>".($_POST['download_links']??'')."</textarea>
                            </div>

                            <div class='form-actions'>
                                <button class='btn btn-primary'><i class='fas fa-save'></i> Criar</button>
                                <a href='?action=my_games' class='btn btn-secondary'>Cancelar</a>
                            </div>
                          </form>";
                break;

                /* ╭───────── EDITAR JOGO ───────────╮ */
                case 'edit_game':
                    $id   = (int)$_GET['id'];
                    $game = $pdo->query("SELECT * FROM games WHERE id=$id")->fetch();

                    if (!$game || !canManageGame($game['posted_by'],$pdo)) {
                        echo "<div class='alert alert-error'>Acesso negado!</div>";
                        break;
                    }

                    $shots = json_decode($game['screenshots'],true) ?: [];
                    $links = implode("\n", json_decode($game['download_links'],true) ?: []);

                    echo "<h1><i class='fas fa-edit'></i> Editar Jogo</h1>
                          <form method='POST' enctype='multipart/form-data' class='form-grid'>
                            <input type='hidden' name='action'   value='update_game'>
                            <input type='hidden' name='game_id' value='{$game['id']}'>

                            <div class='form-group'>
                                <label><i class='fas fa-heading'></i> Título *</label>
                                <input type='text' name='title' required value='".htmlspecialchars($game['title'])."'>
                            </div>

                            <div class='form-group'>
                                <label><i class='fas fa-image'></i> Nova Capa (opcional)</label>
                                <input type='file' name='cover_image' accept='image/*' id='editCoverInput'>
                                <small>Capa atual: <strong>{$game['cover_image']}</strong></small>
                                <img src='uploads/covers/{$game['cover_image']}' alt='' style='max-width:200px;margin:10px 0;border-radius:8px;border:2px solid var(--border)'>
                                <div id='editCoverPreview'></div>
                            </div>

                            <div class='form-group full-width'>
                                <label><i class='fas fa-align-left'></i> Descrição *</label>
                                <textarea name='description' rows='4' required>".htmlspecialchars($game['description'])."</textarea>
                            </div>

                            <div class='form-group'>
                                <label><i class='fas fa-language'></i> Idiomas *</label>
                                <input type='text' name='languages' required value='".htmlspecialchars($game['languages'])."'>
                            </div>

                            <div class='form-group'>
                                <label><i class='fas fa-desktop'></i> Plataformas *</label>
                                <input type='text' name='platforms' required value='".htmlspecialchars($game['platforms'])."'>
                            </div>

                            <div class='form-group'>
                                <label><i class='fas fa-tag'></i> Versão *</label>
                                <input type='text' name='version' required value='".htmlspecialchars($game['version'])."'>
                            </div>

                            <div class='form-group'>
                                <label><i class='fas fa-folder'></i> Categoria *</label>
                                <input type='text' name='category' required value='".htmlspecialchars($game['category'])."'>
                            </div>

                            <div class='form-group'>
                                <label><i class='fas fa-user'></i> Tradutor *</label>
                                <input type='text' name='translator' required value='".htmlspecialchars($game['translator'])."'>
                            </div>

                            <div class='form-group'>
                                <label><i class='fas fa-images'></i> Substituir Screenshots</label>
                                <input type='file' name='screenshots[]' accept='image/*' multiple id='editScreenshotsInput'>
                                <small>Atuais: ".count($shots)."</small>
                                <div style='display:flex;gap:8px;flex-wrap:wrap;margin:10px 0'>";
                    foreach ($shots as $img) {
                        echo "<img src='uploads/screenshots/{$img}' style='width:100px;height:60px;object-fit:cover;border-radius:4px;border:2px solid var(--border)'>";
                    }
                    echo    "</div>
                                <div id='editScreenshotsPreview'></div>
                            </div>

                            <div class='form-group full-width'>
                                <label><i class='fas fa-download'></i> Links de Download *</label>
                                <textarea name='download_links' rows='3' required>{$links}</textarea>
                            </div>

                            <div class='form-actions'>
                                <button class='btn btn-primary'><i class='fas fa-save'></i> Salvar</button>
                                <a href='?action=my_games' class='btn btn-secondary'>Cancelar</a>
                            </div>
                          </form>";
                break;

                /* ╭───────── NOVO USUÁRIO ─────────╮ */
                case 'create_user':
                    if (!isDev() && !isSuperAdmin()) die('403');
                    
                    echo "<h1><i class='fas fa-user-plus'></i> Novo Usuário</h1>
                          <form method='POST' class='form-grid'>
                            <input type='hidden' name='action' value='create_user'>
                            
                            <div class='form-group'>
                                <label><i class='fas fa-user'></i> Nome de Usuário *</label>
                                <input type='text' name='username' required>
                            </div>
                            
                            <div class='form-group'>
                                <label><i class='fas fa-envelope'></i> Email *</label>
                                <input type='email' name='email' required>
                            </div>
                            
                            <div class='form-group'>
                                <label><i class='fas fa-lock'></i> Senha *</label>
                                <input type='password' name='password' required minlength='6'>
                            </div>
                            
                            <div class='form-group'>
                                <label><i class='fas fa-shield-alt'></i> Função *</label>
                                <select name='role' required>";
                    
                    if (isDev()) {
                        echo "<option value='DEV'>DEV</option>
                              <option value='SUPER_ADMIN'>SUPER_ADMIN</option>";
                    }
                    echo "<option value='ADMIN'" . (isSuperAdmin() ? ' selected' : '') . ">ADMIN</option>
                          <option value='USER'>USER</option>
                                </select>
                            </div>
                            
                            <div class='form-actions'>
                                <button type='submit' class='btn btn-primary'><i class='fas fa-save'></i> Criar Usuário</button>
                                <a href='?action=users' class='btn btn-secondary'>Cancelar</a>
                            </div>
                          </form>";
                break;

                /* ╭───────── MEUS JOGOS ───────────╮ */
                case 'my_games':
                    $ids   = managedUserIds($pdo);
                    $ph    = implode(',',array_fill(0,count($ids),'?'));
                    $stmt  = $pdo->prepare("
                        SELECT g.*,u.username author
                        FROM games g
                        JOIN users u ON u.id=g.posted_by
                        WHERE g.posted_by IN ($ph)
                        ORDER BY g.created_at DESC");
                    $stmt->execute($ids);
                    $games = $stmt->fetchAll();

                    echo "<h1><i class='fas fa-gamepad'></i> Jogos (".count($games).")</h1>";
                    if (!isDev()) {
                        echo "<div style='margin-bottom:2rem'>
                                <a href='?action=create_game' class='btn btn-primary'><i class='fas fa-plus'></i> Novo Jogo</a>
                              </div>";
                    }

                    if (!$games) {
                        echo "<p>Nenhum jogo encontrado.</p>";
                    } else {
                        echo "<div class='games-grid'>";
                        foreach ($games as $g) {
                            $shots = json_decode($g['screenshots'],true) ?: [];
                            echo "<div class='game-card'>
                                    <div class='game-cover'><img src='uploads/covers/{$g['cover_image']}' class='game-card-image'></div>
                                    <div class='game-card-content'>
                                        <h3 class='game-title'>{$g['title']}".
                                        (isSuperAdmin()||isDev() ? " <small>({$g['author']})</small>" : '').
                                        "</h3>
                                        <div class='game-stats'>
                                            <span><i class='fas fa-images'></i> ".count($shots)."</span>
                                            <span><i class='fas fa-download'></i> {$g['downloads_count']}</span>
                                        </div>
                                        <div class='game-actions' style='margin-top:1rem'>";
                        echo          "<a class='btn btn-small' href='../index.php?game={$g['id']}' target='_blank'><i class='fas fa-eye'></i></a>";
                        if (canManageGame($g['posted_by'],$pdo)) {
                            echo      "<a class='btn btn-small' href='?action=edit_game&id={$g['id']}'><i class='fas fa-edit'></i></a>
                                       <a class='btn btn-small btn-danger' href='?action=delete_game&id={$g['id']}'
                                          onclick='return confirm(\"Apagar?\")'><i class='fas fa-trash'></i></a>";
                        }
                        echo        "</div></div></div>";
                        }
                        echo "</div>";
                    }
                break;

                /* ╭───────── TODOS OS JOGOS (DEV) ─────╮ */
                case 'all_games':
                    if (!isDev()) die('403');
                    $games = $pdo->query("SELECT g.*,u.username author FROM games g
                                          JOIN users u ON u.id=g.posted_by
                                          ORDER BY g.created_at DESC")->fetchAll();

                    echo "<h1><i class='fas fa-globe'></i> Todos os Jogos (".count($games).")</h1>
                          <div class='table-responsive'><table class='data-table'>
                            <thead><tr>
                                <th>Capa</th><th>Título</th><th>Autor</th><th>Data</th><th>Ações</th>
                            </tr></thead><tbody>";
                    foreach ($games as $g) {
                        echo "<tr>
                                <td><img src='uploads/covers/{$g['cover_image']}' style='width:40px;height:60px;object-fit:cover'></td>
                                <td>{$g['title']}</td>
                                <td>{$g['author']}</td>
                                <td>".date('d/m/Y',strtotime($g['created_at']))."</td>
                                <td>
                                    <a class='btn btn-small' target='_blank' href='../index.php?game={$g['id']}' title='Ver'><i class='fas fa-eye'></i></a>
                                    <a class='btn btn-small' href='?action=edit_game&id={$g['id']}' title='Editar'><i class='fas fa-edit'></i></a>
                                    <a class='btn btn-small btn-danger' href='?action=delete_game&id={$g['id']}'
                                       onclick='return confirm(\"Apagar?\")' title='Excluir'><i class='fas fa-trash'></i></a>
                                </td>
                              </tr>";
                    }
                    echo "</tbody></table></div>";
                break;

                /* ╭─────────── USUÁRIOS ─────────────╮ */
                case 'users':
                    if (!isDev() && !isSuperAdmin()) die('403');

                    $search = trim($_GET['search'] ?? '');
                    $where  = $params = [];

                    if ($search) {
                        $where[]  = "(u.username LIKE ? OR u.email LIKE ?)";
                        $params[] = "%$search%";
                        $params[] = "%$search%";
                    }

                    if (isSuperAdmin() && !isDev()) {  // só ADMINs criados por ele
                        $where[]  = "u.role='ADMIN' AND u.created_by=?";
                        $params[] = $ME;
                    }

                    $sql = "SELECT u.*,COUNT(g.id) games
                            FROM users u
                            LEFT JOIN games g ON g.posted_by=u.id ".
                            ($where ? "WHERE ".implode(' AND ',$where) : '').
                           " GROUP BY u.id ORDER BY u.created_at DESC";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $users = $stmt->fetchAll();

                    echo "<h1><i class='fas fa-users'></i> Usuários (".count($users).")</h1>";

                    echo "<div class='user-search-wrapper'>
                    <div class='search-box'>
                        <input type='text' id='userSearchInput' placeholder='Buscar usuário ou email…'>
                        <i class='fas fa-search'></i>
                        <div id='userSearchResults' class='search-results'></div>
                    </div>
                    <div class='role-filter'>
                        <button data-role='' class='active'>Todos</button>
                        <button data-role='DEV'>DEV</button>
                        <button data-role='SUPER_ADMIN'>Super Admin</button>
                        <button data-role='ADMIN'>Admin</button>
                        <button data-role='USER'>User</button>
                    </div>
                </div>";


                    if (!$users) {
                        echo "<p>Nenhum usuário encontrado.</p>";
                    } else {
                        echo "<div class='table-responsive'><table class='data-table'>
                                <thead><tr>
                                    <th>ID</th><th>Usuário</th><th>Email</th><th>Função</th><th>Jogos</th><th>Cadastro</th>";
                        if (isDev() || isSuperAdmin()) echo "<th>Ações</th>";
                        echo "</tr></thead><tbody>";

                        foreach ($users as $u) {
                            echo "<tr>
                                    <td>{$u['id']}</td>
                                    <td>{$u['username']}</td>
                                    <td>{$u['email']}</td>
                                    <td>";
                            /* DEV altera cargo (exceto outros DEVs/ele mesmo) */
                            if (isDev() && $u['role']!=='DEV' && $u['id']!=$ME) {
                                echo "<form method='POST' style='display:inline'>
                                        <input type='hidden' name='action'  value='update_user_role'>
                                        <input type='hidden' name='user_id' value='{$u['id']}'>
                                        <select name='role' onchange='this.form.submit()' style='border:none;background:transparent'>
                                            <option value='USER' ".($u['role']=='USER'?'selected':'').">USER</option>
                                            <option value='ADMIN' ".($u['role']=='ADMIN'?'selected':'').">ADMIN</option>
                                            <option value='SUPER_ADMIN' ".($u['role']=='SUPER_ADMIN'?'selected':'').">SUPER_ADMIN</option>
                                        </select>
                                      </form>";
                            } else {
                                echo "<span class='role role-{$u['role']}'>{$u['role']}</span>";
                            }
                            echo "</td>
                                  <td>{$u['games']}</td>
                                  <td>".date('d/m/Y',strtotime($u['created_at']))."</td>";

                            if (isDev() || isSuperAdmin()) {
                                echo "<td>";
                                $canDelete = (isDev() && $u['role']!=='DEV' && $u['id']!=$ME) ||
                                             (isSuperAdmin() && $u['role']=='ADMIN' && $u['created_by']==$ME);
                                if ($canDelete) {
                                    echo "<a class='btn btn-small btn-danger' href='?action=delete_user&id={$u['id']}'
                                           onclick='return confirm(\"Deletar {$u['username']}?\")'><i class='fas fa-trash'></i></a>";
                                } else echo "-";
                                echo "</td>";
                            }
                            echo "</tr>";
                        }
                        echo "</tbody></table></div>";
                    }
                break;

                /* ───────────── default ───────────── */
                default:
                    echo "<p>Seção não encontrada.</p>";
                }

                echo "</main></div>";   // container

                /* ───── CSS essencial ──────────────────────────────────────────────── */
                /* ───── CSS mínimo ──────────────────────────────────────────────── */
                echo "<style>
                .stats-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                    gap: 1.5rem;
                    margin: 2rem 0;
                }

                .stat-card {
                    background: var(--bg-elevated);
                    padding: 2rem;
                    border-radius: 12px;
                    display: flex;
                    align-items: center;
                    gap: 1rem;
                    border: 1px solid var(--border);
                    box-shadow: var(--card-shadow);
                }

                .game-actions {
                    display: flex;
                    gap: 0.5rem;
                    flex-wrap: wrap;
                }

                .form-actions {
                    grid-column: 1 / -1;
                    display: flex;
                    gap: 1rem;
                    margin-top: 1rem;
                }

                /* ====== RESPONSIVIDADE EXPANDIDA ====== */

                /* Tablets */
                @media (max-width: 992px) {
                    .dashboard {
                        grid-template-columns: 1fr;
                        gap: 1rem;
                    }
                    
                    .dashboard-sidebar {
                        position: static;
                        padding: 1.5rem;
                    }
                    
                    .stats-grid {
                        grid-template-columns: repeat(2, 1fr);
                        gap: 1rem;
                    }
                }

                /* Mobile médio */
                @media (max-width: 768px) {
                    /* Força containers principais a não estourar */
                    .container {
                        max-width: 100%;
                        overflow-x: hidden;
                        padding: 0 15px;
                    }
                    
                    .dashboard {
                        grid-template-columns: 1fr;
                        max-width: 100%;
                        overflow-x: hidden;
                    }
                    
                    .dashboard-sidebar {
                        position: static;
                        max-width: 100%;
                    }
                    
                    .dashboard-content {
                        max-width: 100%;
                        overflow-x: hidden;
                        padding: 1.5rem;
                    }
                    
                    /* Stats em coluna única */
                    .stats-grid {
                        grid-template-columns: 1fr;
                        gap: 1rem;
                        margin: 1rem 0;
                    }
                    
                    .stat-card {
                        padding: 1.5rem;
                        flex-direction: column;
                        text-align: center;
                    }
                    
                    /* Formulários responsivos */
                    .form-grid {
                        grid-template-columns: 1fr;
                        gap: 1rem;
                    }
                    
                    .form-actions {
                        flex-direction: column;
                        gap: 0.75rem;
                    }
                    
                    .form-actions .btn {
                        width: 100%;
                        justify-content: center;
                    }
                    
                    /* Games grid */
                    .games-grid {
                        grid-template-columns: 1fr;
                        gap: 1rem;
                        padding: 0;
                    }
                    
                    .game-card {
                        max-width: 100%;
                    }
                    
                    /* Hero section */
                    .hero-section {
                        padding: 1.5rem 0;
                    }
                    
                    .hero-section h1 {
                        font-size: 2rem;
                    }
                }

                /* Mobile pequeno */
                @media (max-width: 576px) {
                    /* Garante que NADA ultrapasse a viewport */
                    body {
                        overflow-x: hidden;
                        max-width: 100vw;
                    }
                    
                    * {
                        max-width: 100%;
                        box-sizing: border-box;
                    }
                    
                    /* Containers principais */
                    .container,
                    .nav-container,
                    .dashboard,
                    .dashboard-content,
                    .dashboard-sidebar {
                        max-width: 100vw;
                        overflow-x: hidden;
                    }
                    
                    .dashboard-content {
                        padding: 1rem;
                    }
                    
                    .dashboard-sidebar {
                        padding: 1rem;
                    }
                    
                    /* Stats cards menores */
                    .stat-card {
                        padding: 1rem;
                    }
                    
                    .stat-card i {
                        font-size: 1.5rem;
                    }
                    
                    /* TABELAS - Ajustes expandidos */
                    .table-responsive {
                        overflow-x: auto;
                        -webkit-overflow-scrolling: touch;
                        max-width: 100%;
                        border-radius: 8px;
                    }
                    
                    .data-table {
                        min-width: 600px; /* força scroll se necessário */
                        font-size: 0.8rem;
                    }
                    
                    .data-table tbody tr:hover {
                        transform: none;
                        background: var(--bg-tertiary);
                    }
                    
                    .data-table td, 
                    .data-table th {
                        padding: 0.5rem 0.25rem;
                        font-size: 0.75rem;
                        white-space: nowrap; /* mantém em linha para não quebrar layout */
                        max-width: 120px;
                        overflow: hidden;
                        text-overflow: ellipsis;
                    }
                    
                    /* Células específicas que podem quebrar */
                    .data-table td:nth-child(2), /* username */
                    .data-table td:nth-child(3)  /* email */ {
                        max-width: 100px;
                        word-break: break-all;
                    }
                    
                    .role {
                        font-size: 0.6rem;
                        padding: 0.15rem 0.25rem;
                        white-space: nowrap;
                    }
                    
                    /* User info na sidebar */
                    .user-info {
                        flex-direction: column;
                        text-align: center;
                        gap: 0.5rem;
                    }
                    
                    .user-info i {
                        font-size: 2rem;
                    }
                    
                    /* Navigation mobile */
                    .dashboard-nav a {
                        padding: 0.75rem;
                        justify-content: center;
                        text-align: center;
                    }
                    
                    /* Formulários */
                    .form-group input,
                    .form-group textarea,
                    .form-group select {
                        width: 100%;
                        min-width: 0;
                        box-sizing: border-box;
                    }
                    
                    /* Títulos */
                    h1 {
                        font-size: 1.8rem;
                        word-break: break-word;
                    }
                    
                    h2 {
                        font-size: 1.4rem;
                        word-break: break-word;
                    }
                    
                    /* Botões pequenos */
                    .btn-small {
                        padding: 0.35rem 0.5rem;
                        font-size: 0.7rem;
                    }
                    
                    /* Alerts */
                    .alert {
                        padding: 0.75rem;
                        font-size: 0.9rem;
                        word-wrap: break-word;
                    }
                    
                    /* Paginação */
                    .pagination {
                        flex-wrap: wrap;
                        justify-content: center;
                        gap: 0.25rem;
                    }
                    
                    .pagination .btn {
                        padding: 0.5rem 0.75rem;
                        font-size: 0.8rem;
                        min-width: auto;
                    }
                    
                    /* Busca inline */
                    .form-group input[type=\"text\"],
                    .form-group input[type=\"email\"] {
                        min-width: 0;
                        width: 100%;
                    }
                    
                    /* Game actions */
                    .game-actions {
                        gap: 0.25rem;
                        justify-content: center;
                    }
                    
                    /* Preview de imagens */
                    #coverPreview img,
                    #screenshotsPreview img,
                    #editCoverPreview img,
                    #editScreenshotsPreview img {
                        max-width: 80px;
                        height: 50px;
                    }
                }

                /* Mobile extra pequeno */
                @media (max-width: 360px) {
                    .container {
                        padding: 0 10px;
                    }
                    
                    .dashboard-content,
                    .dashboard-sidebar {
                        padding: 0.75rem;
                    }
                    
                    .data-table th,
                    .data-table td {
                        padding: 0.4rem 0.15rem;
                        font-size: 0.7rem;
                    }
                    
                    .btn {
                        padding: 0.5rem 0.75rem;
                        font-size: 0.8rem;
                    }
                    
                    .hero-section h1 {
                        font-size: 1.5rem;
                    }
                    
                    .stat-card {
                        padding: 0.75rem;
                    }
                }


                /* ====== BUSCA DE USUÁRIOS ====== */
                .user-search-wrapper {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 1rem;
                    margin-bottom: 1.5rem;
                    align-items: center;
                }

                .search-box {
                    position: relative;
                    flex: 1 1 240px;
                }

                .search-box input {
                    width: 100%;
                    padding: 0.65rem 2.5rem 0.65rem 0.9rem;
                    border: 1px solid var(--border);
                    border-radius: 6px;
                    background: var(--bg-elevated);
                    color: var(--text-primary);
                }

                .search-box i {
                    position: absolute;
                    right: 0.9rem;
                    top: 50%;
                    transform: translateY(-50%);
                    color: var(--text-secondary);
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
                    border-radius: 0 0 8px 8px;
                    max-height: 300px;
                    overflow-y: auto;
                    z-index: 200;
                }

                .search-results .res-item {
                    display: block;
                    padding: 0.65rem 0.9rem;
                    text-decoration: none;
                    color: var(--text-primary);
                    border-bottom: 1px solid var(--border);
                    font-size: 0.9rem;
                }

                .search-results .res-item:hover {
                    background: var(--bg-tertiary);
                }

                .search-results .nores {
                    padding: 0.75rem 1rem;
                    color: var(--text-secondary);
                    text-align: center;
                }

                .role-filter {
                    display: flex;
                    gap: 0.5rem;
                    flex-wrap: wrap;
                }

                .role-filter button {
                    background: var(--bg-elevated);
                    border: 1px solid var(--border);
                    padding: 0.5rem 0.75rem;
                    border-radius: 6px;
                    cursor: pointer;
                    font-size: 0.8rem;
                    color: var(--text-primary);
                    transition: all 0.2s ease;
                }

                .role-filter button.active,
                .role-filter button:hover {
                    background: var(--accent);
                    color: #fff;
                    border-color: var(--accent);
                }

                @media (max-width: 576px) {
                    .user-search-wrapper { flex-direction: column; }
                    .role-filter { justify-content: center; }
                    .search-box input { font-size: 0.9rem; }
                }


                /* ====== MENU HAMBÚRGUER - SEU CSS + AJUSTES DO PRINCIPAL ====== */
                .menu-toggle {
                    display: none;
                    background: none;
                    border: none;
                    font-size: 1.5rem;
                    color: var(--text-secondary);
                    cursor: pointer;
                    padding: 0.5rem;
                    border-radius: 6px;
                    transition: all 0.3s ease;
                }

                .menu-toggle:hover {
                    color: var(--text-primary);          /* ← Mudança: era --accent */
                    background: var(--bg-tertiary);
                }

                @media (max-width: 768px) {
                    .navbar,
                    .nav-container {
                        overflow: visible !important;
                        position: relative;
                    }
                    
                    .menu-toggle {
                        display: block !important;
                    }
                    
                    .nav-menu {
                        display: none;
                        position: absolute;
                        top: 100%;
                        left: 0;
                        right: 0;
                        background: var(--bg-secondary);
                        border: 1px solid var(--border);
                        border-top: none;
                        padding: 1rem;
                        flex-direction: column;
                        gap: 0.5rem;                     /* ← Mudança: era 0.25rem */
                        box-shadow: var(--shadow);
                        z-index: 9999;                   /* ← Manter este z-index alto que funciona */
                        width: 100vw;                    /* ← Manter este que funciona */
                        max-width: 100%;                 /* ← Manter este que funciona */
                        border-radius: 0 0 8px 8px;      /* ← Manter este que funciona */
                    }
                    
                    .nav-menu.active {
                        display: flex !important;
                        animation: slideDown 0.3s ease;
                    }
                    
                    .nav-menu a {
                        width: 100%;
                        padding: 0.75rem 1rem;           /* ← Mudança: era 0.65rem 0.75rem */
                        border-radius: 6px;
                        text-decoration: none;
                        color: var(--text-primary);
                        transition: background-color 0.2s ease;
                        display: flex;                   /* ← Adição do CSS principal */
                        align-items: center;             /* ← Adição do CSS principal */
                        gap: 0.5rem;                     /* ← Adição do CSS principal */
                    }
                    
                    .nav-menu a:hover {
                        background: var(--bg-tertiary);
                    }
                    
                    .nav-menu a.active {
                        background: var(--accent);
                        color: white;
                    }
                    
                    .user-info-nav {
                        width: 100%;
                        padding: 0.5rem 1rem;           /* ← Mudança: era 0.65rem 0.75rem */
                        border-bottom: 1px solid var(--border);
                        margin-bottom: 0.5rem;
                    }
                    
                    .theme-toggle {
                        width: 100%;
                        padding: 0.75rem 1rem;           /* ← Mudança: era 0.65rem 0.75rem */
                        background: none;
                        border: none;
                        color: var(--text-primary);
                        cursor: pointer;
                        border-radius: 6px;
                        text-align: left;
                        transition: background-color 0.2s ease;
                        justify-content: flex-start;     /* ← Adição do CSS principal */
                        gap: 0.5rem;                     /* ← Adição do CSS principal */
                    }
                    
                    .theme-toggle:hover {
                        background: var(--bg-tertiary);
                    }
                }

                @keyframes slideDown {
                    from { opacity: 0; transform: translateY(-10px); }
                    to { opacity: 1; transform: translateY(0); }
                }




                /* Scrollbar customizada para tabelas em mobile */
                @media (max-width: 768px) {
                    .table-responsive::-webkit-scrollbar {
                        height: 6px;
                    }
                    
                    .table-responsive::-webkit-scrollbar-track {
                        background: var(--bg-tertiary);
                        border-radius: 3px;
                    }
                    
                    .table-responsive::-webkit-scrollbar-thumb {
                        background: var(--border);
                        border-radius: 3px;
                    }
                    
                    .table-responsive::-webkit-scrollbar-thumb:hover {
                        background: var(--text-secondary);
                    }
                }

                /* Garantia final - força tudo a respeitar viewport */
                @media (max-width: 576px) {
                    .nav-menu,
                    .footer-links,
                    .game-stats,
                    div, section, main, nav, header, footer {
                        max-width: 100vw;
                        overflow-wrap: break-word;
                        word-wrap: break-word;
                    }
                }
                </style>";



                /* ───── JS: preview imagens ────────────────────────────────────────── */
                echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    // ====== MENU HAMBÚRGUER ======
                    const menuToggle = document.getElementById('menuToggle');
                    const navMenu = document.getElementById('navMenu');
                    
                    if (menuToggle && navMenu) {
                        menuToggle.addEventListener('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            
                            navMenu.classList.toggle('active');
                            
                            const icon = menuToggle.querySelector('i');
                            if (icon) {
                                if (navMenu.classList.contains('active')) {
                                    icon.classList.remove('fa-bars');
                                    icon.classList.add('fa-times');
                                } else {
                                    icon.classList.remove('fa-times');
                                    icon.classList.add('fa-bars');
                                }
                            }
                        });
                        
                        document.addEventListener('click', function(e) {
                            if (!navMenu.contains(e.target) && !menuToggle.contains(e.target)) {
                                navMenu.classList.remove('active');
                                const icon = menuToggle.querySelector('i');
                                if (icon) {
                                    icon.classList.remove('fa-times');
                                    icon.classList.add('fa-bars');
                                }
                            }
                        });
                    }
                    
                    // ====== BUSCA AJAX USUÁRIOS ======
                    const inp = document.getElementById('userSearchInput');
                    const results = document.getElementById('userSearchResults');
                    const roleBtns = document.querySelectorAll('.role-filter button');
                    let roleSel = '';

                    if (inp && results && roleBtns.length > 0) {
                        console.log('✅ Elementos de busca encontrados');
                        
                        // Troca cargo
                        roleBtns.forEach(btn => btn.addEventListener('click', () => {
                            roleBtns.forEach(b => b.classList.remove('active'));
                            btn.classList.add('active');
                            roleSel = btn.dataset.role;
                            console.log('Filtro selecionado:', roleSel);
                            fetchUsers(inp.value.trim());
                        }));

                        // Pesquisa conforme digita
                        inp.addEventListener('input', e => {
                            const v = e.target.value.trim();
                            console.log('Digitando:', v);
                            if (v.length < 2 && v.length !== 0) {
                                results.style.display = 'none';
                                return;
                            }
                            fetchUsers(v);
                        });

                        // Carrega resultados
                        function fetchUsers(query = '') {
                            console.log('Fazendo fetch:', query, roleSel);
                            fetch(\`search_users.php?q=\${encodeURIComponent(query)}&role=\${encodeURIComponent(roleSel)}\`)
                                .then(r => {
                                    console.log('Status:', r.status);
                                    return r.json();
                                })
                                .then(data => {
                                    console.log('Dados recebidos:', data);
                                    renderList(data);
                                })
                                .catch(err => {
                                    console.error('Erro:', err);
                                    results.innerHTML = '<div class=\"nores\">Erro na busca</div>';
                                    results.style.display = 'block';
                                });
                        }

                        function renderList(users) {
                            if (!users.length) {
                                results.innerHTML = '<div class=\"nores\">Nenhum usuário encontrado</div>';
                            } else {
                                results.innerHTML = users.map(u => \`
                                    <a href=\"?action=users&search=\${encodeURIComponent(u.username)}\" class=\"res-item\">
                                        <strong>\${u.username}</strong> 
                                        <small>\${u.email}</small> 
                                        <span class=\"role role-\${u.role}\">\${u.role}</span>
                                    </a>\`).join('');
                            }
                            results.style.display = 'block';
                        }

                        // Fecha ao clicar fora
                        document.addEventListener('click', e => {
                            if (!e.target.closest('.search-box')) {
                                results.style.display = 'none';
                            }
                        });
                    } else {
                        console.log('❌ Elementos não encontrados:', {
                            inp: !!inp, 
                            results: !!results, 
                            buttons: roleBtns.length
                        });
                    }
                });

                function toggleTheme() {
                    document.documentElement.classList.toggle('light-mode');
                    const isLight = document.documentElement.classList.contains('light-mode');
                    localStorage.setItem('theme', isLight ? 'light' : 'dark');
                    const themeIcon = document.querySelector('.theme-toggle i');
                    if (themeIcon) {
                        themeIcon.className = isLight ? 'fas fa-sun' : 'fas fa-moon';
                    }
                }

                if (localStorage.getItem('theme') === 'light') {
                    document.documentElement.classList.add('light-mode');
                    const themeIcon = document.querySelector('.theme-toggle i');
                    if (themeIcon) themeIcon.className = 'fas fa-sun';
                }

                function preview(inpId, outId, multi = false) {
                    const inp = document.getElementById(inpId);
                    const out = document.getElementById(outId);
                    if (!inp || !out) return;
                    
                    inp.addEventListener('change', e => {
                        out.innerHTML = '';
                        [...e.target.files].slice(0, 5).forEach((f, i) => {
                            if (!f.type.startsWith('image/')) return;
                            const r = new FileReader();
                            r.onload = ev => {
                                out.insertAdjacentHTML('beforeend',
                                    \`<img src='\${ev.target.result}' style='width:100px;height:60px;object-fit:cover;border-radius:4px;border:2px solid var(--success);margin:2px'>\`
                                );
                            };
                            r.readAsDataURL(f);
                        });
                    });
                }

                preview('coverInput', 'coverPreview');
                preview('screenshotsInput', 'screenshotsPreview', true);
                preview('editCoverInput', 'editCoverPreview');
                preview('editScreenshotsInput', 'editScreenshotsPreview', true);
                </script>";










                renderFooter();
                ?>
