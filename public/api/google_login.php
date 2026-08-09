<?php
/**
 * Googleログイン開始用スクリプト
 */
require_once 'auth_check.php';
require_once 'vendor/autoload.php';

// セッション開始（状態を維持するため）
session_start();

$client = new Google\Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(GOOGLE_REDIRECT_URI);

// 連絡先の表示・管理スコープを追加
$client->addScope(Google\Service\PeopleService::CONTACTS);

// オフラインアクセス（後で自動更新トークンをもらうため）
$client->setAccessType('offline');
// 毎回同意画面を出す（テスト中はこれが確実です）
$client->setPrompt('select_account consent');

// 認証URLを生成してリダイレクト
$authUrl = $client->createAuthUrl();
header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
exit;