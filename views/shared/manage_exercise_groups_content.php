<?php
$editingGroupId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
$editingGroup = $editingGroupId > 0 ? $groupController->getGroupDetails($editingGroupId) : null;
$editingExerciseIds = [];
$editingMachineIds = [];
if ($editingGroup) {
    $editingExerciseIds = array_map('intval', array_column($editingGroup['exercises'], 'exercise_id'));
    $editingMachineIds = array_map('intval', array_column($editingGroup['machines'], 'machine_id'));
}
?>
<div class="app-card">
    <?php if (!empty($msg)): ?>
        <div class="alert alert-info mb-4" role="alert"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <form method="GET" class="row g-3 align-items-end mb-0">
        <div class="col-12 col-md-6 col-lg-4">
            <label for="search" class="form-label">Search exercise groups</label>
            <input type="text" id="search" name="search" class="form-control" placeholder="Group title" value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-12 col-md-3 col-lg-2">
            <button class="btn btn-primary w-100">Search</button>
        </div>
    </form>
</div>

<div class="app-card">
    <h5 class="mb-3"><?= $editingGroup ? 'Edit Exercise Group' : 'Add Exercise Group' ?></h5>
    <form method="POST" class="d-flex flex-column gap-3">
        <input type="hidden" name="action" value="<?= $editingGroup ? 'edit_group' : 'add_group' ?>">
        <?php if ($editingGroup): ?>
            <input type="hidden" name="id" value="<?= (int) $editingGroup['id'] ?>">
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-12 col-md-8">
                <label for="title" class="form-label">Group title</label>
                <input type="text" id="title" name="title" class="form-control" placeholder="e.g. Neck Pain" value="<?= htmlspecialchars($editingGroup['title'] ?? '') ?>" required>
            </div>
            <div class="col-12 col-md-4">
                <label for="is_active" class="form-label">Status</label>
                <select id="is_active" name="is_active" class="form-select" required>
                    <?php $activeValue = (string) ($editingGroup['is_active'] ?? 1); ?>
                    <option value="1" <?= $activeValue === '1' ? 'selected' : '' ?>>Active</option>
                    <option value="0" <?= $activeValue === '0' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
        </div>

        <div>
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="mb-0">Exercises</h6>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addGroupExercise()">+ Add Exercise</button>
            </div>
            <div id="groupExerciseContainer" class="mt-3 d-flex flex-column gap-2"></div>
        </div>

        <div>
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="mb-0">Machines</h6>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addGroupMachine()">+ Add Machine</button>
            </div>
            <div id="groupMachineContainer" class="mt-3 d-flex flex-column gap-2"></div>
        </div>

        <div class="d-flex gap-2">
            <button class="btn btn-success"><?= $editingGroup ? 'Update Group' : 'Save Group' ?></button>
            <?php if ($editingGroup): ?>
                <a href="<?= htmlspecialchars($pageUrl) ?>" class="btn btn-outline-secondary">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="app-card">
    <h5 class="mb-3">Exercise Groups</h5>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">Title</th>
                    <th scope="col">Exercises</th>
                    <th scope="col">Machines</th>
                    <th scope="col">Status</th>
                    <th scope="col">Created By</th>
                    <th scope="col" class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($groups)): ?>
                    <tr><td colspan="6" class="text-center text-muted">No exercise groups found.</td></tr>
                <?php endif; ?>
                <?php foreach ($groups as $group): ?>
                    <tr>
                        <td><?= htmlspecialchars($group['title']) ?></td>
                        <td><?= (int) $group['exercise_count'] ?></td>
                        <td><?= (int) $group['machine_count'] ?></td>
                        <td>
                            <span class="badge <?= $group['is_active'] ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                <?= $group['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($group['created_by_name'] ?? '—') ?></td>
                        <td class="text-end">
                            <div class="d-flex flex-wrap justify-content-end gap-2">
                                <a class="btn btn-sm btn-info" href="<?= htmlspecialchars($pageUrl) ?>?edit=<?= (int) $group['id'] ?>&search=<?= urlencode($search) ?>">Edit</a>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="toggle_group">
                                    <input type="hidden" name="id" value="<?= (int) $group['id'] ?>">
                                    <button class="btn btn-sm <?= $group['is_active'] ? 'btn-warning' : 'btn-success' ?>">
                                        <?= $group['is_active'] ? 'Deactivate' : 'Activate' ?>
                                    </button>
                                </form>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this exercise group?');">
                                    <input type="hidden" name="action" value="delete_group">
                                    <input type="hidden" name="id" value="<?= (int) $group['id'] ?>">
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
        <nav class="mt-3" aria-label="Exercise group pagination">
            <ul class="pagination mb-0">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= htmlspecialchars($pageUrl) ?>?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<script>
const groupExerciseMaster = <?= json_encode($exerciseMaster, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const groupMachineMaster = <?= json_encode($machineMaster, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const initialGroupExerciseIds = <?= json_encode($editingExerciseIds) ?>;
const initialGroupMachineIds = <?= json_encode($editingMachineIds) ?>;

document.addEventListener('DOMContentLoaded', () => {
    if (initialGroupExerciseIds.length) {
        initialGroupExerciseIds.forEach(id => addGroupExercise(String(id)));
    } else {
        addGroupExercise();
    }

    if (initialGroupMachineIds.length) {
        initialGroupMachineIds.forEach(id => addGroupMachine(String(id)));
    } else {
        addGroupMachine();
    }
});

function buildOptions(items, selectedValue, placeholder) {
    return `<option value="">${placeholder}</option>` + items.map(item => {
        const selected = String(item.id) === String(selectedValue) ? 'selected' : '';
        return `<option value="${item.id}" ${selected}>${escapeHtml(item.name)}</option>`;
    }).join('');
}

function addGroupExercise(selectedValue = '') {
    const container = document.getElementById('groupExerciseContainer');
    const row = document.createElement('div');
    row.className = 'row g-2 align-items-center group-exercise-row';
    row.innerHTML = `
        <div class="col-10 col-md-11">
            <select name="exercise_ids[]" class="form-select group-exercise-select">
                ${buildOptions(groupExerciseMaster, selectedValue, '-- Select Exercise --')}
            </select>
        </div>
        <div class="col-2 col-md-1">
            <button type="button" class="btn btn-danger btn-sm w-100" onclick="removeGroupRow(this, updateGroupExerciseOptions)">Remove</button>
        </div>
    `;
    container.appendChild(row);
    $(row).find('.group-exercise-select').select2({width: '100%'}).on('change', updateGroupExerciseOptions);
    updateGroupExerciseOptions();
}

function addGroupMachine(selectedValue = '') {
    const container = document.getElementById('groupMachineContainer');
    const row = document.createElement('div');
    row.className = 'row g-2 align-items-center group-machine-row';
    row.innerHTML = `
        <div class="col-10 col-md-11">
            <select name="machine_ids[]" class="form-select group-machine-select">
                ${buildOptions(groupMachineMaster, selectedValue, '-- Select Machine --')}
            </select>
        </div>
        <div class="col-2 col-md-1">
            <button type="button" class="btn btn-danger btn-sm w-100" onclick="removeGroupRow(this, updateGroupMachineOptions)">Remove</button>
        </div>
    `;
    container.appendChild(row);
    $(row).find('.group-machine-select').select2({width: '100%'}).on('change', updateGroupMachineOptions);
    updateGroupMachineOptions();
}

function removeGroupRow(button, callback) {
    const row = button.closest('.row');
    $(row).find('select').each(function () {
        if ($(this).data('select2')) {
            $(this).select2('destroy');
        }
    });
    row.remove();
    callback();
}

function updateGroupExerciseOptions() {
    disableDuplicateOptions('.group-exercise-select');
}

function updateGroupMachineOptions() {
    disableDuplicateOptions('.group-machine-select');
}

function disableDuplicateOptions(selector) {
    const selects = Array.from(document.querySelectorAll(selector));
    const selectedValues = selects.map(select => select.value).filter(Boolean);

    selects.forEach(select => {
        const currentValue = select.value;
        Array.from(select.options).forEach(option => {
            option.disabled = option.value !== '' && option.value !== currentValue && selectedValues.includes(option.value);
        });
        if ($(select).data('select2')) {
            $(select).trigger('change.select2');
        }
    });
}

function escapeHtml(value) {
    return String(value).replace(/[&<>'"]/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        "'": '&#039;',
        '"': '&quot;'
    }[char]));
}
</script>
