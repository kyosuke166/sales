<?php

// コマンドライン(Cron)以外からの実行を弾く（Webブラウザからの直叩き防止）
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Direct access not permitted.');
}

// 途中でタイムアウトして落ちないように、実行時間制限を無効化する
set_time_limit(0);
ini_set('memory_limit', '256M'); // 念のためメモリ上限も少し上げておく

require_once __DIR__ . '/../../db-config.php';

// PHPMailerの読み込み（api/PHPMailer 配下に配置）
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

try {
    $pdo = get_db_connection();

    // 二重起動（並列処理）の防止、現在 'sending' 状態のタスクがないか確認する
    $checkSql = "SELECT id FROM sendmail_history WHERE status = 'sending' LIMIT 1";
    $runningTask = $pdo->query($checkSql)->fetch();
    if ($runningTask) {
        // すでに別の処理が走っているので今は何もしない（順番待ち）
        exit;
    }

    // 「配信待ち」または「予約時間になった」タスクを1件取得
    $sql = "SELECT * FROM sendmail_history 
            WHERE status = 'waiting' 
               OR (status = 'scheduled' AND senddate <= NOW())
            ORDER BY created ASC LIMIT 1";
    $stmt = $pdo->query($sql);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$task) {
        exit; // 処理対象がなければ終了
    }

    $history_id = $task['id'];
    
    // --- 宛先(target)のパース（カンマ区切りでメアドと宛名を取得） ---
    // 改行コードを統一して行ごとに分割し、空行を除去
    $target_lines = explode("\n", str_replace(["\r\n", "\r"], "\n", trim($task['target'])));
    $target_lines = array_filter(array_map('trim', $target_lines));
    $total_count = count($target_lines);

    // 【DB更新 開始時】状態を「配信中(sending)」に更新
    $updateStart = $pdo->prepare("UPDATE sendmail_history SET status = 'sending', total_count = :total, started = NOW() WHERE id = :id");
    $updateStart->execute([':total' => $total_count, ':id' => $history_id]);

    // CSVファイルの設定とオープン
    $csvFileName = sprintf("sendmail_history_%06d.csv", $history_id);
    $csvFilePath = __DIR__ . "/../storage/log/" . $csvFileName;

    // もし storage/log ディレクトリが存在しない場合は作成する
    $csvDir = dirname($csvFilePath);
    if (!is_dir($csvDir)) {
        mkdir($csvDir, 0777, true);
    }

    $fp = fopen($csvFilePath, 'a');
    fwrite($fp, "\xEF\xBB\xBF"); // Excel用BOM
    fputcsv($fp, ['日時', '結果', 'メールアドレス', '詳細']);

    $success_count = 0;
    $error_count = 0;

    // PHPMailerの基本設定（ローカルのPostfix経由で送信）
    $mail = new PHPMailer(true);
    $mail->isMail(); // ローカルのメール機能（sendmail/Postfix）を使う
    $mail->CharSet = 'UTF-8';
    
    // 差出人とReturn-Path（エラー戻り先）をses@sbt-inc.co.jpに指定
    $mail->setFrom(SMTP_FROM_SES, SMTP_FROM_NAME_SES);
    $mail->Sender = SMTP_FROM_SES;
    
    $mail->Subject = $task['subject'];

    // 複数件の送信ループ
    foreach ($target_lines as $line) {
        $parts = explode(',', $line);
        $email = trim($parts[0]);
        // 宛名が無い場合は「ご担当者様」をフォールバックに使う
        $name  = isset($parts[1]) && trim($parts[1]) !== '' ? trim($parts[1]) : '株式会社SBT ご担当者様';
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_count++;
            fputcsv($fp, [date('Y-m-d H:i:s'), '失敗', $email, '無効なメールアドレス']);
            continue;
        }

        try {
            $mail->addAddress($email);

            // フロントで作られた完成形(task['body'])の宛名部分だけを置換する
            $mail->Body = str_replace("株式会社SBT ご担当者様", $name, $task['full_body']);
            
            // 送信実行
            $mail->send();
            
            $success_count++;
            fputcsv($fp, [date('Y-m-d H:i:s'), '成功', $email, '']);
        } catch (Exception $e) {
            $error_count++;
            fputcsv($fp, [date('Y-m-d H:i:s'), '失敗', $email, $mail->ErrorInfo]);
        }
        
        // Toをクリアして次の宛先へ
        $mail->clearAddresses();
        usleep(200000); 
    }

    $mail->smtpClose();
    fclose($fp);

    // 長時間ループによってDB接続が切断されている対策（もう一度新しく接続し直す）
    $pdo = get_db_connection();
   
    // 【DB更新 終了時】最終結果を記録
    $updateEnd = $pdo->prepare("UPDATE sendmail_history SET status = 'sent', success_count = :success, error_count = :error, finished = NOW() WHERE id = :id");
    $updateEnd->execute([
        ':success' => $success_count,
        ':error' => $error_count,
        ':id' => $history_id
    ]);

} catch (Exception $e) {
    error_log("Sendmail Cron Error: " . $e->getMessage());
    // エラーで強制終了してしまった場合、DBのステータスを'error'にして止める ▼▼
    if (isset($pdo) && isset($history_id)) {
        $updateError = $pdo->prepare("UPDATE sendmail_history SET status = 'error', finished = NOW() WHERE id = :id");
        $updateError->execute([':id' => $history_id]);
    }
}