<?php
require_once 'auth_check.php';
header('Content-Type: application/json; charset=utf-8');

$pdo = null;
$stagedPath = null;
$targetPath = null;
$backupPath = null;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new RuntimeException('POSTで送信してください。');
    }

    $pdo = get_db_connection();
    $invoiceId = isset($_POST['id']) && $_POST['id'] !== '' ? filter_var($_POST['id'], FILTER_VALIDATE_INT) : null;
    $orderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
    $companyId = filter_var($_POST['company_id'] ?? null, FILTER_VALIDATE_INT);
    $contactValue = trim($_POST['contact_id'] ?? '');
    $contactId = $contactValue === '' ? null : filter_var($contactValue, FILTER_VALIDATE_INT);
    $targetMonth = trim($_POST['target_month'] ?? '');
    $invoiceNo = trim($_POST['invoice_no'] ?? '');

    if ($invoiceId === false || ($invoiceId !== null && $invoiceId < 1)) {
        throw new InvalidArgumentException('請求書IDが不正です。');
    }
    if (!$orderId || $orderId < 1 || !$companyId || $companyId < 1 || $contactId === false) {
        throw new InvalidArgumentException('対象注文・会社・担当者の指定が不正です。');
    }
    $month = DateTime::createFromFormat('!Y-m', $targetMonth);
    if (!$month || $month->format('Y-m') !== $targetMonth) {
        throw new InvalidArgumentException('稼働月を正しく入力してください。');
    }
    if (mb_strlen($invoiceNo) > 100) {
        throw new InvalidArgumentException('請求番号は100文字以内で入力してください。');
    }

    $nullableText = static function (string $key, int $maxLength): ?string {
        $value = trim($_POST[$key] ?? '');
        if ($value === '') return null;
        if (mb_strlen($value) > $maxLength) {
            throw new InvalidArgumentException("{$key}は{$maxLength}文字以内で入力してください。");
        }
        return $value;
    };
    $nullableDecimal = static function (string $key, bool $required = false): ?string {
        $value = trim($_POST[$key] ?? '');
        if ($value === '') {
            if ($required) throw new InvalidArgumentException("{$key}を入力してください。");
            return null;
        }
        if (!is_numeric($value) || !is_finite((float)$value) || abs((float)$value) > 99999999.99) {
            throw new InvalidArgumentException("{$key}は有効な金額を入力してください。");
        }
        return number_format((float)$value, 2, '.', '');
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
        'order_id' => (int)$orderId,
        'target_month' => $targetMonth,
        'invoice_no' => $invoiceNo,
        'work_hours' => $nullableText('work_hours', 10),
        'hour_range' => $nullableText('hour_range', 50),
        'unit_price' => $nullableDecimal('unit_price'),
        'adjustment' => $nullableDecimal('adjustment'),
        'expense' => $nullableDecimal('expense'),
        'tax_amount' => $nullableDecimal('tax_amount'),
        'expense_inc' => $nullableDecimal('expense_inc'),
        'total_amount' => $nullableDecimal('total_amount', true),
        'due_date' => $nullableDate('due_date'),
        'completed_date' => $nullableDate('completed_date'),
        'yayoi_kakekin' => isset($_POST['yayoi_kakekin']) ? 1 : 0,
        'yayoi_furikae' => isset($_POST['yayoi_furikae']) ? 1 : 0,
        'file_path' => null,
    ];

    $upload = $_FILES['invoice_file'] ?? null;
    if ($upload && $upload['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($upload['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('ファイルのアップロードに失敗しました。');
        if ($upload['size'] > 25 * 1024 * 1024) throw new InvalidArgumentException('ファイルは25MB以下にしてください。');
        $extension = strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg'];
        if (!in_array($extension, $allowedExtensions, true)) {
            throw new InvalidArgumentException('PDF、Office文書、PNG/JPEGのみアップロードできます。');
        }
    }

    $pdo->beginTransaction();
    $orderStmt = $pdo->prepare('SELECT id, anken_id, order_direction, company_id, contact_id FROM orders WHERE id = :id FOR UPDATE');
    $orderStmt->execute([':id' => $orderId]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) throw new InvalidArgumentException('対象注文が見つかりません。');
    if ((int)$order['company_id'] !== (int)$companyId || (string)($order['contact_id'] ?? '') !== (string)($contactId ?? '')) {
        throw new InvalidArgumentException('会社・担当者と対象注文の組み合わせが一致しません。');
    }
    if (!in_array($order['order_direction'], ['in', 'out'], true)) {
        throw new InvalidArgumentException('対象注文の方向が不正です。');
    }

    if ($invoiceId !== null) {
        $existingStmt = $pdo->prepare('SELECT file_path FROM invoices WHERE id = :id FOR UPDATE');
        $existingStmt->execute([':id' => $invoiceId]);
        $existingFilePath = $existingStmt->fetchColumn();
        if ($existingFilePath === false) throw new InvalidArgumentException('編集対象の請求書が見つかりません。');
        $fields['file_path'] = $existingFilePath;
    }

    if ($invoiceId !== null) {
        $assignments = [];
        foreach (array_keys($fields) as $column) $assignments[] = "`{$column}` = :{$column}";
        $fields['id'] = $invoiceId;
        $saveStmt = $pdo->prepare('UPDATE invoices SET ' . implode(', ', $assignments) . ' WHERE id = :id');
    } else {
        $columns = array_map(static fn($column) => "`{$column}`", array_keys($fields));
        $placeholders = array_map(static fn($column) => ":{$column}", array_keys($fields));
        $saveStmt = $pdo->prepare('INSERT INTO invoices (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')');
    }
    foreach ($fields as $key => $value) $saveStmt->bindValue(':' . $key, $value);
    $saveStmt->execute();
    $savedInvoiceId = $invoiceId ?? (int)$pdo->lastInsertId();

    if ($upload && $upload['error'] === UPLOAD_ERR_OK) {
        $originalFilename = basename(str_replace('\\', '/', (string)$upload['name']));
        $safeOriginalFilename = preg_replace('/[\\/:*?"<>|\x00-\x1F]/', '_', $originalFilename);
        if ($safeOriginalFilename === null) $safeOriginalFilename = $originalFilename;
        $safeOriginalFilename = trim($safeOriginalFilename, " .\\t\\n\\r\\0\\x0B");
        $safeOriginalFilename = substr($safeOriginalFilename, 0, 180);
        if ($safeOriginalFilename === '') $safeOriginalFilename = 'invoice.' . $extension;
        $safeAnkenId = preg_replace('/[^A-Za-z0-9_-]/', '_', $order['anken_id']);
        $storageDirectory = '/var/www/sales/storage/invoice';
        if (!is_dir($storageDirectory) && !mkdir($storageDirectory, 0755, true) && !is_dir($storageDirectory)) {
            throw new RuntimeException('請求書の保存先を作成できません。');
        }
        if (!is_writable($storageDirectory)) throw new RuntimeException('請求書の保存先に書き込めません。');
        $directionCode = $order['order_direction'] === 'in' ? 'I' : 'O';
        $filename = str_pad($safeAnkenId, 4, '0', STR_PAD_LEFT) . '_' . $directionCode . '_' . $safeOriginalFilename;
        $targetPath = $storageDirectory . DIRECTORY_SEPARATOR . $filename;
        $stagedPath = $storageDirectory . DIRECTORY_SEPARATOR . '.upload_' . bin2hex(random_bytes(8));
        if (!move_uploaded_file($upload['tmp_name'], $stagedPath)) throw new RuntimeException('アップロードファイルを保存できません。');
        if (is_file($targetPath)) {
            $backupPath = $storageDirectory . DIRECTORY_SEPARATOR . '.backup_' . bin2hex(random_bytes(8));
            if (!rename($targetPath, $backupPath)) throw new RuntimeException('既存ファイルを更新できません。');
        }
        if (!rename($stagedPath, $targetPath)) throw new RuntimeException('アップロードファイルを配置できません。');
        $stagedPath = null;
        $fields['file_path'] = 'storage/invoice/' . $filename;
        $fileStmt = $pdo->prepare('UPDATE invoices SET file_path = :file_path WHERE id = :id');
        $fileStmt->execute([':file_path' => $fields['file_path'], ':id' => $savedInvoiceId]);
    }

    $pdo->commit();
    if ($backupPath && is_file($backupPath)) unlink($backupPath);
    echo json_encode(['status' => 'success', 'id' => $savedInvoiceId, 'file_path' => $fields['file_path']], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    if ($stagedPath && is_file($stagedPath)) unlink($stagedPath);
    if ($targetPath && is_file($targetPath)) unlink($targetPath);
    if ($backupPath && is_file($backupPath)) rename($backupPath, $targetPath);
    if (http_response_code() < 400) http_response_code($error instanceof InvalidArgumentException ? 400 : 500);
    echo json_encode(['status' => 'error', 'message' => $error instanceof PDOException ? 'データベースの保存に失敗しました。' : $error->getMessage()], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
}
?>