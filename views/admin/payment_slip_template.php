<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';
require_once '../../includes/functions.php';
requireLogin();
requireRole('Admin');

require_once '../../controllers/PaymentSlipTemplateController.php';

$templateController = new PaymentSlipTemplateController($pdo);
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $message = $templateController->save(
            $_POST,
            $_FILES['template_image'] ?? [],
            (int) ($_SESSION['user_id'] ?? 0)
        );
    } catch (Throwable $e) {
        $message = $e->getMessage();
    }
}

$template = $templateController->getTemplate();
$fields = $templateController->getFields($template);
$fieldLabels = $templateController->getFieldLabels();
$templateUrl = !empty($template['image_path'])
    ? BASE_URL . '/' . ltrim($template['image_path'], '/') . '?v=' . time()
    : '';

include '../../includes/header.php';
?>

<div class="admin-layout">
    <?php include '../../layouts/admin_sidebar.php'; ?>
    <div class="admin-content">
        <div class="admin-page-header">
            <div>
                <h1 class="admin-page-title">Payment Slip Template</h1>
                <p class="admin-page-subtitle">Upload a receipt image and map payment fields by position.</p>
            </div>
        </div>

        <?php if ($message !== ''): ?>
            <div class="alert alert-info mb-0" role="alert"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="paymentSlipTemplateForm" class="payment-template-form">
            <div class="app-card">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-lg-8">
                        <label for="template_image" class="form-label">Template image</label>
                        <input type="file" id="template_image" name="template_image" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" <?= $templateUrl === '' ? 'required' : '' ?>>
                        <div class="form-text">
                            <?= $templateUrl === '' ? 'Upload a JPG, PNG, or WEBP image to start mapping fields.' : 'Leave this blank to keep the current template image.' ?>
                        </div>
                    </div>
                    <div class="col-12 col-lg-4">
                        <button class="btn btn-primary w-100">Save Template</button>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-12 col-xl-8">
                    <div class="app-card payment-template-preview-card">
                        <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
                            <div>
                                <h5 class="mb-1">Template Preview</h5>
                                <div class="text-muted small" id="templateImageMeta">
                                    <?php if (!empty($template['image_width']) && !empty($template['image_height'])): ?>
                                        <?= (int) $template['image_width'] ?> x <?= (int) $template['image_height'] ?> px
                                    <?php else: ?>
                                        No template uploaded yet
                                    <?php endif; ?>
                                </div>
                            </div>
                            <select class="form-select payment-template-field-select" id="activeField">
                                <?php foreach ($fieldLabels as $key => $label): ?>
                                    <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="payment-template-canvas-wrap" id="templateCanvasWrap">
                            <?php if ($templateUrl !== ''): ?>
                                <img src="<?= htmlspecialchars($templateUrl) ?>" alt="Payment slip template" id="templateImage" class="payment-template-image">
                            <?php else: ?>
                                <div class="payment-template-empty" id="templateEmpty">Upload a template image to place fields.</div>
                                <img src="" alt="Payment slip template" id="templateImage" class="payment-template-image d-none">
                            <?php endif; ?>

                            <?php foreach ($fieldLabels as $key => $label): ?>
                                <?php $field = $fields[$key]; ?>
                                <button
                                    type="button"
                                    class="payment-template-marker<?= empty($field['enabled']) ? ' d-none' : '' ?>"
                                    data-field="<?= htmlspecialchars($key) ?>"
                                    style="left: 0; top: 0; width: 120px; height: 28px;"
                                ><?= htmlspecialchars($label) ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-xl-4">
                    <div class="app-card">
                        <h5 class="mb-3">Field Coordinates</h5>
                        <div class="payment-template-fields">
                            <?php foreach ($fieldLabels as $key => $label): ?>
                                <?php $field = $fields[$key]; ?>
                                <div class="payment-template-field border rounded p-3 mb-3" data-field-row="<?= htmlspecialchars($key) ?>">
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input field-enabled" type="checkbox" role="switch" id="enabled_<?= htmlspecialchars($key) ?>" name="fields[<?= htmlspecialchars($key) ?>][enabled]" value="1" <?= !empty($field['enabled']) ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-semibold" for="enabled_<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></label>
                                    </div>

                                    <div class="row g-2">
                                        <div class="col-6">
                                            <label class="form-label small" for="<?= htmlspecialchars($key) ?>_x">X</label>
                                            <input class="form-control form-control-sm field-input" id="<?= htmlspecialchars($key) ?>_x" type="number" step="0.01" min="0" name="fields[<?= htmlspecialchars($key) ?>][x]" data-prop="x" value="<?= htmlspecialchars((string) $field['x']) ?>">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small" for="<?= htmlspecialchars($key) ?>_y">Y</label>
                                            <input class="form-control form-control-sm field-input" id="<?= htmlspecialchars($key) ?>_y" type="number" step="0.01" min="0" name="fields[<?= htmlspecialchars($key) ?>][y]" data-prop="y" value="<?= htmlspecialchars((string) $field['y']) ?>">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small" for="<?= htmlspecialchars($key) ?>_width">Width</label>
                                            <input class="form-control form-control-sm field-input" id="<?= htmlspecialchars($key) ?>_width" type="number" step="0.01" min="20" name="fields[<?= htmlspecialchars($key) ?>][width]" data-prop="width" value="<?= htmlspecialchars((string) $field['width']) ?>">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small" for="<?= htmlspecialchars($key) ?>_height">Height</label>
                                            <input class="form-control form-control-sm field-input" id="<?= htmlspecialchars($key) ?>_height" type="number" step="0.01" min="12" name="fields[<?= htmlspecialchars($key) ?>][height]" data-prop="height" value="<?= htmlspecialchars((string) $field['height']) ?>">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small" for="<?= htmlspecialchars($key) ?>_font">Font</label>
                                            <input class="form-control form-control-sm field-input" id="<?= htmlspecialchars($key) ?>_font" type="number" step="0.01" min="6" name="fields[<?= htmlspecialchars($key) ?>][font_size]" data-prop="font_size" value="<?= htmlspecialchars((string) $field['font_size']) ?>">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small" for="<?= htmlspecialchars($key) ?>_line">Line height</label>
                                            <input class="form-control form-control-sm field-input" id="<?= htmlspecialchars($key) ?>_line" type="number" step="0.01" min="8" name="fields[<?= htmlspecialchars($key) ?>][line_height]" data-prop="line_height" value="<?= htmlspecialchars((string) $field['line_height']) ?>">
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<style>
.payment-template-preview-card {
    overflow: hidden;
}

.payment-template-field-select {
    max-width: 320px;
}

.payment-template-canvas-wrap {
    background-color: #f8fafc;
    border: 1px solid rgba(15, 23, 42, 0.1);
    border-radius: 0.75rem;
    min-height: 320px;
    overflow: auto;
    position: relative;
}

.payment-template-image {
    display: block;
    height: auto;
    max-width: 100%;
    user-select: none;
}

.payment-template-empty {
    align-items: center;
    color: #64748b;
    display: flex;
    font-weight: 600;
    justify-content: center;
    min-height: 320px;
    text-align: center;
}

.payment-template-marker {
    align-items: center;
    background-color: rgba(13, 110, 253, 0.12);
    border: 2px solid #0d6efd;
    border-radius: 0.35rem;
    color: #084298;
    cursor: move;
    display: flex;
    font-size: 12px;
    font-weight: 700;
    justify-content: center;
    line-height: 1.1;
    overflow: hidden;
    padding: 0.1rem 0.25rem;
    position: absolute;
    text-align: center;
    touch-action: none;
    z-index: 2;
}

.payment-template-marker.is-active {
    background-color: rgba(25, 135, 84, 0.16);
    border-color: #198754;
    color: #0f5132;
    z-index: 3;
}

.payment-template-field.is-active {
    border-color: #198754 !important;
    box-shadow: 0 0 0 0.15rem rgba(25, 135, 84, 0.12);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const wrap = document.getElementById('templateCanvasWrap');
    const img = document.getElementById('templateImage');
    const empty = document.getElementById('templateEmpty');
    const upload = document.getElementById('template_image');
    const activeField = document.getElementById('activeField');
    const imageMeta = document.getElementById('templateImageMeta');
    const fields = <?= json_encode($fields, JSON_UNESCAPED_SLASHES) ?>;
    let imageNaturalWidth = <?= (int) ($template['image_width'] ?? 0) ?>;
    let imageNaturalHeight = <?= (int) ($template['image_height'] ?? 0) ?>;
    let dragging = null;

    function getScale() {
        if (!img || !imageNaturalWidth || !img.clientWidth) {
            return 1;
        }

        return img.clientWidth / imageNaturalWidth;
    }

    function setActive(fieldKey) {
        activeField.value = fieldKey;
        document.querySelectorAll('.payment-template-marker').forEach(function (marker) {
            marker.classList.toggle('is-active', marker.dataset.field === fieldKey);
        });
        document.querySelectorAll('.payment-template-field').forEach(function (row) {
            row.classList.toggle('is-active', row.dataset.fieldRow === fieldKey);
        });
    }

    function readField(fieldKey) {
        if (!fields[fieldKey]) {
            fields[fieldKey] = {};
        }

        document.querySelectorAll('[data-field-row="' + fieldKey + '"] .field-input').forEach(function (input) {
            fields[fieldKey][input.dataset.prop] = Number(input.value) || 0;
        });

        fields[fieldKey].enabled = document.getElementById('enabled_' + fieldKey).checked ? 1 : 0;
    }

    function writeField(fieldKey) {
        const field = fields[fieldKey] || {};
        Object.keys(field).forEach(function (prop) {
            const input = document.querySelector('[data-field-row="' + fieldKey + '"] [data-prop="' + prop + '"]');
            if (input) {
                input.value = field[prop];
            }
        });
        const enabled = document.getElementById('enabled_' + fieldKey);
        if (enabled) {
            enabled.checked = Boolean(field.enabled);
        }
    }

    function renderMarker(fieldKey) {
        readField(fieldKey);
        const marker = document.querySelector('[data-field="' + fieldKey + '"]');
        const field = fields[fieldKey] || {};
        const scale = getScale();

        if (!marker) {
            return;
        }

        marker.classList.toggle('d-none', !field.enabled || !imageNaturalWidth);
        marker.style.left = ((field.x || 0) * scale) + 'px';
        marker.style.top = ((field.y || 0) * scale) + 'px';
        marker.style.width = Math.max(20, (field.width || 180) * scale) + 'px';
        marker.style.height = Math.max(12, (field.height || 26) * scale) + 'px';
    }

    function renderAllMarkers() {
        Object.keys(fields).forEach(renderMarker);
    }

    function placeFieldFromEvent(event, fieldKey) {
        if (!imageNaturalWidth) {
            return;
        }

        const rect = img.getBoundingClientRect();
        const scale = getScale();
        const field = fields[fieldKey] || {};
        const rawX = (event.clientX - rect.left) / scale;
        const rawY = (event.clientY - rect.top) / scale;
        const maxX = Math.max(0, imageNaturalWidth - (field.width || 180));
        const maxY = Math.max(0, imageNaturalHeight - (field.height || 26));

        field.x = Number(Math.min(Math.max(0, rawX), maxX).toFixed(2));
        field.y = Number(Math.min(Math.max(0, rawY), maxY).toFixed(2));
        field.enabled = 1;
        fields[fieldKey] = field;
        writeField(fieldKey);
        renderMarker(fieldKey);
        setActive(fieldKey);
    }

    activeField.addEventListener('change', function () {
        setActive(this.value);
    });

    wrap.addEventListener('click', function (event) {
        if (event.target.classList.contains('payment-template-marker')) {
            return;
        }

        placeFieldFromEvent(event, activeField.value);
    });

    document.querySelectorAll('.field-input').forEach(function (input) {
        input.addEventListener('input', function () {
            const row = input.closest('[data-field-row]');
            renderMarker(row.dataset.fieldRow);
        });
    });

    document.querySelectorAll('.field-enabled').forEach(function (input) {
        input.addEventListener('change', function () {
            const row = input.closest('[data-field-row]');
            renderMarker(row.dataset.fieldRow);
        });
    });

    document.querySelectorAll('.payment-template-marker').forEach(function (marker) {
        marker.addEventListener('pointerdown', function (event) {
            const fieldKey = marker.dataset.field;
            setActive(fieldKey);
            marker.setPointerCapture(event.pointerId);
            dragging = {
                fieldKey: fieldKey,
                pointerId: event.pointerId
            };
            event.preventDefault();
        });

        marker.addEventListener('pointermove', function (event) {
            if (!dragging || dragging.fieldKey !== marker.dataset.field) {
                return;
            }

            placeFieldFromEvent(event, dragging.fieldKey);
        });

        marker.addEventListener('pointerup', function () {
            dragging = null;
        });
    });

    if (upload) {
        upload.addEventListener('change', function () {
            const file = upload.files && upload.files[0] ? upload.files[0] : null;
            if (!file) {
                return;
            }

            const url = URL.createObjectURL(file);
            img.onload = function () {
                imageNaturalWidth = img.naturalWidth;
                imageNaturalHeight = img.naturalHeight;
                imageMeta.textContent = imageNaturalWidth + ' x ' + imageNaturalHeight + ' px';
                img.classList.remove('d-none');
                if (empty) {
                    empty.classList.add('d-none');
                }
                renderAllMarkers();
                URL.revokeObjectURL(url);
            };
            img.src = url;
        });
    }

    if (img) {
        if (img.complete && img.naturalWidth) {
            imageNaturalWidth = img.naturalWidth;
            imageNaturalHeight = img.naturalHeight;
            renderAllMarkers();
        } else {
            img.addEventListener('load', function () {
                imageNaturalWidth = img.naturalWidth;
                imageNaturalHeight = img.naturalHeight;
                renderAllMarkers();
            });
        }
    }

    window.addEventListener('resize', renderAllMarkers);
    setActive(activeField.value);
});
</script>

<?php include '../../includes/footer.php'; ?>
