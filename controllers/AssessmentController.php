<?php

class AssessmentController {
    private $pdo;
    private $itemTypes = ['question', 'examination'];
    private $inputTypes = ['yes_no', 'text', 'textarea', 'number', 'select'];

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->ensureTables();
    }

    private function ensureTables(): void {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS assessment_sections (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(160) NOT NULL,
                description TEXT DEFAULT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )"
        );

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS assessment_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                section_id INT NOT NULL,
                item_type ENUM('question', 'examination') NOT NULL DEFAULT 'question',
                label VARCHAR(255) NOT NULL,
                input_type ENUM('yes_no', 'text', 'textarea', 'number', 'select') NOT NULL DEFAULT 'yes_no',
                option_values TEXT DEFAULT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_assessment_items_section (section_id)
            )"
        );

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS patient_assessments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                patient_id INT NOT NULL,
                assessed_by INT DEFAULT NULL,
                assessment_date DATE NOT NULL,
                notes TEXT DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_patient_assessments_patient (patient_id)
            )"
        );

        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS patient_assessment_answers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                assessment_id INT NOT NULL,
                item_id INT NOT NULL,
                answer_value TEXT DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_assessment_item (assessment_id, item_id),
                INDEX idx_patient_assessment_answers_assessment (assessment_id)
            )"
        );
    }

    public function getInputTypes(): array {
        return $this->inputTypes;
    }

    public function handleSetupActions(array $post): string {
        $action = $post['action'] ?? '';

        if ($action === 'add_section' || $action === 'edit_section') {
            return $this->saveSection($post);
        }

        if ($action === 'toggle_section') {
            $this->toggleRow('assessment_sections', (int) ($post['id'] ?? 0));
            return 'Section status updated.';
        }

        if ($action === 'delete_section') {
            $this->deleteSection((int) ($post['id'] ?? 0));
            return 'Section deleted.';
        }

        if ($action === 'add_item' || $action === 'edit_item') {
            return $this->saveItem($post);
        }

        if ($action === 'toggle_item') {
            $this->toggleRow('assessment_items', (int) ($post['id'] ?? 0));
            return 'Assessment item status updated.';
        }

        if ($action === 'delete_item') {
            $this->deleteItem((int) ($post['id'] ?? 0));
            return 'Assessment item deleted.';
        }

        return '';
    }

    private function saveSection(array $post): string {
        $id = (int) ($post['id'] ?? 0);
        $title = trim((string) ($post['title'] ?? ''));
        $description = trim((string) ($post['description'] ?? ''));
        $sortOrder = (int) ($post['sort_order'] ?? 0);

        if ($title === '') {
            return 'Section name is required.';
        }

        if ($id > 0) {
            $stmt = $this->pdo->prepare("UPDATE assessment_sections SET title = ?, description = ?, sort_order = ? WHERE id = ?");
            $stmt->execute([$title, $description, $sortOrder, $id]);
            return 'Section updated.';
        }

        $stmt = $this->pdo->prepare("INSERT INTO assessment_sections (title, description, sort_order) VALUES (?, ?, ?)");
        $stmt->execute([$title, $description, $sortOrder]);
        return 'Section added.';
    }

    private function saveItem(array $post): string {
        $id = (int) ($post['id'] ?? 0);
        $sectionId = (int) ($post['section_id'] ?? 0);
        $itemType = in_array($post['item_type'] ?? '', $this->itemTypes, true) ? $post['item_type'] : 'question';
        $inputType = in_array($post['input_type'] ?? '', $this->inputTypes, true) ? $post['input_type'] : 'yes_no';
        $label = trim((string) ($post['label'] ?? ''));
        $optionValues = trim((string) ($post['option_values'] ?? ''));
        $sortOrder = (int) ($post['sort_order'] ?? 0);

        if ($sectionId <= 0) {
            return 'Please select a section.';
        }

        if ($label === '') {
            return 'Question or examination label is required.';
        }

        if ($itemType === 'examination') {
            $inputType = 'text';
            $optionValues = '';
        }

        if ($inputType === 'select' && $optionValues === '') {
            return 'Select inputs require options.';
        }

        if ($inputType !== 'select') {
            $optionValues = '';
        }

        if ($id > 0) {
            $stmt = $this->pdo->prepare(
                "UPDATE assessment_items
                 SET section_id = ?, item_type = ?, label = ?, input_type = ?, option_values = ?, sort_order = ?
                 WHERE id = ?"
            );
            $stmt->execute([$sectionId, $itemType, $label, $inputType, $optionValues, $sortOrder, $id]);
            return 'Assessment item updated.';
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO assessment_items (section_id, item_type, label, input_type, option_values, sort_order)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$sectionId, $itemType, $label, $inputType, $optionValues, $sortOrder]);
        return 'Assessment item added.';
    }

    private function toggleRow(string $table, int $id): void {
        if ($id <= 0) {
            return;
        }

        $stmt = $this->pdo->prepare("UPDATE $table SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$id]);
    }

    private function deleteSection(int $id): void {
        if ($id <= 0) {
            return;
        }

        $this->pdo->beginTransaction();
        try {
            $itemStmt = $this->pdo->prepare("SELECT id FROM assessment_items WHERE section_id = ?");
            $itemStmt->execute([$id]);
            $itemIds = $itemStmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($itemIds)) {
                $placeholders = implode(', ', array_fill(0, count($itemIds), '?'));
                $this->pdo->prepare("DELETE FROM patient_assessment_answers WHERE item_id IN ($placeholders)")
                    ->execute($itemIds);
            }

            $this->pdo->prepare("DELETE FROM assessment_items WHERE section_id = ?")->execute([$id]);
            $this->pdo->prepare("DELETE FROM assessment_sections WHERE id = ?")->execute([$id]);
            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function deleteItem(int $id): void {
        if ($id <= 0) {
            return;
        }

        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("DELETE FROM patient_assessment_answers WHERE item_id = ?")->execute([$id]);
            $this->pdo->prepare("DELETE FROM assessment_items WHERE id = ?")->execute([$id]);
            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function getSectionById(int $sectionId): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM assessment_sections WHERE id = ? LIMIT 1");
        $stmt->execute([$sectionId]);
        $section = $stmt->fetch(PDO::FETCH_ASSOC);

        return $section ?: null;
    }

    public function getItemsBySection(int $sectionId): array {
        $stmt = $this->pdo->prepare(
            "SELECT *
             FROM assessment_items
             WHERE section_id = ?
             ORDER BY sort_order ASC, id ASC"
        );
        $stmt->execute([$sectionId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSectionsWithItems(bool $activeOnly = false): array {
        $sectionSql = "SELECT * FROM assessment_sections";
        if ($activeOnly) {
            $sectionSql .= " WHERE is_active = 1";
        }
        $sectionSql .= " ORDER BY sort_order ASC, title ASC";

        $sections = $this->pdo->query($sectionSql)->fetchAll(PDO::FETCH_ASSOC);
        $itemSql = "SELECT * FROM assessment_items";
        if ($activeOnly) {
            $itemSql .= " WHERE is_active = 1";
        }
        $itemSql .= " ORDER BY sort_order ASC, id ASC";
        $items = $this->pdo->query($itemSql)->fetchAll(PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($items as $item) {
            $grouped[(int) $item['section_id']][] = $item;
        }

        foreach ($sections as &$section) {
            $section['items'] = $grouped[(int) $section['id']] ?? [];
        }
        unset($section);

        return $sections;
    }

    public function getPatientAssessment(int $patientId): array {
        $stmt = $this->pdo->prepare("SELECT * FROM patient_assessments WHERE patient_id = ? ORDER BY assessment_date DESC, id DESC LIMIT 1");
        $stmt->execute([$patientId]);
        $assessment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$assessment) {
            return ['assessment' => null, 'answers' => []];
        }

        $answerStmt = $this->pdo->prepare("SELECT item_id, answer_value FROM patient_assessment_answers WHERE assessment_id = ?");
        $answerStmt->execute([(int) $assessment['id']]);
        $answers = [];
        foreach ($answerStmt->fetchAll(PDO::FETCH_ASSOC) as $answer) {
            $answers[(int) $answer['item_id']] = $answer['answer_value'];
        }

        return ['assessment' => $assessment, 'answers' => $answers];
    }

    public function savePatientAssessment(int $patientId, int $userId, array $post): string {
        $assessmentDate = $post['assessment_date'] ?? date('Y-m-d');
        $notes = trim((string) ($post['notes'] ?? ''));
        $answers = is_array($post['answers'] ?? null) ? $post['answers'] : [];
        $selectedSectionIds = array_values(array_filter(array_map('intval', (array) ($post['assessment_section_ids'] ?? []))));
        $existing = $this->getPatientAssessment($patientId)['assessment'] ?? null;

        $this->pdo->beginTransaction();
        try {
            if ($existing) {
                $assessmentId = (int) $existing['id'];
                $stmt = $this->pdo->prepare("UPDATE patient_assessments SET assessed_by = ?, assessment_date = ?, notes = ? WHERE id = ?");
                $stmt->execute([$userId ?: null, $assessmentDate, $notes, $assessmentId]);
            } else {
                $stmt = $this->pdo->prepare(
                    "INSERT INTO patient_assessments (patient_id, assessed_by, assessment_date, notes)
                     VALUES (?, ?, ?, ?)"
                );
                $stmt->execute([$patientId, $userId ?: null, $assessmentDate, $notes]);
                $assessmentId = (int) $this->pdo->lastInsertId();
            }

            $answerStmt = $this->pdo->prepare(
                "INSERT INTO patient_assessment_answers (assessment_id, item_id, answer_value)
                 VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE answer_value = VALUES(answer_value)"
            );

            $allowedItemIds = $this->getAllowedAssessmentItemIds($selectedSectionIds);
            if (!empty($selectedSectionIds)) {
                $deleteParams = [$assessmentId];
                $deleteSql = "DELETE FROM patient_assessment_answers WHERE assessment_id = ?";

                if (!empty($allowedItemIds)) {
                    $placeholders = implode(', ', array_fill(0, count($allowedItemIds), '?'));
                    $deleteSql .= " AND item_id NOT IN ($placeholders)";
                    $deleteParams = array_merge($deleteParams, $allowedItemIds);
                }

                $this->pdo->prepare($deleteSql)->execute($deleteParams);
            }

            foreach ($answers as $itemId => $answerValue) {
                $itemId = (int) $itemId;
                if (!empty($allowedItemIds) && !in_array($itemId, $allowedItemIds, true)) {
                    continue;
                }

                $answerStmt->execute([$assessmentId, $itemId, trim((string) $answerValue)]);
            }

            $this->pdo->commit();
            return 'Assessment saved successfully.';
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    private function getAllowedAssessmentItemIds(array $sectionIds): array {
        if (empty($sectionIds)) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($sectionIds), '?'));
        $stmt = $this->pdo->prepare("SELECT id FROM assessment_items WHERE section_id IN ($placeholders)");
        $stmt->execute($sectionIds);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}
