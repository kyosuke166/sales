<?php
$page_title = "請求管理";
require_once '../header.php';
?>

  <main class="mx-auto max-w-[1500px] px-4 py-8">
    <section class="mb-6">
      <p class="mb-2 text-xs font-bold tracking-[0.24em] text-blue-600 uppercase">DOCUMENT</p>
      <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div class="flex flex-wrap items-center gap-2 rounded-full border border-slate-200 bg-white p-1 shadow-sm">
          <a href="/order/" class="rounded-full px-4 py-2 text-sm font-bold text-slate-600 hover:bg-slate-100">注文管理</a>
          <a href="/order/invoice.php" class="rounded-full px-4 py-2 text-sm font-bold bg-blue-600 text-white shadow-sm">請求管理</a>
        </div>
      </div>
    </section>

    <div class="space-y-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-soft">
      <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <p class="text-xs font-bold tracking-[0.2em] text-slate-400 uppercase">document</p>
          <h2 class="text-[19px] font-semibold text-slate-700">請求書（請求内訳）</h2>
        </div>
        <label class="flex items-center gap-2 cursor-pointer text-sm font-medium text-slate-600 select-none">
          <input type="checkbox" id="show-past-toggle" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 h-4 w-4" onchange="loadInvoices()">
          <span>終了した案件を表示する</span>
        </label>
      </div>

      <div id="invoice-list-container">
        <div class="py-8 text-center text-slate-400">
          <p class="text-sm">読み込み中...</p>
        </div>
      </div>
    </div>
  </main>

  <div id="invoice-modal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-slate-950/50 p-4" role="dialog" aria-modal="true" aria-labelledby="invoice-modal-title">
    <form id="invoice-form" class="max-h-[92vh] w-full max-w-3xl overflow-y-auto rounded-xl bg-white shadow-2xl">
      <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-white px-6 py-4">
        <h2 id="invoice-modal-title" class="text-lg font-bold text-slate-800">請求書作成</h2>
        <button type="button" data-close-invoice-modal class="rounded p-2 text-slate-500 hover:bg-slate-100" aria-label="閉じる">×</button>
      </div>
      <div class="grid gap-4 p-6 sm:grid-cols-2">
        <input type="hidden" name="id">
        <input type="hidden" name="company_id">
        <input type="hidden" name="contact_id" id="invoice-contact-id">
        <label class="sm:col-span-2 text-sm font-semibold text-slate-700">対象注文 <span class="text-red-600">*</span>
          <select name="order_id" id="invoice-order-select" required class="invoice-input"><option value="">対象注文を選択してください</option></select>
        </label>
        <div class="relative">
          <label class="text-sm font-semibold text-slate-700" for="invoice-company-search">会社名 <span class="text-red-600">*</span></label>
          <input id="invoice-company-search" autocomplete="off" required readonly class="invoice-input bg-slate-50" placeholder="対象注文から表示">
          <div id="invoice-company-suggestions" class="absolute z-20 mt-1 hidden max-h-52 w-full overflow-auto border border-slate-200 bg-white shadow-lg"></div>
        </div>
        <label class="text-sm font-semibold text-slate-700">担当者
          <input id="invoice-contact-select" readonly disabled class="invoice-input bg-slate-50" placeholder="対象注文から表示">
        </label>
        <label class="text-sm font-semibold text-slate-700">稼働月 <span class="text-red-600">*</span>
          <input type="month" name="target_month" required class="invoice-input">
        </label>
        <label class="text-sm font-semibold text-slate-700">請求番号
          <input name="invoice_no" maxlength="100" class="invoice-input">
        </label>
        <label class="text-sm font-semibold text-slate-700">稼働時間
          <input name="work_hours" maxlength="10" aria-describedby="invoice-work-hours-warning" class="invoice-input">
          <span id="invoice-work-hours-warning" class="hidden mt-1 block text-xs font-normal text-red-600" role="alert">稼働時間は「時間:分」形式で入力してください。小数形式は使用できません。</span>
        </label>
        <label class="text-sm font-semibold text-slate-700">精算幅
          <input name="hour_range" maxlength="50" class="invoice-input">
        </label>
        <label class="text-sm font-semibold text-slate-700">単金（税抜き）
          <input type="number" name="unit_price" min="-99999999.99" max="99999999.99" step="0.01" class="invoice-input">
        </label>
        <label class="text-sm font-semibold text-slate-700">精算（税抜き）
          <input type="number" name="adjustment" min="-99999999.99" max="99999999.99" step="0.01" class="invoice-input">
        </label>
        <label class="text-sm font-semibold text-slate-700">実費（税抜き）
          <input type="number" name="expense" min="-99999999.99" max="99999999.99" step="0.01" class="invoice-input">
        </label>
        <label class="text-sm font-semibold text-slate-700">消費税額
          <input type="number" name="tax_amount" min="-99999999.99" max="99999999.99" step="0.01" class="invoice-input">
        </label>
        <label class="text-sm font-semibold text-slate-700">実費（税込み）
          <input type="number" name="expense_inc" min="-99999999.99" max="99999999.99" step="0.01" class="invoice-input">
        </label>
        <label class="text-sm font-semibold text-slate-700">合計額 <span class="text-red-600">*</span>
          <input type="number" name="total_amount" min="-99999999.99" max="99999999.99" step="0.01" required class="invoice-input">
        </label>
        <label class="text-sm font-semibold text-slate-700"><span id="invoice-due-date-label">入金</span>予定日
          <input type="date" name="due_date" class="invoice-input">
        </label>
        <label class="text-sm font-semibold text-slate-700"><span id="invoice-completed-date-label">入金</span>日
          <input type="date" name="completed_date" class="invoice-input">
        </label>
        <fieldset class="sm:col-span-2">
          <legend class="text-sm font-semibold text-slate-700">帳簿登録状況</legend>
          <div class="mt-2 flex flex-wrap gap-6 text-sm text-slate-700">
            <label class="flex items-center gap-2"><input type="checkbox" name="yayoi_kakekin" value="1" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"><span id="invoice-kakekin-label">売掛金</span></label>
            <label class="flex items-center gap-2"><input type="checkbox" name="yayoi_furikae" value="1" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">振替</label>
          </div>
        </fieldset>
        <label class="sm:col-span-2 text-sm font-semibold text-slate-700">請求書ファイル
          <input type="file" name="invoice_file" id="invoice-file" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg" class="invoice-input">
          <span id="invoice-file-current" class="mt-1 block break-all text-xs font-normal text-slate-500">未選択</span>
        </label>
        <p id="invoice-form-error" class="hidden sm:col-span-2 text-sm text-red-700" role="alert"></p>
      </div>
      <div class="sticky bottom-0 flex justify-end gap-3 border-t border-slate-200 bg-white px-6 py-4">
        <button type="button" data-close-invoice-modal class="rounded border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700">キャンセル</button>
        <button type="submit" id="invoice-save-button" class="rounded bg-blue-600 px-5 py-2 text-sm font-bold text-white hover:bg-blue-700">保存</button>
      </div>
    </form>
  </div>

  <script>
    const invoiceModal = document.querySelector('#invoice-modal');
    const invoiceForm = document.querySelector('#invoice-form');
    let invoiceRows = [];
    let activeInvoiceScope = [];
    let selectedInvoiceCompany = null;
    let pendingInvoiceContactLoad = Promise.resolve();
    let invoiceContactLoadError = false;

    function escapeHtml(value) {
      return String(value ?? '').replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]);
    }

    async function parseApiResponse(response, endpoint) {
      const body = await response.text();
      if (!body.trim()) throw new Error(`${endpoint} の応答が空です（HTTP ${response.status}）。`);
      try {
        return JSON.parse(body);
      } catch (error) {
        throw new Error(`${endpoint} の応答がJSONではありません（HTTP ${response.status}）。`);
      }
    }

    function invoiceFileUrl(path) {
      if (!path) return '';
      const normalized = String(path).replace(/^(\.\.\/|\.\/)+/, '').replace(/^\/+/, '');
      const safePath = normalized.split('/').filter(part => part && part !== '.' && part !== '..').map(encodeURIComponent).join('/');
      return safePath ? `/${safePath}` : '';
    }

    function displayDecimal(value) {
      if (value === null || value === undefined || value === '') return '';
      const number = Number(value);
      if (!Number.isFinite(number)) return String(value);
      return Number.isInteger(number) ? String(number) : String(number);
    }

    function formatInvoiceMoney(value) {
      const amount = Number(value);
      return `\\${Number.isFinite(amount) ? Math.trunc(amount).toLocaleString('ja-JP') : value}`;
    }

    function invoiceAmountMarkup(item) {
      const invoice = item.invoice;
      if (!item.hasInvoice || !invoice || item.totalAmount === '—') return escapeHtml(item.totalAmount);
      const breakdown = [
        ['単金', invoice.unit_price],
        ['精算', invoice.adjustment],
        ['実費(税抜)', invoice.expense],
        ['消費税', invoice.tax_amount],
        ['実費(税込み)', invoice.expense_inc]
      ]
        .filter(([, value]) => value !== null && value !== undefined && value !== '')
        .map(([label, value]) => `${label} ${formatInvoiceMoney(value)}`);
      const tooltip = breakdown.join(' / ');
      return tooltip
        ? `<span class="cursor-help" title="${escapeHtml(tooltip)}">${escapeHtml(item.totalAmount)}</span>`
        : escapeHtml(item.totalAmount);
    }

    function monthEndSerial(monthValue) {
      if (!/^\d{4}-\d{2}$/.test(monthValue)) return '';
      const [year, month] = monthValue.split('-').map(Number);
      const monthEnd = Date.UTC(year, month, 0);
      const excelEpoch = Date.UTC(1899, 11, 30);
      return String(Math.floor((monthEnd - excelEpoch) / 86400000)).padStart(5, '0');
    }

    function defaultInvoiceNumber(monthValue, companyId) {
      const serial = monthEndSerial(monthValue);
      const companySerial = companyId ? String(companyId).padStart(4, '0') : '';
      return serial && companySerial ? `SBT-${serial}${companySerial}` : '';
    }

    function defaultHourRange(order) {
      const minimum = displayDecimal(order.range_min);
      const maximum = displayDecimal(order.range_max);
      if (minimum && maximum) return `${minimum}h～${maximum}h`;
      return '固定';
    }

    function updateInvoiceDirectionLabels(direction) {
      const isPayment = direction === 'out';
      document.querySelector('#invoice-due-date-label').textContent = isPayment ? '支払' : '入金';
      document.querySelector('#invoice-completed-date-label').textContent = isPayment ? '支払' : '入金';
      document.querySelector('#invoice-kakekin-label').textContent = isPayment ? '買掛金' : '売掛金';
    }

    function invoiceTaxRate(monthValue) {
      return monthValue && monthValue <= '2019-10' ? 0.08 : 0.10;
    }

    function numericInvoiceValue(name) {
      const value = Number(invoiceForm.elements.namedItem(name).value);
      return Number.isFinite(value) ? value : 0;
    }

    function updateInvoiceTaxAndTotal() {
      const targetMonth = invoiceForm.elements.namedItem('target_month').value;
      const taxableAmount = numericInvoiceValue('unit_price')
        + numericInvoiceValue('adjustment')
        + numericInvoiceValue('expense');
      const tax = Math.floor(taxableAmount * invoiceTaxRate(targetMonth));
      invoiceForm.elements.namedItem('tax_amount').value = displayDecimal(tax);
      const total = numericInvoiceValue('unit_price')
        + numericInvoiceValue('adjustment')
        + numericInvoiceValue('expense')
        + numericInvoiceValue('expense_inc')
        + tax;
      invoiceForm.elements.namedItem('total_amount').value = displayDecimal(total);
    }

    function updateInvoiceTotal() {
      const total = numericInvoiceValue('unit_price')
        + numericInvoiceValue('adjustment')
        + numericInvoiceValue('expense')
        + numericInvoiceValue('expense_inc')
        + numericInvoiceValue('tax_amount');
      invoiceForm.elements.namedItem('total_amount').value = displayDecimal(total);
    }

    function updateInvoiceOrderOptions(preferredOrderId = '') {
      const orderSelect = invoiceForm.elements.namedItem('order_id');
      const matchingOrders = activeInvoiceScope
        .reduce((orders, item) => {
          if (!orders.some(order => String(order.id) === String(item.order.id))) orders.push(item.order);
          return orders;
        }, []);

      if (matchingOrders.length === 0) {
        orderSelect.innerHTML = '<option value="">この会社・担当者に対応する注文がありません</option>';
        return;
      }
      orderSelect.innerHTML = '<option value="">対象注文を選択してください</option>' + matchingOrders.map(order => {
        const period = [order.start_date, order.end_date].filter(Boolean).join(' ～ ');
        const label = [order.anken_name, order.worker_name, period].filter(Boolean).join(' / ');
        return `<option value="${escapeHtml(order.id)}">${escapeHtml(label)}</option>`;
      }).join('');
      orderSelect.value = String(preferredOrderId || matchingOrders[0].id);
    }

    function findInvoiceOrder(orderId) {
      return activeInvoiceScope
        .map(item => item.order)
        .find(order => String(order.id) === String(orderId));
    }

    function updateInvoiceDefaults(order, { updateNumber = true } = {}) {
      if (!order) return;
      document.querySelector('#invoice-company-search').value = order.company_name ?? '';
      const contactSelect = document.querySelector('#invoice-contact-select');
      contactSelect.value = order.contact_name ?? '担当者未指定';
      invoiceForm.elements.namedItem('contact_id').value = order.contact_id ?? '';
      invoiceForm.elements.namedItem('company_id').value = order.company_id ?? '';
      const targetMonth = invoiceForm.elements.namedItem('target_month').value;
      invoiceForm.elements.namedItem('hour_range').value = defaultHourRange(order);
      invoiceForm.elements.namedItem('unit_price').value = displayDecimal(order.unit_price);
      updateInvoiceDirectionLabels(order.order_direction);
      updateInvoiceTaxAndTotal();
      if (updateNumber) {
        invoiceForm.elements.namedItem('invoice_no').value = defaultInvoiceNumber(targetMonth, order.company_id);
      }
    }

    async function selectInvoiceCompany(company, contactId = '', orderId = '') {
      selectedInvoiceCompany = company;
      invoiceForm.elements.namedItem('company_id').value = company.id;
      document.querySelector('#invoice-company-search').value = company.company_name;
      const contactSelect = document.querySelector('#invoice-contact-select');
      const order = findInvoiceOrder(orderId);
      contactSelect.value = order?.contact_name || '担当者未指定';
      invoiceForm.elements.namedItem('contact_id').value = contactId ?? '';
      invoiceContactLoadError = false;
      document.querySelector('#invoice-company-suggestions').classList.add('hidden');
      updateInvoiceOrderOptions(orderId);
      return Promise.resolve();
    }

    async function openInvoiceModal(item, invoice = null) {
      const order = item.order;
      const targetMonth = invoice?.target_month || item.billingMonth || order.start_date?.slice(0, 7) || new Date().toISOString().slice(0, 7);
      activeInvoiceScope = invoiceRows.filter(candidate => candidate.projectId === item.projectId && candidate.order.order_direction === order.order_direction);
      selectedInvoiceCompany = null;
      invoiceContactLoadError = false;
      invoiceForm.reset();
      const set = (name, value) => { invoiceForm.elements.namedItem(name).value = value ?? ''; };
      set('id', invoice?.id ?? '');
      set('target_month', targetMonth);
      set('invoice_no', invoice?.invoice_no ?? defaultInvoiceNumber(targetMonth, order.company_id));
      set('work_hours', invoice?.work_hours ?? '');
      set('hour_range', invoice?.hour_range ?? defaultHourRange(order));
      set('unit_price', displayDecimal(invoice?.unit_price ?? order.unit_price));
      set('adjustment', invoice?.adjustment ?? '');
      set('expense', invoice?.expense ?? '');
      set('tax_amount', displayDecimal(invoice?.tax_amount));
      set('expense_inc', invoice?.expense_inc ?? '');
      set('total_amount', displayDecimal(invoice?.total_amount ?? order.total_amount));
      set('due_date', invoice?.due_date ?? '');
      set('completed_date', invoice?.completed_date ?? '');
      invoiceForm.elements.namedItem('yayoi_kakekin').checked = Boolean(Number(invoice?.yayoi_kakekin ?? 0));
      invoiceForm.elements.namedItem('yayoi_furikae').checked = Boolean(Number(invoice?.yayoi_furikae ?? 0));
      document.querySelector('#invoice-modal-title').textContent = invoice ? '請求書編集' : '請求書作成';
      document.querySelector('#invoice-file-current').textContent = invoice?.file_path ? invoice.file_path.split('/').pop() : '未選択';
      document.querySelector('#invoice-form-error').classList.add('hidden');
      invoiceModal.classList.remove('hidden');
      invoiceModal.classList.add('flex');
      await selectInvoiceCompany({ id: order.company_id, company_name: order.company_name }, order.contact_id ?? '', order.id);
      invoiceForm.elements.namedItem('contact_id').value = order.contact_id ?? '';
      updateInvoiceDirectionLabels(order.order_direction);
      if (!invoice) updateInvoiceDefaults(findInvoiceOrder(order.id));
    }

    function closeInvoiceModal() {
      invoiceModal.classList.add('hidden');
      invoiceModal.classList.remove('flex');
    }

    document.querySelectorAll('[data-close-invoice-modal]').forEach(button => button.addEventListener('click', closeInvoiceModal));
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape') closeInvoiceModal();
    });
    document.querySelector('#invoice-order-select').addEventListener('change', async event => {
      const order = findInvoiceOrder(event.target.value);
      if (!order) return;
      await selectInvoiceCompany({ id: order.company_id, company_name: order.company_name }, order.contact_id ?? '', order.id);
      updateInvoiceDefaults(order);
    });
    document.querySelector('#invoice-company-suggestions').classList.add('hidden');
    document.querySelector('#invoice-company-search').setAttribute('tabindex', '-1');
    document.querySelector('#invoice-contact-select').setAttribute('tabindex', '-1');
    const workHoursInput = invoiceForm.elements.namedItem('work_hours');
    const workHoursWarning = document.querySelector('#invoice-work-hours-warning');
    const clearWorkHoursWarning = () => {
      workHoursWarning.classList.add('hidden');
      workHoursInput.classList.remove('border-red-500');
      workHoursInput.removeAttribute('aria-invalid');
    };
    workHoursInput.addEventListener('input', clearWorkHoursWarning);
    workHoursInput.addEventListener('blur', () => {
      const value = workHoursInput.value.trim();
      clearWorkHoursWarning();
      if (/^\d+$/.test(value)) {
        workHoursInput.value = `${value}:00`;
      } else if (/^\d+\.\d+$/.test(value)) {
        workHoursWarning.classList.remove('hidden');
        workHoursInput.classList.add('border-red-500');
        workHoursInput.setAttribute('aria-invalid', 'true');
      }
    });
    document.querySelector('#invoice-form').elements.namedItem('target_month').addEventListener('change', event => {
      const order = findInvoiceOrder(invoiceForm.elements.namedItem('order_id').value);
      if (order) {
        invoiceForm.elements.namedItem('invoice_no').value = defaultInvoiceNumber(event.target.value, order.company_id);
        updateInvoiceTaxAndTotal();
      }
    });
    ['unit_price', 'adjustment', 'expense'].forEach(name => invoiceForm.elements.namedItem(name).addEventListener('input', updateInvoiceTaxAndTotal));
    ['tax_amount', 'expense_inc'].forEach(name => invoiceForm.elements.namedItem(name).addEventListener('input', updateInvoiceTotal));
    document.querySelector('#invoice-file').addEventListener('change', event => {
      document.querySelector('#invoice-file-current').textContent = event.target.files[0]?.name ?? '未選択';
    });
    invoiceForm.addEventListener('submit', async event => {
      event.preventDefault();
      const errorMessage = document.querySelector('#invoice-form-error');
      if (!selectedInvoiceCompany || !invoiceForm.elements.namedItem('company_id').value || !invoiceForm.elements.namedItem('order_id').value) {
        errorMessage.textContent = '会社・担当者に紐づく対象注文を選択してください。';
        errorMessage.classList.remove('hidden');
        return;
      }
      if (invoiceContactLoadError) {
        errorMessage.textContent = '担当者を取得できませんでした。会社を選び直してください。';
        errorMessage.classList.remove('hidden');
        return;
      }
      const saveButton = document.querySelector('#invoice-save-button');
      saveButton.disabled = true;
      saveButton.textContent = '保存中...';
      errorMessage.classList.add('hidden');
      try {
        await pendingInvoiceContactLoad;
        const response = await fetch('/api/save_invoices.php', { method: 'POST', body: new FormData(invoiceForm) });
        const result = await parseApiResponse(response, 'save_invoices.php');
        if (!response.ok || result.status !== 'success') throw new Error(result.message ?? '保存に失敗しました。');
        closeInvoiceModal();
        await loadInvoices();
      } catch (error) {
        errorMessage.textContent = error.message;
        errorMessage.classList.remove('hidden');
      } finally {
        saveButton.disabled = false;
        saveButton.textContent = '保存';
      }
    });

    function invoiceNumberMarkup(item) {
      if (!item.hasInvoice) {
        return `<button type="button" class="create-invoice-button rounded-full bg-blue-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-blue-700 whitespace-nowrap" data-order-id="${escapeHtml(item.orderId)}" data-target-month="${escapeHtml(item.billingMonth ?? '')}">＋ 請求書を作成</button>`;
      }
      const invoiceId = escapeHtml(item.invoice.id);
      const fileButton = item.filePath
        ? `<button type="button" class="open-invoice-file text-slate-500 hover:text-blue-700" data-invoice-id="${invoiceId}" aria-label="請求書ファイルを開く" title="請求書ファイルを開く"><i class="fa-solid fa-file-lines" aria-hidden="true"></i></button>`
        : '';
      const label = item.invoiceNo
        ? (item.filePath ? item.invoiceNo : `(仮) ${item.invoiceNo}`)
        : '（請求番号なし）';
      return `<span class="inline-flex items-center gap-1">${fileButton}<button type="button" class="edit-invoice-button text-blue-700 hover:underline font-medium" data-invoice-id="${invoiceId}">${escapeHtml(label)}</button></span>`;
    }

    function invoiceTargetMonth(item) {
      if (item.hasInvoice) return item.targetMonth;
      if (item.billingMonth) return `${item.billingMonth.slice(0, 4)}年${item.billingMonth.slice(5, 7)}月`;
      const startDate = item.order.start_date ? item.order.start_date.replaceAll('-', '/') : '—';
      const endDate = item.order.end_date ? item.order.end_date.replaceAll('-', '/') : '—';
      return `${startDate} ～ ${endDate}`;
    }

    function invoiceTargetMonthMarkup(item) {
      const workHours = item.hasInvoice ? item.invoice?.work_hours : '';
      if (!workHours) return escapeHtml(invoiceTargetMonth(item));
      const hourRange = item.invoice.hour_range || defaultHourRange(item.order);
      return `${escapeHtml(item.targetMonth)}<span class="cursor-help" title="精算幅：${escapeHtml(hourRange)}">（${escapeHtml(workHours)}）</span>`;
    }

    function orderContractMonths(order) {
      const start = /^(\d{4})-(\d{2})-\d{2}$/.exec(order.start_date ?? '');
      const end = /^(\d{4})-(\d{2})-\d{2}$/.exec(order.end_date ?? '');
      if (!start || !end || Number(start[2]) < 1 || Number(start[2]) > 12 || Number(end[2]) < 1 || Number(end[2]) > 12) return [];

      const startIndex = Number(start[1]) * 12 + Number(start[2]) - 1;
      const endIndex = Number(end[1]) * 12 + Number(end[2]) - 1;
      if (endIndex < startIndex) return [];

      const months = [];
      for (let index = startIndex; index <= endIndex; index++) {
        const year = Math.floor(index / 12);
        const month = index % 12 + 1;
        months.push(`${String(year).padStart(4, '0')}-${String(month).padStart(2, '0')}`);
      }
      return months;
    }

    function expandInvoiceItems(items) {
      const orderGroups = new Map();
      items.forEach(item => {
        const orderId = String(item.orderId);
        if (!orderGroups.has(orderId)) orderGroups.set(orderId, []);
        orderGroups.get(orderId).push(item);
      });

      return [...orderGroups.values()].flatMap(group => {
        const months = orderContractMonths(group[0].order);
        if (months.length === 0) {
          return group.map(item => ({ ...item, billingMonth: item.invoice?.target_month ?? '' }));
        }

        const invoicesByMonth = new Map();
        group.filter(item => item.hasInvoice).forEach(item => {
          const month = item.invoice?.target_month;
          if (!month) return;
          if (!invoicesByMonth.has(month)) invoicesByMonth.set(month, []);
          invoicesByMonth.get(month).push({ ...item, billingMonth: month });
        });

        const expanded = months.flatMap(month => {
          const invoices = invoicesByMonth.get(month);
          if (invoices) return invoices;
          return [{
            ...group[0],
            hasInvoice: false,
            billingMonth: month,
            targetMonth: `${month.slice(0, 4)}年${month.slice(5, 7)}月`,
            invoiceNo: '',
            filePath: '',
            totalAmount: '—',
            dueDate: '—',
            hasCompleted: false,
            kakekin: false,
            furikae: false,
            invoice: null
          }];
        });

        const outsidePeriod = group
          .filter(item => item.hasInvoice && !months.includes(item.invoice?.target_month))
          .map(item => ({ ...item, billingMonth: item.invoice?.target_month ?? '' }));
        return [...expanded, ...outsidePeriod];
      });
    }

    async function loadInvoices() {
      try {
        const response = await fetch('/api/get_invoices.php');
        const result = await response.json();
        
        const container = document.querySelector('#invoice-list-container');
        
        if (!result || result.status !== 'success' || !result.data || result.data.length === 0) {
          container.innerHTML = `
            <div class="py-8 text-center text-slate-400">
              <p class="text-sm">請求データはまだ登録されていません</p>
            </div>
          `;
          return;
        }

        result.data.forEach(row => {
          row.upper_list = expandInvoiceItems(row.upper_list ?? []);
          row.lower_list = expandInvoiceItems(row.lower_list ?? []);
        });

        const todayStr = "2026-09-01";
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
              <h3 title="案件番号: ${escapeHtml(row.id)}" class="text-[15px] font-bold ${isPast ? 'text-slate-500' : 'text-slate-800'}">${escapeHtml(row.project)} ${isPast ? '<span class="text-xs font-normal text-slate-400">（終了）</span>' : ''}</h3>
              <div class="overflow-x-auto">
                <table class="doc-table ${isPast ? 'opacity-70 bg-slate-50' : ''}">
                  <thead>
                    <tr>
                      <th style="width: 25%;">取引先</th>
                      <th style="width: 15%;">請求番号</th>
                      <th style="width: 15%;">稼働月</th>
                      <th style="width: 13%;">請求金額</th>
                      <th style="width: 10%;">作業者</th>
                      <th style="width: 9%;">予定日</th>
                      <th style="width: 8%;">状態</th>
                    </tr>
                  </thead>
                  <tbody>
          `;

          // 上位請求（上位への請求）
          if (row.upper_list && row.upper_list.length > 0) {
            let lastCompany = '';

            row.upper_list.forEach((item, index) => {
              const isSameCompany = (index > 0 && item.company === lastCompany);

              const displayCompany = isSameCompany ? '' : `<span class="mini-label ${isPast ? 'muted' : ''}">上位</span><span title="会社ID: ${escapeHtml(item.companyId)}">${escapeHtml(item.company)}</span>`;
              const displayInvoiceNo = invoiceNumberMarkup(item);

              lastCompany = item.company;

              const bankOpacity = item.hasCompleted ? 'opacity-100 text-blue-600' : 'opacity-20 text-slate-400';
              const kakekinOpacity = item.kakekin ? 'opacity-100 text-emerald-600' : 'opacity-20 text-slate-400';
              const furikaeOpacity = item.furikae ? 'opacity-100 text-indigo-600' : 'opacity-20 text-slate-400';
              const paymentLabel = item.order.order_direction === 'out' ? '支払' : '入金';
              const kakekinLabel = item.order.order_direction === 'out' ? '買掛金' : '売掛金';

              const iconsHtml = item.hasInvoice ? `
                <div class="flex items-center gap-2 text-xs font-bold">
                  <span class="${bankOpacity}" title="${paymentLabel}完了">🏦</span>
                  <span class="${kakekinOpacity} px-1.5 py-0.5 rounded border border-current" title="${kakekinLabel}登録">掛</span>
                  <span class="${furikaeOpacity} px-1.5 py-0.5 rounded border border-current" title="振替登録">替</span>
                </div>
              ` : '<span class="text-xs text-slate-400">—</span>';

              html += `
                <tr class="case-row ${isPast ? 'bg-slate-100/60' : 'row-upper'}">
                  <td><div class="stack-cell"><div class="stack-row">${displayCompany}</div></div></td>
                  <td><div class="stack-cell"><div class="stack-row">${displayInvoiceNo}</div></div></td>
                  <td><div class="stack-cell"><div class="stack-row">${invoiceTargetMonthMarkup(item)}</div></div></td>
                  <td><div class="stack-cell"><div class="stack-row amount-cell">${invoiceAmountMarkup(item)}</div></div></td>
                  <td><div class="stack-cell"><div class="stack-row">${escapeHtml(item.person)}</div></div></td>
                  <td><div class="stack-cell"><div class="stack-row">${escapeHtml(item.dueDate)}</div></div></td>
                  <td><div class="stack-cell"><div class="stack-row">${iconsHtml}</div></div></td>
                </tr>
              `;
            });
          }

          // 下位請求（所属からの被請求）
          if (row.lower_list && row.lower_list.length > 0) {
            let lastCompany = '';

            row.lower_list.forEach((item, index) => {
              const isSameCompany = (index > 0 && item.company === lastCompany);

              const displayCompany = isSameCompany ? '' : `<span class="mini-label muted">所属</span><span title="会社ID: ${escapeHtml(item.companyId)}">${escapeHtml(item.company)}</span>`;
              const displayInvoiceNo = invoiceNumberMarkup(item);

              lastCompany = item.company;

              const bankOpacity = item.hasCompleted ? 'opacity-100 text-blue-600' : 'opacity-20 text-slate-400';
              const kakekinOpacity = item.kakekin ? 'opacity-100 text-emerald-600' : 'opacity-20 text-slate-400';
              const furikaeOpacity = item.furikae ? 'opacity-100 text-indigo-600' : 'opacity-20 text-slate-400';
              const paymentLabel = item.order.order_direction === 'out' ? '支払' : '入金';
              const kakekinLabel = item.order.order_direction === 'out' ? '買掛金' : '売掛金';

              const iconsHtml = item.hasInvoice ? `
                <div class="flex items-center gap-2 text-xs font-bold">
                  <span class="${bankOpacity}" title="${paymentLabel}完了">🏦</span>
                  <span class="${kakekinOpacity} px-1.5 py-0.5 rounded border border-current" title="${kakekinLabel}登録">掛</span>
                  <span class="${furikaeOpacity} px-1.5 py-0.5 rounded border border-current" title="振替登録">替</span>
                </div>
              ` : '<span class="text-xs text-slate-400">—</span>';

              html += `
                <tr class="case-row ${isPast ? 'bg-slate-100/40' : 'row-lower'}">
                  <td><div class="stack-cell"><div class="stack-row">${displayCompany}</div></div></td>
                  <td><div class="stack-cell"><div class="stack-row">${displayInvoiceNo}</div></div></td>
                  <td><div class="stack-cell"><div class="stack-row">${invoiceTargetMonthMarkup(item)}</div></div></td>
                  <td><div class="stack-cell"><div class="stack-row amount-cell">${invoiceAmountMarkup(item)}</div></div></td>
                  <td><div class="stack-cell"><div class="stack-row">${escapeHtml(item.person)}</div></div></td>
                  <td><div class="stack-cell"><div class="stack-row">${escapeHtml(item.dueDate)}</div></div></td>
                  <td><div class="stack-cell"><div class="stack-row">${iconsHtml}</div></div></td>
                </tr>
              `;
            });
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
              <p class="text-sm">該当する請求データはありません</p>
            </div>
          `;
        }

        container.innerHTML = html;
        invoiceRows = result.data.flatMap(row => [
          ...(row.upper_list ?? []).map(item => ({ ...item, projectId: String(row.id) })),
          ...(row.lower_list ?? []).map(item => ({ ...item, projectId: String(row.id) }))
        ]);
        container.querySelectorAll('.create-invoice-button').forEach(button => button.addEventListener('click', () => {
          const item = invoiceRows.find(candidate => String(candidate.orderId) === button.dataset.orderId && !candidate.hasInvoice && candidate.billingMonth === button.dataset.targetMonth);
          if (item) openInvoiceModal(item);
        }));
        container.querySelectorAll('.edit-invoice-button').forEach(button => button.addEventListener('click', () => {
          const item = invoiceRows.find(candidate => String(candidate.invoice?.id) === button.dataset.invoiceId);
          if (item) openInvoiceModal(item, item.invoice);
        }));
        container.querySelectorAll('.open-invoice-file').forEach(button => button.addEventListener('click', () => {
          const item = invoiceRows.find(candidate => String(candidate.invoice?.id) === button.dataset.invoiceId);
          if (item?.filePath) window.open(invoiceFileUrl(item.filePath), '_blank', 'noopener,noreferrer');
        }));

      } catch (error) {
        console.error('Failed to fetch invoices:', error);
      }
    }

    loadInvoices();
  </script>

  <style>
    .doc-table { 
        width: 100%; 
        min-width: 1100px; 
        table-layout: fixed; 
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
    
    .amount-cell {
        width: 100%;
        justify-content: flex-end !important;
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    .mini-label { display: inline-flex; align-items: center; justify-content: center; min-width: 36px; padding: 0.1rem 0.4rem; border-radius: 999px; border: 1px solid #dbeafe; background: #eff6ff; color: #1d4ed8; font-size: 10px; font-weight: 700; line-height: 1.2; }
    .mini-label.muted { border-color: #e2e8f0; background: #f8fafc; color: #475569; }
    .case-row td:nth-child(n + 2) { position: relative; }
    .invoice-input { display: block; width: 100%; margin-top: 0.35rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; padding: 0.55rem 0.7rem; font-size: 0.875rem; font-weight: 400; color: #0f172a; }
    .invoice-input:focus { outline: 2px solid #2563eb; outline-offset: 1px; }
  </style>

<?php
require_once '../footer.php';
?>