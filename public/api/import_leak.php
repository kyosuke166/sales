<?php
// api/import_leak.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'auth_check.php';
$pdo = get_db_connection();

$source_date = $_POST['source_date'] ?? '';
$source_company = $_POST['source_company'] ?? '';
$source_person = $_POST['source_person'] ?? '';
$mode = $_POST['mode'] ?? 'preview'; // preview か execute

if (!$source_date || !$source_company) {
    exit('エラー: 取得日または発生元会社名が不足しています。');
}

// datetime-local の 'T' をスペースに変換 (DB保存用)
$formatted_date = str_replace('T', ' ', $source_date);

// --- プレビューモード ---
if ($mode === 'preview') {
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        exit('CSVファイルのアップロードに失敗しました。');
    }

    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, "r");
    
    // ヘッダー読み飛ばし (宛先の会社,宛先の担当者,メールアドレス)
    fgetcsv($handle, 0, ",", "\"", "\\");

    $rows = [];
    $i = 0;
    while (($data = fgetcsv($handle, 0, ",", "\"", "\\")) !== FALSE) {
        if (empty($data[2])) continue; // メールアドレスがない行はスキップ

        $rows[] = [
            'raw_company' => $data[0] ?? '',
            'raw_person'  => $data[1] ?? '',
            'email'       => trim($data[2])
        ];
    }
    fclose($handle);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>取込みプレビュー</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: sans-serif; padding: 20px; background: #f4f7f6; color: #333; }
        .preview-header { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px; text-align: center; }
        .preview-header h3 { margin-top: 0; color: #007bff; }
        /* 日付フォーマットを見やすく */
        .info-text { font-size: 1.1rem; }
        
        table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        th { background: #333; color: #fff; padding: 12px 10px; text-align: left; font-size: 0.85rem; }
        td { padding: 10px; border-bottom: 1px solid #eee; font-size: 0.9rem; }
        
        /* ボタンを中央寄せ */
        .footer { margin-top: 30px; display: flex; justify-content: center; gap: 20px; }
        .btn { padding: 12px 30px; border-radius: 25px; border: none; cursor: pointer; font-weight: bold; text-decoration: none; font-size: 1rem; transition: all 0.2s; }
        .btn-primary { background: #007bff; color: white; }
        .btn-primary:hover { background: #0056b3; transform: translateY(-2px); }
        .btn-cancel { background: #6c757d; color: white; }
        .btn-cancel:hover { background: #5a6268; }
    </style>
</head>
<body>
    <div class="preview-header">
        <h3>以下の内容で取り込みます</h3>
        <p class="info-text">
            取得日: <strong><?= str_replace('-', '/', htmlspecialchars($formatted_date)) ?></strong> 
            &nbsp;/&nbsp; 
            発生元: <strong><?= htmlspecialchars($source_company) ?></strong>
        </p>
    </div>

    <form action="import_leak.php" method="POST">
        <input type="hidden" name="mode" value="execute">
        <input type="hidden" name="source_date" value="<?= htmlspecialchars($source_date) ?>">
        <input type="hidden" name="source_company" value="<?= htmlspecialchars($source_company) ?>">
        <input type="hidden" name="source_person" value="<?= htmlspecialchars($source_person) ?>">

        <table>
            <thead>
                <tr>
                    <th style="width: 50px;">No</th>
                    <th>宛先会社</th>
                    <th>宛先担当者</th>
                    <th>メールアドレス</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $idx => $row): ?>
                <tr>
                    <td><?= $idx + 1 ?></td>
                    <td><?= htmlspecialchars($row['raw_company']) ?></td>
                    <td><?= htmlspecialchars($row['raw_person']) ?></td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                </tr>
                <input type="hidden" name="data[<?= $idx ?>][raw_company]" value="<?= htmlspecialchars($row['raw_company']) ?>">
                <input type="hidden" name="data[<?= $idx ?>][raw_person]" value="<?= htmlspecialchars($row['raw_person']) ?>">
                <input type="hidden" name="data[<?= $idx ?>][email]" value="<?= htmlspecialchars($row['email']) ?>">
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="footer">
            <a href="/leak" class="btn btn-cancel">キャンセル</a>
            <button type="submit" class="btn btn-primary">この内容で登録確定</button>
        </div>
    </form>
</body>
</html>
<?php
    exit;
}

// --- 確定実行モード ---
if ($mode === 'execute') {
    $dataList = $_POST['data'] ?? [];
    try {
        $pdo->beginTransaction();
        
        // カラム名に company_id のタイポ（conpany_id）がある場合は適宜直してください
        $sql = "INSERT INTO leaked_contacts (
                    source_date, 
                    source_company, 
                    source_person, 
                    seq_number, 
                    raw_company, 
                    raw_person, 
                    email, 
                    email_domain
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);

        foreach ($dataList as $idx => $item) {
            $email = trim($item['email']);
            
            // ドメイン切り出し（空ならnull）
            $domain = null;
            if (strpos($email, '@') !== false) {
                $domain = substr(strrchr($email, "@"), 1);
            }

            // 各項目の空文字判定（空文字ならnullを入れる）
            // raw_company, raw_person など
            $raw_company = (trim($item['raw_company']) === '') ? null : trim($item['raw_company']);
            $raw_person  = (trim($item['raw_person'])  === '') ? null : trim($item['raw_person']);
            $source_p    = (trim($source_person)       === '') ? null : trim($source_person);

            $stmt->execute([
                $formatted_date,
                $source_company, // ここは必須入力なのでそのまま
                $source_p,
                $idx + 1,
                $raw_company,
                $raw_person,
                $email,
                $domain,
            ]);
        }

        $pdo->commit();
        header("Location: /leak/desc?date=" . urlencode($source_date) . "&company=" . urlencode($source_company));
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        exit("登録失敗: " . $e->getMessage());
    }
}