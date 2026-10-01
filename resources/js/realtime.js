import Echo from "laravel-echo";

let connection;
export function getRealtime() {
    if (import.meta.env.VITE_MERCURE_ENABLED !== "true") return null;
    if (!connection) {
        connection = new Echo({
            broadcaster: "mercure",
            host: import.meta.env.VITE_MERCURE_HUB_URL,
            authEndpoint: "/broadcasting/auth",
        });
    }
    return connection;
}
export function disconnectRealtime() {
    connection?.disconnect();
    connection = undefined;
}
