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
                (patient_id, episode_id, session_date, doctor_id, remarks, progress_notes, advise)
                VALUES (:patient_id, :episode_id, :session_date, :doctor_id, :remarks, :progress_notes, :advise)
            ");
            $stmt->execute([
                'patient_id' => $data['patient_id'],
                'episode_id' => $data['episode_id'],
                'session_date' => $data['session_date'],
                'doctor_id' => $data['doctor_id'],
                'remarks' => $data['remarks'] ?? null,
                'progress_notes' => $data['progress_notes'] ?? null,
                'advise' => $data['advise'] ?? null
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

    public function getPreviousSessionExercises($patient_id, $episode_id) {
        $stmt = $this->pdo->prepare("
            SELECT ts.id AS session_id,
                   ts.session_date,
                   ts.remarks,
                   ts.progress_notes,
                   ts.advise,
                   te.exercise_id,
                   te.reps,
                   te.duration_minutes,
                   te.notes,
                   em.name
            FROM treatment_sessions ts
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
