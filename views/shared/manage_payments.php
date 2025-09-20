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
    $amount = isset($_POST['amount']) ? (float) $_POST['amount'] : 0;

    if ($amount <= 0) {
        $msg = 'Please enter a valid payment amount.';
    } else {
        $data = [
            'patient_id' => $patientId,
            'payment_date' => $paymentDateValue ?: date('Y-m-d'),
            'amount' => $amount,
            'episodes_covered' => null,
            'treatment_covered' => null,
        ];
        $paymentController->savePayment($data);
        header('Location: manage_payments.php?patient_id=' . $patientId);
        exit;
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
            <div class="text-muted fw-semibold">Total Pending</div>
            <div class="fs-5">R <?= number_format($paymentTotals['pending'] ?? 0, 2) ?></div>
          </div>
        </div>
      </div>
      <div class="col-sm-6 col-lg-4">
        <div class="card border-success h-100">
          <div class="card-body">
            <div class="text-muted fw-semibold">Total Credit</div>
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
        <div class="col-sm-4 col-md-3 col-lg-2">
          <button class="btn btn-primary w-100">Add Payment</button>
        </div>
      </div>
    </form>

    <div class="table-responsive">
      <table class="table table-bordered table-striped align-middle">
        <thead>
          <tr>
            <th>Date</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Episode</th>
            <th>Details</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($allPayments)): ?>
            <?php foreach ($allPayments as $pay): ?>
              <?php
                $episodeDisplay = $pay['episodes_covered'] ? 'Episode ' . $pay['episodes_covered'] : '-';
                if ($pay['status'] === 'credit') {
                    $details = 'Credit Balance';
                } elseif (!empty($pay['treatment_covered'])) {
                    $details = 'Session ' . $pay['treatment_covered'];
                } else {
                    $details = 'Manual Payment';
                }
                $statusClass = [
                    'pending' => 'bg-warning text-dark',
                    'received' => 'bg-success',
                    'credit' => 'bg-info text-dark',
                ][$pay['status']] ?? 'bg-secondary';
              ?>
              <tr>
                <td><?= htmlspecialchars($pay['payment_date']) ?></td>
                <td>R <?= number_format((float) $pay['amount'], 2) ?></td>
                <td><span class="badge <?= $statusClass ?>"><?= ucfirst(htmlspecialchars($pay['status'])) ?></span></td>
                <td><?= htmlspecialchars($episodeDisplay) ?></td>
                <td><?= htmlspecialchars($details) ?></td>
                <td>
                  <a href="?patient_id=<?= $patientId ?>&delete=<?= $pay['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete payment?');">Delete</a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" class="text-center">No payment records available.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include '../../includes/footer.php'; ?>
