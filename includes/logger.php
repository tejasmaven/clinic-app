<?php

if (!function_exists('ensure_app_logs_table')) {
    function ensure_app_logs_table(): bool {
        global $pdo;
        static $ready = null;

        if ($ready !== null) {
            return $ready;
        }

        if (!isset($pdo) || !$pdo instanceof PDO) {
            $ready = false;
            return false;
        }

        try {
            $pdo->exec(
                "CREATE TABLE IF NOT EXISTS app_logs (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    log_type VARCHAR(20) NOT NULL,
                    severity VARCHAR(20) NOT NULL DEFAULT 'info',
                    action VARCHAR(150) DEFAULT NULL,
                    message TEXT NOT NULL,
                    user_id INT DEFAULT NULL,
                    user_role VARCHAR(50) DEFAULT NULL,
                    user_name VARCHAR(150) DEFAULT NULL,
                    user_email VARCHAR(190) DEFAULT NULL,
                    request_method VARCHAR(10) DEFAULT NULL,
                    request_uri TEXT DEFAULT NULL,
                    ip_address VARCHAR(45) DEFAULT NULL,
                    user_agent TEXT DEFAULT NULL,
                    context_json JSON DEFAULT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_log_type_created_at (log_type, created_at),
                    INDEX idx_severity_created_at (severity, created_at),
                    INDEX idx_user_role_created_at (user_role, created_at)
                )"
            );
            $ready = true;
        } catch (Throwable $e) {
            $ready = false;
            error_log('Unable to prepare app_logs table: ' . $e->getMessage());
        }

        return $ready;
    }
}

if (!function_exists('sanitize_log_context')) {
    function sanitize_log_context($value) {
        $sensitiveKeys = ['password', 'confirm_password', 'current_password', 'new_password', 'password_hash'];

        if (is_array($value)) {
            $clean = [];
            foreach ($value as $key => $item) {
                $stringKey = is_string($key) ? strtolower($key) : $key;
                if (is_string($stringKey)) {
                    foreach ($sensitiveKeys as $sensitiveKey) {
                        if (strpos($stringKey, $sensitiveKey) !== false) {
                            $clean[$key] = '[redacted]';
                            continue 2;
                        }
                    }
                }

                $clean[$key] = sanitize_log_context($item);
            }
            return $clean;
        }

        if (is_object($value)) {
            return '[object ' . get_class($value) . ']';
        }

        if (is_string($value) && strlen($value) > 500) {
            return substr($value, 0, 500) . '...';
        }

        return $value;
    }
}

if (!function_exists('get_log_user_context')) {
    function get_log_user_context(): array {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        return [
            'user_id' => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
            'user_role' => $_SESSION['role'] ?? null,
            'user_name' => $_SESSION['name'] ?? null,
            'user_email' => $_SESSION['email'] ?? null,
        ];
    }
}

if (!function_exists('write_app_log')) {
    function write_app_log(string $logType, string $severity, ?string $action, string $message, array $context = []): void {
        global $pdo;

        if (!ensure_app_logs_table()) {
            error_log(strtoupper($logType) . ' [' . $severity . '] ' . $message);
            return;
        }

        $user = get_log_user_context();
        $contextJson = null;

        if (!empty($context)) {
            $contextJson = json_encode(sanitize_log_context($context), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($contextJson === false) {
                $contextJson = json_encode(['context_error' => 'Unable to encode log context.']);
            }
        }

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO app_logs (
                    log_type, severity, action, message, user_id, user_role, user_name, user_email,
                    request_method, request_uri, ip_address, user_agent, context_json
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $logType,
                $severity,
                $action,
                $message,
                $user['user_id'],
                $user['user_role'],
                $user['user_name'],
                $user['user_email'],
                $_SERVER['REQUEST_METHOD'] ?? null,
                $_SERVER['REQUEST_URI'] ?? null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $contextJson,
            ]);
        } catch (Throwable $e) {
            error_log('Unable to write app log: ' . $e->getMessage());
        }
    }
}

if (!function_exists('log_user_action')) {
    function log_user_action(?string $action, string $message, array $context = []): void {
        $user = get_log_user_context();

        if (empty($user['user_id']) || ($user['user_role'] ?? '') === 'Super Admin') {
            return;
        }

        write_app_log('action', 'info', $action, $message, $context);
    }
}

if (!function_exists('log_app_error')) {
    function log_app_error(string $severity, string $message, array $context = []): void {
        write_app_log('error', $severity, $context['action'] ?? null, $message, $context);
    }
}

if (!defined('APP_LOGGER_REGISTERED')) {
    define('APP_LOGGER_REGISTERED', true);

    set_error_handler(static function (int $errno, string $errstr, string $errfile, int $errline): bool {
        $nonFatalMap = [
            E_NOTICE => 'notice',
            E_USER_NOTICE => 'notice',
            E_WARNING => 'warning',
            E_USER_WARNING => 'warning',
            E_DEPRECATED => 'notice',
            E_USER_DEPRECATED => 'notice',
        ];

        log_app_error($nonFatalMap[$errno] ?? 'error', $errstr, [
            'error_number' => $errno,
            'file' => $errfile,
            'line' => $errline,
        ]);

        return false;
    });

    set_exception_handler(static function (Throwable $exception): void {
        log_app_error('critical', $exception->getMessage(), [
            'exception' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ]);

        http_response_code(500);
        echo 'An unexpected error occurred.';
    });

    register_shutdown_function(static function (): void {
        $lastError = error_get_last();
        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

        if ($lastError && in_array($lastError['type'], $fatalTypes, true)) {
            log_app_error('critical', $lastError['message'], [
                'error_number' => $lastError['type'],
                'file' => $lastError['file'],
                'line' => $lastError['line'],
            ]);
            return;
        }

        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return;
        }

        $user = get_log_user_context();
        if (empty($user['user_id']) || ($user['user_role'] ?? '') === 'Super Admin') {
            return;
        }

        $script = basename($_SERVER['SCRIPT_NAME'] ?? 'request');
        $postedAction = $_POST['action'] ?? $_POST['bulk_fee_edit'] ?? null;
        $action = is_scalar($postedAction) && (string) $postedAction !== ''
            ? (string) $postedAction
            : pathinfo($script, PATHINFO_FILENAME);

        log_user_action($action, 'User performed an action.', [
            'post' => $_POST,
            'files' => sanitize_log_context($_FILES),
            'script' => $_SERVER['SCRIPT_NAME'] ?? null,
        ]);
    });
}
