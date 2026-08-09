<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

require_once dirname(__DIR__) . '/db-config.php';

// logout の処理
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    header('Location: /login.php');
    exit;
}

function buildRedirectUrl(string $redirect): string {
    if ($redirect === '') {
        return '/';
    }
    if (strpos($redirect, '/') !== 0) {
        return '/';
    }
    if (preg_match('#^//|:\\/#', $redirect)) {
        return '/';
    }
    return $redirect;
}

$error_message = '';
$last_email = '';

$redirect_param = buildRedirectUrl($_GET['redirect'] ?? '');

if (isset($_SESSION['user_id']) && isset($_SESSION['admin']) && intval($_SESSION['admin']) === 1) {
    header('Location: ' . $redirect_param);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $last_email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($last_email === '' || $password === '') {
        $error_message = 'メールアドレスとパスワードを入力してください。';
    } else {
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare('SELECT id, email, password, admin, last_name, first_name FROM members WHERE email = :email AND status = 1');
            $stmt->execute([':email' => $last_email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password']) && intval($user['admin']) === 1) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['admin'] = intval($user['admin']);
                $_SESSION['last_name'] = $user['last_name'] ?? '';
                $_SESSION['first_name'] = $user['first_name'] ?? '';

                $stmt = $pdo->prepare('UPDATE members SET last_login = NOW() WHERE id = :id');
                $stmt->execute([':id' => $user['id']]);

                // ─── ここから追加 ───
                echo "<div style='background:#111; color:#0f0; padding:20px; font-family:monospace;'>";
                echo "<h3>【デバッグ】ログイン成功・セッション保持確認</h3>";
                echo "SESSION ID: " . session_id() . "<br>";
                echo "<pre>";
                print_r($_SESSION);
                echo "</pre>";
                echo "</div>";
                //exit; // ←ここでわざと処理を止めて画面に値を映し出す
                // ─── ここまで追加 ───

                header('Location: ' . $redirect_param);
                exit;
            }

            $error_message = 'メールアドレスまたはパスワードが正しくありません。';
        } catch (Exception $e) {
            $error_message = '認証中にエラーが発生しました。';
        }
    }
}

?><!doctype html>
<html lang="ja">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン | SBT営業管理</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
      body { margin: 0; font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #f8fafc; color: #1f2937; }
      .page { min-height: 100vh; display: flex; flex-direction: column; }
      .header, .footer { background: #fff; border-bottom: 1px solid #e5e7eb; }
      .header .inner, .footer .inner { max-width: 1200px; margin: 0 auto; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; }
      .brand { display: flex; align-items: center; gap: 12px; text-decoration: none; color: #0f172a; }
      .brand img { width: 40px; height: 40px; border-radius: 10px; }
      .main { flex: 1; display: flex; align-items: center; justify-content: center; padding: 48px 24px; }
      .card { width: 100%; max-width: 420px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 18px; box-shadow: 0 24px 60px rgba(15,23,42,.08); padding: 36px; }
      .card h1 { margin: 0 0 14px; font-size: 1.75rem; color: #111827; }
      .card p { margin: 0 0 24px; color: #475569; line-height: 1.75; }
      .alert { margin-bottom: 24px; padding: 14px 16px; border: 1px solid #fda4af; border-radius: 12px; background: #fef2f2; color: #b91c1c; }
      .form-group { margin-bottom: 18px; }
      .form-group label { display: block; margin-bottom: 8px; font-weight: 700; font-size: .95rem; }
      .form-group input { width: 100%; border: 1px solid #cbd5e1; border-radius: 12px; padding: 12px 14px; font-size: 1rem; background: #f8fafc; }
      .form-group input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 3px rgba(59,130,246,.15); }
      .button { width: 100%; display: inline-flex; align-items: center; justify-content: center; gap: 8px; border: 0; border-radius: 12px; background: #0f172a; color: #fff; padding: 14px; font-size: 1rem; cursor: pointer; }
      .button:hover { background: #1e293b; }
      .footer { text-align: center; font-size: .85rem; color: #94a3b8; }
      .footer a { color: #64748b; text-decoration: none; }
      .footer a:hover { text-decoration: underline; }
    </style>
  </head>
  <body class="page">
    <header class="header">
      <div class="inner">
        <a href="/" class="brand">
          <img src="/apple-touch-icon.png" alt="SBT Logo">
          <div>
            <div style="font-weight:700;">SBT営業管理</div>
            <div style="font-size:0.9rem; color:#64748b;">管理者専用ログイン</div>
          </div>
        </a>
      </div>
    </header>

    <main class="main">
      <section class="card">
        <h1>ログイン</h1>
        <p>管理者のメールアドレスとパスワードでログインしてください。</p>
        <?php if ($error_message !== ''): ?>
          <div class="alert"><?= htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <form method="post" action="/login.php<?= !empty($_GET['redirect']) ? '?redirect=' . urlencode($_GET['redirect']) : '' ?>">
          <div class="form-group">
            <label for="email">メールアドレス</label>
            <input id="email" name="email" type="email" autocomplete="email" required value="<?= htmlspecialchars($last_email, ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="form-group">
            <label for="password">パスワード</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
          </div>
          <button type="submit" class="button"><i class="fa-solid fa-right-to-bracket"></i> ログイン</button>
        </form>
      </section>
    </main>

    <footer class="footer">
      &copy; <?= date('Y') ?> SBT-INC. All rights reserved.
    </footer>
  </body>
</html>
