<!-- Tabs -->
<ul class="nav nav-tabs mb-3" id="stepTabs" role="tablist">
  <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#step1">Step 1: Patient Information</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#step2">Step 2: Emergency Information</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#step3">Step 3: Referral</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#step4">Step 4: Assessment</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#step5">Step 5: Patient History</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#step6">Step 6: Files</a></li>
</ul>

<div class="tab-content border p-3 bg-light">
  <!-- Step 1 -->
  <div class="tab-pane fade show active" id="step1">
    <div class="row g-3">
      <div class="col-md-6"><label>First Name *</label>
        <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($patient['first_name'] ?? '') ?>" required>
      </div>
      <div class="col-md-6"><label>Last Name *</label>
        <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($patient['last_name'] ?? '') ?>" required>
       </div>
      <div class="col-md-4"><label>DOB *</label>
        <input type="date" name="date_of_birth" class="form-control" value="<?= htmlspecialchars($patient['date_of_birth'] ?? '') ?>" required>
        </div>

        <div class="col-md-4"><label>Gender *</label>
        <select name="gender" class="form-select" required>
            <option value="">Select</option>
            <?php foreach (['Male','Female','Other'] as $g): ?>
            <option value="<?= $g ?>" <?= isset($patient['gender']) && $patient['gender'] === $g ? 'selected' : '' ?>><?= $g ?></option>
            <?php endforeach; ?>
        </select>
        </div>

      <div class="col-md-4"><label>Contact Number *</label>
        <input type="tel" name="contact_number" pattern="[0-9]{10}" minlength="10" maxlength="10" inputmode="numeric" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0,10)" class="form-control" required value="<?= htmlspecialchars($patient['contact_number'] ?? '') ?>">
        </div>
      <div class="col-md-6"><label>Email</label>
        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($patient['email'] ?? '') ?>">
        </div>
      <div class="col-md-6"><label>Address</label>
        <textarea name="address" class="form-control"><?= htmlspecialchars($patient['address'] ?? '') ?></textarea>
      </div>
    </div>
  </div>

  <!-- Step 2 -->
  <div class="tab-pane fade" id="step2">
    <div class="row g-3">
      <div class="col-md-6"><label>Emergency Contact Name *</label>
        <input type="text" name="emergency_contact_name" class="form-control" value="<?= htmlspecialchars($patient['emergency_contact_name'] ?? '') ?>" required>
        </div>

        <div class="col-md-6"><label>Emergency Contact Number *</label>
        <input type="tel" name="emergency_contact_number" pattern="[0-9]{10}" minlength="10" maxlength="10" inputmode="numeric" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0,10)" class="form-control" value="<?= htmlspecialchars($patient['emergency_contact_number'] ?? '') ?>" required>
        </div>
    </div>
  </div>

  <!-- Step 3 -->
  <div class="tab-pane fade" id="step3">
    <?php
      $referralNames = array_map(function ($referral) {
        return $referral['name'];
      }, $referrals ?? []);
      $currentReferral = $patient['referral_source'] ?? '';
      $useReferralOther = $currentReferral !== '' && !in_array($currentReferral, $referralNames, true);
    ?>
    <div class="row g-3">
      <div class="col-md-6"><label>Referral Source</label>
        <select name="referral_source" class="form-select">
          <option value="">Select</option>
          <?php foreach ($referrals as $r): ?>
            <option value="<?= htmlspecialchars($r['name']) ?>" <?= isset($patient['referral_source']) && $patient['referral_source'] == $r['name'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($r['name']) ?>
            </option>
          <?php endforeach; ?>
          <option value="__other__" <?= $useReferralOther ? 'selected' : '' ?>>Others</option>
        </select>
      </div>
      <div class="col-md-6 d-none" id="referralOtherFields">
        <label>Referral Name</label>
        <input type="text" name="referral_source_other_name" class="form-control" value="<?= $useReferralOther ? htmlspecialchars($currentReferral) : '' ?>">
      </div>
      <div class="col-md-6 d-none" id="referralOtherTypeField">
        <label>Referral Type</label>
        <select name="referral_source_other_type" class="form-select">
          <option value="">Select type</option>
          <?php foreach (['Doctor', 'Hospital', 'Person', 'Other'] as $referralType): ?>
            <option value="<?= $referralType ?>"><?= $referralType ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>
  <!-- Step 4 -->
  <div class="tab-pane fade" id="step4">
    <?php
      $assessmentSections = $assessmentSections ?? [];
      $patientAssessmentData = $patientAssessmentData ?? ['assessment' => null, 'answers' => []];
      $assessmentAnswers = $_POST['answers'] ?? ($patientAssessmentData['answers'] ?? []);
      $selectedAssessmentSectionIds = array_map('intval', $_POST['assessment_section_ids'] ?? []);

      if (empty($selectedAssessmentSectionIds) && !empty($assessmentAnswers)) {
          foreach ($assessmentSections as $assessmentSection) {
              foreach ($assessmentSection['items'] ?? [] as $assessmentItem) {
                  if (array_key_exists((int) $assessmentItem['id'], $assessmentAnswers)) {
                      $selectedAssessmentSectionIds[] = (int) $assessmentSection['id'];
                      break;
                  }
              }
          }
          $selectedAssessmentSectionIds = array_values(array_unique($selectedAssessmentSectionIds));
      }

      $assessmentDateValue = $_POST['assessment_date']
          ?? ($patientAssessmentData['assessment']['assessment_date'] ?? date('Y-m-d'));
      $assessmentNotesValue = $_POST['assessment_notes']
          ?? ($patientAssessmentData['assessment']['notes'] ?? '');
    ?>
    <input type="hidden" name="assessment_capture" value="1">
    <div class="row g-3">
      <div class="col-12 col-md-4">
        <label class="form-label" for="assessment_date">Assessment date</label>
        <input class="form-control" type="date" id="assessment_date" name="assessment_date" value="<?= htmlspecialchars($assessmentDateValue) ?>">
      </div>
      <div class="col-12">
        <label class="form-label">Select assessment sections</label>
        <?php if (!empty($assessmentSections)): ?>
          <div class="assessment-section-picker">
            <?php foreach ($assessmentSections as $assessmentSection): ?>
              <?php
                $sectionId = (int) $assessmentSection['id'];
                $sectionChecked = in_array($sectionId, $selectedAssessmentSectionIds, true);
              ?>
              <label class="assessment-section-choice">
                <input class="form-check-input assessment-section-toggle" type="checkbox" name="assessment_section_ids[]" value="<?= $sectionId ?>" data-section-id="<?= $sectionId ?>" <?= $sectionChecked ? 'checked' : '' ?>>
                <span><?= htmlspecialchars($assessmentSection['title']) ?></span>
              </label>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="text-muted">No active assessment sections have been configured yet.</div>
        <?php endif; ?>
      </div>

      <?php foreach ($assessmentSections as $assessmentSection): ?>
        <?php
          $sectionId = (int) $assessmentSection['id'];
          $sectionChecked = in_array($sectionId, $selectedAssessmentSectionIds, true);
        ?>
        <div class="col-12 assessment-section-panel<?= $sectionChecked ? '' : ' d-none' ?>" data-section-panel="<?= $sectionId ?>">
          <div class="border rounded bg-white p-3">
            <h6 class="mb-1"><?= htmlspecialchars($assessmentSection['title']) ?></h6>
            <?php if (!empty($assessmentSection['description'])): ?>
              <div class="text-muted small mb-3"><?= htmlspecialchars($assessmentSection['description']) ?></div>
            <?php endif; ?>

            <div class="assessment-answer-list">
              <?php foreach ($assessmentSection['items'] ?? [] as $assessmentItem): ?>
                <?php
                  $itemId = (int) $assessmentItem['id'];
                  $answerName = 'answers[' . $itemId . ']';
                  $answerValue = (string) ($assessmentAnswers[$itemId] ?? '');
                  $answerId = 'assessment_answer_' . $itemId;
                ?>
                <div class="assessment-answer-row">
                  <label class="assessment-answer-label" for="<?= $answerId ?>">
                    <?= htmlspecialchars($assessmentItem['label']) ?>
                    <span class="badge bg-light text-dark"><?= $assessmentItem['item_type'] === 'examination' ? 'Exam' : 'Question' ?></span>
                  </label>
                  <div class="assessment-answer-control">
                    <?php if (($assessmentItem['item_type'] ?? '') === 'question' && ($assessmentItem['input_type'] ?? '') === 'yes_no'): ?>
                      <div class="assessment-radio-group">
                        <?php foreach (['Yes', 'No'] as $yesNoOption): ?>
                          <?php $radioId = $answerId . '_' . strtolower($yesNoOption); ?>
                          <div class="form-check form-check-inline mb-0">
                            <input class="form-check-input" type="radio" id="<?= $radioId ?>" name="<?= htmlspecialchars($answerName) ?>" value="<?= $yesNoOption ?>" <?= $answerValue === $yesNoOption ? 'checked' : '' ?>>
                            <label class="form-check-label" for="<?= $radioId ?>"><?= $yesNoOption ?></label>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    <?php elseif (($assessmentItem['item_type'] ?? '') === 'question' && ($assessmentItem['input_type'] ?? '') === 'textarea'): ?>
                      <div class="patient-voice-control">
                        <textarea id="<?= $answerId ?>" name="<?= htmlspecialchars($answerName) ?>" class="form-control assessment-answer-textarea"><?= htmlspecialchars($answerValue) ?></textarea>
                        <button type="button" class="btn btn-outline-primary patient-voice-button" data-voice-target="<?= $answerId ?>" aria-label="Start voice typing for assessment answer" title="Start voice typing">
                          <span class="patient-voice-icon patient-voice-icon-mic" aria-hidden="true"></span>
                          <span class="patient-voice-icon patient-voice-icon-stop" aria-hidden="true"></span>
                        </button>
                      </div>
                    <?php elseif (($assessmentItem['item_type'] ?? '') === 'question' && ($assessmentItem['input_type'] ?? '') === 'number'): ?>
                      <input class="form-control" type="number" step="0.01" id="<?= $answerId ?>" name="<?= htmlspecialchars($answerName) ?>" value="<?= htmlspecialchars($answerValue) ?>">
                    <?php elseif (($assessmentItem['item_type'] ?? '') === 'question' && ($assessmentItem['input_type'] ?? '') === 'select'): ?>
                      <select class="form-select" id="<?= $answerId ?>" name="<?= htmlspecialchars($answerName) ?>">
                        <option value="">Select</option>
                        <?php foreach (array_filter(array_map('trim', explode(',', (string) ($assessmentItem['option_values'] ?? '')))) as $assessmentOption): ?>
                          <option value="<?= htmlspecialchars($assessmentOption) ?>" <?= $answerValue === $assessmentOption ? 'selected' : '' ?>><?= htmlspecialchars($assessmentOption) ?></option>
                        <?php endforeach; ?>
                      </select>
                    <?php else: ?>
                      <div class="patient-voice-control">
                        <input class="form-control" type="text" id="<?= $answerId ?>" name="<?= htmlspecialchars($answerName) ?>" value="<?= htmlspecialchars($answerValue) ?>">
                        <button type="button" class="btn btn-outline-primary patient-voice-button" data-voice-target="<?= $answerId ?>" aria-label="Start voice typing for assessment answer" title="Start voice typing">
                          <span class="patient-voice-icon patient-voice-icon-mic" aria-hidden="true"></span>
                          <span class="patient-voice-icon patient-voice-icon-stop" aria-hidden="true"></span>
                        </button>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
              <?php if (empty($assessmentSection['items'])): ?>
                <div class="text-muted">No active questions configured for this section.</div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>

      <div class="col-12 patient-voice-field">
        <label class="form-label" for="assessment_notes">Assessment notes</label>
        <div class="patient-voice-control">
          <textarea id="assessment_notes" name="assessment_notes" class="form-control patient-history-textarea"><?= htmlspecialchars($assessmentNotesValue) ?></textarea>
          <button type="button" class="btn btn-outline-primary patient-voice-button" data-voice-target="assessment_notes" aria-label="Start voice typing for Assessment notes" title="Start voice typing">
            <span class="patient-voice-icon patient-voice-icon-mic" aria-hidden="true"></span>
            <span class="patient-voice-icon patient-voice-icon-stop" aria-hidden="true"></span>
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Step 5 -->
  <div class="tab-pane fade" id="step5">
    <div class="row g-3">
      <div class="col-12 patient-voice-field">
        <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
          <label class="mb-0" for="allergy_medicines_in_use">Allergy - medicines in use</label>
        </div>
        <div class="patient-voice-control">
          <textarea id="allergy_medicines_in_use" name="allergy_medicines_in_use" class="form-control patient-history-textarea"><?= htmlspecialchars($patient['allergy_medicines_in_use'] ?? '') ?></textarea>
          <button type="button" class="btn btn-outline-primary patient-voice-button" data-voice-target="allergy_medicines_in_use" aria-label="Start voice typing for Allergy - medicines in use" title="Start voice typing">
            <span class="patient-voice-icon patient-voice-icon-mic" aria-hidden="true"></span>
            <span class="patient-voice-icon patient-voice-icon-stop" aria-hidden="true"></span>
          </button>
        </div>
      </div>
      <div class="col-12 patient-voice-field">
        <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
          <label class="mb-0" for="family_history">Family history</label>
        </div>
        <div class="patient-voice-control">
          <textarea id="family_history" name="family_history" class="form-control patient-history-textarea"><?= htmlspecialchars($patient['family_history'] ?? '') ?></textarea>
          <button type="button" class="btn btn-outline-primary patient-voice-button" data-voice-target="family_history" aria-label="Start voice typing for Family history" title="Start voice typing">
            <span class="patient-voice-icon patient-voice-icon-mic" aria-hidden="true"></span>
            <span class="patient-voice-icon patient-voice-icon-stop" aria-hidden="true"></span>
          </button>
        </div>
      </div>
      <div class="col-12 patient-voice-field">
        <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
          <label class="mb-0" for="history">History</label>
        </div>
        <div class="patient-voice-control">
          <textarea id="history" name="history" class="form-control patient-history-textarea"><?= htmlspecialchars($patient['history'] ?? '') ?></textarea>
          <button type="button" class="btn btn-outline-primary patient-voice-button" data-voice-target="history" aria-label="Start voice typing for History" title="Start voice typing">
            <span class="patient-voice-icon patient-voice-icon-mic" aria-hidden="true"></span>
            <span class="patient-voice-icon patient-voice-icon-stop" aria-hidden="true"></span>
          </button>
        </div>
      </div>
      <div class="col-12 patient-voice-field">
        <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
          <label class="mb-0" for="chief_complaints">Chief Complaints</label>
        </div>
        <div class="patient-voice-control">
          <textarea id="chief_complaints" name="chief_complaints" class="form-control patient-history-textarea"><?= htmlspecialchars($patient['chief_complaints'] ?? '') ?></textarea>
          <button type="button" class="btn btn-outline-primary patient-voice-button" data-voice-target="chief_complaints" aria-label="Start voice typing for Chief Complaints" title="Start voice typing">
            <span class="patient-voice-icon patient-voice-icon-mic" aria-hidden="true"></span>
            <span class="patient-voice-icon patient-voice-icon-stop" aria-hidden="true"></span>
          </button>
        </div>
      </div>
      <div class="col-12 patient-voice-field">
        <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
          <label class="mb-0" for="assessment">Assessment</label>
        </div>
        <div class="patient-voice-control">
          <textarea id="assessment" name="assessment" class="form-control patient-history-textarea"><?= htmlspecialchars($patient['assessment'] ?? '') ?></textarea>
          <button type="button" class="btn btn-outline-primary patient-voice-button" data-voice-target="assessment" aria-label="Start voice typing for Assessment" title="Start voice typing">
            <span class="patient-voice-icon patient-voice-icon-mic" aria-hidden="true"></span>
            <span class="patient-voice-icon patient-voice-icon-stop" aria-hidden="true"></span>
          </button>
        </div>
      </div>
      <div class="col-12 patient-voice-field">
        <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
          <label class="mb-0" for="investigation">Investigation</label>
        </div>
        <div class="patient-voice-control">
          <textarea id="investigation" name="investigation" class="form-control patient-history-textarea"><?= htmlspecialchars($patient['investigation'] ?? '') ?></textarea>
          <button type="button" class="btn btn-outline-primary patient-voice-button" data-voice-target="investigation" aria-label="Start voice typing for Investigation" title="Start voice typing">
            <span class="patient-voice-icon patient-voice-icon-mic" aria-hidden="true"></span>
            <span class="patient-voice-icon patient-voice-icon-stop" aria-hidden="true"></span>
          </button>
        </div>
      </div>
      <div class="col-12 patient-voice-field">
        <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
          <label class="mb-0" for="diagnosis">Diagnosis</label>
        </div>
        <div class="patient-voice-control">
          <textarea id="diagnosis" name="diagnosis" class="form-control patient-history-textarea"><?= htmlspecialchars($patient['diagnosis'] ?? '') ?></textarea>
          <button type="button" class="btn btn-outline-primary patient-voice-button" data-voice-target="diagnosis" aria-label="Start voice typing for Diagnosis" title="Start voice typing">
            <span class="patient-voice-icon patient-voice-icon-mic" aria-hidden="true"></span>
            <span class="patient-voice-icon patient-voice-icon-stop" aria-hidden="true"></span>
          </button>
        </div>
      </div>
      <div class="col-12 patient-voice-field">
        <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
          <label class="mb-0" for="goal">Goal</label>
        </div>
        <div class="patient-voice-control">
          <textarea id="goal" name="goal" class="form-control patient-history-textarea"><?= htmlspecialchars($patient['goal'] ?? '') ?></textarea>
          <button type="button" class="btn btn-outline-primary patient-voice-button" data-voice-target="goal" aria-label="Start voice typing for Goal" title="Start voice typing">
            <span class="patient-voice-icon patient-voice-icon-mic" aria-hidden="true"></span>
            <span class="patient-voice-icon patient-voice-icon-stop" aria-hidden="true"></span>
          </button>
        </div>
      </div>
    </div>
  </div>
  <!-- Step 6 -->
  <div class="tab-pane fade" id="step6">
    <div class="row g-3">
      <div class="col-md-12"><label>Reports Upload (max 5 files)</label>
        <input type="file" id="reports" name="reports[]" class="form-control" multiple>
        <div class="form-text">After choosing files, select the file type for each file in the table below.</div>
      </div>
      <div class="col-md-12 d-none" id="selectedReportsContainer">
        <table class="table table-bordered mt-3 mb-0">
          <thead>
            <tr>
              <th>Selected File</th>
              <th>File Type *</th>
            </tr>
          </thead>
          <tbody id="selectedReportsBody"></tbody>
        </table>
      </div>
      <?php if (!empty($files)): ?>
      <div class="col-md-12">
        <table class="table table-bordered mt-3">
          <thead>
            <tr>
              <th>File Name</th>
              <th>File Type</th>
              <th>Uploaded On</th>
              <th>Download</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($files as $file): ?>
            <tr>
              <td><?= htmlspecialchars($file['file_name']) ?></td>
              <td><?= htmlspecialchars($file['file_type_name'] ?? '—') ?></td>
              <td><?= date('d M Y', strtotime($file['upload_date'])) ?></td>
              <td>
                <a href="<?= BASE_URL ?>/views/shared/download_file.php?patient_id=<?= urlencode($patient['id']) ?>&file=<?= urlencode($file['file_name']) ?>" class="btn btn-sm btn-primary">Download</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<style>
.assessment-section-picker {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.assessment-section-choice {
  align-items: center;
  background-color: #fff;
  border: 1px solid rgba(15, 23, 42, 0.12);
  border-radius: 0.75rem;
  cursor: pointer;
  display: inline-flex;
  gap: 0.5rem;
  min-height: 2.75rem;
  padding: 0.6rem 0.85rem;
}

.assessment-answer-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.assessment-answer-row {
  align-items: center;
  display: grid;
  gap: 1rem;
  grid-template-columns: minmax(220px, 0.9fr) minmax(280px, 1.1fr);
}

.assessment-answer-label {
  align-items: center;
  display: flex;
  flex-wrap: wrap;
  font-weight: 600;
  gap: 0.45rem;
  margin-bottom: 0;
}

.assessment-answer-textarea {
  min-height: 2.75rem;
  resize: vertical;
}

@media (max-width: 767.98px) {
  .assessment-answer-row {
    align-items: stretch;
    grid-template-columns: 1fr;
  }
}

.patient-voice-control {
  align-items: stretch;
  display: flex;
  gap: 0.5rem;
}

.patient-history-textarea {
  min-height: 4.25rem;
  resize: vertical;
}

.patient-voice-button {
  align-items: center;
  border-radius: 0.75rem;
  display: inline-flex;
  flex: 0 0 2.75rem;
  justify-content: center;
  min-height: 2.75rem;
  padding: 0;
}

.patient-voice-icon {
  background-color: currentColor;
  display: inline-block;
  height: 1.15rem;
  width: 1.15rem;
}

.patient-voice-icon-mic {
  -webkit-mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z'/%3E%3Cpath d='M19 10v2a7 7 0 0 1-14 0v-2'/%3E%3Cpath d='M12 19v3'/%3E%3Cpath d='M8 22h8'/%3E%3C/svg%3E") center / contain no-repeat;
  mask: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z'/%3E%3Cpath d='M19 10v2a7 7 0 0 1-14 0v-2'/%3E%3Cpath d='M12 19v3'/%3E%3Cpath d='M8 22h8'/%3E%3C/svg%3E") center / contain no-repeat;
}

.patient-voice-icon-stop {
  border-radius: 0.2rem;
  display: none;
  height: 0.9rem;
  width: 0.9rem;
}

.patient-voice-button.is-listening .patient-voice-icon-mic {
  display: none;
}

.patient-voice-button.is-listening .patient-voice-icon-stop {
  display: inline-block;
}
</style>

<!-- Nav Buttons -->
<div class="mt-4 d-flex justify-content-between">
  <button type="button" class="btn btn-secondary" id="prevBtn">Previous</button>
  <button type="button" class="btn btn-primary" id="nextBtn">Next</button>
  <button type="submit" class="btn btn-success d-none" id="submitBtn">Submit</button>
</div>

<script>
let currentTab = 0;
const tabs = [...document.querySelectorAll('.tab-pane')];
const navLinks = document.querySelectorAll('#stepTabs .nav-link');
const prevBtn = document.getElementById('prevBtn');
const nextBtn = document.getElementById('nextBtn');
const submitBtn = document.getElementById('submitBtn');
const reportsInput = document.getElementById('reports');
const selectedReportsContainer = document.getElementById('selectedReportsContainer');
const selectedReportsBody = document.getElementById('selectedReportsBody');
const reportFileTypes = <?= json_encode($fileTypes ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
const referralSelect = document.querySelector('select[name="referral_source"]');
const referralOtherFields = document.getElementById('referralOtherFields');
const referralOtherTypeField = document.getElementById('referralOtherTypeField');
const referralOtherNameInput = document.querySelector('input[name="referral_source_other_name"]');
const referralOtherTypeSelect = document.querySelector('select[name="referral_source_other_type"]');

function showTab(n) {
  currentTab = n;
  tabs.forEach((t, i) => {
    t.classList.remove('show', 'active');
    navLinks[i].classList.remove('active');
  });
  tabs[n].classList.add('show', 'active');
  navLinks[n].classList.add('active');

  prevBtn.style.display = n === 0 ? 'none' : 'inline-block';
  nextBtn.classList.toggle('d-none', n === tabs.length - 1);
  submitBtn.classList.toggle('d-none', n !== tabs.length - 1);
}

nextBtn.addEventListener('click', () => {
  const inputs = tabs[currentTab].querySelectorAll('input, select, textarea');
  for (let input of inputs) {
    if (!input.checkValidity()) {
      input.reportValidity();
      return;
    }
  }
  if (currentTab < tabs.length - 1) showTab(++currentTab);
});

prevBtn.addEventListener('click', () => {
  if (currentTab > 0) showTab(--currentTab);
});

navLinks.forEach((link, index) => {
  link.addEventListener('click', (event) => {
    event.preventDefault();
    showTab(index);
  });
});

showTab(currentTab);

function escapeHtml(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function renderSelectedReportRows() {
  if (!reportsInput || !selectedReportsContainer || !selectedReportsBody) return;

  selectedReportsBody.innerHTML = '';
  if (!reportsInput.files.length) {
    selectedReportsContainer.classList.add('d-none');
    return;
  }

  selectedReportsContainer.classList.remove('d-none');
  [...reportsInput.files].forEach((file) => {
    const options = reportFileTypes.map((fileType) => {
      return `<option value="${escapeHtml(fileType.id)}">${escapeHtml(fileType.name)}</option>`;
    }).join('');

    const row = document.createElement('tr');
    row.innerHTML = `
      <td>${escapeHtml(file.name)}</td>
      <td>
        <select name="report_file_type_ids[]" class="form-select" required>
          <option value="">Select file type</option>
          ${options}
        </select>
      </td>
    `;
    selectedReportsBody.appendChild(row);
  });
}

if (reportsInput) {
  reportsInput.addEventListener('change', function() {
    if (this.files.length > 5) {
      alert('Maximum 5 files allowed.');
      this.value = '';
    }

    if (this.files.length && !reportFileTypes.length) {
      alert('Please add patient report file types before uploading reports.');
      this.value = '';
    }

    renderSelectedReportRows();
  });
}

function toggleReferralOtherFields() {
  if (!referralSelect) return;
  const showOther = referralSelect.value === '__other__';
  if (referralOtherFields) {
    referralOtherFields.classList.toggle('d-none', !showOther);
  }
  if (referralOtherTypeField) {
    referralOtherTypeField.classList.toggle('d-none', !showOther);
  }
  if (referralOtherNameInput) {
    referralOtherNameInput.required = showOther;
  }
  if (referralOtherTypeSelect) {
    referralOtherTypeSelect.required = showOther;
  }
}

if (referralSelect) {
  referralSelect.addEventListener('change', toggleReferralOtherFields);
  toggleReferralOtherFields();
}

function syncAssessmentSections() {
  document.querySelectorAll('.assessment-section-panel').forEach((panel) => {
    const sectionId = panel.getAttribute('data-section-panel');
    const toggle = document.querySelector(`.assessment-section-toggle[data-section-id="${sectionId}"]`);
    const isSelected = Boolean(toggle && toggle.checked);

    panel.classList.toggle('d-none', !isSelected);
    panel.querySelectorAll('input, select, textarea, button.patient-voice-button').forEach((input) => {
      input.disabled = !isSelected;
    });
  });
}

document.querySelectorAll('.assessment-section-toggle').forEach((toggle) => {
  toggle.addEventListener('change', syncAssessmentSections);
});
syncAssessmentSections();

const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
const voiceButtons = document.querySelectorAll('.patient-voice-button');
let activeRecognition = null;
let activeVoiceButton = null;
let shouldKeepListening = false;
let activeVoiceBaseText = '';
let activeVoiceFinalText = '';

function joinVoiceText(...parts) {
  return parts
    .map((part) => part.trim())
    .filter(Boolean)
    .join(' ');
}

function updateVoiceText(textarea, interimText = '') {
  textarea.value = joinVoiceText(activeVoiceBaseText, activeVoiceFinalText, interimText);
  textarea.dispatchEvent(new Event('input', { bubbles: true }));
  textarea.focus();
}

function resetVoiceButton(button) {
  if (!button) return;
  button.classList.remove('is-listening');
  button.classList.remove('btn-danger');
  button.classList.add('btn-outline-primary');
  button.setAttribute('aria-label', button.dataset.startLabel || 'Start voice typing');
  button.title = 'Start voice typing';
  button.disabled = false;
}

if (!SpeechRecognition) {
  voiceButtons.forEach((button) => {
    button.disabled = true;
    button.title = 'Voice typing is not supported in this browser. Please use Chrome or Edge.';
  });
} else {
  voiceButtons.forEach((button) => {
    button.dataset.startLabel = button.getAttribute('aria-label') || 'Start voice typing';

    button.addEventListener('click', () => {
      const textarea = document.getElementById(button.dataset.voiceTarget);
      if (!textarea) return;

      if (activeRecognition) {
        shouldKeepListening = false;
        activeRecognition.stop();
        return;
      }

      const recognition = new SpeechRecognition();
      activeRecognition = recognition;
      activeVoiceButton = button;
      shouldKeepListening = true;
      activeVoiceBaseText = textarea.value.trim();
      activeVoiceFinalText = '';

      recognition.lang = 'en-IN';
      recognition.continuous = true;
      recognition.interimResults = true;

      voiceButtons.forEach((voiceButton) => {
        voiceButton.disabled = voiceButton !== button;
      });
      button.disabled = false;
      button.classList.add('is-listening');
      button.classList.remove('btn-outline-primary');
      button.classList.add('btn-danger');
      button.setAttribute('aria-label', 'Stop voice typing');
      button.title = 'Stop voice typing';

      recognition.onresult = (event) => {
        let interimText = '';

        for (let i = event.resultIndex; i < event.results.length; i++) {
          const transcript = event.results[i][0].transcript;
          if (event.results[i].isFinal) {
            activeVoiceFinalText = joinVoiceText(activeVoiceFinalText, transcript);
          } else {
            interimText = joinVoiceText(interimText, transcript);
          }
        }

        updateVoiceText(textarea, interimText);
      };

      recognition.onerror = (event) => {
        if (event.error === 'not-allowed' || event.error === 'service-not-allowed') {
          shouldKeepListening = false;
          alert('Microphone access is blocked. Please allow microphone permission and try again.');
        }
      };

      recognition.onend = () => {
        if (shouldKeepListening && activeRecognition === recognition) {
          activeVoiceBaseText = textarea.value.trim();
          activeVoiceFinalText = '';
          recognition.start();
          return;
        }

        resetVoiceButton(button);
        voiceButtons.forEach((voiceButton) => {
          voiceButton.disabled = false;
        });
        syncAssessmentSections();
        activeRecognition = null;
        activeVoiceButton = null;
        shouldKeepListening = false;
        activeVoiceBaseText = '';
        activeVoiceFinalText = '';
      };

      try {
        recognition.start();
      } catch (error) {
        shouldKeepListening = false;
        resetVoiceButton(button);
        voiceButtons.forEach((voiceButton) => {
          voiceButton.disabled = false;
        });
        syncAssessmentSections();
        activeRecognition = null;
        activeVoiceButton = null;
        activeVoiceBaseText = '';
        activeVoiceFinalText = '';
        alert('Voice typing could not be started. Please allow microphone access and try again.');
      }
    });
  });
}
</script>
