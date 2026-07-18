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
        body {
            font-family: "Segoe UI", "Noto Sans Arabic", Tahoma, sans-serif;
            background: #f0f2f5;
            display: flex; justify-content: center;
        }
        .chat {
            width: 100%; max-width: 600px; height: 100dvh; background: #fff;
            display: flex; flex-direction: column;
        }
        .chat__header {
            padding: 14px 18px; background: #0d9488; color: #fff;
            display: flex; align-items: center; justify-content: space-between;
        }
        .chat__header h1 { font-size: 16px; margin: 0; }
        .chat__new {
            background: rgba(255,255,255,.2); color: #fff; border: none;
            border-radius: 6px; padding: 7px 12px; cursor: pointer; font-size: 13px;
        }
        .chat__window {
            flex: 1; overflow-y: auto; padding: 16px;
            display: flex; flex-direction: column; gap: 10px;
        }
        .msg {
            max-width: 80%; padding: 10px 14px; border-radius: 14px;
            line-height: 1.6; font-size: 15px; white-space: pre-wrap; word-wrap: break-word;
        }
        .msg--user { align-self: flex-start; background: #0d9488; color: #fff; border-bottom-right-radius: 4px; }
        .msg--bot  { align-self: flex-end; background: #eef1f4; color: #1f2a37; border-bottom-left-radius: 4px; }
        .msg--note { align-self: center; background: transparent; color: #6b7280; font-size: 13px; }
        .chat__form { display: flex; align-items: flex-end; gap: 8px; padding: 10px; border-top: 1px solid #e5e7eb; }
        .chat__voice, .chat__send {
            flex: none; width: 44px; height: 44px; border-radius: 50%; border: none;
            cursor: pointer; font-size: 18px;
        }
        .chat__voice { background: #eef1f4; color: #0d9488; }
        .chat__send { background: #0d9488; color: #fff; }
        .chat__send:disabled { background: #9ca3af; cursor: not-allowed; }
        textarea {
            flex: 1; resize: none; border: 1px solid #d1d5db; border-radius: 20px;
            padding: 11px 14px; font-family: inherit; font-size: 15px; line-height: 1.5;
            outline: none; max-height: 120px;
        }
        textarea:focus { border-color: #0d9488; }
    </style>
</head>
<body>
    <div class="chat">
        <div class="chat__header">
            <h1>Healix — محادثة</h1>
            <button class="chat__new" id="newBtn" type="button">محادثة جديدة</button>
        </div>

        <div class="chat__window" id="window"></div>

        <form class="chat__form" id="form">
            <button class="chat__voice" id="voiceBtn" type="button" title="رسالة صوتية">🎤</button>
            <textarea id="input" rows="1" placeholder="اكتب رسالتك..."></textarea>
            <button class="chat__send" id="sendBtn" type="submit" title="إرسال">➤</button>
        </form>
    </div>

    <script>
        const MSG_URL   = @json(route('interview.message'));
        const RESET_URL = @json(route('interview.reset'));
        const CSRF      = document.querySelector('meta[name="csrf-token"]').content;

        const win     = document.getElementById('window');
        const form    = document.getElementById('form');
        const input   = document.getElementById('input');
        const sendBtn = document.getElementById('sendBtn');
        const voiceBtn = document.getElementById('voiceBtn');

        let sending = false;
        const GREETING = 'مرحباً، صف لي الأعراض التي تشعر بها.';

        function addMessage(text, type) {
            const el = document.createElement('div');
            el.className = 'msg msg--' + type;
            el.textContent = text;
            win.appendChild(el);
            win.scrollTop = win.scrollHeight;
        }

        async function send() {
            if (sending) return;
            const text = input.value.trim();
            if (!text) return;

            addMessage(text, 'user');
            input.value = '';
            input.style.height = 'auto';
            sending = true;
            sendBtn.disabled = true;

            try {
                const res = await fetch(MSG_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                    body: JSON.stringify({ text })
                });
                const body = await res.json();

                if (!body.ok) {
                    addMessage('تعذّر الاتصال بالخدمة.', 'note');
                } else if (body.finished) {
                    addMessage('✅ تم جمع المعلومات الكافية. شكراً لك.', 'bot');
                } else if (body.question) {
                    addMessage(body.question, 'bot');
                }
            } catch (e) {
                addMessage('حدث خطأ في الاتصال.', 'note');
            } finally {
                sending = false;
                sendBtn.disabled = false;
                input.focus();
            }
        }

        form.addEventListener('submit', (e) => { e.preventDefault(); send(); });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); }
        });

        input.addEventListener('input', () => {
            input.style.height = 'auto';
            input.style.height = Math.min(input.scrollHeight, 120) + 'px';
        });

        document.getElementById('newBtn').addEventListener('click', async () => {
            try {
                await fetch(RESET_URL, { method: 'POST', headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' } });
            } catch (e) {}
            win.innerHTML = '';
            addMessage(GREETING, 'bot');
            input.focus();
        });

        // Voice — placeholder only (Whisper to be connected later).
        voiceBtn.addEventListener('click', () => {
            // TODO: record audio and send to POST /api/ai/speech-to-text
            addMessage('🎤 ميزة الرسائل الصوتية قيد التطوير.', 'note');
        });

        addMessage(GREETING, 'bot');
        input.focus();
    </script>
</body>
</html>
