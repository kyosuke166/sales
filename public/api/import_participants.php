<?php
// api/import_participants.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../auth_check.php'; 
$pdo = get_db_connection(); 

$eventId = isset($_POST['event_id']) ? (int)$_POST['event_id'] : null;
$mode = $_POST['mode'] ?? 'preview';

if (!$eventId) exit('エラー: イベントIDがありません');

// --- 1. イベント情報の取得 (日付フォーマット修正) ---
$stmtEv = $pdo->prepare("SELECT DATE_FORMAT(event_date, '%Y/%m/%d') as fmt_date, event_number, event_name, area FROM events WHERE id = ?");
$stmtEv->execute([$eventId]);
$eventInfo = $stmtEv->fetch();

$displayTitle = sprintf(
    "%s %s %s (%s)",
    $eventInfo['fmt_date'] ?? '',
    $eventInfo['event_number'] ?? '',
    $eventInfo['event_name'] ?? '不明なイベント',
    $eventInfo['area'] ?? ''
);

// --- 2. 実行モード ---
if ($mode === 'execute') {
    $importData = $_POST['import_data'] ?? [];
    try {
        $pdo->beginTransaction();
        $stmtMax = $pdo->prepare("SELECT MAX(entry_number) FROM events_participant WHERE event_id = ?");
        $stmtMax->execute([$eventId]);
        $maxNum = (int)$stmtMax->fetchColumn();

        $stmtInsert = $pdo->prepare("
            INSERT INTO events_participant 
            (event_id, entry_number, company_name, participant_name, email, note, company_id, contact_id, send_flg, created, updated) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW())
        ");

        foreach ($importData as $row) {
            $maxNum++;
            $stmtInsert->execute([
                $eventId, $maxNum, $row['company_name'], $row['participant_name'], 
                $row['email'] ?: null, $row['note'], 
                $row['company_id'] ?: null, $row['contact_id'] ?: null
            ]);
        }
        $pdo->commit();
        // 完了後は詳細画面へ戻る
        header("Location: /event/desc?id=" . $eventId);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        exit("DBエラー: " . $e->getMessage());
    }
}

// --- 3. プレビューモード ---
$previewRows = [];
if (isset($_FILES['csv']) && $_FILES['csv']['error'] === UPLOAD_ERR_OK) {
    $handle = fopen($_FILES['csv']['tmp_name'], "r");
    $bom = fread($handle, 3);
    if ($bom !== "\xEF\xBB\xBF") rewind($handle);
    fgetcsv($handle); 

    while (($line = fgetcsv($handle)) !== FALSE) {
        $c_name = trim($line[0] ?? '');
        $p_name = trim($line[1] ?? '');
        $email  = trim($line[2] ?? '');
        $note   = trim($line[3] ?? '');
        if (!$c_name && !$p_name) continue;

        $c_id = null; $p_id = null; $crm_email = ''; $status_html = '';

        // CRM照合 (Email優先)
        if (!empty($email)) {
            $stmt = $pdo->prepare("SELECT id, company_id, email FROM crm_contact WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $res = $stmt->fetch();
            if ($res) { $p_id = $res['id']; $c_id = $res['company_id']; $crm_email = $res['email'] ?? ''; }
        }
        // 会社特定
        if (!$c_id && !empty($c_name)) {
            $c_search = str_replace(['株式会社', '有限会社', '(株)', '（株）', ' ', '　'], '', $c_name);
            $stmt = $pdo->prepare("SELECT id FROM crm_company WHERE company_name = ? OR company_name LIKE ? LIMIT 1");
            $stmt->execute([$c_name, $c_search . '%']);
            $res = $stmt->fetch();
            if ($res) $c_id = $res['id'];
        }
        // 氏名特定
        if ($c_id && !$p_id && !empty($p_name)) {
            $p_search = str_replace(['様', ' ', '　'], '', $p_name);
            $stmt = $pdo->prepare("SELECT id, email FROM crm_contact WHERE company_id = ? AND (REPLACE(CONCAT(last_name, first_name), ' ', '') = ? OR ? LIKE CONCAT('%', last_name, '%')) LIMIT 1");
            $stmt->execute([$c_id, $p_search, $p_search]);
            $res = $stmt->fetch();
            if ($res) { $p_id = $res['id']; $crm_email = $res['email'] ?? ''; }
        }

        // アイコン判定 (会社:オレンジ, 人:ブルー)
        $icons = [];
        if ($c_id) $icons[] = '<i class="fa-solid fa-building" style="color:#fd7e14; margin-right:4px;" title="会社一致"></i>';
        if ($p_id) $icons[] = '<i class="fa-solid fa-user" style="color:#007bff;" title="担当者一致"></i>';
        
        $status_html = !empty($icons) ? implode('', $icons) : '<i class="fa-solid fa-minus" style="color:#dee2e6;"></i>';

        $previewRows[] = [
            'company_name' => $c_name, 'participant_name' => $p_name,
            'email' => !empty($email) ? $email : $crm_email,
            'note' => $note, 'company_id' => $c_id, 'contact_id' => $p_id, 'status_html' => $status_html
        ];
    }
    fclose($handle);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: "Helvetica Neue", Arial, "Hiragino Kaku Gothic ProN", "Hiragino Sans", Meiryo, sans-serif; padding: 20px; background: #f8f9fa; color: #333; }
        .header-card { background: white; padding: 20px; border-radius: 8px; border-left: 5px solid #007bff; box-shadow: 0 2px 4px rgba(0,0,0,0.05); margin-bottom: 20px; position: relative; }
        .cancel-link { position: absolute; top: 20px; right: 20px; color: #666; text-decoration: none; font-size: 0.9rem; border: 1px solid #ccc; padding: 5px 15px; border-radius: 4px; background: #fff; }
        .cancel-link:hover { background: #eee; }
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        th, td { border: 1px solid #dee2e6; padding: 12px; text-align: left; }
        th { background: #f1f3f5; font-size: 0.9rem; }
        .email-input { width: 90%; padding: 6px; border: 1px solid #ced4da; border-radius: 4px; font-size: 0.85rem; outline: none; }
        .email-input:focus { border-color: #80bdff; box-shadow: 0 0 0 0.2rem rgba(0,123,255,0.25); }
        .status-cell { text-align: left; font-size: 1.1rem; padding-left: 15px; }
        .footer { text-align: center; margin-top: 40px; padding-bottom: 80px; display: flex; justify-content: center; align-items: center; gap: 20px; }
        .btn-primary { padding: 15px 60px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 1.1rem; font-weight: bold; box-shadow: 0 4px 6px rgba(40,167,69,0.2); transition: background 0.2s; }
        .btn-primary:hover { background: #218838; }
        .btn-cancel { display: inline-block; padding: 14px 30px; background: #fff; color: #6c757d; border: 1px solid #ced4da; border-radius: 4px; text-decoration: none; font-size: 1rem; transition: all 0.2s; }
        .btn-cancel:hover { background: #f8f9fa; color: #343a40; border-color: #adb5bd; }
    </style>
</head>
<body>

<div class="header-card">
    <h2 style="margin:0; font-size: 1.4rem;">取込み確認：<?= htmlspecialchars($displayTitle) ?></h2>
    <p style="margin:10px 0 0; font-size:0.85rem; color:#666;">
        CRM連携： <i class="fa-solid fa-building" style="color:#fd7e14;"></i> 会社一致 / 
        <i class="fa-solid fa-user" style="color:#007bff;"></i> 担当者一致
    </p>
</div>

<form method="POST" onkeydown="return event.key !== 'Enter';">
    <input type="hidden" name="event_id" value="<?= $eventId ?>">
    <input type="hidden" name="mode" value="execute">

    <table>
        <thead>
            <tr>
                <th>会社名</th>
                <th>参加者名</th>
                <th style="width: 25%;">メールアドレス</th>
                <th>コメント</th>
                <th style="width: 80px;">CRM</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($previewRows as $i => $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['company_name']) ?></td>
                <td><?= htmlspecialchars($row['participant_name']) ?></td>
                <td>
                    <?php if (empty($row['email'])): ?>
                        <input type="email" name="import_data[<?= $i ?>][email]" value="" class="email-input">
                    <?php else: ?>
                        <?= htmlspecialchars($row['email']) ?>
                        <input type="hidden" name="import_data[<?= $i ?>][email]" value="<?= htmlspecialchars($row['email']) ?>">
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($row['note']) ?></td>
                <td class="status-cell"><?= $row['status_html'] ?></td>
            </tr>
            <input type="hidden" name="import_data[<?= $i ?>][company_name]" value="<?= htmlspecialchars($row['company_name']) ?>">
            <input type="hidden" name="import_data[<?= $i ?>][participant_name]" value="<?= htmlspecialchars($row['participant_name']) ?>">
            <input type="hidden" name="import_data[<?= $i ?>][note]" value="<?= htmlspecialchars($row['note']) ?>">
            <input type="hidden" name="import_data[<?= $i ?>][company_id]" value="<?= $row['company_id'] ?>">
            <input type="hidden" name="import_data[<?= $i ?>][contact_id]" value="<?= $row['contact_id'] ?>">
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer">
        <a href="/event/desc?id=<?= $eventId ?>" class="btn-cancel">キャンセルして戻る</a>
        <button type="submit" class="btn-primary">この内容で登録を実行する</button>
    </div>
</form>

</body>
</html>