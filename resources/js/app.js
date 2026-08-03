import Alpine from 'alpinejs';

window.Alpine = Alpine;

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

function ensureProgressBar() {
    let bar = document.getElementById('ajax-progress');
    if (!bar) {
        bar = document.createElement('div');
        bar.id = 'ajax-progress';
        bar.innerHTML = '<div class="ajax-progress-bar"></div>';
        document.body.prepend(bar);
    }
    return bar;
}

function startProgress() {
    const wrap = ensureProgressBar();
    wrap.classList.add('is-active');
    const bar = wrap.querySelector('.ajax-progress-bar');
    bar.style.width = '0%';
    requestAnimationFrame(() => {
        bar.style.width = '70%';
    });
}

function finishProgress() {
    const wrap = document.getElementById('ajax-progress');
    if (!wrap) return;
    const bar = wrap.querySelector('.ajax-progress-bar');
    bar.style.width = '100%';
    setTimeout(() => {
        wrap.classList.remove('is-active');
        bar.style.width = '0%';
    }, 220);
}

function findNamedInput(form, field) {
    const base = String(field || '').split('.')[0];
    return (
        form.querySelector(`[name="${field}"]`)
        || form.querySelector(`[name="${base}"]`)
        || form.querySelector(`[name="${base}[]"]`)
        || form.querySelector(`[name^="${base}["]`)
    );
}

function ensureFormBanner(form) {
    let banner = form.querySelector('[data-form-error]');
    if (banner) return banner;
    banner = document.createElement('div');
    banner.setAttribute('data-form-error', '');
    banner.className = 'hidden mb-2 w-full rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-sm text-red-700';
    form.prepend(banner);
    return banner;
}

function ensureErrorSlot(form, field, input = null) {
    let box = form.querySelector(`[data-error-for="${field}"]`);
    if (box) return box;
    const base = String(field || '').split('.')[0];
    box = form.querySelector(`[data-error-for="${base}"]`);
    if (box) return box;

    const el = input || findNamedInput(form, field);
    if (!el) return null;

    box = document.createElement('p');
    box.className = 'field-error hidden';
    box.setAttribute('data-error-for', base || field);
    const host = el.closest('label') || el.closest('div') || el.parentElement;
    if (host) host.appendChild(box);
    else el.insertAdjacentElement('afterend', box);
    return box;
}

function clearFieldErrors(form) {
    form.querySelectorAll('[data-error-for]').forEach((el) => {
        el.textContent = '';
        el.classList.add('hidden');
    });
    form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
    const banner = form.querySelector('[data-form-error]');
    if (banner) {
        banner.textContent = '';
        banner.classList.add('hidden');
    }
}

function showFieldErrors(form, errors = {}) {
    ensureFormBanner(form);
    Object.entries(errors).forEach(([field, messages]) => {
        const message = Array.isArray(messages) ? messages[0] : String(messages);
        const input = findNamedInput(form, field);
        if (input) input.classList.add('is-invalid');
        const box = ensureErrorSlot(form, field, input);
        if (box) {
            box.textContent = message;
            box.classList.remove('hidden');
        }
    });
}

function setSubmitting(form, submitting, submitter = null) {
    const btn = submitter || form.querySelector('[type="submit"]');
    if (!btn) return;
    if (submitting) {
        btn.dataset.originalText = btn.innerHTML;
        btn.disabled = true;
        btn.classList.add('is-loading');
        btn.innerHTML = `<span class="btn-spinner"></span><span>${btn.dataset.loadingText || 'Please wait…'}</span>`;
        form.dataset.activeSubmitter = '1';
        form.__activeSubmitter = btn;
    } else {
        const active = form.__activeSubmitter || btn;
        active.disabled = false;
        active.classList.remove('is-loading');
        if (active.dataset.originalText) active.innerHTML = active.dataset.originalText;
        form.__activeSubmitter = null;
    }
}

function showFormBanner(form, message) {
    const banner = ensureFormBanner(form);
    banner.textContent = message || 'Please correct the highlighted fields.';
    banner.classList.remove('hidden');
}

async function submitAjaxForm(form, submitter = null) {
    clearFieldErrors(form);
    setSubmitting(form, true, submitter);
    startProgress();

    try {
        const formData = new FormData(form);
        if (submitter?.name) {
            formData.set(submitter.name, submitter.value || '1');
        }

        const methodOverride = formData.get('_method');
        const method = String(methodOverride || form.method || 'POST').toUpperCase();

        const response = await fetch(form.action, {
            method: method === 'GET' ? 'GET' : 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: method === 'GET' ? undefined : formData,
            credentials: 'same-origin',
        });

        const contentType = response.headers.get('content-type') || '';
        const isJson = contentType.includes('application/json');
        const payload = isJson ? await response.json() : null;

        if (response.status === 422) {
            const errors = payload?.errors || {};
            if (Object.keys(errors).length) showFieldErrors(form, errors);
            showFormBanner(
                form,
                payload?.message
                    || (Object.values(errors)[0] && (Array.isArray(Object.values(errors)[0]) ? Object.values(errors)[0][0] : Object.values(errors)[0]))
                    || 'Please correct the highlighted fields.',
            );
            const firstInvalid = form.querySelector('.is-invalid');
            firstInvalid?.focus?.();
            firstInvalid?.scrollIntoView?.({ behavior: 'smooth', block: 'center' });
            return;
        }

        if (!response.ok) {
            showFormBanner(form, payload?.message || 'Something went wrong. Please try again.');
            return;
        }

        if (payload?.redirect) {
            window.location.href = payload.redirect;
            return;
        }

        if (payload?.summary) {
            form.dispatchEvent(new CustomEvent('ajax:success', { detail: payload }));
            return;
        }

        // Non-JSON success (classic redirect followed by fetch) or JSON without redirect.
        if (!isJson || payload?.reload) {
            window.location.reload();
            return;
        }

        if (payload?.message) {
            const ok = form.querySelector('[data-form-success]');
            if (ok) {
                ok.textContent = payload.message;
                ok.classList.remove('hidden');
            } else {
                window.location.reload();
                return;
            }
        }

        form.dispatchEvent(new CustomEvent('ajax:success', { detail: payload }));
    } catch (error) {
        showFormBanner(form, 'Network error. Check your connection and try again.');
    } finally {
        setSubmitting(form, false);
        finishProgress();
    }
}

function shouldBindAjaxForm(form) {
    if (form.dataset.ajaxBound === '1') return false;
    if (form.hasAttribute('data-no-ajax') || form.dataset.noAjax === '1') return false;
    if (form.dataset.ajax === '0') return false;
    const action = (form.getAttribute('action') || '').toLowerCase();
    if (action.includes('/logout')) return false;
    const method = (form.getAttribute('method') || 'get').toLowerCase();
    if (method !== 'post') return false;
    return true;
}

function bindAjaxForms(root = document) {
    root.querySelectorAll('form').forEach((form) => {
        if (!shouldBindAjaxForm(form)) return;
        form.dataset.ajaxBound = '1';
        form.setAttribute('novalidate', 'novalidate');
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            submitAjaxForm(form, event.submitter || null);
        });

        form.querySelectorAll('input, textarea, select').forEach((input) => {
            const clear = () => {
                input.classList.remove('is-invalid');
                const name = (input.name || '').replace(/\[\]$/, '').split('[')[0];
                const box = form.querySelector(`[data-error-for="${input.name}"]`)
                    || (name ? form.querySelector(`[data-error-for="${name}"]`) : null);
                if (box) {
                    box.textContent = '';
                    box.classList.add('hidden');
                }
                const banner = form.querySelector('[data-form-error]');
                if (banner && !form.querySelector('.is-invalid')) {
                    banner.textContent = '';
                    banner.classList.add('hidden');
                }
            };
            input.addEventListener('input', clear);
            input.addEventListener('change', clear);
        });
    });
}

function extractPortal(html) {
    const doc = new DOMParser().parseFromString(html, 'text/html');
    return {
        title: doc.title,
        header: doc.querySelector('[data-portal-header]')?.innerHTML,
        main: doc.querySelector('[data-portal-main]')?.innerHTML,
        nav: doc.querySelector('[data-portal-nav]')?.innerHTML,
    };
}

async function loadPortalPage(url, { push = true } = {}) {
    const main = document.querySelector('[data-portal-main]');
    if (!main) {
        window.location.href = url;
        return;
    }

    startProgress();
    main.classList.add('is-page-loading');

    try {
        const response = await fetch(url, {
            headers: {
                Accept: 'text/html',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            window.location.href = url;
            return;
        }

        const html = await response.text();
        const parts = extractPortal(html);

        if (!parts.main) {
            window.location.href = url;
            return;
        }

        if (parts.title) document.title = parts.title;
        const header = document.querySelector('[data-portal-header]');
        if (header && parts.header) header.innerHTML = parts.header;
        const nav = document.querySelector('[data-portal-nav]');
        if (nav && parts.nav) {
            if (window.Alpine?.destroyTree) window.Alpine.destroyTree(nav);
            nav.innerHTML = parts.nav;
            if (window.Alpine?.initTree) window.Alpine.initTree(nav);
        }
        if (window.Alpine?.destroyTree) window.Alpine.destroyTree(main);
        main.innerHTML = parts.main;
        bindAjaxForms(main);
        if (window.Alpine?.initTree) window.Alpine.initTree(main);
        if (push) history.pushState({ ajaxPortal: true }, '', url);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    } catch (error) {
        window.location.href = url;
    } finally {
        main.classList.remove('is-page-loading');
        finishProgress();
    }
}

function bindPortalAjaxNav() {
    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[data-ajax-nav]');
        if (!link) return;
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        if (link.target === '_blank') return;

        const url = link.href;
        if (!url || url.startsWith('mailto:') || url.startsWith('tel:')) return;
        if (new URL(url).origin !== window.location.origin) return;

        event.preventDefault();
        loadPortalPage(url);
    });

    window.addEventListener('popstate', () => {
        if (document.querySelector('[data-portal-main]')) {
            loadPortalPage(window.location.href, { push: false });
        }
    });
}

document.addEventListener('alpine:init', () => {
    Alpine.data('sendSmsFlow', (config) => ({
        step: 'compose',
        mode: 'single',
        busy: false,
        busyAction: null,
        formError: '',
        errors: {},
        balance: config.balance,
        templates: config.templates || [],
        contacts: config.contacts || [],
        contactQuery: '',
        selectedContactIds: [],
        form: {
            name: '',
            sender_id_id: config.defaultSenderId ? String(config.defaultSenderId) : '',
            category: 'transactional',
            template_id: '',
            scheduled_at: '',
            message_body: '',
            numbers: '',
        },
        file: null,
        numbersRaw: '',
        preview: {},
        canLaunch: false,
        canSchedule: false,
        summary: {},

        get filteredContacts() {
            const term = (this.contactQuery || '').trim().toLowerCase();
            const list = this.contacts || [];
            if (!term) return list.slice(0, 80);
            return list.filter((c) => (c.search || '').includes(term)).slice(0, 80);
        },

        setMode(mode) {
            this.mode = mode;
            this.form.category = mode === 'bulk' ? 'marketing' : 'transactional';
            if (mode === 'single' && this.selectedContactIds.length > 1) {
                this.selectedContactIds = this.selectedContactIds.slice(0, 1);
            }
            this.errors = {};
            this.formError = '';
        },

        categoryLabel() {
            return this.mode === 'bulk' ? 'Bulk SMS' : 'Single SMS';
        },

        recipientHint() {
            if (this.mode === 'single') return 'One number or one saved contact';
            return 'Paste list, file upload, and/or saved contacts';
        },

        toggleContact(id) {
            id = Number(id);
            if (this.mode === 'single') {
                this.selectedContactIds = this.selectedContactIds.includes(id) ? [] : [id];
                return;
            }
            if (this.selectedContactIds.includes(id)) {
                this.selectedContactIds = this.selectedContactIds.filter((x) => x !== id);
            } else {
                this.selectedContactIds = [...this.selectedContactIds, id];
            }
        },

        selectAllFilteredContacts() {
            const ids = this.filteredContacts.map((c) => Number(c.id));
            this.selectedContactIds = Array.from(new Set([...this.selectedContactIds, ...ids]));
        },

        clearContacts() {
            this.selectedContactIds = [];
        },

        formatMoney(value) {
            const n = Number(value || 0);
            return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        applyTemplate() {
            const tpl = this.templates.find((t) => String(t.id) === String(this.form.template_id));
            if (tpl?.body) this.form.message_body = tpl.body;
        },

        onFile(event) {
            this.file = event.target.files?.[0] || null;
            if (this.errors.numbers) delete this.errors.numbers;
        },

        validateCompose() {
            const errors = {};
            if (!this.form.name.trim()) errors.name = 'Enter a campaign or message name.';
            if (!this.form.sender_id_id) errors.sender_id_id = 'Select an approved sender ID.';
            if (!this.form.message_body.trim()) errors.message_body = 'Enter the message body.';
            else if (this.form.message_body.length > 1000) errors.message_body = 'Message must be 1000 characters or fewer.';

            const hasNumbers = !!this.form.numbers.trim();
            const hasContacts = this.selectedContactIds.length > 0;
            const hasFile = !!this.file && this.mode === 'bulk';

            if (!hasNumbers && !hasContacts && !hasFile) {
                errors.numbers = this.mode === 'single'
                    ? 'Enter a phone number or pick a saved contact.'
                    : 'Add recipients, pick contacts, or upload a file.';
            }
            if (this.mode === 'single' && this.selectedContactIds.length > 1) {
                errors.numbers = 'Single SMS allows only one contact.';
            }

            this.errors = errors;
            return Object.keys(errors).length === 0;
        },

        buildFormData(extra = {}) {
            const fd = new FormData();
            fd.append('_token', config.csrf);
            fd.append('send_mode', this.mode);
            const category = this.mode === 'bulk' ? 'marketing' : 'transactional';
            this.form.category = category;
            Object.entries(this.form).forEach(([key, value]) => {
                if (key === 'category') {
                    fd.append('category', category);
                    return;
                }
                if (value !== null && value !== undefined) fd.append(key, value);
            });
            this.selectedContactIds.forEach((id) => fd.append('contact_ids[]', String(id)));
            if (this.file && this.mode === 'bulk') fd.append('file', this.file);
            if (this.numbersRaw && !this.form.numbers.trim()) {
                fd.set('numbers', this.numbersRaw);
            }
            Object.entries(extra).forEach(([key, value]) => fd.append(key, value));
            return fd;
        },

        async preview() {
            this.formError = '';
            if (!this.validateCompose()) {
                this.formError = 'Please correct the highlighted fields.';
                return;
            }

            this.busy = true;
            this.busyAction = 'preview';
            const started = Date.now();

            try {
                const response = await fetch(config.previewUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': config.csrf,
                    },
                    body: this.buildFormData(),
                    credentials: 'same-origin',
                });
                const payload = await response.json().catch(() => ({}));
                await this.waitMin(started, 450);

                if (response.status === 422) {
                    this.applyServerErrors(payload);
                    return;
                }
                if (!response.ok) {
                    this.formError = payload.message || 'Could not validate recipients.';
                    return;
                }

                this.preview = payload.preview || {};
                this.numbersRaw = payload.numbers_raw || this.form.numbers;
                this.canLaunch = !!payload.can_launch;
                this.canSchedule = !!payload.can_schedule;
                this.step = 'review';
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } catch (e) {
                this.formError = 'Network error. Check your connection and try again.';
            } finally {
                this.busy = false;
                this.busyAction = null;
            }
        },

        async send(action) {
            this.formError = '';
            this.busy = true;
            this.busyAction = action;
            const started = Date.now();

            try {
                const fd = this.buildFormData({ action });
                fd.set('numbers', this.numbersRaw || this.form.numbers);

                const response = await fetch(config.storeUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': config.csrf,
                    },
                    body: fd,
                    credentials: 'same-origin',
                });
                const payload = await response.json().catch(() => ({}));
                await this.waitMin(started, 700);

                if (response.status === 422) {
                    this.applyServerErrors(payload);
                    if (payload.errors?.form?.[0] || payload.message) {
                        this.formError = payload.errors?.form?.[0] || payload.message;
                    }
                    return;
                }
                if (!response.ok) {
                    this.formError = payload.message || 'Send failed. Please try again.';
                    return;
                }

                this.summary = payload.summary || { title: payload.message || 'Successful', name: this.form.name };
                if (typeof this.summary.balance === 'number') this.balance = this.summary.balance;
                this.step = 'done';
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } catch (e) {
                this.formError = 'Network error. Check your connection and try again.';
            } finally {
                this.busy = false;
                this.busyAction = null;
            }
        },

        applyServerErrors(payload) {
            const next = {};
            Object.entries(payload.errors || {}).forEach(([field, messages]) => {
                next[field] = Array.isArray(messages) ? messages[0] : String(messages);
            });
            this.errors = next;
            this.formError = payload.message || Object.values(next)[0] || 'Please correct the highlighted fields.';
            if (Object.keys(next).some((k) => ['name', 'sender_id_id', 'category', 'message_body', 'numbers', 'scheduled_at', 'file', 'contact_ids'].includes(k))) {
                this.step = 'compose';
            }
        },

        waitMin(started, ms) {
            const left = ms - (Date.now() - started);
            return left > 0 ? new Promise((r) => setTimeout(r, left)) : Promise.resolve();
        },

        reset() {
            this.step = 'compose';
            this.formError = '';
            this.errors = {};
            this.file = null;
            this.numbersRaw = '';
            this.preview = {};
            this.summary = {};
            this.canLaunch = false;
            this.contactQuery = '';
            this.selectedContactIds = [];
            this.form = {
                name: '',
                sender_id_id: config.defaultSenderId ? String(config.defaultSenderId) : '',
                category: this.mode === 'bulk' ? 'marketing' : 'transactional',
                template_id: '',
                scheduled_at: '',
                message_body: '',
                numbers: '',
            };
        },
    }));
});

document.addEventListener('alpine:init', () => {
    Alpine.data('depositModal', (config) => ({
        visible: false,
        step: 'form',
        phone: '',
        amount: '',
        busy: false,
        error: '',
        depositUuid: null,
        depositRef: '',
        credits: null,
        balance: config.balance || 0,
        dismissLeft: config.successDismiss || 60,
        pollTimer: null,
        dismissTimer: null,
        countdownTimer: null,

        open() {
            if (!config.enabled) {
                this.error = 'Deposits are not enabled yet. Contact support.';
                this.visible = true;
                this.step = 'failed';
                return;
            }
            this.resetForm();
            this.visible = true;
        },

        close() {
            this.stopTimers();
            this.visible = false;
            this.resetForm();
        },

        tryClose() {
            if (this.step === 'waiting' || this.busy) return;
            this.close();
        },

        resetForm() {
            this.stopTimers();
            this.step = 'form';
            this.phone = '';
            this.amount = '';
            this.busy = false;
            this.error = '';
            this.depositUuid = null;
            this.depositRef = '';
            this.credits = null;
            this.dismissLeft = config.successDismiss || 60;
        },

        formatMoney(value) {
            const n = Number(value || 0);
            return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        statusUrl() {
            return String(config.statusUrlTemplate || '').replace('DEPOSIT_UUID', this.depositUuid);
        },

        async submit() {
            this.error = '';
            const amount = Number(this.amount);
            if (!String(this.phone || '').trim()) {
                this.error = 'Enter your mobile money number.';
                return;
            }
            if (!Number.isFinite(amount) || amount < (config.minAmount || 100)) {
                this.error = `Minimum amount is ${config.minAmount || 100} TZS.`;
                return;
            }

            this.busy = true;
            try {
                const response = await fetch(config.storeUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': config.csrf,
                    },
                    body: JSON.stringify({ phone: this.phone, amount }),
                    credentials: 'same-origin',
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) {
                    this.error = payload.message || 'Could not start payment.';
                    return;
                }
                this.applyPayload(payload);
                this.step = 'waiting';
                this.startPolling();
            } catch (e) {
                this.error = 'Network error. Please try again.';
            } finally {
                this.busy = false;
            }
        },

        applyPayload(payload) {
            const d = payload.deposit || {};
            this.depositUuid = d.uuid || this.depositUuid;
            this.depositRef = d.request_ref || d.reference || '';
            if (typeof payload.balance === 'number') {
                this.balance = payload.balance;
                this.syncHeaderBalance();
            }
            if (d.is_completed) {
                this.credits = d.credits ?? d.amount;
                this.showSuccess();
                return;
            }
            if (d.is_final) {
                this.error = d.failure_reason || 'Payment was not completed.';
                this.step = 'failed';
                this.stopTimers();
            }
        },

        syncHeaderBalance() {
            document.querySelectorAll('[data-portal-balance]').forEach((el) => {
                el.textContent = this.formatMoney(this.balance);
            });
        },

        startPolling() {
            this.stopTimers();
            const interval = Math.max(5, Number(config.pollInterval || 5)) * 1000;
            this.pollTimer = setInterval(() => this.poll(), interval);
            this.poll();
        },

        async poll() {
            if (!this.depositUuid || this.step !== 'waiting') return;
            try {
                const response = await fetch(this.statusUrl(), {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) return;
                this.applyPayload(payload);
            } catch (e) {
                // keep waiting; next poll retries
            }
        },

        showSuccess() {
            this.stopTimers();
            this.step = 'success';
            this.dismissLeft = Number(config.successDismiss || 60);
            this.countdownTimer = setInterval(() => {
                this.dismissLeft = Math.max(0, this.dismissLeft - 1);
            }, 1000);
            this.dismissTimer = setTimeout(() => this.close(), this.dismissLeft * 1000);
        },

        stopTimers() {
            if (this.pollTimer) clearInterval(this.pollTimer);
            if (this.dismissTimer) clearTimeout(this.dismissTimer);
            if (this.countdownTimer) clearInterval(this.countdownTimer);
            this.pollTimer = null;
            this.dismissTimer = null;
            this.countdownTimer = null;
        },
    }));
});

document.addEventListener('DOMContentLoaded', () => {
    ensureProgressBar();
    bindAjaxForms();
    bindPortalAjaxNav();
});

Alpine.start();
