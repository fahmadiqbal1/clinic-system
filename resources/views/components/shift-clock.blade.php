{{--
    Shift Clock component — shown at the top of every staff dashboard.
    Provides Clock In / Clock Out with no page reload (Alpine.js + axios).

    x-cloak hides the card until Alpine initialises, preventing a flash where
    all buttons and spinners are visible simultaneously (looks like "stuck").
--}}
<div x-data="shiftClock()"
     x-init="init()"
     x-cloak
     class="card mb-4 fade-in"
     style="border-left: 3px solid var(--accent-info);">
    <div class="card-body py-2 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-clock" style="color:var(--accent-info); font-size:1.2rem;"></i>
            <div>
                <span x-show="initLoading" class="text-muted small">
                    <span class="spinner-border spinner-border-sm me-1"></span>Checking status…
                </span>
                <span x-show="!initLoading && open" style="color:var(--accent-success); font-weight:600;">
                    <i class="bi bi-circle-fill me-1" style="font-size:0.5rem; vertical-align:middle;"></i>
                    On shift since <span x-text="clockedInAt"></span>
                </span>
                <span x-show="!initLoading && !open" style="color:var(--text-muted);">
                    Not clocked in today
                </span>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button x-show="!initLoading && !open"
                    x-on:click="clockIn()"
                    :disabled="loading"
                    class="btn btn-sm btn-success">
                <span x-show="!loading"><i class="bi bi-box-arrow-in-right me-1"></i>Clock In</span>
                <span x-show="loading"><span class="spinner-border spinner-border-sm me-1"></span>…</span>
            </button>
            <button x-show="!initLoading && open"
                    x-on:click="clockOut()"
                    :disabled="loading"
                    class="btn btn-sm btn-outline-warning">
                <span x-show="!loading"><i class="bi bi-box-arrow-right me-1"></i>Clock Out</span>
                <span x-show="loading"><span class="spinner-border spinner-border-sm me-1"></span>…</span>
            </button>
        </div>
        <div x-show="message" x-text="message" class="small" :class="msgClass"></div>
    </div>
</div>

<style>[x-cloak]{display:none!important}</style>

<script>
function shiftClock() {
    return {
        open: false,
        clockedInAt: null,
        loading: false,
        initLoading: true,
        message: '',
        msgClass: '',
        init() {
            if (typeof axios === 'undefined') {
                this.initLoading = false;
                return;
            }
            axios.get('/attendance/status')
                .then(r => {
                    this.open        = r.data.open;
                    this.clockedInAt = r.data.clocked_in_at;
                })
                .catch(() => {})
                .finally(() => { this.initLoading = false; });
        },
        clockIn() {
            if (this.loading) return;
            this.loading = true;
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
                axios.post('/attendance/clock-in', {}, { headers: { 'X-CSRF-TOKEN': csrf } })
                    .then(r => {
                        this.open        = true;
                        this.clockedInAt = r.data.clocked_in_at;
                        this.flash('Clocked in successfully', 'text-success');
                    })
                    .catch(e => {
                        const msg = e.response?.data?.status === 'already_clocked_in'
                            ? 'Already clocked in since ' + e.response.data.clocked_in_at
                            : 'Clock-in failed. Please try again.';
                        this.flash(msg, 'text-warning');
                    })
                    .finally(() => { this.loading = false; });
            } catch {
                this.loading = false;
                this.flash('Clock-in failed. Please try again.', 'text-danger');
            }
        },
        clockOut() {
            if (this.loading) return;
            this.loading = true;
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
                axios.post('/attendance/clock-out', {}, { headers: { 'X-CSRF-TOKEN': csrf } })
                    .then(r => {
                        this.open = false;
                        this.flash('Clocked out — ' + r.data.duration_hours + 'h on shift', 'text-info');
                    })
                    .catch(() => { this.flash('Clock-out failed. Please try again.', 'text-danger'); })
                    .finally(() => { this.loading = false; });
            } catch {
                this.loading = false;
                this.flash('Clock-out failed. Please try again.', 'text-danger');
            }
        },
        flash(msg, cls) {
            this.message  = msg;
            this.msgClass = cls;
            setTimeout(() => { this.message = ''; }, 4000);
        }
    };
}
</script>
