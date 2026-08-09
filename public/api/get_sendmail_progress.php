<?php
require_once 'auth_check.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    $pdo = get_db_connection();
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) throw new Exception('無効なIDです。');

    // DBから全体の件数とステータスを取得
    $stmt = $pdo->prepare("SELECT status, total_count FROM sendmail_history WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$task) throw new Exception('タスクが見つかりません。');

    $csvFileName = sprintf("sendmail_history_%06d.csv", $id);
    $csvFilePath = __DIR__ . "/../storage/log/" . $csvFileName;

    $current_count = 0;
    $log_lines = [];

    if (file_exists($csvFilePath)) {
        // ファイルを配列として読み込む（空行と改行コードを除外）
        $fileLines = file($csvFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if (!empty($fileLines)) {
            // BOM（\xEF\xBB\xBF）が先頭にある場合は除去（1行目のヘッダー）
            $fileLines[0] = preg_replace('/^\xEF\xBB\xBF/', '', $fileLines[0]);
            
            // 1行目（ヘッダー）を取り除く
            array_shift($fileLines); 
            
            $current_count = count($fileLines);
            // 画面に流すログは最新の100件程度に絞る（ブラウザが重くなるのを防ぐため）
            $log_lines = array_slice($fileLines, -100);
        }
    }

    echo json_encode([
        'status' => 'success',
        'task_status' => $task['status'], // 'sending' か 'sent' か 'error' 等
        'total' => (int)$task['total_count'],
        'current' => $current_count,
        'logs' => $log_lines
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}