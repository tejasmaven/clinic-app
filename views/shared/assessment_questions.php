<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireLogin();

if (!in_array($_SESSION['role'] ?? '', ['Admin', 'Super Admin', 'Doctor'], true)) {
    header('Location: ../login.php');
    exit;
}

require_once '../../controllers/AssessmentController.php';
$controller = new AssessmentController($pdo);
$sectionId = (int) ($_GET['section_id'] ?? $_POST['section_id'] ?? 0);
$section = $controller->getSectionById($sectionId);

if (!$section) {
    exit('Assessment section not found.');
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $msg = $controller->handleSetupActions($_POST);
    } catch (Throwable $e) {
        $msg = 'Unable to update questions: ' . $e->getMessage();
    }
    $section = $controller->getSectionById($sectionId);
}

$items = $controller->getItemsBySection($sectionId);
$inputTypes = $controller->getInputTypes();
$isAdmin = in_array($_SESSION['role'] ?? '', ['Admin', 'Super Admin'], true);

include '../../includes/header.php';
?>

<div class="<?= $isAdmin ? 'admin-layout' : 'workspace-layout' ?>">
    <?php include $isAdmin ? '../../layouts/admin_sidebar.php' : '../../layouts/doctor_sidebar.php'; ?>
    <div class="<?= $isAdmin ? 'admin-content' : 'workspace-content' ?>">
        <div class="<?= $isAdmin ? 'admin-page-header' : 'workspace-page-header' ?>">
            <div>
                <h1 class="<?= $isAdmin ? 'admin-page-title' : 'workspace-page-title' ?>">Questions: <?= htmlspecialchars($section['title']) ?></h1>
                <p class="<?= $isAdmin ? 'admin-page-subtitle' : 'workspace-page-subtitle' ?>">Add questions and practical examination inputs for this assessment section.</p>
            </div>
            <a class="btn btn-outline-secondary" href="assessment_setup.php">Back to Sections</a>
        </div>

        <?php if ($msg !== ''): ?>
            <div class="alert alert-info"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <div class="app-card">
            <h5 class="mb-3">Add Question / Examination Input</h5>
            <form method="post" class="row g-3">
                <input type="hidden" name="action" value="add_item">
                <input type="hidden" name="section_id" value="<?= (int) $sectionId ?>">
                <div class="col-12 col-md-4">
                    <label class="form-label" for="item_type">What is this?</label>
                    <select class="form-select assessment-item-type" id="item_type" name="item_type">
                        <option value="question">Question</option>
                        <option value="examination">Practical exam input</option>
                    </select>
                    <div class="form-text">Example: question for pain history, exam input for ROM.</div>
                </div>
                <div class="col-12 col-md-4 assessment-answer-style-wrap">
                    <label class="form-label" for="input_type">Answer style</label>
                    <select class="form-select assessment-input-type" id="input_type" name="input_type">
                        <?php foreach ($inputTypes as $inputType): ?>
                            <option value="<?= htmlspecialchars($inputType) ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $inputType))) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text assessment-input-help">Most history questions can use Yes/No.</div>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label" for="sort_order">Order</label>
                    <input class="form-control" id="sort_order" type="number" name="sort_order" value="0">
                </div>
                <div class="col-12">
                    <label class="form-label" for="item_label">Question / exam wording</label>
                    <input class="form-control assessment-label-input" id="item_label" name="label" placeholder="Example: Does pain travel to the arm?" required>
                </div>
                <div class="col-12 assessment-choices-wrap">
                    <label class="form-label" for="option_values">Choices</label>
                    <input class="form-control assessment-options-input" id="option_values" name="option_values" placeholder="Example: Mild, Moderate, Severe">
                    <div class="form-text">Fill this only when Answer style is Select.</div>
                </div>
                <div class="col-12 col-md-3">
                    <button class="btn btn-primary w-100">Add</button>
                </div>
            </form>
        </div>

        <div class="app-card">
            <h5 class="mb-3">Current Questions / Exam Inputs</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Question / exam wording</th>
                            <th>Answer style</th>
                            <th>Choices</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['item_type'] === 'examination' ? 'Practical exam input' : 'Question') ?></td>
                                <td><?= htmlspecialchars($item['label']) ?></td>
                                <td><?= $item['item_type'] === 'examination' ? '-' : htmlspecialchars(ucwords(str_replace('_', ' ', $item['input_type']))) ?></td>
                                <td><?= ($item['item_type'] === 'question' && $item['input_type'] === 'select') ? htmlspecialchars($item['option_values'] ?: '-') : '-' ?></td>
                                <td>
                                    <span class="badge <?= !empty($item['is_active']) ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= !empty($item['is_active']) ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-flex flex-wrap justify-content-end gap-2">
                                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#editItem<?= (int) $item['id'] ?>">Edit</button>
                                        <form method="post">
                                            <input type="hidden" name="action" value="toggle_item">
                                            <input type="hidden" name="section_id" value="<?= (int) $sectionId ?>">
                                            <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                            <button class="btn btn-sm btn-outline-secondary"><?= !empty($item['is_active']) ? 'Deactivate' : 'Activate' ?></button>
                                        </form>
                                        <form method="post" onsubmit="return confirm('Delete this question or examination input? Existing patient answers for this item will also be removed.');">
                                            <input type="hidden" name="action" value="delete_item">
                                            <input type="hidden" name="section_id" value="<?= (int) $sectionId ?>">
                                            <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <tr class="collapse" id="editItem<?= (int) $item['id'] ?>">
                                <td colspan="6">
                                    <form method="post" class="bg-light border rounded p-3 row g-3 assessment-item-form">
                                        <input type="hidden" name="action" value="edit_item">
                                        <input type="hidden" name="section_id" value="<?= (int) $sectionId ?>">
                                        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                        <div class="col-12 col-md-4">
                                            <label class="form-label">What is this?</label>
                                            <select class="form-select assessment-item-type" name="item_type">
                                                <option value="question" <?= $item['item_type'] === 'question' ? 'selected' : '' ?>>Question</option>
                                                <option value="examination" <?= $item['item_type'] === 'examination' ? 'selected' : '' ?>>Practical exam input</option>
                                            </select>
                                        </div>
                                        <div class="col-12 col-md-4 assessment-answer-style-wrap">
                                            <label class="form-label">Answer style</label>
                                            <select class="form-select assessment-input-type" name="input_type">
                                                <?php foreach ($inputTypes as $inputType): ?>
                                                    <option value="<?= htmlspecialchars($inputType) ?>" <?= $item['input_type'] === $inputType ? 'selected' : '' ?>><?= htmlspecialchars(ucwords(str_replace('_', ' ', $inputType))) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="form-text assessment-input-help">Most history questions can use Yes/No.</div>
                                        </div>
                                        <div class="col-12 col-md-2">
                                            <label class="form-label">Order</label>
                                            <input class="form-control" type="number" name="sort_order" value="<?= (int) $item['sort_order'] ?>">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Question / exam wording</label>
                                            <input class="form-control assessment-label-input" name="label" value="<?= htmlspecialchars($item['label']) ?>" required>
                                        </div>
                                        <div class="col-12 assessment-choices-wrap">
                                            <label class="form-label">Choices</label>
                                            <input class="form-control assessment-options-input" name="option_values" value="<?= htmlspecialchars($item['option_values'] ?? '') ?>" placeholder="Example: Mild, Moderate, Severe">
                                            <div class="form-text">Fill this only when Answer style is Select.</div>
                                        </div>
                                        <div class="col-12 col-md-3">
                                            <button class="btn btn-primary w-100">Save</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($items)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No questions or examination inputs added for this section yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const suggestions = {
        yes_no: {
            help: 'Use for clear yes/no answers.',
            label: 'Example: Does pain travel to the arm?',
            options: ''
        },
        text: {
            help: 'Use for a short one-line answer.',
            label: 'Example: Pain location or short observation',
            options: ''
        },
        textarea: {
            help: 'Use when the answer may need a longer note.',
            label: 'Example: Describe the pain pattern',
            options: ''
        },
        number: {
            help: 'Use for measurable values.',
            label: 'Example: Pain score from 0 to 10',
            options: ''
        },
        select: {
            help: 'Use when clinic wants fixed choices.',
            label: 'Example: Select severity of pain',
            options: 'Example: Mild, Moderate, Severe'
        }
    };

    function syncAssessmentForm(form) {
        const itemType = form.querySelector('.assessment-item-type');
        const inputType = form.querySelector('.assessment-input-type');
        const answerStyleWrap = form.querySelector('.assessment-answer-style-wrap');
        const choicesWrap = form.querySelector('.assessment-choices-wrap');
        const optionsInput = form.querySelector('.assessment-options-input');
        const labelInput = form.querySelector('.assessment-label-input');
        const help = form.querySelector('.assessment-input-help');
        const isExam = itemType && itemType.value === 'examination';
        const style = inputType ? inputType.value : 'yes_no';
        const suggestion = suggestions[style] || suggestions.yes_no;

        if (answerStyleWrap) {
            answerStyleWrap.classList.toggle('d-none', isExam);
        }

        if (choicesWrap) {
            choicesWrap.classList.toggle('d-none', isExam || style !== 'select');
        }

        if (inputType) {
            inputType.disabled = isExam;
        }

        if (optionsInput) {
            optionsInput.disabled = isExam || style !== 'select';
            optionsInput.placeholder = suggestion.options || 'Only used when Answer style is Select';
            if (isExam || style !== 'select') {
                optionsInput.value = '';
            }
        }

        if (labelInput) {
            labelInput.placeholder = isExam ? 'Example: Cervical flexion range / SLR result' : suggestion.label;
        }

        if (help) {
            help.textContent = suggestion.help;
        }
    }

    document.querySelectorAll('form').forEach(function (form) {
        if (!form.querySelector('.assessment-item-type')) {
            return;
        }

        syncAssessmentForm(form);
        form.addEventListener('change', function (event) {
            if (event.target.classList.contains('assessment-item-type') || event.target.classList.contains('assessment-input-type')) {
                syncAssessmentForm(form);
            }
        });
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
