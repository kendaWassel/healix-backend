<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Healix — محادثة</title>
    <style>
        * { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; }
        body { font-family: "Segoe UI", "Noto Sans Arabic", Tahoma, sans-serif; background: #f0f2f5; }
        .app { display: flex; height: 100dvh; }

        /* Sidebar / history */
        .sidebar { width: 260px; flex: none; background: #0b3b38; color: #e8f3f1;
                   display: flex; flex-direction: column; }
        .newbtn { margin: 12px; padding: 10px; border: none; border-radius: 8px; cursor: pointer;
                  background: #0d9488; color: #fff; font-size: 14px; font-family: inherit; }
        .newbtn:hover { background: #0f766e; }
        .convs { flex: 1; overflow-y: auto; padding: 0 8px 12px; }
        .conv { width: 100%; text-align: right; background: transparent; border: none; cursor: pointer;
                color: #cfe6e2; padding: 10px; border-radius: 8px; margin-bottom: 4px; font-family: inherit; }
        .conv:hover { background: rgba(255,255,255,.06); }
        .conv--active { background: rgba(255,255,255,.12); color: #fff; }
        .conv__title { font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .conv__meta { font-size: 11px; color: #8fb8b2; margin-top: 2px; }
        .convs__empty { color: #8fb8b2; font-size: 12px; padding: 10px; }

        /* Chat */
        .chat { flex: 1; display: flex; flex-direction: column; background: #fff; min-width: 0; }
        .chat__header { padding: 12px 16px; background: #0d9488; color: #fff; display: flex; align-items: center; gap: 10px; }
        .chat__header h1 { font-size: 16px; margin: 0; flex: 1; }
        .menu { display: none; background: transparent; border: none; color: #fff; font-size: 20px; cursor: pointer; }
        .chat__window { flex: 1; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 10px; }
        .msg { max-width: 80%; padding: 10px 14px; border-radius: 14px; line-height: 1.6; font-size: 15px; white-space: pre-wrap; word-wrap: break-word; }
        .msg--user { align-self: flex-start; background: #0d9488; color: #fff; border-bottom-right-radius: 4px; }
        .msg--bot  { align-self: flex-end; background: #eef1f4; color: #1f2a37; border-bottom-left-radius: 4px; }
        .msg--note { align-self: center; background: transparent; color: #6b7280; font-size: 13px; }
        .msg--typing { align-self: flex-end; color: #6b7280; letter-spacing: 4px; animation: blink 1s infinite; }
        @keyframes blink { 50% { opacity: .3; } }
        .chat__form { display: flex; align-items: flex-end; gap: 8px; padding: 10px; border-top: 1px solid #e5e7eb; }
        .chat__voice, .chat__send { flex: none; width: 44px; height: 44px; border-radius: 50%; border: none; cursor: pointer; font-size: 18px; }
        .chat__voice { background: #eef1f4; color: #0d9488; }
        .chat__voice.recording { background: #dc2626; color: #fff; animation: blink 1s infinite; }
        .chat__send { background: #0d9488; color: #fff; }
        .chat__send:disabled { background: #9ca3af; cursor: not-allowed; }
        textarea { flex: 1; resize: none; border: 1px solid #d1d5db; border-radius: 20px; padding: 11px 14px;
                   font-family: inherit; font-size: 15px; line-height: 1.5; outline: none; max-height: 120px; }
        textarea:focus { border-color: #0d9488; }

        @media (max-width: 640px) {
            .menu { display: block; }
            .sidebar { position: fixed; inset: 0 auto 0 0; z-index: 20; transform: translateX(100%); transition: transform .2s; }
            .sidebar.open { transform: none; box-shadow: -6px 0 20px rgba(0,0,0,.25); }
        }
    </style>
</head>
<body>
    <div class="app">
        <aside class="sidebar" id="sidebar">
            <button class="newbtn" id="newBtn" type="button">＋ محادثة جديدة</button>
            <div class="convs" id="convs"></div>
        </aside>

        <div class="chat">
            <div class="chat__header">
                <button class="menu" id="menuBtn" type="button">☰</button>
                <h1>Healix — محادثة</h1>
            </div>
            <div class="chat__window" id="window"></div>
            <form class="chat__form" id="form">
                <button class="chat__voice" id="voiceBtn" type="button" title="رسالة صوتية">🎤</button>
                <textarea id="input" rows="1" placeholder="اكتب رسالتك..."></textarea>
                <button class="chat__send" id="sendBtn" type="submit" title="إرسال">➤</button>
            </form>
        </div>
    </div>

    <script>
        // مسارات نسبية (same-origin) لتعمل عبر ngrok أو محلياً.
        const MSG_URL     = @json(route('interview.message', [], false));
        const VOICE_URL   = @json(route('interview.voice', [], false));
        const HISTORY_URL = @json(route('interview.history', [], false));
        const CONVS_URL   = @json(route('interview.conversations', [], false));
        const RESET_URL   = @json(route('interview.reset', [], false));
        const SELECT_URL  = @json(route('interview.select', ['conversation' => '__ID__'], false));
        const CSRF = document.querySelector('meta[name="csrf-token"]').content;
        const H = { 'Accept': 'application/json', 'ngrok-skip-browser-warning': 'true' };

        const win = document.getElementById('window');
        const convs = document.getElementById('convs');
        const form = document.getElementById('form');
        const input = document.getElementById('input');
        const sendBtn = document.getElementById('sendBtn');
        const voiceBtn = document.getElementById('voiceBtn');
        const sidebar = document.getElementById('sidebar');

        let sending = false;
        const GREETING = 'مرحباً، صف لي الأعراض التي تشعر بها.';

        function addMessage(text, type) {
            const el = document.createElement('div');
            el.className = 'msg msg--' + type;
            el.textContent = text;
            win.appendChild(el);
            win.scrollTop = win.scrollHeight;
            return el;
        }
        function showTyping() { return addMessage('•••', 'typing'); }
        function renderMessages(msgs) {
            win.innerHTML = '';
            if (!msgs || !msgs.length) { addMessage(GREETING, 'bot'); return; }
            msgs.forEach(m => addMessage((m.type === 'voice' ? '🎤 ' : '') + m.text, m.role));
        }
        function applyReply(body) {
            if (!body.ok) { addMessage('تعذّر الاتصال بالخدمة: ' + (body.error || ''), 'note'); return; }
            if (body.finished) addMessage('✅ تم جمع المعلومات الكافية. شكراً لك.', 'bot');
            else if (body.question) addMessage(body.question, 'bot');
            loadConversations();
        }

        /* ---------- history / conversations ---------- */
        async function loadHistory() {
            try {
                const body = await (await fetch(HISTORY_URL, { headers: H })).json();
                renderMessages(body.messages);
            } catch (e) { renderMessages([]); }
        }
        async function loadConversations() {
            try {
                const body = await (await fetch(CONVS_URL, { headers: H })).json();
                convs.innerHTML = '';
                if (!body.conversations || !body.conversations.length) {
                    convs.innerHTML = '<div class="convs__empty">لا توجد محادثات بعد</div>';
                    return;
                }
                body.conversations.forEach(c => {
                    const el = document.createElement('button');
                    el.className = 'conv' + (c.current ? ' conv--active' : '');
                    const t = document.createElement('div'); t.className = 'conv__title'; t.textContent = c.title;
                    const m = document.createElement('div'); m.className = 'conv__meta';
                    m.textContent = (c.messages || 0) + ' رسالة' + (c.status === 'completed' ? ' · مكتملة' : '');
                    el.appendChild(t); el.appendChild(m);
                    el.onclick = () => selectConversation(c.id);
                    convs.appendChild(el);
                });
            } catch (e) {}
        }
        async function selectConversation(id) {
            const body = await (await fetch(SELECT_URL.replace('__ID__', id), {
                method: 'POST', headers: { ...H, 'X-CSRF-TOKEN': CSRF }
            })).json();
            renderMessages(body.messages);
            loadConversations();
            sidebar.classList.remove('open');
        }

        /* ---------- text ---------- */
        async function send() {
            if (sending) return;
            const text = input.value.trim();
            if (!text) return;
            addMessage(text, 'user');
            input.value = ''; input.style.height = 'auto';
            sending = true; sendBtn.disabled = true;
            const typing = showTyping();
            try {
                const res = await fetch(MSG_URL, {
                    method: 'POST',
                    headers: { ...H, 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
                    body: JSON.stringify({ text })
                });
                const body = await res.json();
                typing.remove();
                applyReply(body);
            } catch (e) { typing.remove(); addMessage('حدث خطأ في الاتصال.', 'note'); }
            finally { sending = false; sendBtn.disabled = false; input.focus(); }
        }

        /* ---------- voice ---------- */
        let mediaRecorder = null, chunks = [];
        async function toggleRecording() {
            if (mediaRecorder && mediaRecorder.state === 'recording') { mediaRecorder.stop(); return; }
            let stream;
            try { stream = await navigator.mediaDevices.getUserMedia({ audio: true }); }
            catch (e) { addMessage('تعذّر الوصول للميكروفون (اسمح بالإذن، والموقع يجب أن يكون https أو localhost).', 'note'); return; }
            chunks = [];
            mediaRecorder = new MediaRecorder(stream);
            mediaRecorder.ondataavailable = e => { if (e.data.size) chunks.push(e.data); };
            mediaRecorder.onstop = async () => {
                voiceBtn.classList.remove('recording');
                stream.getTracks().forEach(t => t.stop());
                const type = mediaRecorder.mimeType || 'audio/webm';
                await sendVoice(new Blob(chunks, { type }), type);
            };
            mediaRecorder.start();
            voiceBtn.classList.add('recording');
        }
        async function sendVoice(blob, type) {
            const ext = type.includes('ogg') ? 'ogg' : (type.includes('mp4') ? 'mp4' : 'webm');
            const fd = new FormData();
            fd.append('audio', blob, 'recording.' + ext);
            addMessage('🎤 (رسالة صوتية)', 'user');
            const typing = showTyping();
            try {
                const res = await fetch(VOICE_URL, { method: 'POST', headers: { ...H, 'X-CSRF-TOKEN': CSRF }, body: fd });
                const body = await res.json();
                typing.remove();
                if (body.ok && body.transcription) addMessage('📝 ' + body.transcription, 'note');
                applyReply(body);
            } catch (e) { typing.remove(); addMessage('تعذّر إرسال الصوت.', 'note'); }
        }

        /* ---------- events ---------- */
        form.addEventListener('submit', e => { e.preventDefault(); send(); });
        input.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); } });
        input.addEventListener('input', () => { input.style.height = 'auto'; input.style.height = Math.min(input.scrollHeight, 120) + 'px'; });
        voiceBtn.addEventListener('click', toggleRecording);
        document.getElementById('menuBtn').addEventListener('click', () => sidebar.classList.toggle('open'));
        document.getElementById('newBtn').addEventListener('click', async () => {
            await fetch(RESET_URL, { method: 'POST', headers: { ...H, 'X-CSRF-TOKEN': CSRF } });
            renderMessages([]); loadConversations(); sidebar.classList.remove('open'); input.focus();
        });

        /* ---------- init ---------- */
        loadHistory();
        loadConversations();
        input.focus();
    </script>
</body>
</html>
