<?php
session_start();

date_default_timezone_set('America/Sao_Paulo'); // ou outro fuso que desejar


// Configurações do banco de dados
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'u111823599_RenxplayGames');     // banco de dados local de teste
define('DB_USER', 'csmods'); // usuário criado para conexão local
define('DB_PASS', 'zcbm');                   // senha vazia (se não tiver senha local)
define('SITE_NAME', 'Renxplay Teste');  
define('POSTS_PER_PAGE', 10);
define('PAGINATION_RANGE', 2);
define('MAX_UPLOAD_SIZE', 10485760);    // 10MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif']);
// Configurações de segurança
define('UPLOAD_PATH', __DIR__ . '/uploads/');
define('MAX_SCREENSHOTS', 30);
define('MIN_PASSWORD_LENGTH', 6);


// Headers de segurança
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Conexão PDO com pool de conexões
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
            PDO::ATTR_PERSISTENT => true, // Pool de conexões
            PDO::ATTR_TIMEOUT => 5
        ]
    );
} catch (PDOException $e) {
    error_log("Erro de conexão: " . $e->getMessage());
    http_response_code(500);
    die("Erro na conexão com o banco de dados.");
}

// Cria colunas novas se não existirem (migração leve)
function ensureGameColumns(PDO $pdo): void {
    try {
        $columns = $pdo->query("SHOW COLUMNS FROM games")->fetchAll(PDO::FETCH_COLUMN);
        $need = [
            'developer_name' => "ALTER TABLE games ADD COLUMN developer_name VARCHAR(255) NULL AFTER posted_by",
            'languages_multi' => "ALTER TABLE games ADD COLUMN languages_multi TEXT NULL AFTER developer_name",
            'updated_at_custom' => "ALTER TABLE games ADD COLUMN updated_at_custom DATE NULL AFTER languages_multi",
            'released_at_custom' => "ALTER TABLE games ADD COLUMN released_at_custom DATE NULL AFTER updated_at_custom",
            'patreon_url' => "ALTER TABLE games ADD COLUMN patreon_url VARCHAR(255) NULL AFTER released_at_custom",
            'discord_url' => "ALTER TABLE games ADD COLUMN discord_url VARCHAR(255) NULL AFTER patreon_url",
            'subscribestar_url' => "ALTER TABLE games ADD COLUMN subscribestar_url VARCHAR(255) NULL AFTER discord_url",
            'itch_url' => "ALTER TABLE games ADD COLUMN itch_url VARCHAR(255) NULL AFTER subscribestar_url",
            'kofi_url' => "ALTER TABLE games ADD COLUMN kofi_url VARCHAR(255) NULL AFTER itch_url",
            'bmc_url' => "ALTER TABLE games ADD COLUMN bmc_url VARCHAR(255) NULL AFTER kofi_url",
            'steam_url' => "ALTER TABLE games ADD COLUMN steam_url VARCHAR(255) NULL AFTER bmc_url",
            'screenshots' => "ALTER TABLE games ADD COLUMN screenshots TEXT NULL AFTER steam_url"
        ];
        foreach ($need as $col => $ddl) {
            if (!in_array($col, $columns, true)) {
                $pdo->exec($ddl);
            }
        }
    } catch (Throwable $e) {
        // silencioso em prod; logar apenas
        error_log('ensureGameColumns error: ' . $e->getMessage());
    }
}

ensureGameColumns($pdo);

// ====== FUNÇÕES DE AUTENTICAÇÃO MELHORADAS ======
function isLoggedIn() { 
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id']); 
}

function requireLogin() { 
    if (!isLoggedIn()) { 
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: auth.php'); 
        exit; 
    } 
}

function hasRole($roles) { 
    return isLoggedIn() && in_array($_SESSION['user']['role'], (array)$roles); 
}

function requireRole($roles) { 
    if (!hasRole($roles)) { 
        http_response_code(403);
        die('<div class="alert alert-error"><i class="fas fa-ban"></i> Acesso negado. Permissões insuficientes.</div>'); 
    } 
}

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ====== FUNÇÕES DE UPLOAD APRIMORADAS ======
function createUploadDirectory($dir) {
    if (!file_exists($dir)) {
        if (!mkdir($dir, 0755, true)) {
            error_log("Erro ao criar diretório: $dir");
            return false;
        }
        // Adicionar .htaccess para segurança
        file_put_contents($dir . '.htaccess', "Options -Indexes\nDeny from all");
    }
    return true;
}

function validateImage($tmpPath, $ext = null) {
    $ext = $ext ? strtolower($ext) : null;

    // Primeiro, tentar detectar via finfo
    $mime = null;
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        if ($fi) {
            $mime = finfo_file($fi, $tmpPath) ?: null;
            finfo_close($fi);
        }
    }

    // SVG: getimagesize não funciona; validar conteúdo básico e mime
    if ($ext === 'svg' || $mime === 'image/svg+xml') {
        $content = @file_get_contents($tmpPath);
        if ($content === false) return false;
        // Precisa ter uma tag <svg ...>
        if (stripos($content, '<svg') === false) return false;
        // Rejeitar SVGs com script/eventos embutidos por segurança básica
        if (preg_match('/<script|onload\s*=|onerror\s*=/i', $content)) return false;
        return true;
    }

    // AVIF: aceitar por mime/assinatura; getimagesize pode não suportar
    if ($ext === 'avif' || $mime === 'image/avif') {
        return true;
    }

    // Demais formatos raster
    $imageInfo = @getimagesize($tmpPath);
    if (!$imageInfo || empty($imageInfo['mime'])) return false;
    $allowedMimes = [
        'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'
    ];
    return in_array($imageInfo['mime'], $allowedMimes, true);
}

/**
 * Gera fallback (WEBP ou PNG) para um arquivo AVIF caso a extensão/biblioteca suporte.
 * Retorna caminho do fallback gerado ou null.
 */
function generateAvifFallback(string $avifPath, string $preferred = 'webp'): ?string {
    $dir = dirname($avifPath) . '/';
    $base = pathinfo($avifPath, PATHINFO_FILENAME);
    $fallbackWebp = $dir . $base . '.webp';
    $fallbackPng = $dir . $base . '.png';

    // Se já existe, retorna imediatamente
    if ($preferred === 'webp' && file_exists($fallbackWebp)) return $fallbackWebp;
    if (file_exists($fallbackPng)) return $fallbackPng;

    // Tentar com GD (PHP 8.1+ compilado com libavif)
    if (function_exists('imagecreatefromavif')) {
        $img = @imagecreatefromavif($avifPath);
        if ($img) {
            if ($preferred === 'webp' && function_exists('imagewebp')) {
                if (@imagewebp($img, $fallbackWebp, 85)) { imagedestroy($img); return $fallbackWebp; }
            }
            if (@imagepng($img, $fallbackPng, 6)) { imagedestroy($img); return $fallbackPng; }
            imagedestroy($img);
        }
    }

    // Tentar com Imagick, se disponível
    if (class_exists('Imagick')) {
        try {
            $im = new Imagick();
            $im->readImage($avifPath);
            if ($preferred === 'webp' && in_array('WEBP', $im->queryFormats('WEBP') ?: [], true)) {
                $im->setImageFormat('webp');
                $im->setImageCompressionQuality(85);
                if ($im->writeImage($fallbackWebp)) { $im->clear(); $im->destroy(); return $fallbackWebp; }
            }
            $im->setImageFormat('png');
            if ($im->writeImage($fallbackPng)) { $im->clear(); $im->destroy(); return $fallbackPng; }
            $im->clear();
            $im->destroy();
        } catch (Throwable $e) {
            // Ignorar erros silenciosamente; manter apenas o AVIF
        }
    }

    return null;
}

/**
 * Helper para renderizar uma imagem com suporte a AVIF e fallback automático.
 */
function renderImageTag(string $src, string $alt = '', array $attrs = []): string {
    $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
    $attrStr = '';
    foreach ($attrs as $k => $v) {
        if ($v === null || $v === '') continue;
        $attrStr .= ' ' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8') . "='" . htmlspecialchars($v, ENT_QUOTES, 'UTF-8') . "'";
    }
    $altEsc = htmlspecialchars($alt, ENT_QUOTES, 'UTF-8');

    if ($ext === 'avif') {
        $localPath = $src;
        // Garantir caminho relativo do servidor para file_exists
        if (strpos($localPath, '/') === 0) {
            $fsPath = $_SERVER['DOCUMENT_ROOT'] . $localPath;
        } else {
            $fsPath = $localPath;
        }
        $fallback = preg_replace('/\.avif$/i', '.webp', $src);
        $fallbackFs = preg_replace('/\.avif$/i', '.webp', $fsPath);
        if (!file_exists($fallbackFs)) {
            $fallback = preg_replace('/\.avif$/i', '.png', $src);
        }
        return "<picture><source type='image/avif' srcset='" . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . "'><img src='" . htmlspecialchars($fallback, ENT_QUOTES, 'UTF-8') . "' alt='{$altEsc}'{$attrStr}></picture>";
    }

    return "<img src='" . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . "' alt='{$altEsc}'{$attrStr}>";
}

function optimizeImage($source, $destination, $quality = 85) {
    $imageInfo = getimagesize($source);
    if (!$imageInfo) return false;
    
    $width = $imageInfo[0];
    $height = $imageInfo[1];
    $type = $imageInfo[2];
    
    // Redimensionar se muito grande
    $maxWidth = 1920;
    $maxHeight = 1080;
    
    if ($width > $maxWidth || $height > $maxHeight) {
        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $newWidth = intval($width * $ratio);
        $newHeight = intval($height * $ratio);
    } else {
        $newWidth = $width;
        $newHeight = $height;
    }
    
    // Criar imagem baseada no tipo
    switch ($type) {
        case IMAGETYPE_JPEG:
            $srcImage = imagecreatefromjpeg($source);
            break;
        case IMAGETYPE_PNG:
            $srcImage = imagecreatefrompng($source);
            break;
        case IMAGETYPE_GIF:
            $srcImage = imagecreatefromgif($source);
            break;
        case IMAGETYPE_WEBP:
            $srcImage = imagecreatefromwebp($source);
            break;
        default:
            return false;
    }
    
    if (!$srcImage) return false;
    
    // Criar nova imagem
    $newImage = imagecreatetruecolor($newWidth, $newHeight);
    
    // Preservar transparência para PNG e GIF
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
        imagealphablending($newImage, false);
        imagesavealpha($newImage, true);
        $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
        imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
    }
    
    imagecopyresampled($newImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    
    // Salvar imagem otimizada
    $result = false;
    switch ($type) {
        case IMAGETYPE_JPEG:
            $result = imagejpeg($newImage, $destination, $quality);
            break;
        case IMAGETYPE_PNG:
            $result = imagepng($newImage, $destination, intval($quality / 10));
            break;
        case IMAGETYPE_GIF:
            $result = imagegif($newImage, $destination);
            break;
        case IMAGETYPE_WEBP:
            $result = imagewebp($newImage, $destination, $quality);
            break;
    }
    
    imagedestroy($srcImage);
    imagedestroy($newImage);
    
    return $result;
}

function uploadFile($file, $dir = 'uploads/covers/', $optimize = true) {
    error_log("=== UPLOAD INDIVIDUAL INICIADO ===");
    
    if (!$file || !isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        error_log("ERRO: Arquivo inválido - error: " . ($file['error'] ?? 'indefinido'));
        return false;
    }
    
    if (!file_exists($file['tmp_name'])) {
        error_log("ERRO: Arquivo temporário não existe: " . $file['tmp_name']);
        return false;
    }
    
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        error_log("ERRO: Arquivo muito grande: " . formatBytes($file['size']));
        return false;
    }
    
    if (!createUploadDirectory($dir)) {
        error_log("ERRO: Não foi possível criar diretório: $dir");
        return false;
    }
    
    // Verificar extensão
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_IMAGE_TYPES)) {
        error_log("ERRO: Extensão não permitida: $ext");
        return false;
    }
    
    // Validar se é imagem real
    if (!validateImage($file['tmp_name'], $ext)) {
        error_log("ERRO: Arquivo não é uma imagem válida");
        return false;
    }
    
    // Gerar nome único e seguro
    $newName = 'img_' . date('Y-m-d_H-i-s') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $fullPath = $dir . $newName;
    
    error_log("Processando: " . $file['name'] . " -> " . $newName);
    
    // Otimizar e salvar
    if ($optimize && in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
        if (optimizeImage($file['tmp_name'], $fullPath)) {
            chmod($fullPath, 0644);
            error_log("✅ Upload otimizado realizado: $newName");
            return $newName;
        }
    }
    // AVIF: mover e tentar gerar fallback
    if ($ext === 'avif') {
        if (move_uploaded_file($file['tmp_name'], $fullPath)) {
            chmod($fullPath, 0644);
            generateAvifFallback($fullPath, 'webp');
            error_log("✅ Upload AVIF realizado com fallback (se possível): $newName");
            return $newName;
        }
        error_log("❌ Falha no upload AVIF");
        return false;
    }
    
    // Fallback: upload normal
    if (move_uploaded_file($file['tmp_name'], $fullPath)) {
        chmod($fullPath, 0644);
        error_log("✅ Upload realizado: $newName");
        return $newName;
    }
    
    error_log("❌ Falha no upload do arquivo");
    return false;
}

function uploadMultipleFiles($filesArray, $dir = 'uploads/screenshots/', $maxFiles = MAX_SCREENSHOTS) {
    $uploaded = [];
    error_log("=== UPLOAD MÚLTIPLO INICIADO ===");
    
    if (!isset($filesArray['name']) || !is_array($filesArray['name'])) {
        error_log("ERRO: Array de arquivos inválido");
        return $uploaded;
    }
    
    $fileCount = count($filesArray['name']);
    $processCount = min($fileCount, $maxFiles);
    
    error_log("Processando $processCount arquivos de $fileCount enviados");
    
    for ($i = 0; $i < $processCount; $i++) {
        if (empty($filesArray['name'][$i]) || $filesArray['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        
        if ($filesArray['error'][$i] !== UPLOAD_ERR_OK) {
            error_log("Arquivo $i com erro: " . $filesArray['error'][$i]);
            continue;
        }
        
        $file = [
            'name' => $filesArray['name'][$i],
            'type' => $filesArray['type'][$i] ?? '',
            'tmp_name' => $filesArray['tmp_name'][$i],
            'error' => $filesArray['error'][$i],
            'size' => $filesArray['size'][$i] ?? 0
        ];
        
        $filename = uploadFile($file, $dir, true);
        if ($filename) {
            $uploaded[] = $filename;
            error_log("✅ Screenshot $i uploaded: $filename");
        }
    }
    
    error_log("Total de arquivos processados: " . count($uploaded));
    return $uploaded;
}

// ====== FUNÇÕES UTILITÁRIAS ======
function formatBytes($size, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $base = log($size, 1024);
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $units[floor($base)];
}

function generateSlug($text) {
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}

function timeAgo($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'ano',
        'm' => 'mês',
        'w' => 'semana',
        'd' => 'dia',
        'h' => 'hora',
        'i' => 'minuto',
        's' => 'segundo',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' atrás' : 'agora mesmo';
}

function sanitize($text) {
    return htmlspecialchars(trim($text), ENT_QUOTES, 'UTF-8');
}

function validateURL($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

// ====== HEADER MELHORADO ======
function renderHeader($title = '', $description = '', $keywords = '') {
    $pageTitle = $title ? $title . ' - ' . SITE_NAME : SITE_NAME . ' - Jogos Adultos';
    $pageDescription = $description ?: 'Renxplay - A melhor plataforma de jogos adultos';
    $currentPage = basename($_SERVER['PHP_SELF']);

    echo "<!DOCTYPE html>
<html lang='pt-BR'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <meta name='description' content='{$pageDescription}'>
    <title>{$pageTitle}</title>
    <link rel='stylesheet' href='style.css?v=" . time() . "'>
    <link href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css' rel='stylesheet'>
    <link rel='icon' type='image/svg+xml' href='data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 100 100\"><text y=\".9em\" font-size=\"90\">🎮</text></svg>'>
</head>
<body>
    <header class='header'>
        <div class='header-content'>
            <a href='index.php' class='logo'>" . SITE_NAME . "</a>
            <nav class='nav'>
                <a href='index.php' class='nav-link " . ($currentPage === 'index.php' ? 'active' : '') . "'>
                    <i class='fas fa-home'></i>
                    Jogos
                </a>";

    if (isLoggedIn() && hasRole(['ADMIN', 'SUPER_ADMIN', 'DEV'])) {
        echo "<a href='dashboard.php' class='nav-link " . ($currentPage === 'dashboard.php' ? 'active' : '') . "'>
                <i class='fas fa-cog'></i>
                Admin
            </a>";
    }

    if (isLoggedIn()) {
        echo "<div class='user-dropdown'>
                <button class='nav-link' onclick='toggleDropdown()'>
                    <i class='fas fa-user'></i>
                    <i class='fas fa-chevron-down'></i>
                </button>
                <div class='dropdown-content' id='userDropdown'>
                    <a href='profile.php' class='dropdown-item'>
                        <i class='fas fa-user-cog'></i>
                        Perfil
                    </a>
                    <a href='javascript:void(0)' class='dropdown-item theme-toggle' onclick='toggleTheme()'>
                        <i class='fas fa-moon'></i>
                        Tema
                    </a>
                    <a href='auth.php?action=logout' class='dropdown-item'>
                        <i class='fas fa-sign-out-alt'></i>
                        Sair
                    </a>
                </div>
            </div>";
    } else {
        echo "<a href='auth.php' class='" . ($currentPage === 'auth.php' ? 'nav-link active' : 'nav-link') . "'>
                <i class='fas fa-sign-in-alt'></i>
                Entrar
            </a>";
    }

    echo "</nav>
        </div>
    </header>
    <main class='main-content'>";
}


// ====== FOOTER MELHORADO ======
function renderFooter() {
    $currentYear = date('Y');
    echo "</main>
<footer class='footer' role='contentinfo'>
    <div class='container'>
        <div class='footer-content'>
            <div class='footer-section'>
                <h4><i class='fas fa-gamepad'></i> " . SITE_NAME . "</h4>
                <p>A melhor plataforma para jogos adultos Ren'Py.</p>
            </div>

            <div class='footer-section'>
                <h4><i class='fas fa-info-circle'></i> Institucional</h4>
                <ul class='footer-links'>
                    <li><a href='sobre.php'>Sobre</a></li>
                    <li><a href='desenvolvedores.php'>Desenvolvedores</a></li>
                </ul>
            </div>

            <div class='footer-section'>
                <h4><i class='fas fa-shield-alt'></i> Legal</h4>
                <ul class='footer-links'>
                    <li><a href='termos.php'>Termos de Serviço</a></li>
                    <li><a href='privacidade.php'>Política de Privacidade</a></li>
                    <li><a href='cookies.php'>Política de Cookies</a></li>
                    <li><a href='remocao.php'>Política de Remoção</a></li>
                    <li><a href='protecao-infantil.php'>Proteção Infantil</a></li>
                    <li><a href='dmca.php'>DMCA</a></li>
                </ul>
            </div>
        </div>

        <div class='footer-inline'>
            <span class='badge-18'>18+</span>
            <a href='sobre.php'>Sobre</a>
            <span class='sep'>|</span>
            <a href='termos.php'>Termos de Serviço</a>
            <span class='sep'>|</span>
            <a href='privacidade.php'>Política de Privacidade</a>
            <span class='sep'>|</span>
            <a href='desenvolvedores.php'>Desenvolvedores</a>
            <span class='sep'>|</span>
            <a href='cookies.php'>Política de Cookies</a>
            <span class='sep'>|</span>
            <a href='remocao.php'>Política de Remoção</a>
            <span class='sep'>|</span>
            <a href='protecao-infantil.php'>Proteção Infantil</a>
            <span class='sep'>|</span>
            <a href='dmca.php'>DMCA</a>
        </div>

        <div class='footer-bottom'>
            <p>&copy; 1980-{$currentYear} Renxplay.com - Líder mundial em jogos adultos. Todos os direitos reservados.</p>
            <p class='footer-disclaimer'>
                <i class='fas fa-exclamation-triangle'></i>
                Conteúdo adulto. Proibido para menores de 18 anos.
            </p>
        </div>
    </div>
</footer>

<!-- Modal para screenshots -->
<div id='imageModal' class='modal' onclick='closeModal()' role='dialog' aria-labelledby='modalTitle' aria-hidden='true' style='display:none;'>
    <button class='close' onclick='closeModal()' aria-label='Fechar modal'>&times;</button>
    <img class='modal-content' id='modalImage' alt=''>
    <div id='caption' class='modal-caption'></div>
    <div class='modal-controls'>
        <button onclick='previousImage()' aria-label='Imagem anterior'><i class='fas fa-chevron-left'></i></button>
        <button onclick='nextImage()' aria-label='Próxima imagem'><i class='fas fa-chevron-right'></i></button>
    </div>
</div>

<!-- Loading overlay -->
<div id='loadingOverlay' class='loading-overlay' style='display: none;'>
    <div class='loading-spinner'>
        <i class='fas fa-spinner fa-spin'></i>
        <p>Carregando...</p>
    </div>
</div>

<script src='script.js?v=" . time() . "'></script>
</body>
</html>";
}

// ====== FUNÇÕES DE EXIBIÇÃO APRIMORADAS ======
function displayScreenshots($screenshots, $gameTitle = '', $gameId = '', $showTitle = false) {
    if (empty($screenshots)) return '';
    
    $screenshots = is_string($screenshots) ? json_decode($screenshots, true) : $screenshots;
    if (empty($screenshots) || !is_array($screenshots)) return '';
    
    $html = "<div class='screenshots'>";
    if ($showTitle) {
        $html .= "<h3><i class='fas fa-images'></i> Screenshots <span class='count'>(" . count($screenshots) . ")</span></h3>";
    }
    $html .= "<div class='screenshot-gallery' data-game-id='{$gameId}'>";
    
    foreach ($screenshots as $index => $screenshot) {
        $imagePath = "uploads/screenshots/" . $screenshot;
        $thumbnailPath = "uploads/screenshots/thumb_" . $screenshot;
        $alt = $gameTitle ? "$gameTitle - Screenshot " . ($index + 1) : "Screenshot " . ($index + 1);

        // Usar thumbnail se existir
        $displayPath = file_exists($thumbnailPath) ? $thumbnailPath : $imagePath;

        $ext = strtolower(pathinfo($displayPath, PATHINFO_EXTENSION));
        $fallback = '';
        if ($ext === 'avif') {
            $fallbackWebp = preg_replace('/\.avif$/i', '.webp', $displayPath);
            $fallbackPng = preg_replace('/\.avif$/i', '.png', $displayPath);
            $fallback = file_exists($fallbackWebp) ? $fallbackWebp : (file_exists($fallbackPng) ? $fallbackPng : $displayPath);
        }

        $innerImg = ($ext === 'avif')
            ? "<picture><source type='image/avif' srcset='" . htmlspecialchars($displayPath, ENT_QUOTES, 'UTF-8') . "'>" .
              "<img src='" . htmlspecialchars($fallback ?: $displayPath, ENT_QUOTES, 'UTF-8') . "' alt='" . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . "' class='screenshot' loading='lazy' onload='this.classList.add(\"loaded\")' onerror='handleImageError(this)'></picture>"
            : "<img src='" . htmlspecialchars($displayPath, ENT_QUOTES, 'UTF-8') . "' alt='" . htmlspecialchars($alt, ENT_QUOTES, 'UTF-8') . "' class='screenshot' loading='lazy' onload='this.classList.add(\"loaded\")' onerror='handleImageError(this)'>";

        $html .= "<div class='screenshot-item' data-index='{$index}' onclick='openModal(this.querySelector(\"img.screenshot\"), {$index})'>" .
                 // data-full mantém o original; adicionamos também data-fallback para o modal
                 str_replace('<img ', "<img data-full='" . htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8') . "' data-fallback='" . htmlspecialchars($fallback ?: '', ENT_QUOTES, 'UTF-8') . "' ", $innerImg) .
                 "<div class='screenshot-overlay'><i class='fas fa-search-plus'></i></div>" .
                 "</div>";
    }
    
    $html .= "</div></div>";
    return $html;
}

function displayDownloadLinks($downloadLinks, $requiresLogin = true) {
    if (empty($downloadLinks)) return '';
    
    $links = is_string($downloadLinks) ? json_decode($downloadLinks, true) : $downloadLinks;
    if (empty($links) || !is_array($links)) return '';
    
    $html = "<div class='download-section'>";
    $html .= "<h3><i class='fas fa-download'></i> Downloads Disponíveis</h3>";
    
    if ($requiresLogin && !isLoggedIn()) {
        $html .= "<div class='login-required'>
                    <i class='fas fa-lock'></i>
                    <h4>Login Necessário</h4>
                    <p>Você precisa estar logado para acessar os downloads.</p>
                    <a href='auth.php' class='btn btn-primary'>
                        <i class='fas fa-sign-in-alt'></i> Fazer Login
                    </a>
                  </div>";
    } else {
        $html .= "<div class='download-buttons'>";
        
        foreach ($links as $index => $link) {
            $link = trim($link);
            if (empty($link) || !validateURL($link)) continue;
            
            // Detectar tipo de link e definir ícone/cor
            $icon = 'fas fa-download';
            $service = 'Download';
            $btnClass = 'btn-download';
            
            if (strpos($link, 'mega.nz') !== false || strpos($link, 'mega.co.nz') !== false) {
                $icon = 'fas fa-cloud';
                $service = 'MEGA';
                $btnClass = 'btn-mega';
            } elseif (strpos($link, 'drive.google.com') !== false) {
                $icon = 'fab fa-google-drive';
                $service = 'Google Drive';
                $btnClass = 'btn-gdrive';
            } elseif (strpos($link, 'mediafire.com') !== false) {
                $icon = 'fas fa-fire';
                $service = 'MediaFire';
                $btnClass = 'btn-mediafire';
            } elseif (strpos($link, 'dropbox.com') !== false) {
                $icon = 'fab fa-dropbox';
                $service = 'Dropbox';
                $btnClass = 'btn-dropbox';
            } elseif (strpos($link, 'gofile.io') !== false) {
                $icon = 'fas fa-file-archive';
                $service = 'GoFile';
                $btnClass = 'btn-gofile';
            }
            
            $html .= "<a href='{$link}' 
                         target='_blank' 
                         rel='noopener noreferrer' 
                         class='btn {$btnClass}'
                         onclick='trackDownload(this, \"{$service}\")'
                         data-service='{$service}'>
                        <i class='{$icon}'></i> 
                        <span>{$service}</span>
                        <small>Opção " . ($index + 1) . "</small>
                      </a>";
        }
        
        $html .= "</div>";
        
        // Aviso sobre downloads
        $html .= "<div class='download-info'>
                    <div class='alert alert-info'>
                        <i class='fas fa-info-circle'></i>
                        <div>
                            <strong>Informações importantes:</strong>
                            <ul>
                                <li>Todos os downloads são gratuitos</li>
                                <li>Arquivos verificados contra vírus</li>
                               
                            </ul>
                        </div>
                    </div>
                  </div>";
    }
    
    $html .= "</div>";
    return $html;
}

// ====== FUNÇÕES ESTATÍSTICAS ======
function getTotalGames() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM games WHERE status = 'published'");
        return $stmt->fetchColumn() ?: 0;
    } catch (PDOException $e) {
        error_log("Erro ao buscar total de jogos: " . $e->getMessage());
        return 0;
    }
}

function getTotalUsers() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'");
        return $stmt->fetchColumn() ?: 0;
    } catch (PDOException $e) {
        error_log("Erro ao buscar total de usuários: " . $e->getMessage());
        return 0;
    }
}

// ====== FUNÇÕES DE PAGINAÇÃO ======
function renderPagination($currentPage, $totalPages, $baseUrl = '', $params = [], $pageParam = 'p', $range = PAGINATION_RANGE) {
    if ($totalPages <= 1) return '';

    $visible = max(1, $range * 2 + 1);

    if ($totalPages <= $visible) {
        $start = 1;
        $end = $totalPages;
    } else {
        $start = max(1, $currentPage - $range);
        $start = min($start, $totalPages - $visible + 1);
        $end = min($totalPages, $start + $visible - 1);
    }

    $buildUrl = function (int $page) use ($baseUrl, $params, $pageParam): string {
        $query = http_build_query(array_merge($params, [$pageParam => $page]));
        return ($baseUrl ? $baseUrl : '') . '?' . $query;
    };

    $html = "<div class='pagination' role='navigation' aria-label='Páginas'>";

    if ($currentPage > 1) {
        $html .= "<a href='" . $buildUrl($currentPage - 1) . "' class='btn btn-secondary' aria-label='Página anterior'>&larr;</a>";
    }

    for ($i = $start; $i <= $end; $i++) {
        if ($i === (int)$currentPage) {
            $html .= "<span class='current' aria-current='page'>{$i}</span>";
        } else {
            $html .= "<a href='" . $buildUrl($i) . "' class='btn btn-secondary'>{$i}</a>";
        }
    }

    if ($currentPage < $totalPages) {
        $html .= "<a href='" . $buildUrl($currentPage + 1) . "' class='btn btn-secondary' aria-label='Próxima página'>&rarr;</a>";
    }

    $html .= "</div>";
    return $html;
}

// ====== OUTRAS MELHORIAS ======
function handleImageError($defaultImage = 'assets/no-image.png') {
    return "this.src='{$defaultImage}'; this.onerror=null;";
}

function generateBreadcrumb($items) {
    $html = "<nav class='breadcrumb' aria-label='Navegação estrutural'>";
    $html .= "<ol class='breadcrumb-list'>";
    
    foreach ($items as $index => $item) {
        $isLast = $index === count($items) - 1;
        
        if ($isLast) {
            $html .= "<li class='breadcrumb-item active' aria-current='page'>{$item['title']}</li>";
        } else {
            $html .= "<li class='breadcrumb-item'>
                        <a href='{$item['url']}'>{$item['title']}</a>
                      </li>";
        }
    }
    
    $html .= "</ol></nav>";
    return $html;
}

// ====== CSRF TOKEN HELPER ======
function csrfField() {
    $token = generateCSRFToken();
    return "<input type='hidden' name='csrf_token' value='{$token}'>";
}
?>
