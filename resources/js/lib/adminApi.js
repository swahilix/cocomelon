// Small helpers for admin JSON endpoints (suggestions that are not page visits).

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

async function send(url, body) {
    let response;
    try {
        response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
                ...(body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
            },
            body: body instanceof FormData ? body : JSON.stringify(body || {}),
        });
    } catch {
        return { ok: false, message: 'Could not reach the server. Check your connection and try again.' };
    }

    let data = {};
    try {
        data = await response.json();
    } catch {
        data = {};
    }

    if (response.ok) return { ok: true, data };

    const firstError = data.errors ? Object.values(data.errors).flat()[0] : null;
    const message = response.status === 429
        ? 'Too many requests. Wait a minute and try again.'
        : response.status === 419
            ? 'Your session expired. Refresh the page and try again.'
            : firstError || data.message || 'Something went wrong. Please try again.';

    return { ok: false, message };
}

export const postJson = (url, body) => send(url, body);
export const postForm = (url, formData) => send(url, formData);
