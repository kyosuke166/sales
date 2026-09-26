<?php
$page_title = "注文管理";
require_once '../header.php';
?>

  <main class="mx-auto max-w-[1500px] px-4 py-8">
    <section class="mb-6">
      <p class="mb-2 text-xs font-bold tracking-[0.24em] text-blue-600 uppercase">DOCUMENT</p>
      <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div class="flex flex-wrap items-center gap-2 rounded-full border border-slate-200 bg-white p-1 shadow-sm">
          <a href="/order/" class="rounded-full px-4 py-2 text-sm font-bold bg-blue-600 text-white shadow-sm">注文管理</a>
          <a href="/order/invoice.php" class="rounded-full px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-100">請求管理</a>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <button class="rounded-full bg-blue-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-blue-700">見積書作成</button>
          <button class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:border-blue-300 hover:text-blue-600">注文書作成</button>
          <button class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:border-blue-300 hover:text-blue-600">請求書作成</button>
        </div>
      </div>
    </section>

    <div class="space-y-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-soft">
      <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <p class="text-xs font-bold tracking-[0.2em] text-slate-400 uppercase">document</p>
          <h2 class="text-[19px] font-semibold text-slate-700">注文書（注文内容）</h2>
        </div>
        <!-- 終了分を表示する・しないの切り替えチェックボックス -->
        <label class="flex items-center gap-2 cursor-pointer text-sm font-medium text-slate-600 select-none">
          <input type="checkbox" id="show-past-toggle" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 h-4 w-4" onchange="loadOrders()">
          <span>終了した案件を表示する</span>
        </label>
      </div>

      <div id="order-list-container">
        <div class="py-8 text-center text-slate-400">
          <p class="text-sm">読み込み中...</p>
        </div>
      </div>
    </div>
  </main>

  <script>
    async function loadOrders() {
      try {
        const response = await fetch('/api/get_orders.php');
        const result = await response.json();
        
        const container = document.querySelector('#order-list-container');
        
        if (!result || result.status !== 'success' || !result.data || result.data.length === 0) {
          container.innerHTML = `
            <div class="py-8 text-center text-slate-400">
              <p class="text-sm">注文データはまだ登録されていません</p>
            </div>
          `;
          return;
        }

        const todayStr = "2026-09-01"; // 本日の日付
        const showPastToggle = document.querySelector('#show-past-toggle');
        const showPast = showPastToggle ? showPastToggle.checked : false;

        let html = '';
        let displayCount = 0;
        
        result.data.forEach(row => {
          const isPast = row.raw_end_date && row.raw_end_date < todayStr;
          
          if (!showPast && isPast) {
            return;
          }

          displayCount++;
          
          const cardClass = isPast 
            ? 'space-y-3 mb-6 p-4 rounded-xl border border-slate-300 bg-slate-100 text-slate-500 shadow-none' 
            : 'space-y-3 mb-6 p-4 rounded-xl border border-slate-200 bg-white shadow-sm';

          html += `
            <div class="${cardClass}">
              <h3 class="text-[15px] font-bold ${isPast ? 'text-slate-500' : 'text-slate-800'}">${row.project} ${isPast ? '<span class="text-xs font-normal text-slate-400">（終了）</span>' : ''}</h3>
              <div class="overflow-x-auto">
                <table class="doc-table ${isPast ? 'opacity-70 bg-slate-50' : ''}">
                  <thead>
                    <tr>
                      <th style="width: 30%;">取引先</th>
                      <th style="width: 15%;"><div>注文番号</div></th>
                      <th style="width: 15%;">契約期間</th>
                      <th style="width: 13%;">単金（精算幅）</th>
                      <th style="width: 10%;">作業者</th>
                      <th style="width: 9%;">見積</th>
                      <th style="width: 8%;">継続確認</th>
                    </tr>
                  </thead>
                  <tbody>
          `;

          // 上位注文（受注）を複数行ループで出力
          if (row.upper_list && row.upper_list.length > 0) {
            let lastUpperCompany = '';
            let lastUpperOrderNo = '';

            row.upper_list.forEach((upper, index) => {
              const isSameCompany = (index > 0 && upper.company === lastUpperCompany);
              const isSameOrderNo = (index > 0 && upper.orderNo === lastUpperOrderNo);

              const displayCompany = isSameCompany ? '' : `<span class="mini-label ${isPast ? 'muted' : ''}">案件</span>${upper.company}`;
              
              // file_path の有無による注文番号の表示切替（リンク or (仮)）
              let displayOrderNo = '';
              if (isSameOrderNo) {
                displayOrderNo = '';
              } else if (upper.orderNo && upper.orderNo !== '—') {
                if (upper.filePath && upper.filePath.trim() !== '') {
                  displayOrderNo = `<a href="../${upper.filePath}" target="_blank" class="text-blue-600 hover:underline font-medium">${upper.orderNo}</a>`;
                } else {
                  displayOrderNo = `<span class="text-amber-600 font-medium">(仮) ${upper.orderNo}</span>`;
                }
              } else {
                displayOrderNo = '—';
              }

              lastUpperCompany = upper.company;
              lastUpperOrderNo = upper.orderNo;

              html += `
                <tr class="case-row ${isPast ? 'bg-slate-100/60' : 'row-upper'}">
                  <td><div class="stack-cell"><div class="stack-row">${displayCompany}</div></div></td>
                  <td><div class="stack-cell"><div class="stack-row">${displayOrderNo}</div></div></td>
                  <td><div class="stack-cell"><div class="stack-row">${upper.period}</div></div></td>
                  <td><div class="stack-cell"><div class="stack-row">\\${upper.quote}</div></div></td>
                  <td><div class="stack-cell"><div class="stack-row">${upper.person}</div></div></td>
                  <td><div class="stack-cell"><div class="stack-row">${upper.estimate}</div></div></td>
                  <td><div class="stack-cell"><div class="stack-row">${upper.status}</div></div></td>
                </tr>
              `;
            });
          }

          // 下位注文（発注/要員）をループ、または空の場合の未登録行を出力
          if (row.lower_list && row.lower_list.length > 0) {
            let lastLowerCompany = '';
            let lastLowerOrderNo = '';

            row.lower_list.forEach((lower, index) => {
              const isSameCompany = (index > 0 && lower.company === lastLowerCompany);
              const isSameOrderNo = (index > 0 && lower.orderNo === lastLowerOrderNo);

              const displayCompany = isSameCompany ? '' : `<span class="mini-label muted">要員</span>${lower.company}`;
              
              let displayOrderNo = '';
              if (isSameOrderNo) {
                displayOrderNo = '';
              } else if (lower.orderNo && lower.orderNo !== '—') {
                if (lower.filePath && lower.filePath.trim() !== '') {
                  displayOrderNo = `<a href="${lower.filePath}" target="_blank" class="text-blue-600 hover:underline font-medium">${lower.orderNo}</a>`;
                } else {
                  displayOrderNo = `<span class="text-amber-600 font-medium">(仮) ${lower.orderNo}</span>`;
                }
              } else {
                displayOrderNo = '—';
              }

              lastLowerCompany = lower.company;
              lastLowerOrderNo = lower.orderNo;

              html += `
                <tr class="case-row ${isPast ? 'bg-slate-100/40' : 'row-lower'}">
                  <td><div class="stack-cell"><div class="stack-row">${displayCompany}</div></div></td>
                  <td><div class="stack-cell"><div class="stack-row">${displayOrderNo}</div></div></td>
                  <td><div class="stack-cell"><div class="stack-row">${lower.period}</div></div></td>
                  <td><div class="stack-cell"><div class="stack-row">\\${lower.quote}</div></div></td>
                  <td><div class="stack-cell"><div class="stack-row">${lower.person}</div></div></td>
                  <td><div class="stack-cell"><div class="stack-row">${lower.estimate}</div></div></td>
                  <td><div class="stack-cell"><div class="stack-row">${lower.status}</div></div></td>
                </tr>
              `;
            });
          } else {
            // 下位注文が1件もない場合の「追加用行」
            html += `
              <tr class="case-row bg-slate-50/80 border-dashed">
                <td colspan="7">
                  <div class="py-2 px-3 text-center text-slate-400 text-xs flex items-center justify-center gap-2">
                    <span>所属への注文データはまだ登録されていません</span>
                    <button class="text-blue-600 hover:underline font-bold bg-white px-2 py-1 rounded border border-slate-200 shadow-xs">＋ 発注を追加</button>
                  </div>
                </td>
              </tr>
            `;
          }

          html += `
                  </tbody>
                </table>
              </div>
            </div>
          `;
        });

        if (displayCount === 0) {
          html = `
            <div class="py-8 text-center text-slate-400">
              <p class="text-sm">該当する注文データはありません</p>
            </div>
          `;
        }

        container.innerHTML = html;

      } catch (error) {
        console.error('Failed to fetch orders:', error);
      }
    }

    loadOrders();
  </script>

  <style>
    .doc-table { 
        width: 100%; 
        min-width: 1100px; 
        table-layout: fixed; /* 列幅を完全に固定して全体で統一 */
        border-collapse: separate; 
        border-spacing: 0; 
        background: #fff; 
        border-top: 1px solid #cbd5e1; 
        border-left: 1px solid #cbd5e1; 
    }
    .doc-table th, .doc-table td { padding: 0.7rem 0.75rem; border-right: 1px solid #e2e8f0; border-bottom: 1px solid #e2e8f0; vertical-align: middle; white-space: nowrap; font-size: 13px; }
    .doc-table thead th { background: #f0f0f0; color: #334155; font-size: 13px; font-weight: 800; text-align: left; }
    .row-upper { background-color: #f0f7ff; }
    .row-lower { background-color: #fff8f8; }
    .stack-cell { display: flex; flex-direction: column; justify-content: center; min-height: 40px; gap: 0.2rem; position: relative; }
    .stack-row { display: flex; align-items: center; gap: 0.35rem; font-size: 13px; line-height: 1.5; color: #0f172a; min-height: 18px; }
    .mini-label { display: inline-flex; align-items: center; justify-content: center; min-width: 36px; padding: 0.1rem 0.4rem; border-radius: 999px; border: 1px solid #dbeafe; background: #eff6ff; color: #1d4ed8; font-size: 10px; font-weight: 700; line-height: 1.2; }
    .mini-label.muted { border-color: #e2e8f0; background: #f8fafc; color: #475569; }
    .case-row td:nth-child(n + 2) { position: relative; }
  </style>

<?php
require_once '../footer.php';
?>