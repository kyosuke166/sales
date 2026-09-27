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
          <button class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 hover:border-blue-300 hover:text-blue-600">見積書作成</button>
          <button id="create-order-button" type="button" class="rounded-full bg-blue-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-blue-700">注文書作成</button>
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

  <div id="order-modal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-slate-950/50 p-4" role="dialog" aria-modal="true" aria-labelledby="order-modal-title">
    <form id="order-form" class="max-h-[92vh] w-full max-w-xl overflow-y-auto rounded-xl bg-white shadow-2xl">
      <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-white px-6 py-4">
        <h2 id="order-modal-title" class="text-lg font-bold text-slate-800">注文書作成</h2>
        <button type="button" data-close-order-modal class="rounded p-2 text-slate-500 hover:bg-slate-100" aria-label="閉じる">×</button>
      </div>
      <div class="grid gap-4 p-6 sm:grid-cols-2">
        <input type="hidden" name="id">
        <input type="hidden" name="anken_id">
        <input type="hidden" name="company_id">
        <fieldset class="flex flex-col justify-end">
          <div class="flex h-[42px] items-center gap-5 text-sm">
            <label class="flex items-center gap-2"><input type="radio" name="order_direction" value="in"> 受注</label>
            <label class="flex items-center gap-2"><input type="radio" name="order_direction" value="out"> 発注</label>
          </div>
        </fieldset>
        <label class="text-sm font-semibold text-slate-700">案件ID
          <input name="anken_id_display" readonly class="order-input bg-slate-50">
        </label>
        <label class="sm:col-span-2 text-sm font-semibold text-slate-700">案件名 <span class="text-red-600">*</span>
          <input name="anken_name" required maxlength="255" class="order-input">
        </label>
        <div class="relative">
          <label class="text-sm font-semibold text-slate-700" for="order-company-search">取引先 <span class="text-red-600">*</span></label>
          <input id="order-company-search" autocomplete="off" required class="order-input" placeholder="会社名を入力して選択">
          <div id="company-suggestions" class="absolute z-20 mt-1 hidden max-h-52 w-full overflow-auto border border-slate-200 bg-white shadow-lg"></div>
        </div>
        <label class="text-sm font-semibold text-slate-700">担当者
          <select name="contact_id" disabled class="order-input"><option value="">会社を選択してください</option></select>
        </label>
        <label class="text-sm font-semibold text-slate-700">作業者
          <input name="worker_name" maxlength="100" class="order-input">
        </label>
        <label class="text-sm font-semibold text-slate-700">見積書
          <input name="estimate" maxlength="100" class="order-input">
        </label>
        <label class="text-sm font-semibold text-slate-700">注文番号
          <input name="order_no" maxlength="100" class="order-input">
        </label>
        <label class="text-sm font-semibold text-slate-700">継続確認
          <select name="renewal_status" class="order-input">
            <option value="pending">要確認</option><option value="checking">確認中</option><option value="renewed">更新済</option><option value="terminated">終了</option>
          </select>
        </label>
        <label class="text-sm font-semibold text-slate-700">契約開始日
          <input type="date" name="start_date" class="order-input">
        </label>
        <label class="text-sm font-semibold text-slate-700">契約終了日
          <input type="date" name="end_date" class="order-input">
        </label>
        <label class="text-sm font-semibold text-slate-700">単価
          <input type="number" name="unit_price" step="1" class="order-input">
        </label>
        <label class="text-sm font-semibold text-slate-700">総額
          <input type="number" name="total_amount" step="1" class="order-input">
        </label>
        <label class="text-sm font-semibold text-slate-700">精算幅（下限）
          <input type="number" name="range_min" step="1" class="order-input">
        </label>
        <label class="text-sm font-semibold text-slate-700">精算幅（上限）
          <input type="number" name="range_max" step="1" class="order-input">
        </label>
        <label class="text-sm font-semibold text-slate-700">時間単位
          <input type="number" name="time_unit" step="1" class="order-input">
        </label>
        <label class="text-sm font-semibold text-slate-700">支払サイト
          <input name="payment_site" maxlength="50" class="order-input">
        </label>
        <label class="sm:col-span-2 text-sm font-semibold text-slate-700">注文書ファイル
          <input type="file" name="order_file" id="order-file" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg" class="order-input">
          <span id="order-file-current" class="mt-1 block break-all text-xs font-normal text-slate-500">未選択</span>
        </label>
        <label class="sm:col-span-2 text-sm font-semibold text-slate-700">メモ
          <textarea name="memo" rows="3" class="order-input"></textarea>
        </label>
        <p id="order-form-error" class="hidden sm:col-span-2 text-sm text-red-700" role="alert"></p>
      </div>
      <div class="sticky bottom-0 flex justify-end gap-3 border-t border-slate-200 bg-white px-6 py-4">
        <button type="button" data-close-order-modal class="rounded border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">キャンセル</button>
        <button type="submit" id="order-save-button" class="rounded bg-blue-600 px-5 py-2 text-sm font-bold text-white hover:bg-blue-700">保存</button>
      </div>
    </form>
  </div>
  <div id="order-context-menu" class="fixed z-[110] hidden border border-slate-200 bg-white py-1 shadow-xl">
    <button type="button" id="duplicate-order-button" class="px-4 py-2 text-left text-sm font-semibold text-slate-700 hover:bg-slate-100">この案件を複製</button>
  </div>

  <script>
    const orderModal = document.querySelector('#order-modal');
    const orderForm = document.querySelector('#order-form');
    const contextMenu = document.querySelector('#order-context-menu');
    let contextOrder = null;
    let selectedCompany = null;
    let pendingContactLoad = Promise.resolve();
    let contactLoadError = false;
    let latestNextAnkenId = 1;
    let activeOrderMode = 'create';
    let totalCalculationLocked = false;

    function escapeHtml(value) {
      return String(value ?? '').replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]);
    }

    function displayAnkenNumber(value) {
      return String(value ?? '').replace(/^0+(?=\d)/, '');
    }

    async function parseApiResponse(response, endpoint) {
      const body = await response.text();
      if (!body.trim()) throw new Error(`${endpoint} の応答が空です（HTTP ${response.status}）。`);
      try {
        return JSON.parse(body);
      } catch (error) {
        const contentType = response.headers.get('content-type') || '不明';
        throw new Error(`${endpoint} の応答がJSONではありません（HTTP ${response.status}, ${contentType}）。`);
      }
    }

    function fileUrl(path) {
      if (!path) return '';
      const normalized = String(path).replace(/^(\.\.\/|\.\/)+/, '').replace(/^\/+/, '');
      const safePath = normalized.split('/').filter(part => part && part !== '.' && part !== '..').map(encodeURIComponent).join('/');
      return safePath ? `/${safePath}` : '';
    }

    function integerValue(value, fallback = '') {
      if (value === null || value === undefined || value === '') return fallback;
      const number = Number(value);
      return Number.isFinite(number) ? Math.trunc(number) : fallback;
    }

    function updateOrderTotal() {
      if (totalCalculationLocked) return;
      const startValue = orderForm.elements.namedItem('start_date').value;
      const endValue = orderForm.elements.namedItem('end_date').value;
      const unitPriceValue = orderForm.elements.namedItem('unit_price').value;
      const unitPrice = Number(unitPriceValue);
      const totalAmount = orderForm.elements.namedItem('total_amount');
      if (!startValue || !endValue || unitPriceValue === '' || !Number.isFinite(unitPrice)) {
        totalAmount.value = '';
        return;
      }
      const start = new Date(`${startValue}T00:00:00`);
      const end = new Date(`${endValue}T00:00:00`);
      const monthCount = (end.getFullYear() - start.getFullYear()) * 12 + end.getMonth() - start.getMonth() + 1;
      if (monthCount < 1) return;
      orderForm.elements.namedItem('total_amount').value = Math.trunc(unitPrice * monthCount);
    }

    function nextDate(value) {
      const date = new Date(`${value}T00:00:00Z`);
      date.setUTCDate(date.getUTCDate() + 1);
      return date.toISOString().slice(0, 10);
    }

    function orderPeriodsOverlap(leftOrder, rightOrder) {
      if (!leftOrder.start_date || !leftOrder.end_date || !rightOrder.start_date || !rightOrder.end_date) return false;
      return leftOrder.start_date <= rightOrder.end_date && rightOrder.start_date <= leftOrder.end_date;
    }

    function isOrderPeriodCovered(upperOrder, lowerOrders) {
      const periodStart = upperOrder.start_date;
      const periodEnd = upperOrder.end_date;
      if (!periodStart || !periodEnd || periodStart > periodEnd) return false;

      const periods = lowerOrders
        .map(order => ({ start: order.start_date, end: order.end_date }))
        .filter(period => period.start && period.end && period.start <= period.end)
        .filter(period => orderPeriodsOverlap(
          { start_date: periodStart, end_date: periodEnd },
          { start_date: period.start, end_date: period.end }
        ))
        .sort((left, right) => left.start.localeCompare(right.start));

      let coveredThrough = null;
      for (const period of periods) {
        const start = period.start < periodStart ? periodStart : period.start;
        const end = period.end > periodEnd ? periodEnd : period.end;
        if (coveredThrough === null) {
          if (start > periodStart) return false;
          coveredThrough = end;
        } else if (start > nextDate(coveredThrough)) {
          return false;
        } else if (end > coveredThrough) {
          coveredThrough = end;
        }
        if (coveredThrough >= periodEnd) return true;
      }
      return false;
    }

    function orderNumberMarkup(item) {
      const hasOrderNumber = item.orderNo && item.orderNo !== '—';
      const label = hasOrderNumber
        ? `${item.filePath ? '' : '(仮) '}${item.orderNo}`
        : '（注文書なし）';
      const fileButton = item.filePath
        ? `<button type="button" class="open-order-file text-slate-500 hover:text-blue-700" data-order-id="${escapeHtml(item.order.id)}" aria-label="注文書ファイルを開く" title="注文書ファイルを開く"><i class="fa-solid fa-file-lines" aria-hidden="true"></i></button>`
        : '';
      return `<span class="inline-flex items-center gap-1">${fileButton}<button type="button" class="order-number text-blue-700 hover:underline font-medium" data-order-id="${escapeHtml(item.order.id)}">${escapeHtml(label)}</button></span>`;
    }

    function openOrderModal(order = {}, mode = 'create') {
      orderForm.reset();
      activeOrderMode = mode;
      selectedCompany = null;
      const set = (name, value) => { orderForm.elements.namedItem(name).value = value ?? ''; };
      set('id', mode === 'edit' ? order.id : '');
      set('anken_id', order.anken_id ?? '');
      set('anken_id_display', order.anken_id ?? latestNextAnkenId);
      set('anken_name', order.anken_name ?? '');
      set('worker_name', order.worker_name);
      set('estimate', order.estimate);
      set('order_no', mode === 'duplicate' ? '' : order.order_no);
      set('start_date', order.start_date);
      set('end_date', order.end_date);
      set('renewal_status', mode === 'duplicate' ? 'pending' : (order.renewal_status ?? 'pending'));
      set('unit_price', integerValue(order.unit_price));
      set('total_amount', integerValue(order.total_amount));
      totalCalculationLocked = mode === 'edit' || order.total_amount !== null && order.total_amount !== undefined && order.total_amount !== '';
      set('range_min', integerValue(order.range_min, mode === 'edit' ? '' : 140));
      set('range_max', integerValue(order.range_max, mode === 'edit' ? '' : 180));
      set('time_unit', integerValue(order.time_unit, mode === 'edit' ? '' : 30));
      set('payment_site', order.payment_site);
      set('memo', order.memo);
      orderForm.querySelector(`[name="order_direction"][value="${order.order_direction ?? 'in'}"]`).checked = true;
      document.querySelector('#order-company-search').value = order.company_name ?? '';
      const currentFileName = (order.file_path ?? '').split('/').pop();
      document.querySelector('#order-file-current').textContent = mode === 'duplicate' ? '新しい注文書を選択してください' : (currentFileName || '未選択');
      document.querySelector('#order-modal-title').textContent = mode === 'edit' ? '注文書編集' : (mode === 'duplicate' ? '注文書を複製' : '注文書作成');
      document.querySelector('#order-form-error').classList.add('hidden');
      if (order.company_id) selectCompany({ id: order.company_id, company_name: order.company_name ?? '' }, order.contact_id);
      updateOrderTotal();
      orderModal.classList.remove('hidden');
      orderModal.classList.add('flex');
      document.querySelector('#company-suggestions').classList.add('hidden');
    }

    function closeOrderModal() {
      orderModal.classList.add('hidden');
      orderModal.classList.remove('flex');
    }

    function selectCompany(company, contactId = '') {
      selectedCompany = company;
      orderForm.elements.namedItem('company_id').value = company.id;
      document.querySelector('#order-company-search').value = company.company_name;
      const contactSelect = orderForm.elements.namedItem('contact_id');
      contactSelect.disabled = true;
      contactSelect.innerHTML = '<option value="">読み込み中...</option>';
      contactLoadError = false;
      document.querySelector('#company-suggestions').classList.add('hidden');
      pendingContactLoad = (async () => {
      try {
        const response = await fetch(`/api/get_contacts.php?company_id=${encodeURIComponent(company.id)}&contacts=1`);
        const result = await parseApiResponse(response, 'get_contacts.php');
        const contacts = result.data ?? [];
        contactSelect.innerHTML = '<option value="">担当者を選択（任意）</option>' + contacts.map(contact => {
          const name = `${contact.last_name ?? ''} ${contact.first_name ?? ''}`.trim();
          return `<option value="${escapeHtml(contact.id)}">${escapeHtml(name)}</option>`;
        }).join('');
        contactSelect.disabled = false;
        contactSelect.value = contactId ?? '';
      } catch (error) {
        contactLoadError = true;
        contactSelect.innerHTML = '<option value="">担当者を取得できません</option>';
      }
      })();
      return pendingContactLoad;
    }

    document.querySelector('#create-order-button').addEventListener('click', () => openOrderModal({}, 'create'));
    document.querySelectorAll('[data-close-order-modal]').forEach(button => button.addEventListener('click', closeOrderModal));
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape') { closeOrderModal(); contextMenu.classList.add('hidden'); }
    });
    ['start_date', 'end_date', 'unit_price'].forEach(name => {
      orderForm.elements.namedItem(name).addEventListener('input', updateOrderTotal);
      orderForm.elements.namedItem(name).addEventListener('change', updateOrderTotal);
    });
    orderForm.elements.namedItem('total_amount').addEventListener('input', () => {
      totalCalculationLocked = activeOrderMode === 'edit' || orderForm.elements.namedItem('total_amount').value !== '';
    });
    document.querySelector('#order-company-search').addEventListener('input', async event => {
      const query = event.target.value.trim();
      selectedCompany = null;
      orderForm.elements.namedItem('company_id').value = '';
      const contactSelect = orderForm.elements.namedItem('contact_id');
      contactSelect.disabled = true;
      contactSelect.innerHTML = '<option value="">会社を選択してください</option>';
      const suggestions = document.querySelector('#company-suggestions');
      if (!query) { suggestions.classList.add('hidden'); return; }
      try {
        const response = await fetch(`/api/get_contacts.php?company_search=${encodeURIComponent(query)}&limit=20`);
        const result = await parseApiResponse(response, 'get_contacts.php');
        suggestions.replaceChildren();
        (result.data ?? []).forEach(company => {
          const option = document.createElement('button');
          option.type = 'button';
          option.className = 'block w-full px-3 py-2 text-left text-sm hover:bg-blue-50';
          option.textContent = company.company_name;
          option.addEventListener('click', () => selectCompany(company));
          suggestions.append(option);
        });
        suggestions.classList.toggle('hidden', suggestions.childElementCount === 0);
      } catch (error) { suggestions.classList.add('hidden'); }
    });
    document.querySelector('#order-file').addEventListener('change', event => {
      document.querySelector('#order-file-current').textContent = event.target.files[0]?.name ?? '未選択';
    });
    orderForm.addEventListener('submit', async event => {
      event.preventDefault();
      if (!selectedCompany || !orderForm.elements.namedItem('company_id').value) {
        document.querySelector('#order-form-error').textContent = '取引先を候補から選択してください。';
        document.querySelector('#order-form-error').classList.remove('hidden');
        return;
      }
      if (contactLoadError) {
        document.querySelector('#order-form-error').textContent = '担当者を取得できませんでした。取引先を選び直してください。';
        document.querySelector('#order-form-error').classList.remove('hidden');
        return;
      }
      const saveButton = document.querySelector('#order-save-button');
      const errorMessage = document.querySelector('#order-form-error');
      saveButton.disabled = true;
      saveButton.textContent = '保存中...';
      errorMessage.classList.add('hidden');
      try {
        await pendingContactLoad;
        const response = await fetch('/api/save_orders.php', { method: 'POST', body: new FormData(orderForm) });
        const result = await parseApiResponse(response, 'save_orders.php');
        if (!response.ok || result.status !== 'success') throw new Error(result.message ?? '保存に失敗しました。');
        closeOrderModal();
        await loadOrders();
      } catch (error) {
        errorMessage.textContent = error.message;
        errorMessage.classList.remove('hidden');
      } finally {
        saveButton.disabled = false;
        saveButton.textContent = '保存';
      }
    });
    document.addEventListener('click', event => {
      if (!contextMenu.contains(event.target)) contextMenu.classList.add('hidden');
    });
    document.querySelector('#duplicate-order-button').addEventListener('click', () => {
      contextMenu.classList.add('hidden');
      if (!contextOrder) return;
      openOrderModal({ ...contextOrder.order, company_name: contextOrder.company }, 'duplicate');
    });

    async function loadOrders() {
      try {
        const response = await fetch('/api/get_orders.php');
        const result = await parseApiResponse(response, 'get_orders.php');
        latestNextAnkenId = Number(result.next_anken_id) || 1;
        
        const container = document.querySelector('#order-list-container');
        
        if (!result || result.status !== 'success' || !result.data || result.data.length === 0) {
          container.innerHTML = `
            <div class="py-8 text-center text-slate-400">
              <p class="text-sm">注文データはまだ登録されていません</p>
            </div>
          `;
          return;
        }

        const today = new Date();
        const todayStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
        const showPastToggle = document.querySelector('#show-past-toggle');
        const showPast = showPastToggle ? showPastToggle.checked : false;

        let html = '';
        let displayCount = 0;
        
        result.data.forEach(row => {
          const upperOrders = row.upper_list ?? [];
          const lowerOrders = row.lower_list ?? [];
          const uncoveredUpper = upperOrders.find(upper => !isOrderPeriodCovered(
            upper.order,
            lowerOrders.map(lower => lower.order)
          ));
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
              <h3 title="案件番号: ${escapeHtml(displayAnkenNumber(row.id))}" class="text-[15px] font-bold ${isPast ? 'text-slate-500' : 'text-slate-800'}">${escapeHtml(row.project)} ${isPast ? '<span class="text-xs font-normal text-slate-400">（終了）</span>' : ''}</h3>
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
              const isSameOrderNo = (index > 0 && upper.orderNo !== '—' && upper.orderNo === lastUpperOrderNo);

              const displayCompany = isSameCompany ? '' : `<span class="mini-label ${isPast ? 'muted' : ''}">案件</span>${escapeHtml(upper.company)}`;
              
              const displayOrderNo = isSameOrderNo ? '' : orderNumberMarkup(upper);

              lastUpperCompany = upper.company;
              lastUpperOrderNo = upper.orderNo;

              html += `
                <tr class="case-row ${isPast ? 'bg-slate-100/60' : 'row-upper'}">
                  <td><div class="stack-cell"><div class="stack-row">${displayCompany}</div></div></td>
                  <td><div class="stack-cell"><div class="stack-row">${displayOrderNo}</div></div></td>
                  <td><div class="stack-cell"><div class="stack-row">${escapeHtml(upper.period)}</div></div></td>
                  <td><div class="stack-cell"><div class="stack-row">\\${escapeHtml(upper.quote)}</div></div></td>
                  <td><div class="stack-cell"><div class="stack-row">${escapeHtml(upper.person)}</div></div></td>
                  <td><div class="stack-cell"><div class="stack-row">${escapeHtml(upper.estimate)}</div></div></td>
                  <td><div class="stack-cell"><div class="stack-row">${escapeHtml(upper.status)}</div></div></td>
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
              const isSameOrderNo = (index > 0 && lower.orderNo !== '—' && lower.orderNo === lastLowerOrderNo);

              const displayCompany = isSameCompany ? '' : `<span class="mini-label muted">要員</span>${escapeHtml(lower.company)}`;
              
              const displayOrderNo = isSameOrderNo ? '' : orderNumberMarkup(lower);

              lastLowerCompany = lower.company;
              lastLowerOrderNo = lower.orderNo;

              html += `
                <tr class="case-row ${isPast ? 'bg-slate-100/40' : 'row-lower'}">
                  <td><div class="stack-cell"><div class="stack-row">${displayCompany}</div></div></td>
                  <td><div class="stack-cell"><div class="stack-row">${displayOrderNo}</div></div></td>
                  <td><div class="stack-cell"><div class="stack-row">${escapeHtml(lower.period)}</div></div></td>
                  <td><div class="stack-cell"><div class="stack-row">\\${escapeHtml(lower.quote)}</div></div></td>
                  <td><div class="stack-cell"><div class="stack-row">${escapeHtml(lower.person)}</div></div></td>
                  <td><div class="stack-cell"><div class="stack-row">${escapeHtml(lower.estimate)}</div></div></td>
                  <td><div class="stack-cell"><div class="stack-row">${escapeHtml(lower.status)}</div></div></td>
                </tr>
              `;
            });
          }

          if (uncoveredUpper) {
            const hasOutOrderInUncoveredPeriod = lowerOrders.some(lower => orderPeriodsOverlap(
              uncoveredUpper.order,
              lower.order
            ));
            html += `
              <tr class="case-row bg-slate-50/80 border-dashed">
                <td colspan="7">
                  <div class="py-2 px-3 text-center text-slate-500 text-xs flex items-center justify-center gap-2">
                    ${!hasOutOrderInUncoveredPeriod ? '<span>所属への注文データはまだ登録されていません</span>' : ''}
                    <button type="button" class="add-out-order text-blue-700 hover:underline font-bold bg-white px-3 py-1.5 rounded border border-slate-200" data-anken-id="${escapeHtml(row.id)}" data-anken-name="${escapeHtml(row.project)}" data-start-date="${escapeHtml(uncoveredUpper.order.start_date)}" data-end-date="${escapeHtml(uncoveredUpper.order.end_date)}">＋ 発注を追加</button>
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
        const allOrders = (result.data ?? []).flatMap(project => [...(project.upper_list ?? []), ...(project.lower_list ?? [])]);
        container.querySelectorAll('.add-out-order').forEach(button => button.addEventListener('click', () => {
          openOrderModal({
            anken_id: button.dataset.ankenId,
            anken_name: button.dataset.ankenName,
            start_date: button.dataset.startDate,
            end_date: button.dataset.endDate,
            order_direction: 'out'
          }, 'create');
        }));
        container.querySelectorAll('.order-number').forEach(button => {
          const order = allOrders.find(item => String(item.order.id) === button.dataset.orderId);
          if (!order) return;
          button.addEventListener('click', event => {
            event.preventDefault();
            openOrderModal({ ...order.order, company_name: order.company }, 'edit');
          });
          button.addEventListener('contextmenu', event => {
            event.preventDefault();
            contextOrder = order;
            contextMenu.style.left = `${Math.min(event.clientX, window.innerWidth - 190)}px`;
            contextMenu.style.top = `${Math.min(event.clientY, window.innerHeight - 60)}px`;
            contextMenu.classList.remove('hidden');
          });
        });
        container.querySelectorAll('.open-order-file').forEach(button => {
          const order = allOrders.find(item => String(item.order.id) === button.dataset.orderId);
          if (!order?.filePath) return;
          button.addEventListener('click', event => {
            event.stopPropagation();
            window.open(fileUrl(order.filePath), '_blank', 'noopener,noreferrer');
          });
        });

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
    .order-input { display: block; width: 100%; margin-top: 0.35rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; padding: 0.55rem 0.7rem; font-size: 0.875rem; font-weight: 400; color: #0f172a; }
    .order-input:focus { outline: 2px solid #2563eb; outline-offset: 1px; }
  </style>

<?php
require_once '../footer.php';
?>