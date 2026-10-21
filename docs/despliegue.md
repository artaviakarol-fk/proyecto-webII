# Despliegue en el VPS

La API corre en un VPS con Docker: los mismos contenedores que en local (API y PostgreSQL) más **Caddy**, que recibe el HTTPS con un certificado automático y le pasa las peticiones a la API.

```
Internet ──443──▶ Caddy ──▶ API (Laravel, puerto 8000 interno) ──▶ PostgreSQL (5432 interno)
```

Solo Caddy publica puertos (80 y 443). La API y la base de datos no son accesibles desde internet.

## 1. Preparar el servidor (una sola vez)

Entrar con `ssh ubuntu@IP_DEL_VPS` (o el usuario que indique el correo del proveedor) y ejecutar:

```bash
sudo apt update && sudo apt upgrade -y
sudo ufw allow OpenSSH && sudo ufw allow 80 && sudo ufw allow 443 && sudo ufw --force enable
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER
exit
```

Volver a entrar con `ssh` para que el permiso de Docker se active.

## 2. Descargar el proyecto y configurarlo

```bash
git clone https://github.com/artaviakarol-fk/proyecto-webII.git
cd proyecto-webII
git checkout main
cp .env.example .env
nano .env
```

En el `.env` de la raíz:

| Variable | Valor |
|---|---|
| `DB_PASSWORD` | Contraseña larga y nueva para el rol de la aplicación |
| `DB_ADMIN_PASSWORD` | Otra contraseña larga y nueva para el dueño del esquema |
| `DOMAIN` | La IP del VPS **con guiones** + `.sslip.io`. Si la IP es `51.79.10.20`: `51-79-10-20.sslip.io` |

Guardar con `Ctrl+O`, `Enter` y `Ctrl+X`. Este archivo nunca se sube al repositorio.

## 3. Levantar todo

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.yml -f docker-compose.prod.yml logs -f api
```

La primera vez tarda unos minutos: construye la imagen, crea los roles de PostgreSQL, instala dependencias, genera la clave y corre las migraciones. Cuando el log diga `Server running on [http://0.0.0.0:8000]`, salir con `Ctrl+C`.

Cargar los dos negocios de demostración (datos ficticios):

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec api php artisan db:seed --force
```

## 4. Comprobar

Con el dominio del `.env`:

- `https://DOMINIO/api/v1/health` → `"status": "ok"` y `"database": "ok"`
- `https://DOMINIO/docs/api` → la documentación de Scramble

Esa es la **dirección pública** que se entrega.

## Actualizar después de un cambio

```bash
cd proyecto-webII
git pull
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

## Si algo falla

| Síntoma | Qué revisar |
|---|---|
| El navegador no abre el dominio | `docker compose ... ps`: los tres contenedores deben decir `running`. `sudo ufw status` debe permitir 80 y 443 |
| Error de certificado | Caddy tarda hasta un minuto la primera vez. Ver `docker compose ... logs caddy` |
| `database: unavailable` | `docker compose ... logs db`. Si se cambiaron las contraseñas después del primer arranque, hay que borrar el volumen: `docker compose ... down -v` y volver a levantar (se pierden los datos) |
| Error 500 | `docker compose ... logs api` |
