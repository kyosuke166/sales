<?php
// 必要に応じてセッションや共通の準備をここに記述
?>
<!doctype html>
<html lang="ja">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo isset($page_title) ? $page_title : 'SBT事務管理画面'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Tailwind CSS (Astro版のグローバルCSSまたはCDN) -->
    <script src="https://cdn.tailwindcss.com"></script>
  </head>
  <body class="bg-gray-50 min-h-screen flex flex-col text-gray-800">
    <header class="bg-white border-b sticky top-0 z-50">
        <div class="max-w-[1400px] mx-auto px-4 h-14 flex items-center justify-between text-slate-600">
            <div class="flex items-center gap-6">
                <a href="/" class="hover:opacity-80 transition-opacity flex items-center gap-3">
                    <img src="/apple-touch-icon.png" alt="Logo" class="w-8 h-8 rounded-lg">
                    <span class="text-lg font-bold text-slate-700 italic tracking-tight">SBT事務管理画面</span>
                </a>
                <div class="hidden md:flex items-center gap-6 ml-4 border-l pl-6 border-slate-200">
                    <nav class="flex items-center gap-4 text-[12px] font-bold text-slate-500">
                        <a href="https://www.sbt-inc.co.jp/" target="_blank" class="hover:text-blue-600">会社HP</a>
                        <a href="https://www.sbt-inc.co.jp/stats.php" target="_blank" class="hover:text-blue-600">HPログ</a>
                        <a href="https://member.sbt-inc.co.jp/dashboard/" target="_blank" class="hover:text-blue-600">メンバー</a>
                        <a href="https://member.sbt-inc.co.jp/admin/" target="_blank" class="hover:text-blue-600">管理</a>
                    </nav>
                    <div class="flex items-center gap-2 ml-2">
                        <div class="w-3 h-3 rounded-full bg-blue-500 cursor-pointer hover:scale-110 transition-transform" title="案件配信リスト" onclick="handleEmailExtract('send_project')"></div>
                        <div class="w-3 h-3 rounded-full bg-red-300 cursor-pointer hover:scale-110 transition-transform" title="技術者配信リスト" onclick="handleEmailExtract('send_engineer')"></div>
                        <div class="w-3 h-3 rounded-full bg-emerald-500 cursor-pointer hover:scale-110 transition-transform" title="イベント配信リスト" onclick="handleEmailExtract('send_event')"></div>

                        <div class="h-3 w-[1px] bg-slate-300 mx-1"></div> 
                        <a href="/crm/sync" class="flex items-center justify-center w-5 h-5 text-indigo-500 hover:text-indigo-700 hover:scale-110 transition-all" title="未紐付けデータの一括照合">
                            <i class="fa-solid fa-arrows-rotate text-sm"></i>
                        </a>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-6 text-[13px]">
                <div class="hidden md:flex items-center gap-4">
                    <span id="display_user_name" class="font-bold">読み込み中...</span>
                </div>
                <div class="h-4 w-[1px] bg-slate-200"></div>
                <a href="/login.php?action=logout" class="hover:text-red-600 transition-colors">ログアウト</a>
            </div>
        </div>
    </header>

    <div class="flex-grow">