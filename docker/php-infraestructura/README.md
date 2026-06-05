# Infraestructura Docker — efforwai

Entorno de desarrollo con 3 servicios:

| Servicio | Tecnología        | URL / Puerto            |
|----------|-------------------|-------------------------|
| `db`     | MySQL 8           | `localhost:8002`        |
| `api`    | Laravel 13 (PHP 8.4) | http://localhost:8000 |
| `admin`  | Vue 3 + Vite      | http://localhost:8001   |

## Arrancar todo

Desde este directorio (`php-infraestructura/`):

```bash
docker compose up --build
```

La primera vez tarda un poco: construye la imagen de PHP, instala dependencias de
Composer y npm, y ejecuta las migraciones. Cuando veas los servidores corriendo:

- Frontend → http://localhost:8001
- Backend  → http://localhost:8000

Para pararlo: `Ctrl+C` y luego `docker compose down`.
(Para borrar también la base de datos: `docker compose down -v`)

## Comandos útiles

```bash
docker compose up -d              # arrancar en segundo plano
docker compose logs -f api        # ver logs del backend
docker compose exec api bash      # entrar al contenedor de Laravel
docker compose exec api php artisan migrate    # ejecutar migraciones
docker compose exec db mysql -uefforwai -p efforwai   # consola MySQL (demana la contrasenya)
# Desde el host (p.ej. con un cliente GUI) usa el puerto 8002.
```

## Credenciales de la base de datos

Las credenciales reales viven en `php-infraestructura/.env` (no versionado). Hay una plantilla
en `php-infraestructura/.env.example`. Las variables son:

```
MYSQL_DATABASE
MYSQL_USER
MYSQL_PASSWORD
MYSQL_ROOT_PASSWORD
```

Laravel las consume desde `php-api/.env` (`DB_HOST=db`, `DB_PORT=8002`, etc.),
que también está fuera del repositorio.

> Nota: dentro de Docker, Laravel se conecta a la BBDD usando el host `db`
> (no `localhost`), porque es el nombre del servicio en la red de Compose.
