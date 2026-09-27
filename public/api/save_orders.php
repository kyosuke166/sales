<?php
require_once 'auth_check.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = null;
$locked = false;
$stagedPath = null;
$targetPath = null;
$backupPath = null;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new RuntimeException('POSTで送信してください。');
    }

    $pdo = get_db_connection();
    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
    $ankenId = trim($_POST['anken_id'] ?? '');
    $ankenName = trim($_POST['anken_name'] ?? '');
    $direction = $_POST['order_direction'] ?? '';
    $companyId = filter_var($_POST['company_id'] ?? null, FILTER_VALIDATE_INT);
    $contactId = ($_POST['contact_id'] ?? '') === '' ? null : filter_var($_POST['contact_id'], FILTER_VALIDATE_INT);

    if ($ankenName === '' || mb_strlen($ankenName) > 255) {
        throw new InvalidArgumentException('案件名を入力してください（255文字以内）。');
    }
    if (!in_array($direction, ['in', 'out'], true)) {
        throw new InvalidArgumentException('注文方向の値が不正です。');
    }
    if (!$companyId || $companyId < 1) {
        throw new InvalidArgumentException('取引先を選択してください。');
    }
    if ($contactId === false) {
        throw new InvalidArgumentException('担当者の指定が不正です。');
    }

    $nullableText = static function (string $key, ?int $maxLength = null): ?string {
        $value = trim($_POST[$key] ?? '');
        if ($value === '') return null;
        if ($maxLength !== null && mb_strlen($value) > $maxLength) {
            throw new InvalidArgumentException("{$key}は{$maxLength}文字以内で入力してください。");
        }
        return $value;
    };
    $nullableInteger = static function (string $key): ?int {
        $value = trim($_POST[$key] ?? '');
        if ($value === '') return null;
        if (!is_numeric($value)) throw new InvalidArgumentException("{$key}は数値で入力してください。");
        return (int)$value;
    };
    $nullableDate = static function (string $key): ?string {
        $value = trim($_POST[$key] ?? '');
        if ($value === '') return null;
        $date = DateTime::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException("{$key}の日付が不正です。");
        }
        return $value;
    };

    $fields = [
        'order_direction' => $direction,
        'company_id' => (int)$companyId,
        'contact_id' => $contactId === null ? null : (int)$contactId,
        'worker_name' => $nullableText('worker_name', 100),
        'estimate' => $nullableText('estimate', 100),
        'order_no' => $nullableText('order_no', 100),
        'start_date' => $nullableDate('start_date'),
        'end_date' => $nullableDate('end_date'),
        'renewal_status' => $_POST['renewal_status'] ?? 'pending',
        'unit_price' => $nullableInteger('unit_price'),
        'total_amount' => $nullableInteger('total_amount'),
        'range_min' => $nullableInteger('range_min'),
        'range_max' => $nullableInteger('range_max'),
        'time_unit' => $nullableInteger('time_unit'),
        'payment_site' => $nullableText('payment_site', 50),
        'memo' => $nullableText('memo'),
    ];
    if (!in_array($fields['renewal_status'], ['pending', 'checking', 'renewed', 'terminated'], true)) {
        throw new InvalidArgumentException('継続確認の値が不正です。');
    }

    $upload = $_FILES['order_file'] ?? null;
    if ($upload && $upload['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($upload['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('ファイルのアップロードに失敗しました。');
        if ($upload['size'] > 25 * 1024 * 1024) throw new InvalidArgumentException('ファイルは25MB以下にしてください。');
        $extension = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg'];
        if (!in_array($extension, $allowedExtensions, true)) {
            throw new InvalidArgumentException('PDF、Office文書、PNG/JPEGのみアップロードできます。');
        }
        if (($fields['order_no'] ?? '') === '') throw new InvalidArgumentException('ファイルを添付する場合は注文番号を入力してください。');
    }

    $pdo->beginTransaction();
    $companyCheck = $pdo->prepare('SELECT id FROM crm_company WHERE id = :id AND deleted IS NULL');
    $companyCheck->execute([':id' => $companyId]);
    if (!$companyCheck->fetchColumn()) throw new InvalidArgumentException('選択した取引先が見つかりません。');
    if ($contactId !== null) {
        $contactCheck = $pdo->prepare('SELECT id FROM crm_contact WHERE id = :id AND company_id = :company_id AND deleted IS NULL');
        $contactCheck->execute([':id' => $contactId, ':company_id' => $companyId]);
        if (!$contactCheck->fetchColumn()) throw new InvalidArgumentException('担当者は選択した取引先に所属する方を指定してください。');
    }

    if ($id !== null) {
        $existingStmt = $pdo->prepare('SELECT anken_id, file_path FROM orders WHERE id = :id FOR UPDATE');
        $existingStmt->execute([':id' => $id]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) throw new InvalidArgumentException('編集対象の注文書が見つかりません。');
        $ankenId = $existing['anken_id'];
        $fields['file_path'] = $existing['file_path'];
    } elseif ($ankenId !== '') {
        $projectCheck = $pdo->prepare('SELECT anken_id FROM orders WHERE anken_id = :anken_id LIMIT 1');
        $projectCheck->execute([':anken_id' => $ankenId]);
        if (!$projectCheck->fetchColumn()) throw new InvalidArgumentException('指定された案件IDが見つかりません。');
    } else {
        $lockStmt = $pdo->query("SELECT GET_LOCK('sales_orders_anken_id', 10)");
        $locked = (int)$lockStmt->fetchColumn() === 1;
        if (!$locked) throw new RuntimeException('案件IDを採番できませんでした。もう一度お試しください。');
        $maxId = (int)$pdo->query("SELECT COALESCE(MAX(CAST(anken_id AS UNSIGNED)), 0) FROM orders")->fetchColumn();
        $ankenId = (string)($maxId + 1);
    }

    $values = ['anken_id' => $ankenId, 'anken_name' => $ankenName] + $fields;
    if ($id !== null) {
        $assignments = [];
        foreach (array_keys($values) as $column) $assignments[] = "`{$column}` = :{$column}";
        $values['id'] = $id;
        $sql = 'UPDATE orders SET ' . implode(', ', $assignments) . ' WHERE id = :id';
    } else {
        $columns = array_map(static fn($column) => "`{$column}`", array_keys($values));
        $placeholders = array_map(static fn($column) => ":{$column}", array_keys($values));
        $sql = 'INSERT INTO orders (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
    }
    $saveStmt = $pdo->prepare($sql);
    foreach ($values as $key => $value) $saveStmt->bindValue(':' . $key, $value);
    $saveStmt->execute();
    $orderId = $id ?? (int)$pdo->lastInsertId();

    if ($upload && $upload['error'] === UPLOAD_ERR_OK) {
        $safeOrderNo = preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]/u', '_', $fields['order_no']);
        $safeOrderNo = trim($safeOrderNo, " .\\t\\n\\r\\0\\x0B");
        $safeOrderNo = mb_strcut($safeOrderNo, 0, 180, 'UTF-8');
        if ($safeOrderNo === '') throw new InvalidArgumentException('注文番号をファイル名に使用できません。');
        $safeAnkenId = preg_replace('/[^A-Za-z0-9_-]/', '_', $ankenId);
        $storageDirectory = '/var/www/sales/storage/order';
        if (!is_dir($storageDirectory) && !mkdir($storageDirectory, 0755, true) && !is_dir($storageDirectory)) {
            throw new RuntimeException('注文書の保存先を作成できません。');
        }
        if (!is_writable($storageDirectory)) throw new RuntimeException('注文書の保存先に書き込めません。');
        $directionCode = $fields['order_direction'] === 'in' ? 'I' : 'O';
        $filename = str_pad($safeAnkenId, 4, '0', STR_PAD_LEFT) . '_' . $directionCode . '_' . $safeOrderNo . '.' . $extension;
        $targetPath = $storageDirectory . DIRECTORY_SEPARATOR . $filename;
        $stagedPath = $storageDirectory . DIRECTORY_SEPARATOR . '.upload_' . bin2hex(random_bytes(8));
        if (!move_uploaded_file($upload['tmp_name'], $stagedPath)) throw new RuntimeException('アップロードファイルを保存できません。');
        if (is_file($targetPath)) {
            $backupPath = $storageDirectory . DIRECTORY_SEPARATOR . '.backup_' . bin2hex(random_bytes(8));
            if (!rename($targetPath, $backupPath)) throw new RuntimeException('既存ファイルを更新できません。');
        }
        if (!rename($stagedPath, $targetPath)) throw new RuntimeException('アップロードファイルを配置できません。');
        $stagedPath = null;
        $fields['file_path'] = 'storage/order/' . $filename;
        $pathStmt = $pdo->prepare('UPDATE orders SET file_path = :file_path WHERE id = :id');
        $pathStmt->execute([':file_path' => $fields['file_path'], ':id' => $orderId]);
    }

    $pdo->commit();
    if ($backupPath && is_file($backupPath)) unlink($backupPath);
    echo json_encode(['status' => 'success', 'id' => $orderId, 'anken_id' => $ankenId, 'file_path' => $fields['file_path'] ?? null], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    if ($stagedPath && is_file($stagedPath)) unlink($stagedPath);
    if ($targetPath && is_file($targetPath)) unlink($targetPath);
    if ($backupPath && is_file($backupPath)) rename($backupPath, $targetPath);
    if (http_response_code() < 400) http_response_code($error instanceof InvalidArgumentException ? 400 : 500);
    echo json_encode(['status' => 'error', 'message' => $error instanceof PDOException ? 'データベースの保存に失敗しました。' : $error->getMessage()], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
} finally {
    if ($locked && $pdo instanceof PDO) $pdo->query("SELECT RELEASE_LOCK('sales_orders_anken_id')");
}
