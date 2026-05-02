(function () {
    'use strict';

    var builder    = document.getElementById('soapBuilder');
    var hiddenTA   = document.getElementById('consultationNotesTA');
    var addendumTA = document.getElementById('soapAddendumTA');
    var form       = document.getElementById('soapBuilderForm');
    var errorEl    = document.getElementById('soapValidationError');

    // Safety guard — bail out completely if any core element is missing.
    // The noscript fallback form remains visible in that case.
    if (!builder || !hiddenTA || !form) return;

    // Show the chip builder (hidden by default so noscript users see the fallback)
    builder.style.display = '';

    // Per-section chip state
    var chips = { S: [], O: [], A: [], P: [] };

    // Per-section Sortable instances (destroyed and recreated on each render)
    var sortableInstances = {};

    // ─── syncTextarea ─────────────────────────────────────────────────────────
    // Assembles chip state + addendum into the hidden textarea that the form POSTs.
    function syncTextarea() {
        var lines = ['S', 'O', 'A', 'P'].map(function (sec) {
            return sec + ': ' + chips[sec].join('. ');
        });
        var text = lines.join('\n');

        var addendum = addendumTA ? addendumTA.value.trim() : '';
        if (addendum) {
            text += '\n\nAddendum:\n' + addendum;
        }

        hiddenTA.value = text;
    }

    // ─── renderActive ─────────────────────────────────────────────────────────
    // Re-renders the active chip zone for a section and rebinds Sortable.
    function renderActive(section) {
        var zone = document.getElementById('soapActive' + section);
        if (!zone) return;

        // Destroy existing Sortable to avoid memory leaks
        if (sortableInstances[section]) {
            try { sortableInstances[section].destroy(); } catch (e) {}
            sortableInstances[section] = null;
        }

        zone.innerHTML = '';

        if (chips[section].length === 0) {
            var hint = document.createElement('span');
            hint.className = 'soap-empty-hint text-muted small';
            hint.style.alignSelf = 'center';
            hint.textContent = 'Click chips below to add…';
            zone.appendChild(hint);
            return;
        }

        chips[section].forEach(function (text, idx) {
            var chip = document.createElement('span');
            chip.className = 'badge badge-glass-secondary d-inline-flex align-items-center gap-1 me-1 mb-1';
            chip.style.fontSize = '0.85rem';
            chip.dataset.chipIndex = idx;

            var label = document.createTextNode(text + ' '); // non-breaking space before ×
            chip.appendChild(label);

            var rm = document.createElement('button');
            rm.type = 'button';
            rm.setAttribute('aria-label', 'Remove ' + text);
            rm.style.cssText = 'border:none;background:none;padding:0;cursor:pointer;font-size:0.75rem;line-height:1;color:inherit;opacity:0.7;';
            rm.textContent = '×'; // ×
            rm.addEventListener('click', (function (s, i) {
                return function () { removeChip(s, i); };
            }(section, idx)));

            chip.appendChild(rm);
            zone.appendChild(chip);
        });

        // Progressive enhancement: Sortable drag-to-reorder
        if (window.Sortable) {
            sortableInstances[section] = Sortable.create(zone, {
                animation: 150,
                onEnd: function () {
                    var newOrder = [];
                    zone.querySelectorAll('.badge').forEach(function (badge) {
                        // First text node contains the chip text
                        var text = badge.childNodes[0] ? badge.childNodes[0].textContent.replace(/ $/, '').trim() : '';
                        if (text) newOrder.push(text);
                    });
                    chips[section] = newOrder;
                    syncTextarea();
                },
            });
        }
    }

    // ─── addChip ──────────────────────────────────────────────────────────────
    function addChip(section, text, keywordId) {
        text = text.trim();
        if (!text || !chips[section]) return;
        if (chips[section].indexOf(text) !== -1) return; // dedup

        chips[section].push(text);
        renderActive(section);
        syncTextarea();

        if (keywordId) {
            incrementUse(keywordId);
        }
    }

    // ─── removeChip ───────────────────────────────────────────────────────────
    function removeChip(section, idx) {
        chips[section].splice(idx, 1);
        renderActive(section);
        syncTextarea();
    }

    // ─── incrementUse ─────────────────────────────────────────────────────────
    // Fire-and-forget — failure is non-critical.
    function incrementUse(keywordId) {
        fetch(window.soapRoutes.useBase + '/' + keywordId + '/use', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': window.csrfToken,
                'Accept': 'application/json',
            },
        }).catch(function () {});
    }

    // ─── addChipButton ────────────────────────────────────────────────────────
    // Inserts a new clickable chip button into the available-chips row.
    function addChipButton(section, kw) {
        var container = document.getElementById('soapAvailable' + section);
        if (!container) return;
        if (container.querySelector('[data-id="' + kw.id + '"]')) return; // already there

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-sm btn-outline-secondary soap-chip-btn';
        btn.dataset.section = section;
        btn.dataset.text    = kw.display_text;
        btn.dataset.id      = kw.id;
        btn.textContent     = kw.display_text;
        btn.addEventListener('click', function () {
            addChip(btn.dataset.section, btn.dataset.text, btn.dataset.id);
        });
        container.appendChild(btn);
    }

    // ─── saveNewChip ──────────────────────────────────────────────────────────
    function saveNewChip(section, displayText) {
        displayText = displayText.trim().substring(0, 100);
        if (displayText.length < 2) return;

        // Optimistic UI: add to active zone immediately
        addChip(section, displayText, null);

        fetch(window.soapRoutes.store, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ section: section, display_text: displayText }),
        })
        .then(function (res) {
            if (!res.ok) return null;
            return res.json();
        })
        .then(function (data) {
            if (data && data.id) {
                addChipButton(section, data);
            }
        })
        .catch(function () {}); // chip is already in the UI; AJAX failure is non-critical
    }

    // ─── Wire existing chip buttons ───────────────────────────────────────────
    document.querySelectorAll('.soap-chip-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            addChip(btn.dataset.section, btn.dataset.text, btn.dataset.id || null);
        });
    });

    // ─── Wire add-chip buttons and Enter key ──────────────────────────────────
    document.querySelectorAll('.soap-add-chip-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var section = btn.dataset.section;
            var input = btn.closest('.soap-add-row').querySelector('.soap-new-chip-input');
            if (!input) return;
            var val = input.value.trim();
            if (!val) return;
            saveNewChip(section, val);
            input.value = '';
        });
    });

    document.querySelectorAll('.soap-new-chip-input').forEach(function (input) {
        input.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            var section = input.dataset.section;
            var val = input.value.trim();
            if (!val) return;
            saveNewChip(section, val);
            input.value = '';
        });
    });

    // ─── Sync addendum changes to hidden textarea ─────────────────────────────
    if (addendumTA) {
        addendumTA.addEventListener('input', syncTextarea);
    }

    // ─── Auto-fill O section from triage vitals ───────────────────────────────
    if (window.soapVitals) {
        var v = window.soapVitals;
        if (v.bp)   addChip('O', 'BP ' + v.bp + ' mmHg', null);
        if (v.hr)   addChip('O', 'HR ' + v.hr + ' bpm', null);
        if (v.temp) addChip('O', 'Temp ' + v.temp + '°C', null);
        if (v.spo2) addChip('O', 'SpO₂ ' + v.spo2 + '%', null);
    }

    // ─── Auto-fill S from chief complaint ────────────────────────────────────
    if (window.soapChiefComplaint && window.soapChiefComplaint.trim()) {
        addChip('S', window.soapChiefComplaint.trim(), null);
    }

    // ─── Pre-populate addendum with existing notes (Bug 4 fix) ───────────────
    // If the patient has notes saved from an earlier save in this visit,
    // pre-load them into the addendum so they are not silently overwritten.
    if (addendumTA && window.soapExistingNotes && window.soapExistingNotes.trim()) {
        addendumTA.value = window.soapExistingNotes;
    }

    // Initial textarea sync
    syncTextarea();

    // ─── Form submit guard (Bug 3 fix) ────────────────────────────────────────
    form.addEventListener('submit', function (e) {
        var hasChips    = Object.keys(chips).some(function (s) { return chips[s].length > 0; });
        var hasAddendum = addendumTA && addendumTA.value.trim().length > 0;

        if (!hasChips && !hasAddendum) {
            e.preventDefault();
            if (errorEl) {
                errorEl.textContent = 'Please add at least one chip or dictate notes before saving.';
                errorEl.style.display = '';
                errorEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            return;
        }

        if (errorEl) errorEl.style.display = 'none';
        syncTextarea(); // Final sync before POST
    });

    // ─── Fallback toggle ──────────────────────────────────────────────────────
    var fallbackBtn = document.getElementById('soapFallbackToggle');
    if (fallbackBtn) {
        fallbackBtn.addEventListener('click', function () {
            // Hide the chip builder
            builder.style.display = 'none';
            // Un-hide the noscript fallback textarea that was already rendered
            // We cannot undo <noscript> server-side, so we re-create a minimal fallback inline.
            var fallbackWrap = document.getElementById('soapFallbackWrap');
            if (fallbackWrap) {
                fallbackWrap.style.display = '';
            }
        });
    }

    // ─── AI Suggest ───────────────────────────────────────────────────────────
    var aiBtn         = document.getElementById('soapAiSuggestBtn');
    var aiSuggestions = document.getElementById('soapAiSuggestions');
    var aiContent     = document.getElementById('soapAiContent');

    if (aiBtn && aiSuggestions && aiContent) {
        aiBtn.addEventListener('click', function () {
            var sText = chips['S'].join('. ');
            var oText = chips['O'].join('. ');

            if (!sText && !oText) {
                aiContent.innerHTML = '<p class="text-muted small mb-0"><i class="bi bi-info-circle me-1"></i>Please add Subjective and Objective chips first.</p>';
                aiSuggestions.style.display = '';
                return;
            }

            aiBtn.disabled = true;
            aiBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span>Analysing…';
            aiSuggestions.style.display = 'none';

            var query = 'Clinical SOAP. Subjective: ' + sText +
                        '. Objective: ' + oText +
                        '. Suggest Assessment (likely diagnoses) and Plan (investigations, treatment, follow-up) as short bullet points.';

            fetch(window.soapRoutes.aiQuery, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': window.csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ query: query, collection: 'service_catalog' }),
            })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                aiBtn.disabled = false;
                aiBtn.innerHTML = '<i class="bi bi-stars me-1"></i>Suggest Assessment &amp; Plan';

                if (data.error || !data.answer) {
                    aiContent.innerHTML = '<p class="text-muted small mb-0"><i class="bi bi-exclamation-triangle me-1"></i>' +
                        escapeHtml(data.error || 'AI unavailable — try again later.') + '</p>';
                    aiSuggestions.style.display = '';
                    return;
                }

                var aChips = [], pChips = [];
                var mode   = null;

                data.answer.split('\n').forEach(function (line) {
                    line = line.trim();
                    if (!line) return;

                    var lower = line.toLowerCase();
                    if (/^(a[:\-]|assessment|diagnos)/.test(lower)) { mode = 'A'; return; }
                    if (/^(p[:\-]|plan|invest|treatment|follow)/.test(lower)) { mode = 'P'; return; }

                    var clean = line.replace(/^[-*•\d]+[.)]\s*/, '').trim();
                    if (clean.length < 3) return;

                    if (mode === 'A')      aChips.push(clean);
                    else if (mode === 'P') pChips.push(clean);
                    else if (!aChips.length) aChips.push(clean); // ungrouped → Assessment
                });

                if (!aChips.length && !pChips.length) {
                    aiContent.innerHTML = '<p class="text-muted small mb-0">' +
                        escapeHtml(data.answer.substring(0, 500)) + '</p>';
                    aiSuggestions.style.display = '';
                    return;
                }

                var html = '';
                if (aChips.length) {
                    html += '<p class="small fw-semibold mb-1" style="color:var(--accent-primary);">Assessment:</p>' +
                            '<div class="d-flex flex-wrap gap-1 mb-2">';
                    aChips.forEach(function (c) {
                        html += '<button type="button" class="btn btn-sm btn-outline-primary soap-ai-chip" data-section="A" data-text="' +
                                escapeAttr(c) + '">' + escapeHtml(c) + '</button>';
                    });
                    html += '</div>';
                }
                if (pChips.length) {
                    html += '<p class="small fw-semibold mb-1" style="color:var(--accent-success);">Plan:</p>' +
                            '<div class="d-flex flex-wrap gap-1 mb-2">';
                    pChips.forEach(function (c) {
                        html += '<button type="button" class="btn btn-sm btn-outline-success soap-ai-chip" data-section="P" data-text="' +
                                escapeAttr(c) + '">' + escapeHtml(c) + '</button>';
                    });
                    html += '</div>';
                }

                aiContent.innerHTML = html;
                aiSuggestions.style.display = '';

                aiContent.querySelectorAll('.soap-ai-chip').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        addChip(btn.dataset.section, btn.dataset.text, null);
                        btn.classList.add('active');
                        btn.disabled = true;
                    });
                });
            })
            .catch(function () {
                aiBtn.disabled = false;
                aiBtn.innerHTML = '<i class="bi bi-stars me-1"></i>Suggest Assessment &amp; Plan';
                aiContent.innerHTML = '<p class="text-muted small mb-0"><i class="bi bi-wifi-off me-1"></i>AI temporarily unavailable.</p>';
                aiSuggestions.style.display = '';
            });
        });
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────
    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function escapeAttr(s) {
        return String(s).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

}());
