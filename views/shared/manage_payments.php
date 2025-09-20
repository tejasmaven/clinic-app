<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireLogin();
if (!in_array($_SESSION['role'] ?? '', ['Admin','Doctor','Receptionist'])) {
    header('Location: ../login.php');
    exit;
}

require_once '../../controllers/PatientController.php';
require_once '../../controllers/PaymentController.php';
$patientController = new PatientController($pdo);
$paymentController = new PaymentController($pdo);

$patientId = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
if ($patientId <= 0) {
    exit('Invalid patient ID.');
}

$patient = $patientController->getPatientById($patientId);
if (!$patient) {
    exit('Patient not found.');
}

$msg = null;
$paymentDateValue = date('Y-m-d');
$amountValue = '';
$notesValue = $_POST['notes'] ?? '';
$isEditing = false;
$editingPaymentId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;

if ($editingPaymentId > 0) {
    $editingPayment = $paymentController->getPaymentById($editingPaymentId);
    if ($editingPayment && (int) $editingPayment['patient_id'] === $patientId && $editingPayment['transaction_type'] === 'payment') {
        $isEditing = true;
        $paymentDateValue = $editingPayment['transaction_date'];
        $amountValue = number_format((float) $editingPayment['amount'], 2, '.', '');
        $notesValue = $editingPayment['notes'] ?? '';
    } else {
        $msg = 'Only credit entries can be edited.';
        $editingPaymentId = 0;
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    if ($paymentController->deletePayment((int)$_GET['delete'])) {
        header('Location: manage_payments.php?patient_id=' . $patientId);
        exit;
    }

    $msg = 'Only credit entries can be deleted.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentDateValue = $_POST['payment_date'] ?? $paymentDateValue;
    $amountValue = $_POST['amount'] ?? $amountValue;
    $notesValue = $_POST['notes'] ?? $notesValue;

    $amount = isset($_POST['amount']) ? (float) $_POST['amount'] : 0;
    $transactionType = $_POST['transaction_type'] ?? 'payment';
    $editingPaymentIdFromPost = isset($_POST['payment_id']) ? (int) $_POST['payment_id'] : 0;

    if ($amount <= 0) {
        $msg = 'Please enter a valid payment amount.';
    } elseif ($transactionType !== 'payment') {
        $msg = 'Invalid transaction type selected.';
    } elseif ($editingPaymentIdFromPost > 0) {
        try {
            $paymentController->updatePayment($editingPaymentIdFromPost, [
                'patient_id' => $patientId,
                'payment_date' => $paymentDateValue ?: date('Y-m-d'),
                'amount' => $amount,
                'notes' => trim((string) ($_POST['notes'] ?? '')),
            ]);
            header('Location: manage_payments.php?patient_id=' . $patientId);
            exit;
        } catch (Exception $e) {
            $msg = 'Unable to update payment: ' . $e->getMessage();
            $isEditing = true;
            $editingPaymentId = $editingPaymentIdFromPost;
        }
    } else {
        $data = [
            'patient_id' => $patientId,
            'payment_date' => $paymentDateValue ?: date('Y-m-d'),
            'amount' => $amount,
            'transaction_type' => $transactionType,
            'notes' => trim((string) ($_POST['notes'] ?? '')),
        ];

        try {
            $paymentController->savePayment($data);
            header('Location: manage_payments.php?patient_id=' . $patientId);
            exit;
        } catch (Exception $e) {
            $msg = 'Unable to save payment: ' . $e->getMessage();
        }
    }
}

$paymentTotals = $paymentController->getPatientTotals($patientId);
$allPayments = $paymentController->getAllPaymentsByPatient($patientId);

include '../../includes/header.php';
?>
<div class="workspace-layout">
  <?php
    switch ($_SESSION['role']) {
      case 'Doctor':
        include '../../layouts/doctor_sidebar.php';
        break;
      case 'Receptionist':
        include '../../layouts/receptionist_sidebar.php';
        break;
      default:
        include '../../layouts/admin_sidebar.php';
    }
  ?>
  <div class="workspace-content">
    <div class="workspace-page-header">
      <div>
        <h1 class="workspace-page-title">Payments for <?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?></h1>
        <p class="workspace-page-subtitle">Track charges and credit balances applied to this patient.</p>
      </div>
      <div class="d-flex gap-2">
        <a href="javascript:history.back()" class="btn btn-outline-secondary">Back</a>
      </div>
    </div>

    <?php if ($msg): ?>
      <div class="alert alert-warning"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="row g-3">
      <div class="col-12 col-md-6 col-xl-4">
        <div class="app-card h-100 border-warning border-2">
          <div class="text-muted fw-semibold">Total Pending Charges</div>
          <div class="fs-3 mt-2">R <?= number_format($paymentTotals['pending'] ?? 0, 2) ?></div>
        </div>
      </div>
      <div class="col-12 col-md-6 col-xl-4">
        <div class="app-card h-100 border-success border-2">
          <div class="text-muted fw-semibold">Available Credit</div>
          <div class="fs-3 mt-2">R <?= number_format($paymentTotals['credit'] ?? 0, 2) ?></div>
        </div>
      </div>
    </div>

    <div class="app-card">
      <form method="post" class="row g-3 align-items-end">
        <input type="hidden" name="transaction_type" value="payment">
        <?php if ($isEditing && $editingPaymentId > 0): ?>
          <input type="hidden" name="payment_id" value="<?= $editingPaymentId ?>">
        <?php endif; ?>

        <div class="col-12 col-sm-6 col-lg-3">
          <label class="form-label" for="payment_date">Date</label>
          <input type="date" id="payment_date" name="payment_date" class="form-control" value="<?= htmlspecialchars($paymentDateValue) ?>" required>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
          <label class="form-label" for="payment_amount">Amount</label>
          <input type="number" step="0.01" min="0" id="payment_amount" name="amount" class="form-control" value="<?= htmlspecialchars($amountValue) ?>" placeholder="0.00" required>
        </div>
        <div class="col-12 col-lg-4">
          <label class="form-label" for="payment_notes">Notes</label>
          <input type="text" id="payment_notes" name="notes" class="form-control" value="<?= htmlspecialchars($notesValue) ?>" placeholder="Optional description">
        </div>
        <div class="col-12 col-lg-2 d-grid gap-2">
          <button class="btn btn-primary"><?= $isEditing ? 'Update Credit' : 'Add Credit' ?></button>
          <?php if ($isEditing): ?>
            <a href="?patient_id=<?= $patientId ?>" class="btn btn-outline-secondary">Cancel</a>
          <?php endif; ?>
        </div>
        <div class="col-12">
          <small class="text-muted">Use this form to add or adjust credit entries for the patient. Treatment charges are generated automatically.</small>
        </div>
      </form>
    </div>

    <div class="app-card">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th scope="col">Date</th>
              <th scope="col">Type</th>
              <th scope="col">Amount</th>
              <th scope="col">Status</th>
              <th scope="col">Session</th>
              <th scope="col">Notes</th>
              <th scope="col">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($allPayments)): ?>
              <?php foreach ($allPayments as $pay): ?>
                <?php
                  $type = $pay['transaction_type'] === 'charge' ? 'Charge' : 'Payment';
                  $typeClass = $pay['transaction_type'] === 'charge' ? 'bg-warning text-dark' : 'bg-info text-dark';
                  $statusClass = $pay['status'] === 'received' ? 'bg-success' : 'bg-secondary';
                  if ($pay['transaction_type'] === 'charge' && $pay['status'] === 'pending') {
                      $statusClass = 'bg-warning text-dark';
                  }
                  $sessionDisplay = $pay['session_reference'] ? $pay['session_reference'] : '-';
                  $noteDisplay = $pay['notes'] ? $pay['notes'] : '-';
                ?>
                <tr>
                  <td><?= htmlspecialchars($pay['transaction_date']) ?></td>
                  <td><span class="badge <?= $typeClass ?>"><?= htmlspecialchars($type) ?></span></td>
                  <td>R <?= number_format((float) $pay['amount'], 2) ?></td>
                  <td><span class="badge <?= $statusClass ?>"><?= ucfirst(htmlspecialchars($pay['status'])) ?></span></td>
                  <td><?= htmlspecialchars($sessionDisplay) ?></td>
                  <td><?= htmlspecialchars($noteDisplay) ?></td>
                  <td class="text-nowrap">
                    <?php if ($pay['transaction_type'] === 'payment'): ?>
                      <a href="?patient_id=<?= $patientId ?>&edit=<?= $pay['id'] ?>" class="btn btn-sm btn-outline-primary me-1">Edit</a>
                      <a href="?patient_id=<?= $patientId ?>&delete=<?= $pay['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete credit entry?');">Delete</a>
                    <?php else: ?>
                      <span class="text-muted small">Not available</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="7" class="text-center text-muted py-4">No payment records available.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php include '../../includes/footer.php'; ?>
