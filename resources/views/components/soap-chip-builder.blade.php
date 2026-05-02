{{--
    SOAP Chip Builder component.

    Parameters:
      $patient      — Patient model
      $latestVitals — TriageVital|null
      $keywords     — Collection<string, Collection<SoapKeyword>> grouped by section
      $aiEnabled    — bool (PlatformSetting::isEnabled('ai.chat.enabled.doctor'))
--}}

{{-- ── CONSULTATION NOTES CARD ─────────────────────────────────────────── --}}
<div class="card mb-4 fade-in delay-2">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-pencil-square me-2" style="color:var(--accent-warning);"></i>Consultation Notes</span>
    </div>
    <div class="card-body">

        @if($patient->status === 'with_doctor')

            {{-- ── DATA BOOTSTRAP ── runs synchronously during HTML parse so that
                 deferred soap-builder.js can read these globals on DOMContentLoaded ── --}}
            <script>
            window.soapKeywords     = @json($keywords);
            window.soapVitals       = {
                bp:   @json($latestVitals?->blood_pressure),
                hr:   @json($latestVitals?->pulse_rate),
                temp: @json($latestVitals?->temperature),
                spo2: @json($latestVitals?->oxygen_saturation),
            };
            window.soapChiefComplaint = @json($latestVitals?->chief_complaint ?? '');
            window.soapExistingNotes  = @json(old('consultation_notes', $patient->consultation_notes ?? ''));
            window.soapRoutes = {
                store:   '{{ route('doctor.soap-keywords.store') }}',
                useBase: '{{ url('doctor/soap-keywords') }}',
                aiQuery: '/ai-assistant/query',
            };
            window.csrfToken = '{{ csrf_token() }}';
            </script>

            {{-- ── NO-JS FALLBACK (shown only when JavaScript is disabled) ──────── --}}
            <noscript>
                <form action="{{ route('doctor.consultation.save-notes', $patient) }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <small class="text-muted d-block mb-1">Consultation Notes</small>
                        <textarea name="consultation_notes"
                                  class="form-control"
                                  rows="10"
                                  required
                                  minlength="3"
                                  maxlength="5000"
                                  placeholder="Enter consultation notes…">{{ old('consultation_notes', $patient->consultation_notes) }}</textarea>
                        @error('consultation_notes')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Save Notes
                    </button>
                </form>
            </noscript>

            {{-- ── CHIP BUILDER (hidden until soap-builder.js runs) ─────────────── --}}
            <div id="soapBuilder" style="display:none;">
                <form id="soapBuilderForm"
                      action="{{ route('doctor.consultation.save-notes', $patient) }}"
                      method="POST">
                    @csrf

                    {{-- Hidden textarea — the actual form field POSTed to saveNotes().
                         Managed entirely by soap-builder.js; never shown to the user.
                         position:absolute + off-screen placement keeps it in the DOM
                         (required for form submission) without occupying layout space. --}}
                    <textarea id="consultationNotesTA"
                              name="consultation_notes"
                              style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;"
                              aria-hidden="true"
                              tabindex="-1"></textarea>

                    @error('consultation_notes')
                        <div class="alert alert-danger py-2 mb-3">{{ $message }}</div>
                    @enderror

                    {{-- ── 4 SOAP SECTION CARDS ─────────────────────────────────── --}}
                    @foreach(['S' => 'Subjective', 'O' => 'Objective', 'A' => 'Assessment', 'P' => 'Plan'] as $sec => $secLabel)
                    <div class="card mb-3" style="border:1px solid var(--glass-border); background:var(--glass-bg);">
                        <div class="card-header py-2 d-flex align-items-center gap-2"
                             style="background:rgba(var(--accent-primary-rgb),0.06);">
                            <span class="fw-bold" style="color:var(--accent-primary); font-size:1rem;">{{ $sec }}</span>
                            <span class="text-muted small">— {{ $secLabel }}</span>
                        </div>
                        <div class="card-body py-2">

                            {{-- Active chip zone --}}
                            <div id="soapActive{{ $sec }}"
                                 class="soap-active-zone d-flex flex-wrap align-items-center gap-1 mb-2 p-2 rounded"
                                 style="min-height:40px; background:rgba(var(--accent-primary-rgb),0.04); border:1px dashed var(--glass-border);"
                                 data-section="{{ $sec }}">
                                <span class="soap-empty-hint text-muted small" style="align-self:center;">Click chips below to add…</span>
                            </div>

                            {{-- Available chips from keyword library --}}
                            <div id="soapAvailable{{ $sec }}" class="d-flex flex-wrap gap-1 mb-2">
                                @foreach(($keywords->get($sec) ?? collect()) as $kw)
                                <button type="button"
                                        class="btn btn-sm btn-outline-secondary soap-chip-btn"
                                        data-section="{{ $sec }}"
                                        data-text="{{ $kw->display_text }}"
                                        data-id="{{ $kw->id }}">
                                    {{ $kw->display_text }}
                                </button>
                                @endforeach
                            </div>

                            {{-- Add custom chip --}}
                            <div class="d-flex gap-2 soap-add-row">
                                <input type="text"
                                       class="form-control form-control-sm soap-new-chip-input"
                                       data-section="{{ $sec }}"
                                       placeholder="Type new chip + Enter…"
                                       maxlength="100"
                                       autocomplete="off">
                                <button type="button"
                                        class="btn btn-sm btn-outline-primary soap-add-chip-btn"
                                        data-section="{{ $sec }}"
                                        title="Add chip">
                                    <i class="bi bi-plus-lg"></i>
                                </button>
                            </div>

                        </div>
                    </div>
                    @endforeach

                    {{-- ── AI SUGGEST (flag-gated — flag check is server-side) ────── --}}
                    @if($aiEnabled)
                    <div class="mb-3">
                        <button type="button" id="soapAiSuggestBtn" class="btn btn-outline-info btn-sm">
                            <i class="bi bi-stars me-1"></i>Suggest Assessment &amp; Plan
                        </button>
                        <span class="ms-2 text-muted small">Sends S+O to AI — suggestions are not final diagnoses</span>
                    </div>
                    <div id="soapAiSuggestions" class="card mb-3" style="display:none; border:1px solid rgba(var(--accent-info-rgb),0.3);">
                        <div class="card-header py-2" style="background:rgba(var(--accent-info-rgb),0.06);">
                            <i class="bi bi-exclamation-triangle me-1" style="color:var(--accent-warning);"></i>
                            <strong>AI Suggestions</strong>
                            <span class="text-muted small ms-1">(not a final diagnosis — review before accepting)</span>
                        </div>
                        <div class="card-body py-2" id="soapAiContent"></div>
                    </div>
                    @endif

                    {{-- ── ADDENDUM / DICTATION ─────────────────────────────────── --}}
                    <div class="mb-3">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <small class="text-muted">Dictated / Additional Notes</small>
                            {{-- STT button — shown by the STT script below if Web Speech API is available --}}
                            <button type="button"
                                    id="sttBtn"
                                    class="btn btn-sm btn-outline-secondary ms-auto"
                                    title="Dictate notes (speech to text)"
                                    style="display:none;">
                                <i class="bi bi-mic" id="sttIcon"></i>
                                <span id="sttLabel">Dictate</span>
                            </button>
                            <span id="sttStatus"
                                  class="badge bg-danger"
                                  style="display:none; font-size:0.7rem;">● REC</span>
                        </div>
                        <textarea id="soapAddendumTA"
                                  class="form-control"
                                  rows="3"
                                  placeholder="Additional notes or dictated text…"></textarea>
                    </div>

                    {{-- Validation error (shown by JS) --}}
                    <div id="soapValidationError"
                         class="alert alert-danger py-2 mb-3"
                         style="display:none;"
                         role="alert"></div>

                    {{-- Submit --}}
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i>Save Notes
                    </button>
                    <button type="button"
                            id="soapFallbackToggle"
                            class="btn btn-link btn-sm text-muted ms-2">
                        Switch to free text
                    </button>
                </form>

                {{-- ── FALLBACK FORM (revealed by "Switch to free text" button) ─── --}}
                <div id="soapFallbackWrap" style="display:none;" class="mt-3">
                    <form action="{{ route('doctor.consultation.save-notes', $patient) }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <small class="text-muted d-block mb-1">Consultation Notes (free text)</small>
                            <textarea name="consultation_notes"
                                      class="form-control"
                                      rows="10"
                                      required
                                      minlength="3"
                                      maxlength="5000"
                                      placeholder="Enter consultation notes…">{{ old('consultation_notes', $patient->consultation_notes) }}</textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save me-1"></i>Save Notes
                        </button>
                    </form>
                </div>

            </div>{{-- /#soapBuilder --}}

            {{-- ── SPEECH-TO-TEXT (target changed to #soapAddendumTA vs original #consultationNotesTA) --}}
            <script>
            (function () {
                var SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
                if (!SpeechRecognition) return;

                var btn    = document.getElementById('sttBtn');
                var icon   = document.getElementById('sttIcon');
                var label  = document.getElementById('sttLabel');
                var status = document.getElementById('sttStatus');
                var ta     = document.getElementById('soapAddendumTA'); // ← addendum, not hidden TA

                if (!btn || !ta) return;
                btn.style.display = '';

                var recognition = new SpeechRecognition();
                recognition.continuous     = true;
                recognition.interimResults = true;
                recognition.lang           = 'en-US';

                var isListening = false;

                recognition.onresult = function (event) {
                    var interim = '', finalText = '';
                    for (var i = event.resultIndex; i < event.results.length; i++) {
                        if (event.results[i].isFinal) {
                            finalText += event.results[i][0].transcript;
                        } else {
                            interim += event.results[i][0].transcript;
                        }
                    }
                    if (finalText) {
                        var cur = ta.value;
                        ta.value = cur + (cur && !cur.endsWith(' ') && !cur.endsWith('\n') ? ' ' : '') + finalText;
                        ta.dispatchEvent(new Event('input')); // trigger syncTextarea
                    }
                    status.textContent = interim
                        ? ('● ' + interim.substring(0, 40) + (interim.length > 40 ? '…' : ''))
                        : '● REC';
                };

                recognition.onerror = function (event) {
                    if (event.error === 'no-speech') return;
                    isListening = false;
                    setIdle();
                    if (event.error === 'not-allowed') {
                        alert('Microphone access denied. Please allow microphone permission to use dictation.');
                    }
                };

                recognition.onend = function () {
                    if (isListening) {
                        try { recognition.start(); } catch (e) {}
                    } else {
                        setIdle();
                    }
                };

                function setIdle() {
                    icon.className = 'bi bi-mic';
                    label.textContent = 'Dictate';
                    status.style.display = 'none';
                    btn.classList.remove('btn-danger');
                    btn.classList.add('btn-outline-secondary');
                }

                function setRecording() {
                    icon.className = 'bi bi-mic-fill';
                    label.textContent = 'Stop';
                    status.style.display = '';
                    status.textContent = '● REC';
                    btn.classList.remove('btn-outline-secondary');
                    btn.classList.add('btn-danger');
                }

                btn.addEventListener('click', function () {
                    if (isListening) {
                        isListening = false;
                        recognition.stop();
                        setIdle();
                    } else {
                        isListening = true;
                        setRecording();
                        recognition.start();
                    }
                });
            }());
            </script>

        @else

            {{-- ── READ-ONLY view for completed / non-active patients ────────────── --}}
            <div class="p-3 rounded" style="background:var(--glass-bg); border:1px solid var(--glass-border);">
                {!! nl2br(e($patient->consultation_notes ?? 'No notes recorded.')) !!}
            </div>

        @endif

    </div>
</div>

@push('scripts')
    {{-- Sortable.js (optional drag-to-reorder) — must load before soap-builder.js --}}
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js" defer></script>
    <script src="{{ asset('js/soap-builder.js') }}" defer></script>
@endpush
