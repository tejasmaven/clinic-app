<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireLogin();

if (!in_array($_SESSION['role'] ?? '', ['Admin', 'Super Admin', 'Receptionist'], true)) {
    header('Location: ../login.php');
    exit;
}

require_once '../../controllers/PaymentReceiptController.php';

$patientId = isset($_GET['patient_id']) ? (int) $_GET['patient_id'] : 0;
$paymentId = isset($_GET['payment_id']) ? (int) $_GET['payment_id'] : 0;

if ($patientId <= 0 || $paymentId <= 0) {
    exit('Invalid receipt request.');
}

$receiptController = new PaymentReceiptController($pdo);

try {
    $receiptData = $receiptController->getReceiptData($paymentId, $patientId);
} catch (Throwable $e) {
    exit(htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}

$values = $receiptData['values'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($values as $key => $value) {
        if (array_key_exists($key, $_POST)) {
            $values[$key] = trim((string) $_POST[$key]);
        }
    }
}

$template = $receiptData['template'];
$fields = $receiptData['fields'];
$imageUrl = BASE_URL . '/' . ltrim($template['image_path'], '/') . '?v=' . strtotime((string) ($template['updated_at'] ?? 'now'));
$imageWidth = (int) ($template['image_width'] ?? 0);
$imageHeight = (int) ($template['image_height'] ?? 0);
$patient = $receiptData['patient'];
$payment = $receiptData['payment'];
$patientName = trim(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''));

include '../../includes/header.php';
?>

<style>
  .receipt-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    justify-content: space-between;
  }

  .receipt-sheet {
    background: #fff;
    border: 1px solid rgba(15, 23, 42, 0.12);
    box-shadow: 0 1.5rem 3rem rgba(15, 23, 42, 0.08);
    margin: 0 auto;
    max-width: 100%;
    overflow: auto;
    padding: 1rem;
  }

  .receipt-canvas {
    background: #fff;
    position: relative;
    width: <?= $imageWidth > 0 ? $imageWidth : 900 ?>px;
    height: <?= $imageHeight > 0 ? $imageHeight : 640 ?>px;
    max-width: none;
  }

  .receipt-canvas img {
    display: block;
    height: 100%;
    width: 100%;
  }

  .receipt-field {
    color: #1f2937;
    font-family: "Segoe UI", Arial, sans-serif;
    font-weight: 600;
    overflow: hidden;
    position: absolute;
    white-space: normal;
    word-break: break-word;
  }

  @media print {
    body {
      background: #fff !important;
    }

    nav,
    .receipt-toolbar,
    .receipt-editor,
    .admin-sidebar-wrapper,
    .workspace-sidebar-wrapper,
    .toast-container {
      display: none !important;
    }

    .app-main {
      margin: 0;
      max-width: none;
      padding: 0 !important;
      width: auto;
    }

    .workspace-layout,
    .workspace-content {
      display: block;
      margin: 0;
      padding: 0;
    }

    .receipt-sheet {
      border: 0;
      box-shadow: none;
      margin: 0;
      overflow: visible;
      padding: 0;
    }

    @page {
      size: landscape;
      margin: 8mm;
    }
  }
</style>

<div class="workspace-layout">
  <?php
    switch ($_SESSION['role']) {
      case 'Receptionist':
        include '../../layouts/receptionist_sidebar.php';
        break;
      default:
        include '../../layouts/admin_sidebar.php';
    }
  ?>
  <div class="workspace-content">
    <div class="workspace-page-header receipt-toolbar">
      <div>
        <h1 class="workspace-page-title">Payment Receipt</h1>
        <p class="workspace-page-subtitle">
          <?= htmlspecialchars($patientName) ?> | <?= htmlspecialchars(format_display_date($payment['transaction_date'] ?? '')) ?> | <?= htmlspecialchars($values['amount_number']) ?>
        </p>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="manage_payments.php?patient_id=<?= (int) $patientId ?>">Back</a>
        <button class="btn btn-outline-primary" type="button" onclick="window.print()">Print / Save PDF</button>
      </div>
    </div>

    <form method="post" class="app-card receipt-editor">
      <h5 class="mb-3">Receipt Details</h5>
      <div class="row g-3">
        <div class="col-12 col-md-6">
          <label class="form-label" for="received_from">Received from</label>
          <input class="form-control" id="received_from" name="received_from" value="<?= htmlspecialchars($values['received_from']) ?>">
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label" for="amount_number">Amount number</label>
          <input class="form-control" id="amount_number" name="amount_number" value="<?= htmlspecialchars($values['amount_number']) ?>">
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label" for="amount_words">Amount words</label>
          <input class="form-control" id="amount_words" name="amount_words" value="<?= htmlspecialchars($values['amount_words']) ?>">
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label" for="purpose_line_1">Purpose line 1</label>
          <input class="form-control" id="purpose_line_1" name="purpose_line_1" value="<?= htmlspecialchars($values['purpose_line_1']) ?>">
        </div>
        <div class="col-12 col-md-6">
          <label class="form-label" for="purpose_line_2">Purpose line 2</label>
          <input class="form-control" id="purpose_line_2" name="purpose_line_2" value="<?= htmlspecialchars($values['purpose_line_2']) ?>">
        </div>
        <div class="col-12 col-sm-4 col-md-2">
          <label class="form-label" for="treatment_start_date">Start</label>
          <input class="form-control" id="treatment_start_date" name="treatment_start_date" value="<?= htmlspecialchars($values['treatment_start_date']) ?>">
        </div>
        <div class="col-12 col-sm-4 col-md-2">
          <label class="form-label" for="treatment_end_date">End</label>
          <input class="form-control" id="treatment_end_date" name="treatment_end_date" value="<?= htmlspecialchars($values['treatment_end_date']) ?>">
        </div>
        <div class="col-12 col-sm-4 col-md-2">
          <label class="form-label" for="treatment_days">Days</label>
          <input class="form-control" id="treatment_days" name="treatment_days" value="<?= htmlspecialchars($values['treatment_days']) ?>">
        </div>
        <div class="col-12 col-md-2 d-grid align-items-end">
          <button class="btn btn-primary">Update Preview</button>
        </div>
      </div>
    </form>

    <div class="receipt-sheet">
      <div class="receipt-canvas">
        <img src="<?= htmlspecialchars($imageUrl) ?>" alt="Payment receipt template">
        <?php foreach ($fields as $fieldKey => $field): ?>
          <?php if (empty($field['enabled'])) continue; ?>
          <div
            class="receipt-field"
            style="
              left: <?= htmlspecialchars((string) $field['x']) ?>px;
              top: <?= htmlspecialchars((string) $field['y']) ?>px;
              width: <?= htmlspecialchars((string) $field['width']) ?>px;
              height: <?= htmlspecialchars((string) $field['height']) ?>px;
              font-size: <?= htmlspecialchars((string) $field['font_size']) ?>px;
              line-height: <?= htmlspecialchars((string) $field['line_height']) ?>px;
            "
          ><?= nl2br(htmlspecialchars($values[$fieldKey] ?? '')) ?></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<?php include '../../includes/footer.php'; ?>
