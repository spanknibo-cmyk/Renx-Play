<?php
/**
 * security.php — Camada central de segurança para toda a aplicação.
 * Inclua este arquivo antes de qualquer saída para endurecer a aplicação.
 */

// ===== 1) Configurações de erros/log =====
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
@ini_set('error_log', __DIR__ . '/php-error.log');
error_reporting(E_ALL);

// ===== 2) Ocultar pegadas =====
@ini_set('expose_php', '0');
if (function_exists('header_remove')) {
	header_remove('X-Powered-By');
}

// ===== 3) Cabeçalhos de segurança =====
// Observação: CSP aqui é ampla o suficiente para não quebrar o site, mas ainda restritiva.
// Ajuste as origens conforme você internaliza recursos externos.
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? '';

$csp = [
	"default-src 'self'",
	"base-uri 'self'",
	"form-action 'self'",
	"frame-ancestors 'none'",
	"object-src 'none'",
	// scripts: permitir inline por compatibilidade atual; ideal migrar para nonces
	"script-src 'self' 'unsafe-inline'",
	// styles: idem
	"style-src 'self' 'unsafe-inline'",
	// imagens do próprio site e data: (para favicons/inline)
	"img-src 'self' data: blob: https:",
	// fontes locais e CDN do Font Awesome (ajuste se remover CDN)
	"font-src 'self' data: https://cdnjs.cloudflare.com",
	// conexões XHR/Fetch
	"connect-src 'self'",
	// mídia local
	"media-src 'self'",
];

if (!headers_sent()) {
	header('X-Content-Type-Options: nosniff');
	header('X-Frame-Options: DENY');
	header('Referrer-Policy: no-referrer');
	header('Permissions-Policy: accelerometer=(), ambient-light-sensor=(), autoplay=(), battery=(), camera=(), display-capture=(), document-domain=(), encrypted-media=(), fullscreen=(self), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), midi=(), payment=(), picture-in-picture=(self), publickey-credentials-get=(), sync-xhr=(), usb=(), wake-lock=(), xr-spatial-tracking=()');
	header('X-Permitted-Cross-Domain-Policies: none');
	header('X-Download-Options: noopen');
	header('Cross-Origin-Resource-Policy: same-origin');
	header('Cross-Origin-Opener-Policy: same-origin');
}

// Evitar duplicidade de CSP se o servidor já injeta via .htaccess
if (!headers_sent()) {
	header('Content-Security-Policy: ' . implode('; ', $csp));
}

// HSTS apenas em HTTPS (e após validar que o site usa TLS)
if ($scheme === 'https' && !headers_sent()) {
	header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}

// ===== 4) Sessões endurecidas =====
@ini_set('session.use_strict_mode', '1');
@ini_set('session.use_only_cookies', '1');
@ini_set('session.use_trans_sid', '0');
@ini_set('session.cookie_httponly', '1');
@ini_set('session.cookie_secure', $scheme === 'https' ? '1' : '0');
@ini_set('session.cookie_samesite', 'Lax');

if (session_status() !== PHP_SESSION_ACTIVE) {
	$cookieParams = session_get_cookie_params();
	// Garantir domínio coerente; fallback para atual
	$cookieDomain = $cookieParams['domain'] ?: ($host ?: null);
	$cookiePath = $cookieParams['path'] ?: '/';
	$secure = ($scheme === 'https');
	session_set_cookie_params([
		'lifetime' => 0,
		'path' => $cookiePath,
		'domain' => $cookieDomain,
		'secure' => $secure,
		'httponly' => true,
		'samesite' => 'Lax'
	]);
	session_start();
}

// Regeneração periódica do ID de sessão para mitigar fixação
if (empty($_SESSION['__sid_regen_time'])) {
	$_SESSION['__sid_regen_time'] = time();
} elseif (time() - (int)$_SESSION['__sid_regen_time'] > 600) { // 10 min
	@session_regenerate_id(true);
	$_SESSION['__sid_regen_time'] = time();
}

// ===== 5) CSRF helpers (compatível com funções existentes) =====
if (!function_exists('generateCSRFToken')) {
	function generateCSRFToken(): string {
		if (!isset($_SESSION['csrf_token'])) {
			$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
		}
		return $_SESSION['csrf_token'];
	}
}

if (!function_exists('validateCSRFToken')) {
	function validateCSRFToken(?string $token): bool {
		return isset($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
	}
}

// Enforce opcional: se o formulário enviar _csrf ou csrf_token, validar automaticamente
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
	$token = null;
	if (isset($_POST['_csrf'])) {
		$token = $_POST['_csrf'];
	} elseif (isset($_POST['csrf_token'])) {
		$token = $_POST['csrf_token'];
	}
	if ($token !== null && !validateCSRFToken($token)) {
		if (!headers_sent()) { http_response_code(403); }
		die('Falha de validação CSRF. Recarregue a página e tente novamente.');
	}
}

// ===== 6) Anti-bruteforce / rate limiting helpers =====
if (!function_exists('getClientIp')) {
	function getClientIp(): string {
		$keys = ['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'];
		foreach ($keys as $k) {
			if (!empty($_SERVER[$k])) {
				$val = $_SERVER[$k];
				if ($k === 'HTTP_X_FORWARDED_FOR') {
					$parts = explode(',', $val);
					return trim($parts[0]);
				}
				return trim($val);
			}
		}
		return '0.0.0.0';
	}
}

if (!function_exists('enforceRateLimit')) {
	/**
	 * Chame antes de operações sensíveis (ex.: login). Lança saída 429 se exceder.
	 */
	function enforceRateLimit(string $key, int $maxRequests, int $windowSeconds): void {
		$ip = getClientIp();
		$bucketKey = '__rate_' . hash('sha256', $ip . '|' . $key);
		$now = time();
		if (!isset($_SESSION[$bucketKey])) {
			$_SESSION[$bucketKey] = ['start' => $now, 'count' => 0];
		}
		$bucket = &$_SESSION[$bucketKey];
		if ($now - $bucket['start'] > $windowSeconds) {
			$bucket = ['start' => $now, 'count' => 0];
		}
		$bucket['count']++;
		if ($bucket['count'] > $maxRequests) {
			if (!headers_sent()) {
				http_response_code(429);
				header('Retry-After: ' . max(1, $windowSeconds - ($now - $bucket['start'])));
			}
			die('Muitas requisições. Tente novamente mais tarde.');
		}
	}
}

// ===== 7) Validações gerais de request (sanity checks) =====
// Bloquear payloads exagerados
$maxPostSizeBytes = 30 * 1024 * 1024; // 30 MB hard cap
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
	$cl = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
	if ($cl > 0 && $cl > $maxPostSizeBytes) {
		if (!headers_sent()) { http_response_code(413); }
		die('Payload muito grande.');
	}
}

// Rejeitar sequências de path traversal básicas no query string
if (!empty($_SERVER['QUERY_STRING'])) {
	$q = $_SERVER['QUERY_STRING'];
	if (strpos($q, "..") !== false || strpos($q, "%00") !== false) {
		if (!headers_sent()) { http_response_code(400); }
		die('Requisição inválida.');
	}
}

// ===== 8) Logout seguro helper =====
if (!function_exists('secureLogout')) {
	function secureLogout(): void {
		$_SESSION = [];
		if (ini_get('session.use_cookies')) {
			$params = session_get_cookie_params();
			setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', true);
		}
		@session_destroy();
		@session_regenerate_id(true);
	}
}

// ===== 9) Sanitização de saída utilitária =====
if (!function_exists('e')) {
	function e(?string $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
}

// ===== 10) Handler global de exceções/erros =====
set_exception_handler(function ($e) {
	error_log('Uncaught exception: ' . $e->getMessage());
	if (!headers_sent()) { http_response_code(500); }
	die('Erro interno.');
});

set_error_handler(function ($severity, $message, $file, $line) {
	if (!(error_reporting() & $severity)) { return false; }
	error_log("PHP error [$severity] $message in $file:$line");
	if (!headers_sent()) { http_response_code(500); }
	die('Erro interno.');
});

register_shutdown_function(function () {
	$err = error_get_last();
	if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
		error_log('Fatal error: ' . print_r($err, true));
		if (!headers_sent()) { http_response_code(500); }
		echo 'Erro interno.';
	}
});

?>