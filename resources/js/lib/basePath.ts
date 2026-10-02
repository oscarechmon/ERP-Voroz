/**
 * Prefijo de URL bajo el que se sirve la app: '' en la raíz de un (sub)dominio,
 * como en producción (sistema.sinexcusas.org.pe), o '/carpeta' si se sirve
 * desde una subcarpeta (por ejemplo XAMPP en localhost/sistema).
 *
 * Lo escribe Laravel en app.blade.php a partir de la petición, de modo que el
 * router, la API y las redirecciones nunca llevan la carpeta escrita a mano.
 */
export const basePath: string = (
    document.querySelector<HTMLMetaElement>('meta[name="base-path"]')?.content ?? ''
).replace(/\/+$/, '');
