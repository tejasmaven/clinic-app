<?php

$patientId = isset($_GET['patient_id']) ? preg_replace('/[^0-9]/', '', $_GET['patient_id']) : '';
$fileName  = isset($_GET['file']) ? basename($_GET['file']) : '';

if ($patientId === '' || $fileName === '') {
    http_response_code(404);
    exit('File not found.');
}

$filePath = dirname(__DIR__, 2) . '/uploads/patient_docs/' . $patientId . '/' . $fileName;

if (!is_file($filePath) || !is_readable($filePath)) {
    http_response_code(404);
    exit('File not found.');
}

$mimeType = mime_content_type($filePath);
if ($mimeType === false) {
    $mimeType = 'application/octet-stream';
}

header('Content-Description: File Transfer');
header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: public');
readfile($filePath);
exit;
?>
