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

// Handle delete
if (isset($_GET['delete'])) {
    $paymentController->deletePayment((int)$_GET['delete']);
    header('Location: manage_payments.php?patient_id=' . $patientId);
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'id' => $_POST['id'] ?? null,
        'patient_id' => $patientId,
        'payment_date' => $_POST['payment_date'] ?? date('Y-m-d'),
        'amount' => $_POST['amount'] ?? 0,
        'episodes_covered' => $_POST['episodes_covered'] ?? 0,
        'treatment_covered' => $_POST['treatment_covered'] ?? '',
        'status' => $_POST['status'] ?? 'received'
    ];
    $paymentController->savePayment($data);
    header('Location: manage_payments.php?patient_id=' . $patientId);
    exit;
}

$editPayment = null;
if (isset($_GET['edit'])) {
    $editPayment = $paymentController->getPaymentById((int)$_GET['edit']);
}

$receivedPayments = $paymentController->getPaymentsByPatient($patientId, 'received');
$pendingPayments  = $paymentController->getPaymentsByPatient($patientId, 'pending');

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

    <form method="post" class="mb-4">
      <input type="hidden" name="id" value="<?= $editPayment['id'] ?? '' ?>">
      <div class="row g-2">
        <div class="col-md-3">
          <label class="form-label">Date</label>
          <input type="date" name="payment_date" class="form-control" value="<?= $editPayment['payment_date'] ?? '' ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">Amount</label>
          <input type="number" step="0.01" name="amount" class="form-control" value="<?= $editPayment['amount'] ?? '' ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">Episodes</label>
          <input type="number" name="episodes_covered" class="form-control" value="<?= $editPayment['episodes_covered'] ?? '' ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">Treatment Covered</label>
          <input type="text" name="treatment_covered" class="form-control" value="<?= htmlspecialchars($editPayment['treatment_covered'] ?? '') ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <option value="received" <?= (isset($editPayment['status']) && $editPayment['status'] === 'received') ? 'selected' : '' ?>>Received</option>
            <option value="pending" <?= (isset($editPayment['status']) && $editPayment['status'] === 'pending') ? 'selected' : '' ?>>Pending</option>
          </select>
        </div>
      </div>
      <div class="mt-3">
        <button class="btn btn-primary">Save Payment</button>
        <?php if ($editPayment): ?>
          <a href="manage_payments.php?patient_id=<?= $patientId ?>" class="btn btn-secondary">Cancel</a>
        <?php endif; ?>
      </div>
    </form>

    <ul class="nav nav-tabs" id="paymentTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="received-tab" data-bs-toggle="tab" data-bs-target="#received" type="button" role="tab">Received</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab">Pending</button>
      </li>
    </ul>
    <div class="tab-content mt-3">
      <div class="tab-pane fade show active" id="received" role="tabpanel">
        <?php if (!empty($receivedPayments)): ?>
        <table class="table table-bordered">
          <thead>
            <tr>
              <th>Date</th>
              <th>Amount</th>
              <th>Episode + Treatment</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($receivedPayments as $pay): ?>
            <tr>
              <td><?= htmlspecialchars($pay['payment_date']) ?></td>
              <td><?= htmlspecialchars($pay['amount']) ?></td>
              <td><?= htmlspecialchars($pay['episodes_covered'] . ' / ' . $pay['treatment_covered']) ?></td>
              <td>
                <a href="?patient_id=<?= $patientId ?>&edit=<?= $pay['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                <a href="?patient_id=<?= $patientId ?>&delete=<?= $pay['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete payment?');">Delete</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php else: ?>
          <p>No received payments recorded.</p>
        <?php endif; ?>
      </div>
      <div class="tab-pane fade" id="pending" role="tabpanel">
        <?php if (!empty($pendingPayments)): ?>
        <table class="table table-bordered">
          <thead>
            <tr>
              <th>Date</th>
              <th>Amount</th>
              <th>Episode + Treatment</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pendingPayments as $pay): ?>
            <tr>
              <td><?= htmlspecialchars($pay['payment_date']) ?></td>
              <td><?= htmlspecialchars($pay['amount']) ?></td>
              <td><?= htmlspecialchars($pay['episodes_covered'] . ' / ' . $pay['treatment_covered']) ?></td>
              <td>
                <a href="?patient_id=<?= $patientId ?>&edit=<?= $pay['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                <a href="?patient_id=<?= $patientId ?>&delete=<?= $pay['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete payment?');">Delete</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php else: ?>
          <p>No pending payments.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include '../../includes/footer.php'; ?>
