<?php
class TreatmentController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function saveSession($data) {
        try {
            $this->pdo->beginTransaction();

            // Insert treatment session
            $stmt = $this->pdo->prepare("
                INSERT INTO treatment_sessions
                (patient_id, episode_id, session_date, doctor_id, primary_therapist_id, secondary_therapist_id, remarks, progress_notes, advise, additional_treatment_notes)
                VALUES (:patient_id, :episode_id, :session_date, :doctor_id, :primary_therapist_id, :secondary_therapist_id, :remarks, :progress_notes, :advise, :additional_treatment_notes)
            ");
            $stmt->execute([
                'patient_id' => $data['patient_id'],
                'episode_id' => $data['episode_id'],
                'session_date' => $data['session_date'],
                'doctor_id' => $data['doctor_id'],
                'primary_therapist_id' => $data['primary_therapist_id'],
                'secondary_therapist_id' => $data['secondary_therapist_id'],
                'remarks' => $data['remarks'] ?? null,
                'progress_notes' => $data['progress_notes'] ?? null,
                'advise' => $data['advise'] ?? null,
                'additional_treatment_notes' => isset($data['additional_treatment_notes']) && $data['additional_treatment_notes'] !== ''
                    ? $data['additional_treatment_notes']
                    : null
            ]);

            $session_id = (int) $this->pdo->lastInsertId();

            // Insert exercises
            $exerciseIds = $data['exercises']['exercise_id'] ?? [];
            $exerciseReps = $data['exercises']['reps'] ?? [];
            $exerciseDurations = $data['exercises']['duration_minutes'] ?? [];
            $exerciseNotes = $data['exercises']['notes'] ?? [];
            $exerciseNames = $data['exercises']['new_name'] ?? [];

            $exerciseCount = is_array($exerciseIds) ? count($exerciseIds) : 0;

            for ($i = 0; $i < $exerciseCount; $i++) {
                $exerciseId = $exerciseIds[$i];
                if ($exerciseId === 'other') {
                    $name = $exerciseNames[$i] ?? 'Custom Exercise';
                    $reps = $exerciseReps[$i] ?? 0;
                    $dur = $exerciseDurations[$i] ?? 0;
                    $stmtNew = $this->pdo->prepare("INSERT INTO exercises_master (name, default_reps, default_duration_minutes, is_active) VALUES (?, ?, ?, 1)");
                    $stmtNew->execute([$name, $reps, $dur]);
                    $exerciseId = $this->pdo->lastInsertId();
                }

                $stmtEx = $this->pdo->prepare("
                        INSERT INTO treatment_exercises
                        (session_id, exercise_id, exercise_name, reps, duration_minutes, notes)
                        VALUES (:session_id, :exercise_id, :exercise_name, :reps, :duration_minutes, :notes)
                    ");
                $stmtEx->execute([
                        'session_id' => $session_id,
                        'exercise_id' => $exerciseId,
                        'exercise_name' => '',
                        'reps' => $exerciseReps[$i] ?? null,
                        'duration_minutes' =>  $exerciseDurations[$i] ?? null,
                        'notes' =>  $exerciseNotes[$i] ?? null
                    ]);

            }

            // Insert machines
            $machineIds = $data['machines']['machine_id'] ?? [];
            $machineDurations = $data['machines']['duration_minutes'] ?? [];
            $machineNotes = $data['machines']['notes'] ?? [];
            $machineNames = $data['machines']['new_name'] ?? [];

            $machineCount = is_array($machineIds) ? count($machineIds) : 0;

            $machineInsertStmt = $this->pdo->prepare("
                INSERT INTO treatment_machines
                (session_id, machine_id, machine_name, duration_minutes, notes)
                VALUES (:session_id, :machine_id, :machine_name, :duration_minutes, :notes)
            ");

            $machineNameLookupStmt = $this->pdo->prepare("SELECT name FROM machines WHERE id = ?");
            $machineFindByNameStmt = $this->pdo->prepare("SELECT id FROM machines WHERE name = ? LIMIT 1");
            $machineCreateStmt = $this->pdo->prepare("INSERT INTO machines (name, default_duration_minutes) VALUES (?, ?)");

            for ($i = 0; $i < $machineCount; $i++) {
                $rawMachineId = $machineIds[$i] ?? '';
                $machineDuration = $machineDurations[$i] ?? null;
                $machineNote = $machineNotes[$i] ?? null;
                $customName = isset($machineNames[$i]) ? trim((string) $machineNames[$i]) : '';

                if ($rawMachineId === '' && $customName === '') {
                    continue;
                }

                $machineId = null;
                $machineName = '';

                if ($rawMachineId === 'other') {
                    if ($customName === '') {
                        throw new InvalidArgumentException('Machine name is required when selecting Other.');
                    }

                    $machineFindByNameStmt->execute([$customName]);
                    $existing = $machineFindByNameStmt->fetch(PDO::FETCH_ASSOC);
                    if ($existing) {
                        $machineId = (int) $existing['id'];
                    } else {
                        $defaultDuration = is_numeric($machineDuration) ? (int) $machineDuration : 0;
                        $machineCreateStmt->execute([$customName, $defaultDuration]);
                        $machineId = (int) $this->pdo->lastInsertId();
                    }
                    $machineName = $customName;
                } elseif ($rawMachineId !== '') {
                    $machineId = (int) $rawMachineId;
                    $machineNameLookupStmt->execute([$machineId]);
                    $foundName = $machineNameLookupStmt->fetchColumn();
                    $machineName = $foundName !== false ? (string) $foundName : '';
                }

                if ($machineId === null) {
                    continue;
                }

                $machineInsertStmt->execute([
                    'session_id' => $session_id,
                    'machine_id' => $machineId,
                    'machine_name' => $machineName,
                    'duration_minutes' => $machineDuration !== '' ? $machineDuration : null,
                    'notes' => $machineNote,
                ]);
            }

            // Upload file if provided
            if (!empty($data['file']) && $data['file']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = dirname(__DIR__) . '/uploads/patient_docs/' . $data['patient_id'] . '/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $original = $data['file']['name'];
                $safeName = time() . '_' . preg_replace('/[^A-Za-z0-9.\-_]/', '_', $original);
                move_uploaded_file($data['file']['tmp_name'], $uploadDir . $safeName);
                $stmtFile = $this->pdo->prepare("INSERT INTO file_master (patient_id, file_name, upload_date) VALUES (:pid, :fname, NOW())");
                $stmtFile->execute([':pid' => $data['patient_id'], ':fname' => $safeName]);
            }
            /*if (!empty($data['exercises'])) {
                foreach ($data['exercises'] as $exercise) {
                    $stmtEx = $this->pdo->prepare("
                        INSERT INTO treatment_exercises 
                        (session_id, exercise_id, exercise_name, reps, duration_minutes, notes)
                        VALUES (:session_id, :exercise_id, :exercise_name, :reps, :duration_minutes, :notes)
                    ");
                    $stmtEx->execute([
                        'session_id' => $session_id,
                        'exercise_id' => $exercise['exercise_id'] ?? null,
                        'exercise_name' => $exercise['exercise_name'] ?? null,
                        'reps' => $exercise['reps'] ?? null,
                        'duration_minutes' => $exercise['duration_minutes'] ?? null,
                        'notes' => $exercise['notes'] ?? null
                    ]);
                }
            }*/

            // Commit Transaction
            $this->pdo->commit();

            return [
                'success' => true,
                'session_id' => $session_id,
            ];
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getSessionById($sessionId) {
        $stmt = $this->pdo->prepare("
            SELECT id, patient_id, episode_id, session_date
            FROM treatment_sessions
            WHERE id = ?
        ");
        $stmt->execute([$sessionId]);

        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$session) {
            return null;
        }

        return [
            'id' => (int) $session['id'],
            'patient_id' => (int) $session['patient_id'],
            'episode_id' => (int) $session['episode_id'],
            'session_date' => $session['session_date'],
        ];
    }

    public function getSessionWithDetails($sessionId) {
        $stmt = $this->pdo->prepare("
            SELECT
                ts.id,
                ts.patient_id,
                ts.episode_id,
                ts.session_date,
                ts.doctor_id,
                ts.primary_therapist_id,
                ts.secondary_therapist_id,
                ts.remarks,
                ts.progress_notes,
                ts.advise,
                ts.additional_treatment_notes
            FROM treatment_sessions ts
            WHERE ts.id = ?
        ");
        $stmt->execute([$sessionId]);

        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$session) {
            return null;
        }

        $amountStmt = $this->pdo->prepare("
            SELECT amount
            FROM patient_payment_ledger
            WHERE patient_id = :patient_id
              AND episode_id = :episode_id
              AND transaction_type = 'charge'
              AND (session_reference = :session_ref OR session_reference = :session_date)
            ORDER BY transaction_date DESC, id DESC
            LIMIT 1
        ");
        $amountStmt->execute([
            ':patient_id' => $session['patient_id'],
            ':episode_id' => $session['episode_id'],
            ':session_ref' => 'session:' . $session['id'],
            ':session_date' => $session['session_date'],
        ]);
        $amount = $amountStmt->fetchColumn();

        $exerciseStmt = $this->pdo->prepare("
            SELECT
                te.exercise_id,
                te.exercise_name,
                te.reps,
                te.duration_minutes,
                te.notes,
                em.name AS master_name
            FROM treatment_exercises te
            LEFT JOIN exercises_master em ON te.exercise_id = em.id
            WHERE te.session_id = ?
            ORDER BY te.id ASC
        ");
        $exerciseStmt->execute([$session['id']]);
        $exerciseRows = $exerciseStmt->fetchAll(PDO::FETCH_ASSOC);

        $machineStmt = $this->pdo->prepare("
            SELECT
                tm.machine_id,
                tm.machine_name,
                tm.duration_minutes,
                tm.notes,
                m.name AS master_name
            FROM treatment_machines tm
            LEFT JOIN machines m ON tm.machine_id = m.id
            WHERE tm.session_id = ?
            ORDER BY tm.id ASC
        ");
        $machineStmt->execute([$session['id']]);
        $machineRows = $machineStmt->fetchAll(PDO::FETCH_ASSOC);

        $session['primary_therapist_id'] = $session['primary_therapist_id'] !== null
            ? (int) $session['primary_therapist_id']
            : null;
        $session['secondary_therapist_id'] = $session['secondary_therapist_id'] !== null
            ? (int) $session['secondary_therapist_id']
            : null;
        $session['amount'] = $amount !== false ? (string) $amount : null;

        $session['exercises'] = array_map(function ($row) {
            return [
                'exercise_id' => $row['exercise_id'] !== null ? (int) $row['exercise_id'] : null,
                'name' => $row['master_name'] ?? $row['exercise_name'] ?? '',
                'exercise_name' => $row['exercise_name'] ?? '',
                'reps' => $row['reps'],
                'duration_minutes' => $row['duration_minutes'],
                'notes' => $row['notes'],
            ];
        }, $exerciseRows);

        $session['machines'] = array_map(function ($row) {
            return [
                'machine_id' => $row['machine_id'] !== null ? (int) $row['machine_id'] : null,
                'name' => $row['master_name'] ?? $row['machine_name'] ?? '',
                'machine_name' => $row['machine_name'] ?? '',
                'duration_minutes' => $row['duration_minutes'],
                'notes' => $row['notes'],
            ];
        }, $machineRows);

        return $session;
    }

    public function updateSession($sessionId, array $data) {
        $existingStmt = $this->pdo->prepare(
            "SELECT patient_id, episode_id FROM treatment_sessions WHERE id = ?"
        );
        $existingStmt->execute([$sessionId]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            return [
                'success' => false,
                'error' => 'Session not found.',
            ];
        }

        if ((int) $existing['patient_id'] !== (int) $data['patient_id']
            || (int) $existing['episode_id'] !== (int) $data['episode_id']) {
            return [
                'success' => false,
                'error' => 'Session context mismatch.',
            ];
        }

        try {
            $this->pdo->beginTransaction();

            $updateStmt = $this->pdo->prepare(
                "UPDATE treatment_sessions"
                . " SET session_date = :session_date,"
                . " doctor_id = :doctor_id,"
                . " primary_therapist_id = :primary_therapist_id,"
                . " secondary_therapist_id = :secondary_therapist_id,"
                . " remarks = :remarks,"
                . " progress_notes = :progress_notes,"
                . " advise = :advise,"
                . " additional_treatment_notes = :additional_treatment_notes"
                . " WHERE id = :session_id"
            );
            $updateStmt->execute([
                'session_date' => $data['session_date'],
                'doctor_id' => $data['doctor_id'],
                'primary_therapist_id' => $data['primary_therapist_id'],
                'secondary_therapist_id' => $data['secondary_therapist_id'],
                'remarks' => $data['remarks'] ?? null,
                'progress_notes' => $data['progress_notes'] ?? null,
                'advise' => $data['advise'] ?? null,
                'additional_treatment_notes' => isset($data['additional_treatment_notes']) && $data['additional_treatment_notes'] !== ''
                    ? $data['additional_treatment_notes']
                    : null,
                'session_id' => $sessionId,
            ]);

            $deleteExercises = $this->pdo->prepare(
                "DELETE FROM treatment_exercises WHERE session_id = ?"
            );
            $deleteExercises->execute([$sessionId]);

            $deleteMachines = $this->pdo->prepare(
                "DELETE FROM treatment_machines WHERE session_id = ?"
            );
            $deleteMachines->execute([$sessionId]);

            $exerciseIds = $data['exercises']['exercise_id'] ?? [];
            $exerciseReps = $data['exercises']['reps'] ?? [];
            $exerciseDurations = $data['exercises']['duration_minutes'] ?? [];
            $exerciseNotes = $data['exercises']['notes'] ?? [];
            $exerciseNames = $data['exercises']['new_name'] ?? [];

            $exerciseCount = is_array($exerciseIds) ? count($exerciseIds) : 0;

            for ($i = 0; $i < $exerciseCount; $i++) {
                $exerciseId = $exerciseIds[$i];
                $customName = isset($exerciseNames[$i]) ? trim((string) $exerciseNames[$i]) : '';

                if ($exerciseId === '' || $exerciseId === null) {
                    if ($customName === '') {
                        continue;
                    }
                    $exerciseId = 'other';
                }

                if ($exerciseId === 'other') {
                    $name = $customName !== '' ? $customName : 'Custom Exercise';
                    $reps = isset($exerciseReps[$i]) ? (int) $exerciseReps[$i] : 0;
                    $duration = isset($exerciseDurations[$i]) ? (int) $exerciseDurations[$i] : 0;
                    $stmtNew = $this->pdo->prepare(
                        "INSERT INTO exercises_master (name, default_reps, default_duration_minutes, is_active)"
                        . " VALUES (?, ?, ?, 1)"
                    );
                    $stmtNew->execute([$name, $reps, $duration]);
                    $exerciseId = $this->pdo->lastInsertId();
                }

                if ($exerciseId === '') {
                    continue;
                }

                $stmtEx = $this->pdo->prepare(
                    "INSERT INTO treatment_exercises"
                    . " (session_id, exercise_id, exercise_name, reps, duration_minutes, notes)"
                    . " VALUES (:session_id, :exercise_id, :exercise_name, :reps, :duration_minutes, :notes)"
                );
                $stmtEx->execute([
                    'session_id' => $sessionId,
                    'exercise_id' => $exerciseId,
                    'exercise_name' => '',
                    'reps' => $exerciseReps[$i] ?? null,
                    'duration_minutes' => $exerciseDurations[$i] ?? null,
                    'notes' => $exerciseNotes[$i] ?? null,
                ]);
            }

            $machineIds = $data['machines']['machine_id'] ?? [];
            $machineDurations = $data['machines']['duration_minutes'] ?? [];
            $machineNotes = $data['machines']['notes'] ?? [];
            $machineNames = $data['machines']['new_name'] ?? [];

            $machineCount = is_array($machineIds) ? count($machineIds) : 0;

            $machineInsertStmt = $this->pdo->prepare(
                "INSERT INTO treatment_machines"
                . " (session_id, machine_id, machine_name, duration_minutes, notes)"
                . " VALUES (:session_id, :machine_id, :machine_name, :duration_minutes, :notes)"
            );

            $machineNameLookupStmt = $this->pdo->prepare(
                "SELECT name FROM machines WHERE id = ?"
            );
            $machineFindByNameStmt = $this->pdo->prepare(
                "SELECT id FROM machines WHERE name = ? LIMIT 1"
            );
            $machineCreateStmt = $this->pdo->prepare(
                "INSERT INTO machines (name, default_duration_minutes) VALUES (?, ?)"
            );

            for ($i = 0; $i < $machineCount; $i++) {
                $rawMachineId = $machineIds[$i] ?? '';
                $machineDuration = $machineDurations[$i] ?? null;
                $machineNote = $machineNotes[$i] ?? null;
                $customName = isset($machineNames[$i]) ? trim((string) $machineNames[$i]) : '';

                if ($rawMachineId === '' && $customName === '') {
                    continue;
                }

                $machineId = null;
                $machineName = '';

                if ($rawMachineId === 'other') {
                    if ($customName === '') {
                        throw new InvalidArgumentException('Machine name is required when selecting Other.');
                    }

                    $machineFindByNameStmt->execute([$customName]);
                    $existingMachine = $machineFindByNameStmt->fetch(PDO::FETCH_ASSOC);
                    if ($existingMachine) {
                        $machineId = (int) $existingMachine['id'];
                    } else {
                        $defaultDuration = is_numeric($machineDuration) ? (int) $machineDuration : 0;
                        $machineCreateStmt->execute([$customName, $defaultDuration]);
                        $machineId = (int) $this->pdo->lastInsertId();
                    }
                    $machineName = $customName;
                } else {
                    $machineId = (int) $rawMachineId;
                    $machineNameLookupStmt->execute([$machineId]);
                    $foundName = $machineNameLookupStmt->fetchColumn();
                    $machineName = $foundName !== false ? (string) $foundName : '';
                }

                if ($machineId === null) {
                    continue;
                }

                $machineInsertStmt->execute([
                    'session_id' => $sessionId,
                    'machine_id' => $machineId,
                    'machine_name' => $machineName,
                    'duration_minutes' => $machineDuration !== '' ? $machineDuration : null,
                    'notes' => $machineNote,
                ]);
            }

            if (!empty($data['file']) && $data['file']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = dirname(__DIR__) . '/uploads/patient_docs/' . $data['patient_id'] . '/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $original = $data['file']['name'];
                $safeName = time() . '_' . preg_replace('/[^A-Za-z0-9.\-_]/', '_', $original);
                move_uploaded_file($data['file']['tmp_name'], $uploadDir . $safeName);
                $stmtFile = $this->pdo->prepare(
                    "INSERT INTO file_master (patient_id, file_name, upload_date) VALUES (:pid, :fname, NOW())"
                );
                $stmtFile->execute([':pid' => $data['patient_id'], ':fname' => $safeName]);
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'session_id' => $sessionId,
            ];
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function deleteSessionById($sessionId) {
        $stmtExercises = $this->pdo->prepare("DELETE FROM treatment_exercises WHERE session_id = ?");
        $stmtExercises->execute([$sessionId]);

        $stmtMachines = $this->pdo->prepare("DELETE FROM treatment_machines WHERE session_id = ?");
        $stmtMachines->execute([$sessionId]);

        $stmtSession = $this->pdo->prepare("DELETE FROM treatment_sessions WHERE id = ?");
        $stmtSession->execute([$sessionId]);

        return $stmtSession->rowCount() > 0;
    }

    public function getLatestSessionWithDetails($patient_id, $episode_id) {
        $stmt = $this->pdo->prepare("
            SELECT
                ts.id,
                ts.session_date,
                ts.remarks,
                ts.progress_notes,
                ts.advise,
                ts.additional_treatment_notes,
                ts.primary_therapist_id,
                ts.secondary_therapist_id
            FROM treatment_sessions ts
            WHERE ts.patient_id = ? AND ts.episode_id = ?
            ORDER BY ts.session_date DESC, ts.id DESC
            LIMIT 1
        ");
        $stmt->execute([$patient_id, $episode_id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            return null;
        }

        $amountStmt = $this->pdo->prepare("
            SELECT amount
            FROM patient_payment_ledger
            WHERE patient_id = ?
              AND episode_id = ?
              AND transaction_type = 'charge'
              AND session_reference IN (?, ?)
            ORDER BY transaction_date DESC, id DESC
            LIMIT 1
        ");
        $amountStmt->execute([
            $patient_id,
            $episode_id,
            'session:' . $session['id'],
            $session['session_date'],
        ]);
        $amount = $amountStmt->fetchColumn();

        $exerciseStmt = $this->pdo->prepare("
            SELECT
                te.exercise_id,
                te.exercise_name,
                te.reps,
                te.duration_minutes,
                te.notes,
                em.name AS master_name
            FROM treatment_exercises te
            LEFT JOIN exercises_master em ON te.exercise_id = em.id
            WHERE te.session_id = ?
            ORDER BY te.id ASC
        ");
        $exerciseStmt->execute([$session['id']]);
        $exerciseRows = $exerciseStmt->fetchAll(PDO::FETCH_ASSOC);

        $machineStmt = $this->pdo->prepare("
            SELECT
                tm.machine_id,
                tm.machine_name,
                tm.duration_minutes,
                tm.notes,
                m.name AS master_name
            FROM treatment_machines tm
            LEFT JOIN machines m ON tm.machine_id = m.id
            WHERE tm.session_id = ?
            ORDER BY tm.id ASC
        ");
        $machineStmt->execute([$session['id']]);
        $machineRows = $machineStmt->fetchAll(PDO::FETCH_ASSOC);

        $session['primary_therapist_id'] = $session['primary_therapist_id'] !== null
            ? (int) $session['primary_therapist_id']
            : null;
        $session['secondary_therapist_id'] = $session['secondary_therapist_id'] !== null
            ? (int) $session['secondary_therapist_id']
            : null;
        $session['amount'] = $amount !== false ? (string) $amount : null;
        $session['exercises'] = array_map(function ($row) {
            return [
                'exercise_id' => $row['exercise_id'] !== null ? (int) $row['exercise_id'] : null,
                'name' => $row['master_name'] ?? $row['exercise_name'] ?? '',
                'exercise_name' => $row['exercise_name'] ?? '',
                'reps' => $row['reps'],
                'duration_minutes' => $row['duration_minutes'],
                'notes' => $row['notes'],
            ];
        }, $exerciseRows);

        $session['machines'] = array_map(function ($row) {
            return [
                'machine_id' => $row['machine_id'] !== null ? (int) $row['machine_id'] : null,
                'name' => $row['master_name'] ?? $row['machine_name'] ?? '',
                'machine_name' => $row['machine_name'] ?? '',
                'duration_minutes' => $row['duration_minutes'],
                'notes' => $row['notes'],
            ];
        }, $machineRows);

        return $session;
    }

    public function getSessionsWithDetails($patient_id, $episode_id) {
        $stmt = $this->pdo->prepare("
            SELECT
                ts.id,
                ts.session_date,
                ts.remarks,
                ts.progress_notes,
                ts.advise,
                ts.additional_treatment_notes,
                ts.primary_therapist_id,
                ts.secondary_therapist_id,
                primary_user.name AS primary_therapist_name,
                secondary_user.name AS secondary_therapist_name
            FROM treatment_sessions ts
            LEFT JOIN users primary_user ON ts.primary_therapist_id = primary_user.id
            LEFT JOIN users secondary_user ON ts.secondary_therapist_id = secondary_user.id
            WHERE ts.patient_id = ? AND ts.episode_id = ?
            ORDER BY ts.session_date DESC, ts.id DESC
        ");
        $stmt->execute([$patient_id, $episode_id]);
        $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$sessions) {
            return [];
        }

        $sessionIds = array_map(static function ($session) {
            $session['id'] = (int) $session['id'];
            $session['primary_therapist_id'] = $session['primary_therapist_id'] !== null
                ? (int) $session['primary_therapist_id']
                : null;
            $session['secondary_therapist_id'] = $session['secondary_therapist_id'] !== null
                ? (int) $session['secondary_therapist_id']
                : null;

            return $session;
        }, $sessions);

        $sessionIdList = array_column($sessionIds, 'id');
        $placeholders = implode(',', array_fill(0, count($sessionIdList), '?'));

        $exerciseStmt = $this->pdo->prepare("
            SELECT
                te.session_id,
                te.exercise_id,
                te.exercise_name,
                te.reps,
                te.duration_minutes,
                te.notes,
                em.name AS master_name
            FROM treatment_exercises te
            LEFT JOIN exercises_master em ON te.exercise_id = em.id
            WHERE te.session_id IN ($placeholders)
            ORDER BY te.session_id DESC, te.id ASC
        ");
        $exerciseStmt->execute($sessionIdList);
        $exerciseRows = $exerciseStmt->fetchAll(PDO::FETCH_ASSOC);

        $machineStmt = $this->pdo->prepare("
            SELECT
                tm.session_id,
                tm.machine_id,
                tm.machine_name,
                tm.duration_minutes,
                tm.notes,
                m.name AS master_name
            FROM treatment_machines tm
            LEFT JOIN machines m ON tm.machine_id = m.id
            WHERE tm.session_id IN ($placeholders)
            ORDER BY tm.session_id DESC, tm.id ASC
        ");
        $machineStmt->execute($sessionIdList);
        $machineRows = $machineStmt->fetchAll(PDO::FETCH_ASSOC);

        $amountStmt = $this->pdo->prepare("
            SELECT amount
            FROM patient_payment_ledger
            WHERE patient_id = ?
              AND episode_id = ?
              AND transaction_type = 'charge'
              AND session_reference IN (?, ?)
            ORDER BY transaction_date DESC, id DESC
            LIMIT 1
        ");

        $sessionMap = [];
        foreach ($sessionIds as $session) {
            $sessionMap[$session['id']] = $session + [
                'amount' => null,
                'exercises' => [],
                'machines' => [],
            ];
        }

        foreach ($exerciseRows as $row) {
            $sid = (int) $row['session_id'];
            if (!isset($sessionMap[$sid])) {
                continue;
            }
            $sessionMap[$sid]['exercises'][] = [
                'exercise_id' => $row['exercise_id'] !== null ? (int) $row['exercise_id'] : null,
                'name' => $row['master_name'] ?? $row['exercise_name'] ?? '',
                'exercise_name' => $row['exercise_name'] ?? '',
                'reps' => $row['reps'],
                'duration_minutes' => $row['duration_minutes'],
                'notes' => $row['notes'],
            ];
        }

        foreach ($machineRows as $row) {
            $sid = (int) $row['session_id'];
            if (!isset($sessionMap[$sid])) {
                continue;
            }
            $sessionMap[$sid]['machines'][] = [
                'machine_id' => $row['machine_id'] !== null ? (int) $row['machine_id'] : null,
                'name' => $row['master_name'] ?? $row['machine_name'] ?? '',
                'machine_name' => $row['machine_name'] ?? '',
                'duration_minutes' => $row['duration_minutes'],
                'notes' => $row['notes'],
            ];
        }

        foreach ($sessionMap as $sessionId => &$session) {
            $amountStmt->execute([
                $patient_id,
                $episode_id,
                'session:' . $sessionId,
                $session['session_date'],
            ]);
            $amount = $amountStmt->fetchColumn();
            $session['amount'] = $amount !== false ? (string) $amount : null;
        }
        unset($session);

        return array_values($sessionMap);
    }

    public function getPreviousSessionExercises($patient_id, $episode_id) {
        $stmt = $this->pdo->prepare("
            SELECT ts.id AS session_id,
                   ts.session_date,
                   ts.remarks,
                   ts.progress_notes,
                   ts.advise,
                   ts.additional_treatment_notes,
                   ts.primary_therapist_id,
                   ts.secondary_therapist_id,
                   primary_user.name AS primary_therapist_name,
                   secondary_user.name AS secondary_therapist_name,
                   te.exercise_id,
                   te.reps,
                   te.duration_minutes,
                   te.notes,
                   em.name
            FROM treatment_sessions ts
            LEFT JOIN users primary_user ON ts.primary_therapist_id = primary_user.id
            LEFT JOIN users secondary_user ON ts.secondary_therapist_id = secondary_user.id
            LEFT JOIN treatment_exercises te ON ts.id = te.session_id
            LEFT JOIN exercises_master em ON te.exercise_id = em.id
            WHERE ts.patient_id = ? AND ts.episode_id = ?
            ORDER BY ts.session_date DESC, ts.id DESC, te.id
        ");
        $stmt->execute([$patient_id, $episode_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPreviousSessionMachines($patient_id, $episode_id) {
        $stmt = $this->pdo->prepare("
            SELECT ts.id AS session_id,
                   ts.session_date,
                   ts.remarks,
                   ts.progress_notes,
                   ts.advise,
                   ts.additional_treatment_notes,
                   primary_user.name AS primary_therapist_name,
                   secondary_user.name AS secondary_therapist_name,
                   tm.machine_id,
                   tm.machine_name,
                   tm.duration_minutes,
                   tm.notes,
                   m.name AS master_name
            FROM treatment_sessions ts
            LEFT JOIN users primary_user ON ts.primary_therapist_id = primary_user.id
            LEFT JOIN users secondary_user ON ts.secondary_therapist_id = secondary_user.id
            LEFT JOIN treatment_machines tm ON ts.id = tm.session_id
            LEFT JOIN machines m ON tm.machine_id = m.id
            WHERE ts.patient_id = ? AND ts.episode_id = ?
            ORDER BY ts.session_date DESC, ts.id DESC, tm.id
        ");
        $stmt->execute([$patient_id, $episode_id]);

        $rows = array_filter($stmt->fetchAll(PDO::FETCH_ASSOC), function ($row) {
            return $row['machine_id'] !== null
                || $row['machine_name'] !== null
                || $row['duration_minutes'] !== null
                || $row['notes'] !== null;
        });

        return array_map(function ($row) {
            return [
                'session_id' => (int) $row['session_id'],
                'session_date' => $row['session_date'],
                'remarks' => $row['remarks'],
                'progress_notes' => $row['progress_notes'],
                'advise' => $row['advise'],
                'additional_treatment_notes' => $row['additional_treatment_notes'],
                'primary_therapist_name' => $row['primary_therapist_name'],
                'secondary_therapist_name' => $row['secondary_therapist_name'],
                'machine_id' => $row['machine_id'] !== null ? (int) $row['machine_id'] : null,
                'machine_name' => $row['machine_name'],
                'duration_minutes' => $row['duration_minutes'],
                'notes' => $row['notes'],
                'name' => $row['master_name'] ?? $row['machine_name'] ?? '',
            ];
        }, $rows);
    }
}
?>
