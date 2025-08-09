<!-- Tabs -->
<ul class="nav nav-tabs mb-3" id="stepTabs" role="tablist">
  <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#step1">Step 1</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#step2">Step 2</a></li>
  <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#step3">Step 3</a></li>
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
        <input type="tel" name="contact_number" pattern="[0-9]{10}" maxlength="10" class="form-control" required value="<?= htmlspecialchars($patient['contact_number'] ?? '') ?>">
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
        <input type="tel" name="emergency_contact_number" pattern="[0-9]{10}" maxlength="10" class="form-control" value="<?= htmlspecialchars($patient['emergency_contact_number'] ?? '') ?>" required>
        </div>
      <div class="col-md-6"><label>Medical History</label>
        <textarea name="medical_history" class="form-control"><?= htmlspecialchars($patient['medical_history'] ?? '') ?></textarea>
      </div>
      <div class="col-md-6"><label>Allergies</label>
        <textarea name="allergies" class="form-control"><?= htmlspecialchars($patient['allergies'] ?? '') ?></textarea>
      </div>
    </div>
  </div>

  <!-- Step 3 -->
  <div class="tab-pane fade" id="step3">
    <div class="row g-3">
      <div class="col-md-6"><label>Referral Source</label>
        <select name="referral_source" class="form-select">
          <option value="">Select</option>
          <?php foreach ($referrals as $r): ?>
            <option value="<?= htmlspecialchars($r['name']) ?>" <?= isset($patient['referral_source']) && $patient['referral_source'] == $r['name'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($r['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>
</div>

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

function showTab(n) {
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

showTab(currentTab);
</script>
