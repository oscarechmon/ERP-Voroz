import axios, { type AxiosInstance } from 'axios';
import { basePath } from './basePath';

/**
 * Instancia central de Axios para consumir la API.
 *
 * - `withCredentials`: envía las cookies de sesión de Sanctum (auth SPA).
 * - `X-Requested-With`: identifica la petición como AJAX para Laravel.
 * - Interceptor de respuesta: normaliza errores y expulsa al login ante un 401.
 */
const http: AxiosInstance = axios.create({
    baseURL: `${basePath}/api/v1`,
    // Sin límite, una petición que no contesta deja la pantalla cargando para
    // siempre. Las subidas de imágenes piden más tiempo por su cuenta.
    timeout: 30000,
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
    },
});

/** URL completa de un endpoint de la API, para abrirla en otra pestaña (PDF, Excel). */
export const apiUrl = (path: string): string => `${basePath}/api/v1${path}`;

/** Obtiene la cookie CSRF de Sanctum antes de operaciones que mutan estado/login. */
export async function ensureCsrf(): Promise<void> {
    await axios.get(`${basePath}/sanctum/csrf-cookie`, { withCredentials: true });
}

// Manejo global de errores de autenticación/sesión.
http.interceptors.response.use(
    (response) => response,
    (error) => {
        const status = error?.response?.status;
        // Ruta dentro de la app, sin el prefijo: es lo que entiende el router.
        const path = window.location.pathname.slice(basePath.length) || '/';
        if (status === 401 && !path.startsWith('/login')) {
            // Sesión expirada: redirige al login preservando destino.
            const redirect = encodeURIComponent(path);
            window.location.assign(`${basePath}/login?redirect=${redirect}`);
        }
        return Promise.reject(error);
    },
);

export default http;
