<?php
class TreatmentController {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function saveSession($data) {
        try {
            // Begin Transaction
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

            $session_id = $this->pdo->lastInsertId();

            // Insert exercises
            for($i=0;$i<count($data['exercises']['exercise_id']);$i++)
            {
                $exerciseId = $data['exercises']['exercise_id'][$i];
                if ($exerciseId === 'other') {
                    $name = $data['exercises']['new_name'][$i] ?? 'Custom Exercise';
                    $reps = $data['exercises']['reps'][$i] ?? 0;
                    $dur = $data['exercises']['duration_minutes'][$i] ?? 0;
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
                        'reps' => $data['exercises']['reps'][$i],
                        'duration_minutes' =>  $data['exercises']['duration_minutes'][$i],
                        'notes' =>  $data['exercises']['notes'][$i]
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
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return $e->getMessage();
        }
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
              AND session_reference = ?
            ORDER BY transaction_date DESC, id DESC
            LIMIT 1
        ");
        $amountStmt->execute([$patient_id, $episode_id, $session['session_date']]);
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

        return $session;
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
}
?>
