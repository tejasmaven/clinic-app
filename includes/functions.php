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
?>
