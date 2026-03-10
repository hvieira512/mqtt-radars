export const BASE_URL = "http://localhost:8000/proxy.php";

export const sendRequest = async (endpoint, params = {}) => {
    const url = new URL(BASE_URL);
    url.searchParams.append("endpoint", endpoint);

    Object.keys(params).forEach((key) => {
        url.searchParams.append(key, params[key]);
    });

    const response = await fetch(url);
    if (!response.ok)
        throw new Error(`Request failed with status ${response.status}`);
    return response.json();
};
