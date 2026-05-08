{{-- Smart Assistant — floating chat widget, visible to all authenticated roles --}}
@if(Auth::check() && \App\Models\PlatformSetting::isEnabled('ai.sidecar.enabled'))
@php
    $role     = Auth::user()->getRoleNames()->first() ?? 'User';
    $userName = Auth::user()->name;
    $sessionId = session()->getId();
@endphp

<style>
#sa-toggle {
    position: fixed;
    bottom: 1.5rem;
    right: 1.5rem;
    width: 52px;
    height: 52px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--accent-primary), var(--accent-secondary, var(--accent-primary)));
    border: none;
    color: #fff;
    font-size: 1.3rem;
    cursor: pointer;
    z-index: 1050;
    box-shadow: 0 4px 20px rgba(0,0,0,0.35);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.2s, box-shadow 0.2s;
}
#sa-toggle:hover { transform: scale(1.08); box-shadow: 0 6px 24px rgba(0,0,0,0.45); }
#sa-toggle .sa-badge {
    position: absolute;
    top: 2px; right: 2px;
    width: 12px; height: 12px;
    background: #22c55e;
    border-radius: 50%;
    border: 2px solid #0f0f1a;
}

#sa-panel {
    position: fixed;
    bottom: 5rem;
    right: 1.5rem;
    width: 360px;
    max-height: 520px;
    border-radius: 16px;
    /* Fully opaque — never use glass/blur variables that leak through */
    background: #0f0f1e;
    border: 1px solid rgba(255,255,255,0.12);
    box-shadow: 0 16px 56px rgba(0,0,0,0.7);
    z-index: 1049;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    transition: opacity 0.2s, transform 0.2s;
    /* Isolate from any backdrop-filter on parent elements */
    isolation: isolate;
}
#sa-panel.sa-hidden { opacity: 0; pointer-events: none; transform: translateY(12px) scale(0.97); }

#sa-header {
    padding: 0.75rem 1rem;
    background: rgba(255,255,255,0.06);
    border-bottom: 1px solid rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-shrink: 0;
}
#sa-header .sa-avatar {
    width: 32px; height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--accent-primary), #7c3aed);
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem; flex-shrink: 0;
}
#sa-header .sa-title { font-size: 0.88rem; font-weight: 600; color: var(--text-primary); }
#sa-header .sa-sub   { font-size: 0.72rem; color: var(--text-muted); }
#sa-close {
    margin-left: auto;
    background: none; border: none;
    color: var(--text-muted); cursor: pointer; font-size: 1.1rem; padding: 0.2rem;
    line-height: 1;
}
#sa-close:hover { color: var(--text-primary); }

#sa-messages {
    flex: 1;
    overflow-y: auto;
    padding: 0.75rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    scrollbar-width: thin;
}
.sa-msg {
    max-width: 88%;
    padding: 0.5rem 0.75rem;
    border-radius: 12px;
    font-size: 0.83rem;
    line-height: 1.45;
    word-break: break-word;
}
.sa-msg.sa-bot {
    background: rgba(255,255,255,0.11);
    color: var(--text-primary);
    align-self: flex-start;
    border-bottom-left-radius: 4px;
}
.sa-msg.sa-user {
    background: rgba(var(--accent-primary-rgb, 99,102,241), 0.25);
    color: var(--text-primary);
    align-self: flex-end;
    border-bottom-right-radius: 4px;
}
.sa-msg.sa-file {
    background: rgba(255,255,255,0.05);
    color: var(--text-muted);
    align-self: flex-end;
    border-bottom-right-radius: 4px;
    font-style: italic;
    font-size: 0.78rem;
}
.sa-action-btn {
    margin-top: 0.4rem;
    display: inline-block;
    padding: 0.3rem 0.8rem;
    border-radius: 8px;
    font-size: 0.78rem;
    font-weight: 600;
    background: rgba(var(--accent-primary-rgb, 99,102,241), 0.2);
    color: var(--accent-primary);
    border: 1px solid rgba(var(--accent-primary-rgb, 99,102,241), 0.35);
    cursor: pointer;
    text-decoration: none;
    transition: background 0.15s;
}
.sa-action-btn:hover { background: rgba(var(--accent-primary-rgb, 99,102,241), 0.35); color: var(--accent-primary); }

#sa-typing {
    padding: 0.4rem 0.75rem;
    display: none;
    align-self: flex-start;
}
#sa-typing span {
    display: inline-block;
    width: 6px; height: 6px;
    background: var(--text-muted);
    border-radius: 50%;
    animation: sa-bounce 1.2s infinite;
    margin: 0 1px;
}
#sa-typing span:nth-child(2) { animation-delay: 0.2s; }
#sa-typing span:nth-child(3) { animation-delay: 0.4s; }
@keyframes sa-bounce {
    0%, 80%, 100% { transform: translateY(0); opacity: 0.4; }
    40%           { transform: translateY(-5px); opacity: 1; }
}

#sa-footer {
    padding: 0.6rem 0.75rem;
    border-top: 1px solid rgba(255,255,255,0.07);
    flex-shrink: 0;
}
#sa-file-preview {
    font-size: 0.75rem;
    color: var(--text-muted);
    padding: 0.2rem 0.4rem;
    background: rgba(255,255,255,0.05);
    border-radius: 6px;
    margin-bottom: 0.4rem;
    display: none;
    align-items: center;
    gap: 0.4rem;
}
#sa-file-preview .sa-rm { cursor: pointer; color: var(--accent-danger, #ef4444); }
#sa-input-row {
    display: flex;
    gap: 0.4rem;
    align-items: flex-end;
}
#sa-input {
    flex: 1;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 10px;
    color: var(--text-primary);
    padding: 0.45rem 0.7rem;
    font-size: 0.83rem;
    resize: none;
    min-height: 38px;
    max-height: 100px;
    outline: none;
}
#sa-input::placeholder { color: var(--text-muted); }
#sa-input:focus { border-color: rgba(var(--accent-primary-rgb, 99,102,241), 0.5); }
.sa-icon-btn {
    width: 36px; height: 36px;
    border-radius: 9px;
    border: 1px solid rgba(255,255,255,0.1);
    background: rgba(255,255,255,0.05);
    color: var(--text-muted);
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
    transition: background 0.15s, color 0.15s;
}
.sa-icon-btn:hover { background: rgba(255,255,255,0.1); color: var(--text-primary); }
#sa-send {
    background: var(--accent-primary);
    border-color: transparent;
    color: #fff;
}
#sa-send:hover { filter: brightness(1.15); }
#sa-file-input { display: none; }
</style>

{{-- Toggle button --}}
<button id="sa-toggle" title="Ask AI Assistant" aria-label="Open AI Assistant">
    <i class="bi bi-stars"></i>
    <span class="sa-badge"></span>
</button>

{{-- Chat panel --}}
<div id="sa-panel" class="sa-hidden" role="dialog" aria-label="AI Assistant">
    <div id="sa-header">
        <div class="sa-avatar"><i class="bi bi-stars"></i></div>
        <div>
            <div class="sa-title">Aviva Assistant</div>
            <div class="sa-sub">{{ $role }} &mdash; {{ $userName }}</div>
        </div>
        <button id="sa-close" aria-label="Close"><i class="bi bi-x-lg"></i></button>
    </div>

    <div id="sa-messages">
        <div class="sa-msg sa-bot">
            Hi {{ explode(' ', $userName)[0] }}! I'm your AI assistant. You can ask me anything, or drop a file and I'll figure out what to do with it.
        </div>
        <div id="sa-typing" class="sa-msg sa-bot" style="display:none; padding:0.5rem 0.75rem;">
            <span></span><span></span><span></span>
        </div>
    </div>

    <div id="sa-footer">
        <div id="sa-file-preview">
            <i class="bi bi-paperclip"></i>
            <span id="sa-file-name"></span>
            <span class="sa-rm" id="sa-file-remove" title="Remove file"><i class="bi bi-x"></i></span>
        </div>
        <div id="sa-input-row">
            <textarea id="sa-input" placeholder="Ask anything or describe what you need…" rows="1"></textarea>
            <button class="sa-icon-btn" id="sa-attach" title="Attach file">
                <i class="bi bi-paperclip"></i>
            </button>
            <button class="sa-icon-btn" id="sa-send" title="Send">
                <i class="bi bi-send-fill"></i>
            </button>
        </div>
    </div>
</div>

<input type="file" id="sa-file-input" accept=".pdf,.csv,.jpg,.jpeg,.png">

<script>
(function () {
    var panel     = document.getElementById('sa-panel');
    var toggle    = document.getElementById('sa-toggle');
    var closeBtn  = document.getElementById('sa-close');
    var messages  = document.getElementById('sa-messages');
    var typing    = document.getElementById('sa-typing');
    var input     = document.getElementById('sa-input');
    var sendBtn   = document.getElementById('sa-send');
    var attachBtn = document.getElementById('sa-attach');
    var fileInput = document.getElementById('sa-file-input');
    var filePreview = document.getElementById('sa-file-preview');
    var fileName  = document.getElementById('sa-file-name');
    var fileRemove = document.getElementById('sa-file-remove');
    var currentFile = null;
    var sessionId = '{{ $sessionId }}';

    // Toggle
    toggle.addEventListener('click', function () {
        panel.classList.toggle('sa-hidden');
        if (!panel.classList.contains('sa-hidden')) {
            input.focus();
            scrollBottom();
        }
    });
    closeBtn.addEventListener('click', function () { panel.classList.add('sa-hidden'); });

    // File attachment
    attachBtn.addEventListener('click', function () { fileInput.click(); });
    fileInput.addEventListener('change', function () {
        if (fileInput.files[0]) {
            currentFile = fileInput.files[0];
            fileName.textContent = currentFile.name;
            filePreview.style.display = 'flex';
        }
    });
    fileRemove.addEventListener('click', function () {
        currentFile = null;
        fileInput.value = '';
        filePreview.style.display = 'none';
    });

    // Send on Enter (Shift+Enter = newline)
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); }
    });
    input.addEventListener('input', function () {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 100) + 'px';
    });
    sendBtn.addEventListener('click', send);

    // Drag-and-drop onto panel
    panel.addEventListener('dragover', function (e) { e.preventDefault(); });
    panel.addEventListener('drop', function (e) {
        e.preventDefault();
        var f = e.dataTransfer.files[0];
        if (f) {
            currentFile = f;
            fileName.textContent = f.name;
            filePreview.style.display = 'flex';
        }
    });

    function addMessage(text, type) {
        var div = document.createElement('div');
        div.className = 'sa-msg sa-' + type;
        div.textContent = text;
        messages.insertBefore(div, typing);
        scrollBottom();
        return div;
    }

    function addActionBtn(label, url, type, data) {
        var btn;
        if (type === 'upload_price_list') {
            btn = document.createElement('a');
            btn.href = url || '/owner/vendors';
            btn.className = 'sa-action-btn';
            btn.innerHTML = '<i class="bi bi-arrow-up-circle me-1"></i>' + label;
            btn.title = 'Go to vendor page to upload this file as a price list';
        } else if (type === 'navigate') {
            btn = document.createElement('a');
            btn.href = url || '#';
            btn.className = 'sa-action-btn';
            btn.innerHTML = '<i class="bi bi-arrow-right-circle me-1"></i>' + label;
        } else {
            return null;
        }
        var wrap = document.createElement('div');
        wrap.style.marginTop = '0.3rem';
        wrap.appendChild(btn);
        messages.insertBefore(wrap, typing);
        scrollBottom();
        return btn;
    }

    function scrollBottom() {
        setTimeout(function () { messages.scrollTop = messages.scrollHeight; }, 30);
    }

    function send() {
        var text = input.value.trim();
        if (!text && !currentFile) return;

        if (text)        addMessage(text, 'user');
        if (currentFile) addMessage('📎 ' + currentFile.name, 'file');

        input.value = '';
        input.style.height = 'auto';
        sendBtn.disabled = true;
        typing.style.display = 'flex';
        scrollBottom();

        var fd = new FormData();
        fd.append('message',      text);
        fd.append('role',         '{{ $role }}');
        fd.append('current_page', window.location.pathname);
        fd.append('session_id',   sessionId);
        fd.append('_token',       '{{ csrf_token() }}');
        if (currentFile) fd.append('file', currentFile, currentFile.name);

        fetch('{{ route("assistant.chat") }}', {
            method: 'POST',
            credentials: 'same-origin',
            body: fd,
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            sendBtn.disabled = false;
            typing.style.display = 'none';

            var reply = data.reply || 'No response.';
            addMessage(reply, 'bot');

            if (data.clarifying_question) {
                addMessage(data.clarifying_question, 'bot');
            }

            if (data.action && data.action.type) {
                addActionBtn(data.action.label || 'Go', data.action.url, data.action.type, data.action.data || {});
            }

            // Clear file after send
            currentFile = null;
            fileInput.value = '';
            filePreview.style.display = 'none';
        })
        .catch(function () {
            sendBtn.disabled = false;
            typing.style.display = 'none';
            addMessage('Network error — please try again.', 'bot');
        });
    }
}());
</script>
@endif
