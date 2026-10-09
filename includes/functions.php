<?php
// includes/functions.php

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function alert($type, $message) {
    return "<div class='alert alert-$type' role='alert'>$message</div>";
}

function get_app_setting($key, $default = '') {
    global $pdo;

    if (!isset($pdo) || !$pdo instanceof PDO) {
        return $default;
    }

    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();

        return $value !== false ? $value : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

function get_site_name() {
    $siteName = trim((string) get_app_setting('site_name', 'Hiral Physiotherapy Clinic'));

    return $siteName !== '' ? $siteName : 'Hiral Physiotherapy Clinic';
}

function get_site_logo_url() {
    $customLogoPath = __DIR__ . '/../assets/logo.jpg';

    if (is_file($customLogoPath)) {
        return BASE_URL . '/assets/logo.jpg?v=' . filemtime($customLogoPath);
    }

    return BASE_URL . '/assets/img/logo.jpg';
}

function format_display_date($date, $format = 'd-M-Y') {
    if (empty($date)) {
        return '';
    }

    try {
        $dateTime = new DateTime($date);
        return $dateTime->format($format);
    } catch (Exception $e) {
        return $date;
    }
}
?>
