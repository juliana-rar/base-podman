# Guia de Podman — com fer servir aquest projecte

Totes les comandes s'executen des de la carpeta **`php-infraestructura/`** (on hi ha el `docker-compose.yml` i el `.env`):

```bash
cd podman/php-infraestructura
```

> Recordatori de serveis: `api` (Laravel + Vue/Inertia · 8000), `admin` (Vue · 8001), `db` (MySQL · 8002).
> L'aplicació de reserva d'hores és al servei `api` → http://localhost:8000.
>
> En executar des d'aquesta carpeta, `podman compose` ja carrega el `.env` automàticament.

---

## 1. Cicle de vida dels contenidors

| Comanda | Què fa |
|---|---|
| `podman compose up -d --build` | **Aixeca** els contenidors (i reconstrueix les imatges). El `-d` els deixa en segon pla |
| `podman compose ps` | **Veure** els contenidors aixecats i el seu estat |
| `podman compose down` | **Atura i elimina** els contenidors (⚠️ **conserva** la base de dades) |
| `podman compose down -v` | Atura i elimina els contenidors **I també esborra la BBDD** (la `-v` elimina el volum) |
| `podman compose restart api` | **Reinicia** només el backend (pots posar `admin` o `db`) |

> 🔑 Diferència clau: `down` és segur (no perds dades). `down -v` ho esborra tot, inclosa la base de dades. Fes servir `-v` només quan vulguis començar de zero.

---

## 2. Veure què passa (logs i diagnòstic)

| Comanda | Què fa |
|---|---|
| `podman compose logs -f` | Veure els logs de **tots** els serveis en directe |
| `podman compose logs -f api` | Logs en directe només del backend |
| `podman compose logs --tail=100 api` | Últimes 100 línies de logs del backend |
| `podman compose top` | Processos que corren dins dels contenidors |
| `podman compose config` | Veure la config final ja resolta (útil per detectar errors al `.yml`) |

---

## 3. Treballar dins dels contenidors (`exec`)

`podman compose exec <servei> <comanda>` executa una comanda **dins** del contenidor.

### Backend (Laravel + Vue/Inertia · servei `api`)

```bash
podman compose exec api bash                          # entrar a la terminal del contenidor
podman compose exec api php artisan migrate           # executar migracions
podman compose exec api php artisan db:seed           # sembrar dades (admin, usuari, franges, posts)
podman compose exec api php artisan cache:clear       # netejar cachés
podman compose exec api php artisan test              # passar els tests
podman compose exec api composer require nom/paquet   # instal·lar un paquet de PHP
podman compose exec api npm run build                 # recompilar el frontend (Vite)
```

### Frontend admin (Vue SPA · servei `admin`)

```bash
podman compose exec admin npm run build               # compilar per a producció
podman compose exec admin npm install nom-paquet      # instal·lar un paquet de JS
```

### Base de dades (MySQL · servei `db`)

```bash
podman compose exec db mysql -ureserva -p reserva     # consola MySQL (demana la contrasenya)
```

---

## 4. Comandes globals de Podman (no de Compose)

Aquestes afecten **tot Podman**, no només aquest projecte:

| Comanda | Què fa |
|---|---|
| `podman stats` | CPU i RAM que consumeix cada contenidor, en directe |
| `podman system df` | Quant espai ocupen imatges, contenidors i volums |
| `podman system prune` | Allibera espai esborrant coses no utilitzades (⚠️ demana confirmació) |

---

## Recepta ràpida del dia a dia

```bash
cd podman/php-infraestructura
podman compose up -d --build     # arrencar
podman compose ps                # comprovar que estan amunt
podman compose logs -f           # mirar què passa
# ... treballar ...
podman compose down              # plegar (sense perdre dades)
```
