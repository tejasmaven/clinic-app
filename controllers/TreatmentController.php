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
                (patient_id, session_date, doctor_id, remarks, progress_notes) 
                VALUES (:patient_id, :session_date, :doctor_id, :remarks, :progress_notes)
            ");
            $stmt->execute([
                'patient_id' => $data['patient_id'],
                'session_date' => $data['session_date'],
                'doctor_id' => $data['doctor_id'],
                'remarks' => $data['remarks'] ?? null,
                'progress_notes' => $data['progress_notes'] ?? null
            ]);

            $session_id = $this->pdo->lastInsertId();

            // Insert exercises
            for($i=0;$i<count($data['exercises']['exercise_id']);$i++)
            {
                /*print $data['exercises']['exercise_id'][$i]." - ";
                print $data['exercises']['reps'][$i]." - ";
                print $data['exercises']['duration_minutes'][$i]." - ";
                print $data['exercises']['notes'][$i]." <br> "; */

                $stmtEx = $this->pdo->prepare("
                        INSERT INTO treatment_exercises 
                        (session_id, exercise_id, exercise_name, reps, duration_minutes, notes)
                        VALUES (:session_id, :exercise_id, :exercise_name, :reps, :duration_minutes, :notes)
                    ");
                    $stmtEx->execute([
                        'session_id' => $session_id,
                        'exercise_id' => $data['exercises']['exercise_id'][$i],
                        'exercise_name' => '',
                        'reps' => $data['exercises']['reps'][$i],
                        'duration_minutes' =>  $data['exercises']['duration_minutes'][$i],
                        'notes' =>  $data['exercises']['notes'][$i]
                    ]);

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

    public function getPreviousSessionExercises($patient_id) {
        $stmt = $this->pdo->prepare("
            SELECT te.* , em.name 
            FROM treatment_exercises te
            JOIN treatment_sessions ts ON ts.id = te.session_id
            JOIN exercises_master em ON te.exercise_id = em.id
            WHERE ts.patient_id = ?
            ORDER BY ts.session_date DESC, ts.id DESC
            LIMIT 10
        ");
        $stmt->execute([$patient_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
