<?php
session_start();

date_default_timezone_set('America/Sao_Paulo'); // ou outro fuso que desejar


// Configurações do banco de dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'u111823599_RenxplayGames');
define('DB_USER', 'u111823599_hmods_Renxplay');
define('DB_PASS', 'e]WGBa7CzW4');
define('SITE_NAME', 'Renxplay');
define('POSTS_PER_PAGE', 12);
define('MAX_UPLOAD_SIZE', 10485760); // 10MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// Configurações de segurança
define('UPLOAD_PATH', __DIR__ . '/uploads/');
define('MAX_SCREENSHOTS', 10);
define('MIN_PASSWORD_LENGTH', 6);

// Ativar logs de erro
//ini_set('log_errors', 1);
//ini_set('error_log', __DIR__ . '/upload_errors.log');

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

function validateImage($tmpPath) {
    $imageInfo = @getimagesize($tmpPath);
    if (!$imageInfo) return false;
    
    // Verificar se é uma imagem real
    $allowedMimes = [
        'image/jpeg', 'image/jpg', 'image/png', 
        'image/gif', 'image/webp'
    ];
    
    return in_array($imageInfo['mime'], $allowedMimes);
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
    if (!validateImage($file['tmp_name'])) {
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
    <link rel='stylesheet' href='theme.css?v=" . time() . "'>
    <link rel='preconnect' href='https://fonts.googleapis.com'>
    <link rel='preconnect' href='https://fonts.gstatic.com' crossorigin>
    <link href='https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap' rel='stylesheet'>
    <link rel='icon' type='image/svg+xml' href='data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 100 100\"><text y=\".9em\" font-size=\"90\">🎮</text></svg>'>
</head>
<body>
<nav class='nav'>
    <div class='container'>
        <div class='nav-container'>
            <a href='index.php' class='nav-brand'>
                <svg class='w-6 h-6' style='width: 1.5rem; height: 1.5rem; margin-right: 0.5rem;' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                    <circle cx='12' cy='12' r='10'></circle>
                    <polygon points='10,8 16,12 10,16 10,8'></polygon>
                </svg>
                " . SITE_NAME . "
            </a>
            
            <div class='nav-menu'>";
    
    if (isLoggedIn()) {
        $user = $_SESSION['user'];
        
        echo "<div class='flex items-center gap-2 text-sm text-muted-foreground'>
                <svg style='width: 1.25rem; height: 1.25rem;' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                    <path d='M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2'></path>
                    <circle cx='12' cy='7' r='4'></circle>
                </svg>
                <span>Olá, {$user['username']}</span>
              </div>";
        
        echo "<a href='index.php' class='btn btn-ghost'>
                <svg style='width: 1rem; height: 1rem; margin-right: 0.5rem;' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                    <path d='m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z'></path>
                    <polyline points='9,22 9,12 15,12 15,22'></polyline>
                </svg>
                Jogos
              </a>";
        
        if ($user['role'] !== 'USER') {
            echo "<a href='dashboard.php' class='btn btn-ghost'>
                    <svg style='width: 1rem; height: 1rem; margin-right: 0.5rem;' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                        <path d='M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z'></path>
                        <circle cx='12' cy='12' r='3'></circle>
                    </svg>
                    Admin
                  </a>";
        }
        
        echo "<button onclick='toggleTheme()' class='btn btn-ghost' title='Alternar tema'>
                <svg style='width: 1rem; height: 1rem;' id='theme-icon' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                    <path d='M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z'></path>
                </svg>
              </button>";
        
        echo "<a href='auth.php?action=logout' onclick='return confirm(\"Deseja realmente sair?\")' class='btn btn-outline'>
                <svg style='width: 1rem; height: 1rem; margin-right: 0.5rem;' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                    <path d='M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4'></path>
                    <polyline points='16,17 21,12 16,7'></polyline>
                    <line x1='21' y1='12' x2='9' y2='12'></line>
                </svg>
                Sair
              </a>";
    } else {
        echo "<a href='index.php' class='btn btn-ghost'>
                <svg style='width: 1rem; height: 1rem; margin-right: 0.5rem;' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                    <path d='m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z'></path>
                    <polyline points='9,22 9,12 15,12 15,22'></polyline>
                </svg>
                Jogos
              </a>";
              
        echo "<button onclick='toggleTheme()' class='btn btn-ghost' title='Alternar tema'>
                <svg style='width: 1rem; height: 1rem;' id='theme-icon' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                    <path d='M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z'></path>
                </svg>
              </button>";
              
        echo "<a href='auth.php' class='btn btn-outline'>
                <svg style='width: 1rem; height: 1rem; margin-right: 0.5rem;' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                    <path d='M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4'></path>
                    <polyline points='10,17 15,12 10,7'></polyline>
                    <line x1='15' y1='12' x2='3' y2='12'></line>
                </svg>
                Entrar
              </a>";
    }
    
    echo "        </div>
        </div>
    </div>
</nav>

<script>
function toggleTheme() {
    const html = document.documentElement;
    const icon = document.getElementById('theme-icon');
    
    if (html.classList.contains('dark')) {
        html.classList.remove('dark');
        localStorage.setItem('theme', 'light');
        if (icon) {
            icon.innerHTML = '<path d=\"M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z\"></path>';
        }
    } else {
        html.classList.add('dark');
        localStorage.setItem('theme', 'dark');
        if (icon) {
            icon.innerHTML = '<circle cx=\"12\" cy=\"12\" r=\"5\"></circle><path d=\"M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42\"></path>';
        }
    }
}

// Aplicar tema salvo
if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
    document.documentElement.classList.add('dark');
    const icon = document.getElementById('theme-icon');
    if (icon) {
        icon.innerHTML = '<circle cx=\"12\" cy=\"12\" r=\"5\"></circle><path d=\"M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42\"></path>';
    }
}
</script>";
}


// ====== FOOTER MELHORADO ======
function renderFooter() {
    $currentYear = date('Y');
    echo "
<footer class='py-8 mt-8 border-t border-border bg-muted/30'>
    <div class='container'>
        <div class='grid grid-cols-1 md:grid-cols-3 gap-8'>
            <div>
                <div class='flex items-center gap-2 mb-4'>
                    <svg style='width: 1.5rem; height: 1.5rem;' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                        <circle cx='12' cy='12' r='10'></circle>
                        <polygon points='10,8 16,12 10,16 10,8'></polygon>
                    </svg>
                    <h4 class='font-bold'>" . SITE_NAME . "</h4>
                </div>
                <p class='text-muted-foreground text-sm'>A melhor plataforma para jogos Ren'Py.</p>
            </div>
            
            <div>
                <h4 class='font-semibold mb-4'>
                    <svg style='width: 1rem; height: 1rem; display: inline; margin-right: 0.5rem;' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                        <circle cx='12' cy='12' r='3'></circle>
                        <circle cx='12' cy='1' r='1'></circle>
                        <circle cx='12' cy='23' r='1'></circle>
                        <circle cx='20' cy='12' r='1'></circle>
                        <circle cx='4' cy='12' r='1'></circle>
                        <circle cx='18.36' cy='5.64' r='1'></circle>
                        <circle cx='18.36' cy='18.36' r='1'></circle>
                        <circle cx='5.64' cy='5.64' r='1'></circle>
                        <circle cx='5.64' cy='18.36' r='1'></circle>
                    </svg>
                    Sobre
                </h4>
                <ul class='space-y-2 text-sm text-muted-foreground'>
                    <li><a href='#' class='hover:text-foreground transition-colors'>Contato</a></li>
                </ul>
            </div>
            
            <div>
                <h4 class='font-semibold mb-4'>
                    <svg style='width: 1rem; height: 1rem; display: inline; margin-right: 0.5rem;' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                        <rect x='3' y='11' width='18' height='11' rx='2' ry='2'></rect>
                        <circle cx='12' cy='16' r='1'></circle>
                        <path d='m7 11V7a5 5 0 0 1 10 0v4'></path>
                    </svg>
                    Legal
                </h4>
                <ul class='space-y-2 text-sm text-muted-foreground'>
                    <li><a href='#' class='hover:text-foreground transition-colors'>Termos de Uso</a></li>
                    <li><a href='#' class='hover:text-foreground transition-colors'>Política de Privacidade</a></li>
                    <li><a href='#' class='hover:text-foreground transition-colors'>Sobre</a></li>
                </ul>
            </div>
        </div>
        
        <div class='mt-8 pt-8 border-t border-border text-center space-y-2'>
            <p class='text-sm text-muted-foreground'>
                &copy; {$currentYear} " . SITE_NAME . ". Desenvolvido com 
                <svg style='width: 1rem; height: 1rem; display: inline; color: #e74c3c;' viewBox='0 0 24 24' fill='currentColor'>
                    <path d='M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z'></path>
                </svg>
                para a comunidade.
            </p>
            <p class='text-xs text-muted-foreground'>
                <svg style='width: 1rem; height: 1rem; display: inline; margin-right: 0.25rem;' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2'>
                    <path d='M12 9v4l3 3'></path>
                    <circle cx='12' cy='12' r='10'></circle>
                </svg>
                Conteúdo adulto. Proibido para menores de 18 anos.
            </p>
        </div>
    </div>
</footer>

</body>
</html>";
}

// ====== FUNÇÕES DE EXIBIÇÃO APRIMORADAS ======
function displayScreenshots($screenshots, $gameTitle = '', $gameId = '') {
    if (empty($screenshots)) return '';
    
    $screenshots = is_string($screenshots) ? json_decode($screenshots, true) : $screenshots;
    if (empty($screenshots) || !is_array($screenshots)) return '';
    
    $html = "<div class='screenshots'>";
    $html .= "<h3><i class='fas fa-images'></i> Screenshots <span class='count'>(" . count($screenshots) . ")</span></h3>";
    $html .= "<div class='screenshot-gallery' data-game-id='{$gameId}'>";
    
    foreach ($screenshots as $index => $screenshot) {
        $imagePath = "uploads/screenshots/" . $screenshot;
        $thumbnailPath = "uploads/screenshots/thumb_" . $screenshot;
        $alt = $gameTitle ? "$gameTitle - Screenshot " . ($index + 1) : "Screenshot " . ($index + 1);
        
        // Usar thumbnail se existir
        $displayPath = file_exists($thumbnailPath) ? $thumbnailPath : $imagePath;
        
        $html .= "<div class='screenshot-item' data-index='{$index}'>
                    <img src='{$displayPath}' 
                         data-full='{$imagePath}'
                         alt='{$alt}' 
                         class='screenshot' 
                         loading='lazy'
                         onclick='openModal(this, {$index})'
                         onload='this.classList.add(\"loaded\")'
                         onerror='handleImageError(this)'>
                    <div class='screenshot-overlay'>
                        <i class='fas fa-search-plus'></i>
                    </div>
                  </div>";
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
function renderPagination($currentPage, $totalPages, $baseUrl = '', $params = []) {
    if ($totalPages <= 1) return '';
    
    $html = "<div class='pagination' role='navigation' aria-label='Páginas'>";
    
    // Página anterior
    if ($currentPage > 1) {
        $prevUrl = $baseUrl . '?' . http_build_query(array_merge($params, ['page' => $currentPage - 1]));
        $html .= "<a href='{$prevUrl}' class='btn btn-secondary' aria-label='Página anterior'>
                    <i class='fas fa-chevron-left'></i> Anterior
                  </a>";
    }
    
    // Páginas numeradas
    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);
    
    if ($start > 1) {
        $firstUrl = $baseUrl . '?' . http_build_query(array_merge($params, ['page' => 1]));
        $html .= "<a href='{$firstUrl}' class='btn btn-secondary'>1</a>";
        if ($start > 2) {
            $html .= "<span class='pagination-dots'>...</span>";
        }
    }
    
    for ($i = $start; $i <= $end; $i++) {
        $pageUrl = $baseUrl . '?' . http_build_query(array_merge($params, ['page' => $i]));
        $activeClass = $i == $currentPage ? ' active' : '';
        $html .= "<a href='{$pageUrl}' class='btn btn-secondary{$activeClass}' " . 
                 ($i == $currentPage ? 'aria-current="page"' : '') . ">{$i}</a>";
    }
    
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            $html .= "<span class='pagination-dots'>...</span>";
        }
        $lastUrl = $baseUrl . '?' . http_build_query(array_merge($params, ['page' => $totalPages]));
        $html .= "<a href='{$lastUrl}' class='btn btn-secondary'>{$totalPages}</a>";
    }
    
    // Próxima página
    if ($currentPage < $totalPages) {
        $nextUrl = $baseUrl . '?' . http_build_query(array_merge($params, ['page' => $currentPage + 1]));
        $html .= "<a href='{$nextUrl}' class='btn btn-secondary' aria-label='Próxima página'>
                    Próxima <i class='fas fa-chevron-right'></i>
                  </a>";
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
