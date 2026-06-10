# Infraestructura Podman — reserva d'hores

Entorno de desarrollo con 3 servicios:

| Servicio | Tecnología        | URL / Puerto            |
|----------|-------------------|-------------------------|
| `db`     | MySQL 8           | `localhost:8002`        |
| `api`    | Laravel 13 (PHP 8.4) + Vue/Inertia | http://localhost:8000 |
| `admin`  | Vue 3 + Vite      | http://localhost:8001   |

> La aplicación de reserva de hores (presentación, reservas y panel de admin)
> vive en el servicio `api` → **http://localhost:8000**.

## Arrancar todo

Desde este directorio (`php-infraestructura/`):

```bash
podman compose --env-file .env up --build
```

La primera vez tarda un poco: construye la imagen de PHP+Node, instala dependencias de
Composer y npm, compila el frontend (Vite), ejecuta las migraciones y siembra datos.
Cuando veas los servidores corriendo:

- App (presentación + reservas) → http://localhost:8000
- Frontend admin (SPA starter)  → http://localhost:8001

Para pararlo: `Ctrl+C` y luego `podman compose --env-file .env down`.
(Para borrar también la base de datos: `podman compose --env-file .env down -v`)

> También puedes usar los scripts de `bin/` desde la raíz del repo:
> `./bin/up`, `./bin/down`, `./bin/api`, `./bin/artisan api <cmd>`, etc.

## Usuarios sembrados

| Rol     | Email                | Contraseña |
|---------|----------------------|------------|
| Admin   | `admin@example.com`  | `password` |
| Usuario | `user@example.com`   | `password` |

El admin puede crear franjas horarias y publicar posts; el usuario normal reserva
entre las horas disponibles.

## Comandos útiles

```bash
podman compose --env-file .env up -d              # arrancar en segundo plano
podman compose --env-file .env logs -f api        # ver logs del backend
podman compose --env-file .env exec api bash      # entrar al contenedor de Laravel
podman compose --env-file .env exec api php artisan migrate    # ejecutar migraciones
podman compose --env-file .env exec db mysql -ureserva -p reserva   # consola MySQL (pide la contraseña)
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

> Nota: dentro de Podman, Laravel se conecta a la BBDD usando el host `db`
> (no `localhost`), porque es el nombre del servicio en la red de Compose.
