<?php
/**
 * Googleからのコールバック受信用スクリプト（暫定ID版）
 */
require_once __DIR__ . '/../../auth_check.php';
require_once 'vendor/autoload.php';

// セッション開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 暫定的にあなたのメンバーIDを 1 と固定（実際のIDに合わせて変更してください）
$target_member_id = 1; 

$client = new Google\Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(GOOGLE_REDIRECT_URI);

if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    
    if (!isset($token['error'])) {
        // リフレッシュトークンがあるか確認
        if (isset($token['refresh_token'])) {
            $refreshToken = $token['refresh_token'];

            try {
                $pdo = get_db_connection();
                // IDが $target_member_id のレコードに保存
                $sql = "UPDATE members SET google_token = :token WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':token' => $refreshToken,
                    ':id'    => $target_member_id
                ]);

                echo "<h1>認証成功！</h1>";
                echo "<p>ID: {$target_member_id} のメンバーにトークンを保存しました。</p>";
                echo "<p>これでバックグラウンドでの同期が可能になりました！</p>";

            } catch (Exception $e) {
                die("DB保存エラー: " . $e->getMessage());
            }
        } else {
            echo "<h1>認証成功（更新なし）</h1>";
            echo "<p>リフレッシュトークンが送られてきませんでした。すでに連携済みです。</p>";
        }
        echo "<br><a href='../'>管理画面へ戻る</a>";
    } else {
        echo "トークン取得エラー: " . htmlspecialchars($token['error_description']);
    }
} else {
    echo "認証コードが見つかりません。";
}