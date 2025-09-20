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
$transactionTypeValue = $_POST['transaction_type'] ?? 'payment';
$notesValue = $_POST['notes'] ?? '';

// Handle delete
if (isset($_GET['delete'])) {
    $paymentController->deletePayment((int)$_GET['delete']);
    header('Location: manage_payments.php?patient_id=' . $patientId);
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentDateValue = $_POST['payment_date'] ?? $paymentDateValue;
    $amountValue = $_POST['amount'] ?? $amountValue;
    $transactionTypeValue = $_POST['transaction_type'] ?? $transactionTypeValue;
    $notesValue = $_POST['notes'] ?? $notesValue;

    $amount = isset($_POST['amount']) ? (float) $_POST['amount'] : 0;
    $transactionType = $_POST['transaction_type'] ?? 'payment';

    if ($amount <= 0) {
        $msg = 'Please enter a valid payment amount.';
    } elseif (!in_array($transactionType, ['payment', 'charge'], true)) {
        $msg = 'Invalid transaction type selected.';
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

<div class="row">
  <div class="col-md-3">
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
  </div>
  <div class="col-md-9">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4>Payments for <?= htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']) ?></h4>
      <a href="javascript:history.back()" class="btn btn-secondary">Back</a>
    </div>

    <?php if ($msg): ?>
      <div class="alert alert-warning"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
      <div class="col-sm-6 col-lg-4">
        <div class="card border-warning h-100">
          <div class="card-body">
            <div class="text-muted fw-semibold">Total Pending Charges</div>
            <div class="fs-5">R <?= number_format($paymentTotals['pending'] ?? 0, 2) ?></div>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-lg-4">
        <div class="card border-success h-100">
          <div class="card-body">
            <div class="text-muted fw-semibold">Available Credit</div>
            <div class="fs-5">R <?= number_format($paymentTotals['credit'] ?? 0, 2) ?></div>
          </div>
        </div>
      </div>
    </div>

    <form method="post" class="mb-4">
      <div class="row g-2 align-items-end">
        <div class="col-sm-4 col-md-3 col-lg-2">
          <label class="form-label">Date</label>
          <input type="date" name="payment_date" class="form-control" value="<?= htmlspecialchars($paymentDateValue) ?>" required>
        </div>
        <div class="col-sm-4 col-md-3 col-lg-2">
          <label class="form-label">Amount</label>
          <input type="number" step="0.01" min="0" name="amount" class="form-control" value="<?= htmlspecialchars($amountValue) ?>" placeholder="0.00" required>
        </div>
        <div class="col-sm-6 col-md-4 col-lg-3">
          <label class="form-label">Transaction Type</label>
          <select name="transaction_type" class="form-select">
            <option value="payment" <?= $transactionTypeValue === 'payment' ? 'selected' : '' ?>>Payment (Add Credit)</option>
            <option value="charge" <?= $transactionTypeValue === 'charge' ? 'selected' : '' ?>>Charge (Use Credit)</option>
          </select>
        </div>
        <div class="col-12 col-lg-3">
          <label class="form-label">Notes</label>
          <input type="text" name="notes" class="form-control" value="<?= htmlspecialchars($notesValue) ?>" placeholder="Optional description">
        </div>
        <div class="col-sm-4 col-md-3 col-lg-2">
          <button class="btn btn-primary w-100">Save Entry</button>
        </div>
      </div>
      <div class="row mt-2">
        <div class="col-12">
          <small class="text-muted">Charge entries automatically consume available credit. Payments add to the patient&rsquo;s credit balance.</small>
        </div>
      </div>
    </form>

    <div class="table-responsive">
      <table class="table table-bordered table-striped align-middle">
        <thead>
          <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Episode</th>
            <th>Session</th>
            <th>Notes</th>
            <th>Action</th>
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
                $episodeDisplay = $pay['episode_id'] ? 'Episode ' . $pay['episode_id'] : '-';
                $sessionDisplay = $pay['session_reference'] ? $pay['session_reference'] : '-';
                $noteDisplay = $pay['notes'] ? $pay['notes'] : '-';
              ?>
              <tr>
                <td><?= htmlspecialchars($pay['transaction_date']) ?></td>
                <td><span class="badge <?= $typeClass ?>"><?= htmlspecialchars($type) ?></span></td>
                <td>R <?= number_format((float) $pay['amount'], 2) ?></td>
                <td><span class="badge <?= $statusClass ?>"><?= ucfirst(htmlspecialchars($pay['status'])) ?></span></td>
                <td><?= htmlspecialchars($episodeDisplay) ?></td>
                <td><?= htmlspecialchars($sessionDisplay) ?></td>
                <td><?= htmlspecialchars($noteDisplay) ?></td>
                <td>
                  <a href="?patient_id=<?= $patientId ?>&delete=<?= $pay['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete payment entry?');">Delete</a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="8" class="text-center">No payment records available.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include '../../includes/footer.php'; ?>
