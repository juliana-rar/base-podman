# Guia de Docker — com fer servir aquest projecte

Totes les comandes s'executen des de la carpeta **`php-infraestructura/`** (on hi ha el `docker-compose.yml`):

```bash
cd docker/php-infraestructura
```

> Recordatori de serveis: `api` (Laravel · 8000), `admin` (Vue · 8001), `db` (MySQL · 8002).

---

## 1. Cicle de vida dels contenidors

| Comanda | Què fa |
|---|---|
| `docker compose up -d --build` | **Aixeca** els contenidors (i reconstrueix les imatges). El `-d` els deixa en segon pla |
| `docker compose ps` | **Veure** els contenidors aixecats i el seu estat |
| `docker compose down` | **Atura i elimina** els contenidors (⚠️ **conserva** la base de dades) |
| `docker compose down -v` | Atura i elimina els contenidors **I també esborra la BBDD** (la `-v` elimina el volum) |
| `docker compose restart api` | **Reinicia** només el backend (pots posar `admin` o `db`) |

> 🔑 Diferència clau: `down` és segur (no perds dades). `down -v` ho esborra tot, inclosa la base de dades. Fes servir `-v` només quan vulguis començar de zero.

---

## 2. Veure què passa (logs i diagnòstic)

| Comanda | Què fa |
|---|---|
| `docker compose logs -f` | Veure els logs de **tots** els serveis en directe |
| `docker compose logs -f api` | Logs en directe només del backend |
| `docker compose logs --tail=100 api` | Últimes 100 línies de logs del backend |
| `docker compose top` | Processos que corren dins dels contenidors |
| `docker compose config` | Veure la config final ja resolta (útil per detectar errors al `.yml`) |

---

## 3. Treballar dins dels contenidors (`exec`)

`docker compose exec <servei> <comanda>` executa una comanda **dins** del contenidor.

### Backend (Laravel · servei `api`)

```bash
docker compose exec api bash                          # entrar a la terminal del contenidor
docker compose exec api php artisan migrate           # executar migracions
docker compose exec api php artisan cache:clear       # netejar cachés
docker compose exec api php artisan test              # passar els tests
docker compose exec api composer require nom/paquet   # instal·lar un paquet de PHP
```

### Frontend (Vue · servei `admin`)

```bash
docker compose exec admin npm run build               # compilar per a producció
docker compose exec admin npm install nom-paquet      # instal·lar un paquet de JS
```

### Base de dades (MySQL · servei `db`)

```bash
docker compose exec db mysql -uefforwai -p efforwai   # consola MySQL (demana la contrasenya)
```

---

## 4. Comandes globals de Docker (no de Compose)

Aquestes afecten **tot Docker**, no només aquest projecte:

| Comanda | Què fa |
|---|---|
| `docker stats` | CPU i RAM que consumeix cada contenidor, en directe |
| `docker system df` | Quant espai ocupen imatges, contenidors i volums |
| `docker system prune` | Allibera espai esborrant coses no utilitzades (⚠️ demana confirmació) |

---

## Recepta ràpida del dia a dia

```bash
cd docker/php-infraestructura
docker compose up -d --build     # arrencar
docker compose ps                # comprovar que estan amunt
docker compose logs -f           # mirar què passa
# ... treballar ...
docker compose down              # plegar (sense perdre dades)
```
