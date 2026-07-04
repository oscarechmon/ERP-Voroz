import axios, { type AxiosInstance } from 'axios';

/**
 * Instancia central de Axios para consumir la API.
 *
 * - `withCredentials`: envía las cookies de sesión de Sanctum (auth SPA).
 * - `X-Requested-With`: identifica la petición como AJAX para Laravel.
 * - Interceptor de respuesta: normaliza errores y expulsa al login ante un 401.
 */
const http: AxiosInstance = axios.create({
    baseURL: '/api/v1',
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
    },
});

/** Obtiene la cookie CSRF de Sanctum antes de operaciones que mutan estado/login. */
export async function ensureCsrf(): Promise<void> {
    await axios.get('/sanctum/csrf-cookie', { withCredentials: true });
}

// Manejo global de errores de autenticación/sesión.
http.interceptors.response.use(
    (response) => response,
    (error) => {
        const status = error?.response?.status;
        if (status === 401 && !window.location.pathname.startsWith('/login')) {
            // Sesión expirada: redirige al login preservando destino.
            const redirect = encodeURIComponent(window.location.pathname);
            window.location.assign(`/login?redirect=${redirect}`);
        }
        return Promise.reject(error);
    },
);

export default http;
