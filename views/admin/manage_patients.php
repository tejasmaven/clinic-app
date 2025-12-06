<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
requireLogin();
requireRole('Admin');

require_once '../../controllers/PatientController.php';
$controller = new PatientController($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_patient') {
    $patientId = (int) ($_POST['id'] ?? 0);
    $result = $controller->deletePatient($patientId);
    $flashType = $result['success'] ? 'success' : 'danger';
    $msg = $result['message'] ?? 'Unable to delete patient.';
    header('Location: manage_patients.php?msg=' . urlencode($msg) . '&type=' . $flashType);
    exit;
}

// Pagination + Search Setup
$search = $_GET['search'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 10;

$patients = $controller->getPatients($search, $page, $limit);
$total = $controller->countPatients($search);
$totalPages = (int) ceil($total / $limit);
$flash = $_GET['msg'] ?? '';
$flashType = $_GET['type'] ?? 'success';

include '../../includes/header.php';
?>

<div class="admin-layout">
    <?php include '../../layouts/admin_sidebar.php'; ?>
    <div class="admin-content">
        <div class="admin-page-header">
            <div>
                <h1 class="admin-page-title">Patient Onboarding</h1>
                <p class="admin-page-subtitle">Review and manage patient profiles in one place.</p>
            </div>
            <a href="patient_form.php" class="btn btn-success">+ Add New Patient</a>
        </div>

        <?php if (!empty($flash)): ?>
            <div class="alert alert-<?= htmlspecialchars($flashType) ?>" role="alert"><?= htmlspecialchars($flash) ?></div>
        <?php endif; ?>

        <div class="app-card">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-12 col-md-6 col-lg-4">
                    <label for="search" class="form-label">Search patients</label>
                    <input type="text" id="search" name="search" class="form-control" placeholder="Search by name" value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-12 col-md-3 col-lg-2">
                    <button class="btn btn-primary w-100">Search</button>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Gender</th>
                        <th scope="col">DOB</th>
                        <th scope="col">Contact</th>
                        <th scope="col">Referral</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($patients as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></td>
                        <td><?= htmlspecialchars($p['gender']) ?></td>
                        <td><?= htmlspecialchars(format_display_date($p['date_of_birth'])) ?></td>
                        <td><?= htmlspecialchars($p['contact_number']) ?></td>
                        <td><?= htmlspecialchars($p['referral_source']) ?></td>
                        <td class="text-end">
                            <div class="d-flex flex-wrap justify-content-end gap-2">
                                <a href="patient_form.php?id=<?= (int) $p['id'] ?>" class="btn btn-sm btn-info">Edit</a>
                                <a href="../shared/manage_payments.php?patient_id=<?= (int) $p['id'] ?>" class="btn btn-sm btn-secondary">Payments</a>
                                <a href="../shared/manage_patients_files.php?patient_id=<?= (int) $p['id'] ?>" class="btn btn-sm btn-warning">View Files</a>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this patient and all related records?');">
                                    <input type="hidden" name="action" value="delete_patient">
                                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                    <button class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <nav aria-label="Patient pagination" class="d-flex justify-content-end">
            <ul class="pagination mt-3">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <li class="page-item <?= $p == $page ? 'active' : '' ?>">
                    <a class="page-link" href="?search=<?= urlencode($search) ?>&page=<?= $p ?>"><?= $p ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
