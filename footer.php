</div><!-- /.flex-grow の閉じタグ -->

    <!-- モーダルウィンドウ（Layout.astroのものをそのまま移行） -->
    <div id="extractModal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-[2px] z-[200] flex items-center justify-center hidden">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6 border border-slate-200">
            <div class="flex justify-between items-center mb-4">
                <h4 id="extractModalTitle" class="font-bold text-slate-700">会社を選択</h4>
                <button onclick="closeExtractModal()" class="text-slate-400 hover:text-slate-600"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="relative mb-4">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input type="text" id="extractSearchInput" placeholder="会社名を検索..." class="w-full pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-lg text-sm outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div id="extractResultList" class="max-h-60 overflow-y-auto space-y-1 mb-4 border-t pt-2"></div>
            <div class="border-t pt-4">
                <button id="noSelectionBtn" onclick="executeExtract(currentExtractType, null)" class="w-full py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg text-sm font-bold transition-colors">
                    会社を選択せずにリストを作成
                </button>
            </div>
        </div>
    </div>

    <footer class="py-10 border-t bg-white">
      <div class="text-center text-xs text-gray-400 tracking-[0.2em]">
        &copy; 2015 SBT-INC. ALL RIGHTS RESERVED.
      </div>
    </footer>

    <script>
        // Layout.astro にあったJavaScript機能をそのまま移植
        (async () => {
            try {
                const res = await fetch('/api/get_contacts.php?limit=1', { 
                    credentials: 'include',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                });
                const result = await res.json();
                if (result.status === 'success' && result.user_name) {
                    const el = document.getElementById('display_user_name');
                    if (el) el.textContent = result.user_name + ' 様';
                }
            } catch (e) {
                const el = document.getElementById('display_user_name');
                if (el) el.textContent = 'GUEST USER';
            }
        })();

        window.currentExtractType = '';
        let currentTargetParticipantId = null; 
        let isLinkingMode = false;            

        window.openExtractModal = (type) => {
            isLinkingMode = false;
            window.currentExtractType = type;
            const modal = document.getElementById('extractModal');
            document.getElementById('noSelectionBtn').parentElement.style.display = 'block';
            document.getElementById('extractModalTitle').textContent = type === 'send_project' ? '案件配信：元会社を選択' : '技術者配信：元会社を選択';
            modal.classList.remove('hidden');
            document.getElementById('extractSearchInput').value = '';
            document.getElementById('extractResultList').innerHTML = '<p class="text-center text-xs text-slate-400 py-4">会社名を入力してください</p>';
            setTimeout(() => document.getElementById('extractSearchInput').focus(), 100);
        };

        window.openLinkCompanyModal = (participantId) => {
            currentTargetParticipantId = participantId;
            isLinkingMode = true; 
            const modal = document.getElementById('extractModal');
            document.getElementById('noSelectionBtn').parentElement.style.display = 'none';
            document.getElementById('extractModalTitle').textContent = 'CRM会社と連携';
            modal.classList.remove('hidden');
            document.getElementById('extractSearchInput').value = '';
            document.getElementById('extractResultList').innerHTML = '<p class="text-center text-xs text-slate-400 py-4">会社名を入力してください</p>';
            setTimeout(() => document.getElementById('extractSearchInput').focus(), 100);
        };

        window.closeExtractModal = () => {
            document.getElementById('extractModal').classList.add('hidden');
        };

        document.getElementById('extractSearchInput').addEventListener('input', async (e) => {
            const keyword = e.target.value.trim();
            const listEl = document.getElementById('extractResultList');
            if (keyword.length < 2) return;
            try {
                const res = await fetch(`/api/get_contacts.php?q=${encodeURIComponent(keyword)}&limit=50`, { credentials: 'include' });
                const result = await res.json();
                listEl.innerHTML = '';
                const seen = new Set();
                if (result.status === 'success' && result.data) {
                    result.data.forEach(item => {
                        const cId = item.crm_company_id; 
                        const cName = item.crm_company_company_name;
                        if (cId && !seen.has(cId)) {
                            const btn = document.createElement('button');
                            btn.className = 'w-full text-left px-3 py-2 text-sm hover:bg-blue-50 rounded-lg transition-colors flex justify-between items-center group';
                            btn.innerHTML = `<span>${cName}</span><i class="fa-solid fa-chevron-right text-[10px] text-slate-300 group-hover:text-blue-500"></i>`;
                            btn.onclick = () => {
                                if (isLinkingMode) {
                                    saveParticipantLink(currentTargetParticipantId, cId);
                                } else {
                                    window.executeExtract(window.currentExtractType, cId);
                                }
                            };
                            listEl.appendChild(btn);
                            seen.add(cId);
                        }
                    });
                }
            } catch (e) { console.error("検索エラー", e); }
        });

        window.executeExtract = async (type, excludeId = null) => {
            try {
                let url = `/api/extract_emails.php?type=${type}`;
                if (excludeId) url += `&exclude_company_id=${excludeId}`;
                const res = await fetch(url, { credentials: 'include' });
                const result = await res.json();
                if (result.status === 'success') {
                    if (!result.data || result.data.trim() === "") {
                        alert('対象者が0件でした。');
                    } else {
                        await navigator.clipboard.writeText(result.data);
                        alert('クリップボードにコピーしました！\n（' + result.data.split('\n').length + '件）');
                    }
                    window.closeExtractModal();
                }
            } catch (e) { alert('抽出エラーが発生しました。'); }
        };

        window.handleEmailExtract = (type) => {
            if (type === 'send_event') window.executeExtract(type);
            else window.openExtractModal(type);
        };

        async function saveParticipantLink(pId, coId) {
            const formData = new URLSearchParams();
            formData.append('id', pId);
            formData.append('company_id', coId);
            const res = await fetch('/api/save_participant.php', { method: 'POST', body: formData });
            const result = await res.json();
            if (result.success) location.reload();
            else alert('保存に失敗しました');
        }
    </script>
  </body>
</html>