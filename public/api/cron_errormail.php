<?php
// cron_errormail.php (本番バッチ処理版・リアルタイムログ対応)

// コマンドライン(Cron)以外からの実行を弾く（Webブラウザからの直叩き防止）
//if (php_sapi_name() !== 'cli') {
//    http_response_code(403);
//    exit('Direct access not permitted.');
//}

// 途中でタイムアウトして落ちないように制限を無効化
set_time_limit(0);
ini_set('memory_limit', '256M');

require_once __DIR__ . '/../../db-config.php';

$host = IMAP_HOST;
$port = 993;
$user = SMTP_USER_SES;
$pass = SMTP_PASS_SES;

$errno = 0;
$errstr = '';
$socket = @fsockopen('ssl://' . $host, $port, $errno, $errstr, 30);
if (!$socket) {
    error_log("IMAPソケット接続失敗: [{$errno}] {$errstr}");
    exit;
}
fgets($socket);

function send_imap_command($socket, $command) {
    static $tag = 0;
    $tag++;
    $tagStr = 'A' . sprintf('%03d', $tag);
    fwrite($socket, "{$tagStr} {$command}\r\n");
    
    $response = [];
    while (($line = fgets($socket)) !== false) {
        $response[] = $line;
        if (preg_match("/^{$tagStr} (OK|NO|BAD)/i", $line)) {
            break;
        }
    }
    return $response;
}

try {
    // 2. ログイン
    $loginRes = send_imap_command($socket, "LOGIN " . $user . " " . $pass);
    if (strpos(end($loginRes), 'OK') === false) {
        fclose($socket);
        error_log("IMAPログイン失敗");
        exit;
    }

    // 3. ゴミ箱フォルダの選択
    $selectRes = send_imap_command($socket, "SELECT Trash");
    if (strpos(end($selectRes), 'OK') === false) {
        $selectRes = send_imap_command($socket, "SELECT INBOX.Trash");
        if (strpos(end($selectRes), 'OK') === false) {
            fclose($socket);
            error_log("ゴミ箱フォルダの選択に失敗しました。");
            exit;
        }
    }

    // 4. メッセージ検索
    $searchRes = send_imap_command($socket, "SEARCH ALL");
    $msgIds = [];
    foreach ($searchRes as $line) {
        if (stripos($line, '* SEARCH') !== false) {
            $parts = preg_split('/\s+/', trim($line));
            for ($i = 2; $i < count($parts); $i++) {
                if (is_numeric($parts[$i])) {
                    $msgIds[] = (int)$parts[$i];
                }
            }
        }
    }

    if (empty($msgIds)) {
        send_imap_command($socket, "LOGOUT");
        fclose($socket);
        exit;
    }

    $pdo = get_db_connection();

    // ログファイルの初期化処理（絶対パスで確実に指定）
    $logDir = __DIR__ . "/../storage/log/";
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    $csvFilePath = $logDir . "bouncemail_" . date("Ymd_His") . ".csv";

    $csvFile = fopen($csvFilePath, 'w');
    if ($csvFile) {
        fwrite($csvFile, "\xEF\xBB\xBF"); // BOM
        fputcsv($csvFile, [
            '処理日時', 
            'メールID', 
            '対象のメールアドレス', 
            'エラーメッセージ', 
            '判定処理内容', 
            'error_status変化', 
            'error_count', 
            'error_suspend_until'
        ]);
        fflush($csvFile);
    }

    // 全件を対象にループ処理
    foreach ($msgIds as $id) {
        $fetchRes = send_imap_command($socket, "FETCH {$id} BODY.PEEK[]");
        $rawEmail = implode("", $fetchRes);

        $isErrorMail = (
            stripos($rawEmail, 'Mailer-Daemon') !== false ||
            stripos($rawEmail, 'Delivery') !== false ||
            stripos($rawEmail, 'Undelivered') !== false ||
            stripos($rawEmail, 'Failure') !== false ||
            stripos($rawEmail, 'Diagnostic-Code') !== false
        );

        $targetEmail = '';
        $errorMessage = '';
        $actionStatus = '';
        $statusChange = '-';
        $updatedCount = '-';
        $suspendUntilVal = '-';
        $shouldDeleteMail = false;

        if (!$isErrorMail) {
            $actionStatus = '処理対象外（一般メール）';
        } else {
            // 宛先抽出
            if (preg_match('/Final-Recipient:\s*rfc822;\s*([^\s\r\n]+)/i', $rawEmail, $matchFin)) {
                $targetEmail = trim($matchFin[1]);
            } elseif (preg_match('/Original-Recipient:\s*rfc822;\s*([^\s\r\n]+)/i', $rawEmail, $matchOrig)) {
                $targetEmail = trim($matchOrig[1]);
            } else {
                if (preg_match_all('/<([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})>/', $rawEmail, $matches)) {
                    foreach ($matches[1] as $addr) {
                        if (strtolower($addr) !== 'ses@sbt-inc.co.jp') {
                            $targetEmail = trim($addr);
                            break;
                        }
                    }
                }
            }

            // エラーメッセージの取得
            if (preg_match('/Diagnostic-Code:\s*(?:smtp;\s*)?([^\r\n]+)/i', $rawEmail, $errMatches)) {
                $errorMessage = trim($errMatches[1]);
            } else {
                $errorMessage = trim(mb_substr(strip_tags($rawEmail), 0, 200));
            }

            if (empty($targetEmail)) {
                $actionStatus = '対象のメールアドレスが不明';
                $shouldDeleteMail = true; 
            } else {
                // DBから現在の状態を取得
                $stmt_c = $pdo->prepare("SELECT error_status, error_count, error_suspend_until FROM crm_contact WHERE email = ?");
                $stmt_c->execute([$targetEmail]);
                $row = $stmt_c->fetch();

                if ($row) {
                    $oldStatus = $row['error_status'] ?: 'active';
                    $currentCount = (int)$row['error_count'];
                    $suspendUntil = $row['error_suspend_until'];
                    $now = date('Y-m-d H:i:s');
                    
                    // === 重複処理・二重カウント防止のガード条件 ===
                    if ($oldStatus === 'bounce') {
                        $actionStatus = '処理スキップ（既にbounce判定済み）';
                        $shouldDeleteMail = true; 
                        goto write_log;
                    }
                    
                    if ($oldStatus === 'suspend' && !empty($suspendUntil) && $suspendUntil > $now) {
                        $actionStatus = '処理スキップ（一時停止期間中）';
                        $shouldDeleteMail = true; 
                        goto write_log;
                    }
                    // =============================================

                    // 永続的バウンス判定
                    $isPermanentBounce = (
                        stripos($errorMessage, 'User unknown') !== false ||
                        stripos($errorMessage, 'does not exist') !== false ||
                        stripos($errorMessage, 'Access denied') !== false ||
                        stripos($errorMessage, 'Host not found') !== false
                    );

                    if ($isPermanentBounce) {
                        $actionStatus = '正常に判定・処理完了';
                        $newStatus = 'bounce';
                        $updatedCount = $currentCount + 1;
                        $suspendUntilVal = null;
                        $statusChange = "{$oldStatus} → {$newStatus}";

                        $stmt = $pdo->prepare("UPDATE crm_contact SET error_status = 'bounce', error_count = error_count + 1, send_error = ?, updated = NOW() WHERE email = ?");
                        $stmt->execute([$errorMessage, $targetEmail]);
                        $shouldDeleteMail = true;
                    } else {
                        $actionStatus = '正常に判定・処理完了';
                        $newStatus = 'suspend';
                        $updatedCount = $currentCount + 1;
                        $monthsToAdd = $updatedCount;
                        
                        $suspendUntilVal = date('Y-m-d H:i:s', strtotime("+{$monthsToAdd} months"));
                        $statusChange = "{$oldStatus} → {$newStatus}";

                        $stmt = $pdo->prepare("UPDATE crm_contact SET error_status = 'suspend', error_count = ?, error_suspend_until = ?, send_error = ?, updated = NOW() WHERE email = ?");
                        $stmt->execute([$updatedCount, $suspendUntilVal, $errorMessage, $targetEmail]);
                        $shouldDeleteMail = true;
                    }
                } else {
                    $actionStatus = '対象のメールアドレスがCRMに無い';
                    $shouldDeleteMail = true; 
                }
            }
        }

        write_log:
        // ログファイルへ1行書き込み ＆ 即時フラッシュ（tail -f対応）
        if ($csvFile) {
            fputcsv($csvFile, [
                date('Y-m-d H:i:s'),
                $id,
                $targetEmail,
                $errorMessage,
                $actionStatus,
                $statusChange,
                $updatedCount,
                $suspendUntilVal !== null ? $suspendUntilVal : 'NULL'
            ]);
            fflush($csvFile); // 毎行確実にファイル書き込みを反映
        }

        // 処理済みのメールに削除フラグを立てる
        if ($shouldDeleteMail) {
            send_imap_command($socket, "STORE {$id} +FLAGS (\\Deleted)");
        }
    }

    // ゴミ箱から削除フラグがついたメールを完全に削除
    send_imap_command($socket, "EXPUNGE");

    send_imap_command($socket, "LOGOUT");
    fclose($socket);

    if ($csvFile) {
        fclose($csvFile);
    }

} catch (Exception $e) {
    error_log("Bouncemail Cron Error: " . $e->getMessage());
    if (isset($socket)) {
        @fclose($socket);
    }
    if (isset($csvFile) && is_resource($csvFile)) {
        @fclose($csvFile);
    }
}