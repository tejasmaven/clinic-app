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
