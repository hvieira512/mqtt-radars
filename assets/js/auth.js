const APP_ID = "ql692202504222240166abe";
const APP_SECRET = "1b6fc3d804ce06b087660d282c313ac4fed200c2";
const HOBACARE_USERNAME = "test9";
const HOBACARE_PASSWORD = "test2024";
const LOGIN_URL = "https://qinglanst.com/prod-api/login";

export const BASE_URL = "https://qinglanst.com/prod-api";

const isTokenValid = () => !!localStorage.getItem("credentials");

const login = async () => {
    const response = await fetch(LOGIN_URL, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
            username: HOBACARE_USERNAME,
            password: HOBACARE_PASSWORD,
            pattern: "monitor",
            grantType: "password",
        }),
    });

    if (!response.ok) throw new Error("Login failed");

    const { data } = await response.json();
    const credentials = { appId: APP_ID, appSecret: APP_SECRET, ...data };
    localStorage.setItem("credentials", JSON.stringify(credentials));
    return credentials;
};

const getCredentials = async () => {
    if (!isTokenValid()) await login();
    const stored = localStorage.getItem("credentials");
    return JSON.parse(stored);
};

const flattenObject = (obj, parentKey = "") => {
    let pairs = [];

    for (const key of Object.keys(obj)) {
        const value = obj[key];
        const fullKey = parentKey ? `${parentKey}=${key}` : key;

        if (Array.isArray(value)) {
            pairs.push(`${key}=${value.join("=")}`);
        } else if (typeof value === "object" && value !== null) {
            const nested = flattenObject(value, fullKey);

            nested.forEach((v) => {
                pairs.push(`${key}=${v}`);
            });
        } else {
            pairs.push(`${key}=${value}`);
        }
    }

    return pairs;
};

const generateAuthHeaders = (credentials, data = {}) => {
    const timestamp = Math.floor(Date.now() / 1000);

    let serialized = "";

    if (Object.keys(data).length > 0) {
        const flattened = flattenObject(data);

        flattened.sort((a, b) => {
            const keyA = a.split("=")[0];
            const keyB = b.split("=")[0];
            return keyA.localeCompare(keyB);
        });

        serialized = flattened.join("#") + "#";
    }

    const signatureString = `${credentials.appSecret}#${timestamp}#${serialized}`;

    const signature = sha1(signatureString).toUpperCase();

    return {
        appid: credentials.appId,
        timestamp,
        signature,
    };
};

export const sendRequest = async (url, body = {}, method = "GET") => {
    const credentials = await getCredentials();
    const headers = {
        "Content-Type": "application/json",
        ...generateAuthHeaders(credentials, body),
    };

    const options = { method, headers };
    if (method !== "GET" && body && Object.keys(body).length > 0) {
        options.body = JSON.stringify(body);
    }

    const response = await fetch(url, options);
    if (!response.ok)
        throw new Error(`Request failed with status ${response.status}`);
    return response.json();
};
