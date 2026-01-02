<?php
require_once '../../includes/session.php';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../controllers/TreatmentController.php';

requireRole('Patient', 'login.php');

$patientId = (int) ($_SESSION['patient_id'] ?? $_SESSION['user_id'] ?? 0);
$episodeIdFilter = (int) ($_GET['episode_id'] ?? 0);

$treatmentController = new TreatmentController($pdo);

$episodeQuery = "SELECT id, start_date, status, initial_complaints FROM treatment_episodes WHERE patient_id = ?";
$episodeParams = [$patientId];

if ($episodeIdFilter > 0) {
    $episodeQuery .= " AND id = ?";
    $episodeParams[] = $episodeIdFilter;
}

$episodeQuery .= " ORDER BY start_date DESC, id DESC";
$episodeStmt = $pdo->prepare($episodeQuery);
$episodeStmt->execute($episodeParams);
$episodes = $episodeStmt->fetchAll(PDO::FETCH_ASSOC);

$sessionsByYear = [];

foreach ($episodes as $episode) {
    $episodeId = (int) $episode['id'];
    $episodeSessions = $treatmentController->getSessionsWithDetails($patientId, $episodeId);

    foreach ($episodeSessions as $session) {
        $dateObj = DateTime::createFromFormat('Y-m-d', $session['session_date']);
        if (!$dateObj) {
            continue;
        }

        $yearKey = $dateObj->format('Y');
        $monthKey = $dateObj->format('Y-m');
        $dayKey = $dateObj->format('Y-m-d');

        if (!isset($sessionsByYear[$yearKey])) {
            $sessionsByYear[$yearKey] = [
                'label' => $yearKey,
                'months' => [],
            ];
        }

        if (!isset($sessionsByYear[$yearKey]['months'][$monthKey])) {
            $sessionsByYear[$yearKey]['months'][$monthKey] = [
                'label' => $dateObj->format('F Y'),
                'days' => [],
            ];
        }

        if (!isset($sessionsByYear[$yearKey]['months'][$monthKey]['days'][$dayKey])) {
            $sessionsByYear[$yearKey]['months'][$monthKey]['days'][$dayKey] = [
                'label' => $dateObj->format('j M Y'),
                'sessions' => [],
            ];
        }

        $sessionsByYear[$yearKey]['months'][$monthKey]['days'][$dayKey]['sessions'][] = $session + [
            'episode' => [
                'id' => $episodeId,
                'status' => $episode['status'] ?? 'Active',
                'start_date' => $episode['start_date'] ?? null,
                'initial_complaints' => $episode['initial_complaints'] ?? '',
            ],
        ];
    }
}

krsort($sessionsByYear);
foreach ($sessionsByYear as &$yearData) {
    krsort($yearData['months']);
    foreach ($yearData['months'] as &$monthData) {
        krsort($monthData['days']);
    }
    unset($monthData);
}
unset($yearData);

include '../../includes/header.php';
?>
<style>
  .prev-session-year .accordion-button {
    background-color: #e3f2fd;
  }

  .prev-session-month .accordion-button {
    background-color: #e8f5e9;
  }

  .prev-session-day .accordion-button {
    background-color: #fff3e0;
  }

  .nested-accordion {
    margin-left: 1rem;
  }

  .session-detail-card {
    background-color: #f9fbfd;
  }
</style>
<div class="workspace-layout">
  <?php include '../../layouts/patient_sidebar.php'; ?>
  <div class="workspace-content">
    <div class="workspace-page-header">
      <div>
        <h1 class="workspace-page-title">Episodes History</h1>
        <p class="workspace-page-subtitle">
          <?= $episodeIdFilter > 0 ? 'Review your selected episode sessions.' : 'Browse all your treatment sessions grouped by year, month, and date.' ?>
        </p>
      </div>
      <div class="d-flex gap-2">
        <a href="history.php" class="btn btn-outline-secondary">Back to Episodes</a>
      </div>
    </div>

    <?php if (empty($sessionsByYear)): ?>
      <div class="alert alert-info">No session history available yet.</div>
    <?php else: ?>
      <div class="app-card">
        <h5 class="mb-3">Previous Sessions</h5>
        <div class="accordion" id="patientSessionsAccordion">
          <?php $yearIndex = 0; foreach ($sessionsByYear as $yearData): $yearIndex++; ?>
            <div class="accordion-item prev-session-year">
              <h2 class="accordion-header" id="yearHeading<?= $yearIndex ?>">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#yearCollapse<?= $yearIndex ?>" aria-expanded="false" aria-controls="yearCollapse<?= $yearIndex ?>">
                  <?= htmlspecialchars($yearData['label']) ?>
                </button>
              </h2>
              <div id="yearCollapse<?= $yearIndex ?>" class="accordion-collapse collapse" aria-labelledby="yearHeading<?= $yearIndex ?>" data-bs-parent="#patientSessionsAccordion">
                <div class="accordion-body">
                  <div class="accordion nested-accordion" id="monthAccordion<?= $yearIndex ?>">
                    <?php $monthIndex = 0; foreach ($yearData['months'] as $monthData): $monthIndex++; ?>
                      <div class="accordion-item prev-session-month">
                        <h2 class="accordion-header" id="monthHeading<?= $yearIndex ?>_<?= $monthIndex ?>">
                          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#monthCollapse<?= $yearIndex ?>_<?= $monthIndex ?>" aria-expanded="false" aria-controls="monthCollapse<?= $yearIndex ?>_<?= $monthIndex ?>">
                            <?= htmlspecialchars($monthData['label']) ?>
                          </button>
                        </h2>
                        <div id="monthCollapse<?= $yearIndex ?>_<?= $monthIndex ?>" class="accordion-collapse collapse" aria-labelledby="monthHeading<?= $yearIndex ?>_<?= $monthIndex ?>" data-bs-parent="#monthAccordion<?= $yearIndex ?>">
                          <div class="accordion-body">
                            <div class="accordion nested-accordion" id="dayAccordion<?= $yearIndex ?>_<?= $monthIndex ?>">
                              <?php $dayIndex = 0; foreach ($monthData['days'] as $dayData): $dayIndex++; ?>
                                <div class="accordion-item prev-session-day">
                                  <h2 class="accordion-header" id="dayHeading<?= $yearIndex ?>_<?= $monthIndex ?>_<?= $dayIndex ?>">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#dayCollapse<?= $yearIndex ?>_<?= $monthIndex ?>_<?= $dayIndex ?>" aria-expanded="false" aria-controls="dayCollapse<?= $yearIndex ?>_<?= $monthIndex ?>_<?= $dayIndex ?>">
                                      <?= htmlspecialchars($dayData['label']) ?>
                                    </button>
                                  </h2>
                                  <div id="dayCollapse<?= $yearIndex ?>_<?= $monthIndex ?>_<?= $dayIndex ?>" class="accordion-collapse collapse" aria-labelledby="dayHeading<?= $yearIndex ?>_<?= $monthIndex ?>_<?= $dayIndex ?>" data-bs-parent="#dayAccordion<?= $yearIndex ?>_<?= $monthIndex ?>">
                                    <div class="accordion-body">
                                      <?php foreach ($dayData['sessions'] as $session): ?>
                                        <div class="session-detail-card border rounded p-3 mb-3">
                                          <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
                                            <div>
                                              <div class="fw-semibold">
                                                Doctor: <?= htmlspecialchars($session['primary_therapist_name'] ?? 'Not assigned') ?>
                                              </div>
                                              <?php if (!empty($session['secondary_therapist_name'])): ?>
                                                <div class="text-muted small">Assistant: <?= htmlspecialchars($session['secondary_therapist_name']) ?></div>
                                              <?php endif; ?>
                                            </div>
                                            <?php if (!empty($session['amount'])): ?>
                                              <span class="badge bg-primary-subtle text-primary">Charge: R <?= htmlspecialchars(number_format((float) $session['amount'], 2)) ?></span>
                                            <?php else: ?>
                                              <span class="badge bg-light text-muted">Charge not recorded</span>
                                            <?php endif; ?>
                                          </div>

                                          <?php if (!empty($session['exercises'])): ?>
                                            <div class="mb-3">
                                              <p class="fw-semibold mb-2">Exercises</p>
                                              <div class="table-responsive">
                                                <table class="table table-sm mb-0">
                                                  <thead class="table-light">
                                                    <tr>
                                                      <th scope="col">Exercise</th>
                                                      <th scope="col">Reps</th>
                                                      <th scope="col">Duration (mins)</th>
                                                    </tr>
                                                  </thead>
                                                  <tbody>
                                                    <?php foreach ($session['exercises'] as $exercise): ?>
                                                      <tr>
                                                        <td><?= htmlspecialchars($exercise['name']) ?></td>
                                                        <td><?= htmlspecialchars($exercise['reps'] ?? '-') ?></td>
                                                        <td><?= htmlspecialchars($exercise['duration_minutes'] ?? '-') ?></td>
                                                      </tr>
                                                    <?php endforeach; ?>
                                                  </tbody>
                                                </table>
                                              </div>
                                            </div>
                                          <?php endif; ?>

                                          <?php if (!empty($session['machines'])): ?>
                                            <div class="mb-0">
                                              <p class="fw-semibold mb-2">Machines</p>
                                              <div class="table-responsive">
                                                <table class="table table-sm mb-0">
                                                  <thead class="table-light">
                                                    <tr>
                                                      <th scope="col">Machine</th>
                                                      <th scope="col">Duration (mins)</th>
                                                    </tr>
                                                  </thead>
                                                  <tbody>
                                                    <?php foreach ($session['machines'] as $machine): ?>
                                                      <tr>
                                                        <td><?= htmlspecialchars($machine['name']) ?></td>
                                                        <td><?= htmlspecialchars($machine['duration_minutes'] ?? '-') ?></td>
                                                      </tr>
                                                    <?php endforeach; ?>
                                                  </tbody>
                                                </table>
                                              </div>
                                            </div>
                                          <?php endif; ?>
                                        </div>
                                      <?php endforeach; ?>
                                    </div>
                                  </div>
                                </div>
                              <?php endforeach; ?>
                            </div>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php include '../../includes/footer.php'; ?>
