# Rendimiento de la API

Cómo se mide cuánto tarda la API, qué se encontró y qué se cambió.

## Cómo medir

Con la API corriendo y el `db:seed` hecho, desde la raíz del repositorio:

```bash
bash scripts/medir-rendimiento.sh          # 20 peticiones por ruta contra http://localhost:8000/api/v1
bash scripts/medir-rendimiento.sh 50       # más peticiones, para una medición más estable
```

El script inicia sesión como `admin@teresita.test`, registra un cliente de prueba y llama varias veces a las rutas principales. Por cada ruta muestra:

- **p50 (mediana):** la mitad de las peticiones tardó menos que esto. Es el tiempo "normal".
- **p95:** el 95 % de las peticiones tardó menos que esto. Muestra las peticiones lentas sin que una sola rara cambie el resultado.
- **peor:** la petición más lenta de la medición.

Además, la prueba `tests/Feature/Performance/QueryCountTest.php` corre en el CI y falla si un listado vuelve a hacer una consulta por cada fila.

## Qué se encontró

Medido con la API en un servidor Linux (PostgreSQL 17, PHP 8.4 con OPcache), 20 peticiones por ruta:

| Ruta | Consultas antes | Consultas después | p50 |
|---|---|---|---|
| `GET /clientes` (página de 15) | 5 + 1 por cliente | 6, sin importar cuántos clientes haya | ~25 ms |
| `GET /catalogo` (página de 15) | 5 + 2 por producto | 7, sin importar cuántos productos haya | ~25 ms |
| `GET /productos`, `/ventas`, `/vencidos`, `/reservas`, `/reportes/cierre-diario` | 4 o 5 | sin cambio | 20–30 ms |
| `POST /tokens` (inicio de sesión) | 11 | sin cambio | ~300 ms |

1. **Consultas repetidas por fila ("N+1").** La lista de clientes de fiado pedía las ampliaciones de límite de cada cliente por separado, y el catálogo pedía los vencidos y los reservados de cada producto por separado. Con 15 filas por página eran 15 o 30 consultas extra. Ahora se piden las de toda la página en una sola consulta agrupada (`ofMany` en los contadores del catálogo y `with('activeIncreases')` en los clientes).
2. **`GET /pagos/pasarelas` respondía 500** cuando una pasarela no tenía llaves (por ejemplo, ONVO sin `ONVO_SECRET_KEY`), y cobrar con esa pasarela daba un "error inesperado". Ahora la pasarela aparece como no configurada y cobrar con ella responde `503` con el mensaje "Esa pasarela de pago no está configurada".
3. **El inicio de sesión tarda unos 300 ms a propósito:** casi todo ese tiempo es bcrypt, que hace lenta cada prueba de contraseña para que adivinarla por fuerza bruta sea muy costoso. No se bajó.

## Por qué en Windows tardaba segundos

En la compu, la API corre en Docker con la carpeta `api/` montada desde Windows. Leer archivos de esa carpeta desde Linux es muy lento, y había dos cosas que leían miles de archivos:

1. **Cada arranque revisaba todas las dependencias** (`composer install`). Con la carpeta montada eso tardaba de 5 a 6 minutos, y mientras tanto la página daba "Error 502". Ahora `docker/entrypoint.sh` solo instala cuando falta `vendor/` o cuando cambió `composer.lock`.
2. **Cada petición leía las dependencias de PHP desde Windows.** Ahora `vendor/` vive en un volumen de Docker (`api_vendor`), dentro de Linux, y la carpeta de Windows solo tiene el código del proyecto. OPcache (ya activo) guarda el código compilado en memoria.

La primera vez después de este cambio, Docker llena el volumen con `composer install` (unos minutos). Los arranques siguientes tardan segundos.

> El volumen `api_vendor` es aparte de la carpeta `api/vendor` de Windows. Para que el editor encuentre las clases de Laravel, `api/vendor` de Windows se sigue llenando con `composer install` en la compu, como antes.
