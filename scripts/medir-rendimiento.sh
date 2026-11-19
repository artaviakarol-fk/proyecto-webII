#!/usr/bin/env bash
# Mide cuánto tarda la API en responder las rutas principales.
# Uso (con la API corriendo y el db:seed hecho):  bash scripts/medir-rendimiento.sh [veces] [url]
# Muestra, por ruta, la mediana (p50), el percentil 95 (p95) y el peor tiempo, en milisegundos.
set -euo pipefail

VECES=${1:-20}
API=${2:-http://localhost:8000/api/v1}
NEGOCIO=019a0000-0000-7000-8000-000000000001
JSON=(-H 'Content-Type: application/json' -H 'Accept: application/json')

token() {
  curl -s "${JSON[@]}" -X POST "$API/tokens" -d "{\"correo\":\"$1\",\"contrasena\":\"$2\",\"dispositivo\":\"medicion\"}" \
    | sed -n 's/.*"token":"\([^"]*\)".*/\1/p'
}

ADMIN=$(token admin@teresita.test password)
[ -n "$ADMIN" ] || { echo "No se pudo iniciar sesión como admin@teresita.test. ¿Está la API arriba y hecho el db:seed?"; exit 1; }

CORREO="medicion$RANDOM$RANDOM@ejemplo.test"
curl -s -o /dev/null "${JSON[@]}" -X POST "$API/negocios/$NEGOCIO/registro" \
  -d "{\"nombre\":\"Medición\",\"correo\":\"$CORREO\",\"contrasena\":\"password123\",\"contrasena_confirmation\":\"password123\"}"
CLIENTE=$(token "$CORREO" password123)

medir() { # medir <nombre> <token> <ruta>
  local tiempos=()
  curl -s -o /dev/null "${JSON[@]}" -H "Authorization: Bearer $2" "$API$3" # calentar
  for _ in $(seq "$VECES"); do
    tiempos+=("$(curl -s -o /dev/null -w '%{time_total}' "${JSON[@]}" -H "Authorization: Bearer $2" "$API$3")")
  done
  printf '%s\n' "${tiempos[@]}" | sort -n | awk -v nombre="$1" '
    { t[NR] = $1 * 1000 }
    END {
      p50 = t[int((NR + 1) / 2)]; i = int(NR * 0.95); if (i < 1) i = 1; p95 = t[i]
      printf "%-28s %8.0f %8.0f %8.0f\n", nombre, p50, p95, t[NR]
    }'
}

printf '%-28s %8s %8s %8s   (ms, %s veces)\n' 'ruta' 'p50' 'p95' 'peor' "$VECES"
medir 'GET /usuario' "$ADMIN" /usuario
medir 'GET /negocio' "$ADMIN" /negocio
medir 'GET /productos' "$ADMIN" /productos
medir 'GET /clientes' "$ADMIN" /clientes
medir 'GET /ventas' "$ADMIN" /ventas
medir 'GET /vencidos' "$ADMIN" /vencidos
medir 'GET /reportes/cierre-diario' "$ADMIN" /reportes/cierre-diario
[ -n "$CLIENTE" ] && medir 'GET /catalogo' "$CLIENTE" /catalogo
[ -n "$CLIENTE" ] && medir 'GET /reservas' "$CLIENTE" /reservas

INICIO=$(date +%s%N)
token admin@teresita.test password >/dev/null
printf '%-28s %8.0f   (una vez: incluye cifrar la contraseña con bcrypt)\n' 'POST /tokens (login)' "$(( ($(date +%s%N) - INICIO) / 1000000 ))"
