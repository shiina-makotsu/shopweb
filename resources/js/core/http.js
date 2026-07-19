export const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

export const fetchJson = async (url, options = {}) => {
    const response = await fetch(url, {
        ...options,
        headers: {
            Accept: 'application/json',
            ...options.headers,
        },
    });

    if (!response.ok) {
        throw new Error(`request-failed:${response.status}`);
    }

    return response.json();
};
