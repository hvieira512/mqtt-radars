export const BASE_URL = "http://localhost:8000/proxy.php";

/**
 * Core flexible request function
 * @param {string} endpoint - API endpoint
 * @param {object} options - Options including method, params, body, headers
 * @returns {Promise<any>} - Parsed JSON response
 */
export const sendRequest = async (endpoint, options = {}) => {
    const { method = "GET", params = {}, body = null, headers = {} } = options;

    // Build URL
    const url = new URL(BASE_URL);
    url.searchParams.append("endpoint", endpoint);

    // Append any additional query params
    if (params && Object.keys(params).length) {
        Object.entries(params).forEach(([key, value]) => {
            url.searchParams.append(key, value);
        });
    }

    // Fetch options
    const fetchOptions = {
        method,
        headers: {
            "Content-Type": "application/json",
            ...headers,
        },
    };

    if (body) {
        fetchOptions.body = JSON.stringify(body);
    }

    const response = await fetch(url, fetchOptions);

    if (!response.ok) {
        const text = await response.text();
        throw new Error(
            `Request to ${url} failed with status ${response.status}: ${text}`,
        );
    }

    return response.json();
};

/**
 * Helper functions for convenience
 */
export const getRequest = (endpoint, params = {}, headers = {}) =>
    sendRequest(endpoint, { method: "GET", params, headers });

export const postRequest = (endpoint, body = {}, params = {}, headers = {}) =>
    sendRequest(endpoint, { method: "POST", body, params, headers });

export const putRequest = (endpoint, body = {}, params = {}, headers = {}) =>
    sendRequest(endpoint, { method: "PUT", body, params, headers });

export const patchRequest = (endpoint, body = {}, params = {}, headers = {}) =>
    sendRequest(endpoint, { method: "PATCH", body, params, headers });

export const deleteRequest = (endpoint, params = {}, headers = {}) =>
    sendRequest(endpoint, { method: "DELETE", params, headers });
