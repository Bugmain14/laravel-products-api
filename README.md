# Products API

API RESTful en Laravel para gestionar productos y sus precios en diferentes divisas.

## Tecnologías

- PHP 8.2+ y Laravel 11/12
- Eloquent ORM
- Laravel Sanctum (autenticación por token)
- MySQL, PostgreSQL o SQLite
- PHPUnit (pruebas de integración)

## Instalación

```bash
git clone https://github.com/Bugmain14/laravel-products-api.git
cd laravel-products-api
composer install
cp .env.example .env
php artisan key:generate
```

Configura la base de datos en `.env` y luego:
Por defecto usa SQLite; si `php artisan migrate` pregunta si desea crear el archivo de la base de datos, responda `yes`.

```bash
php artisan migrate --seed
php artisan serve
```

Los precios calculados se recalculan en segundo plano (ver Decisiones de diseño). Con `QUEUE_CONNECTION=database` (el valor de `.env.example`) hay que dejar corriendo el worker de colas en otra terminal:

```bash
php artisan queue:work
```

Con `QUEUE_CONNECTION=sync` los jobs se ejecutan en la misma petición y no hace falta el worker.

El seeder crea las divisas USD, COP, EUR y MXN, 10 productos de ejemplo y estos usuarios:

| Email | Password | Rol |
|---|---|---|
| admin@example.com | password | admin |
| viewer@example.com | password | viewer |

## Autenticación

Todos los endpoints de productos y divisas requieren un token Bearer.

```http
POST /api/login
Content-Type: application/json

{ "email": "admin@example.com", "password": "password" }
```

Respuesta:

```json
{ "token": "1|abc...", "token_type": "Bearer" }
```

Envía el token en cada petición:

```
Authorization: Bearer 1|abc...
Accept: application/json
```

## Endpoints

| Método | Ruta | Descripción |
|---|---|---|
| POST | `/api/login` | Obtener token |
| POST | `/api/logout` | Revocar el token actual |
| GET | `/api/products` | Listar productos (paginado, con filtros, búsqueda y orden) |
| POST | `/api/products` | Crear producto |
| GET | `/api/products/{id}` | Obtener un producto con sus precios |
| PUT | `/api/products/{id}` | Actualizar producto (actualización parcial permitida; recalcula sus precios calculados) |
| DELETE | `/api/products/{id}` | Eliminar producto (borrado lógico; conserva sus precios) |
| GET | `/api/products/trashed` | Listar productos eliminados (mismo formato y mismos filtros que el listado) |
| POST | `/api/products/{id}/restore` | Restaurar un producto eliminado (409 si no está eliminado) |
| DELETE | `/api/products/{id}/force` | Borrar definitivamente un producto eliminado y sus precios (409 si está activo) |
| GET | `/api/products/{id}/prices` | Listar precios del producto |
| POST | `/api/products/{id}/prices` | Registrar precio en otra divisa |
| DELETE | `/api/products/{id}/prices/{priceId}` | Eliminar un precio del producto (404 si el precio no pertenece al producto) |
| GET | `/api/currencies` | Listar divisas (sin paginar, ordenadas por `name`) |
| POST | `/api/currencies` | Crear divisa |
| GET | `/api/currencies/{id}` | Obtener una divisa |
| PUT | `/api/currencies/{id}` | Actualizar divisa (actualización parcial permitida; un cambio de tasa recalcula los precios calculados) |
| DELETE | `/api/currencies/{id}` | Eliminar divisa (409 si está en uso) |

### Filtros, búsqueda y orden

`GET /api/products` y `GET /api/products/trashed` aceptan estos parámetros opcionales:

| Parámetro | Descripción |
|---|---|
| `search` | Coincidencia parcial en `name` o `description`, sin distinguir mayúsculas |
| `currency` | Código de la divisa base (`USD`, `usd`…), sin distinguir mayúsculas |
| `min_price` / `max_price` | Rango sobre `price`, límites incluidos (`min_price` ≤ `max_price`) |
| `sort` | `name`, `price` o `created_at`; prefijo `-` para descendente (`-price`). Por defecto, los más recientes primero (en `trashed`, los eliminados más recientemente) |
| `page` / `per_page` | Paginación; `per_page` entre 1 y 100 (por defecto 15) |

Los parámetros inválidos responden 422, y los enlaces de paginación (`links`) conservan los filtros.

```http
GET /api/products?search=laptop&currency=usd&min_price=100&max_price=2000&sort=-price&per_page=10
```

### Crear producto

```json
{
  "name": "Laptop",
  "description": "Business laptop",
  "price": 1200.50,
  "currency_id": 1,
  "tax_cost": 228.10,
  "manufacturing_cost": 800
}
```

También se pueden registrar sus precios en otras divisas en la misma petición, de forma atómica:

```json
{
  "name": "Laptop",
  "price": 1200.50,
  "currency_id": 1,
  "tax_cost": 228.10,
  "manufacturing_cost": 800,
  "prices": [
    { "currency_id": 2, "price": 4800000 },
    { "currency_id": 3 }
  ]
}
```

`prices` es opcional (hasta 20 elementos). Cada `currency_id` debe existir, no repetirse y ser distinto de la divisa base; si se omite `price`, se calcula con las tasas de cambio. La respuesta 201 incluye el producto con su divisa y sus precios.

### Registrar precio en otra divisa

```json
{ "currency_id": 2, "price": 4800000 }
```

`price` es opcional. Si no se envía, se calcula con las tasas de cambio (ver Decisiones de diseño). La divisa debe ser distinta de la divisa base del producto, y el precio admite hasta 9999999999999999.99.

Cada precio indica su origen en el campo `origin`: `manual` (enviado en la petición) o `calculated` (calculado con las tasas de cambio). Solo los calculados se recalculan automáticamente.

```json
{
  "data": {
    "id": 7,
    "product_id": 3,
    "price": "4802000.00",
    "origin": "calculated",
    "currency": { "id": 2, "name": "COP", "symbol": "$", "exchange_rate": "4000.000000" },
    "created_at": "2026-10-30T15:04:05+00:00"
  }
}
```

### Crear divisa

```json
{ "name": "COP", "symbol": "$", "exchange_rate": 4000 }
```

`name` es un código ISO 4217 de 3 letras; se normaliza a mayúsculas antes de validar (`cop` → `COP`). `exchange_rate` debe ser mayor que 0, con hasta 6 decimales.

### Códigos de respuesta

| Código | Caso |
|---|---|
| 200 | Consulta o actualización exitosa |
| 201 | Recurso creado |
| 204 | Producto, divisa o precio eliminados |
| 401 | Sin token o token inválido |
| 403 | El rol del usuario no permite la acción |
| 404 | Recurso no encontrado |
| 409 | Divisa en uso que no se puede eliminar, restaurar un producto que no está eliminado o borrar definitivamente un producto activo |
| 422 | Error de validación |
| 429 | Demasiadas peticiones |

## Documentación

La colección de Postman está en `docs/products-api.postman_collection.json`. La petición **Login** guarda el token automáticamente en la variable `token` de la colección.

## Pruebas

```bash
php artisan test
```

Las pruebas unitarias (`tests/Unit`) cubren `PriceConverter`: conversión, misma divisa, redondeo, precio manual frente a calculado y un caso de montos grandes en el que `float` falla. Las de integración (`tests/Feature`) cubren autenticación, límites de peticiones, autorización por rol (403 para viewer en acciones de escritura), CRUD de productos y divisas, validaciones (incluida la normalización del código de divisa y la tasa mayor que 0), 404, registro de precios (manual y calculado), eliminación de precios (incluido el 404 para un precio de otro producto), creación de un producto con sus precios en una sola petición (validaciones y rollback de la transacción), divisas duplicadas, filtros, búsqueda y orden del listado (incluidos sus 422 y los enlaces de paginación), borrado lógico, listado de eliminados, restauración y borrado definitivo (con su cascada y sus 409), el 409 al eliminar una divisa en uso, incluso si solo la usan productos eliminados, y el recálculo automático de precios (despacho del job solo cuando cambia la tasa, el precio o la divisa base; precios manuales intactos; idempotencia; cambio de divisa base con precio calculado o manual, y el flujo completo de eliminar el precio manual para poder cambiarla).

## Decisiones de diseño

- **Tasa de cambio:** `exchange_rate` indica cuántas unidades de la divisa equivalen a 1 unidad de la divisa de referencia (USD = 1). Para convertir de la divisa A a la B: `precio × tasa_B / tasa_A`, con un único redondeo final a 2 decimales (`HALF_UP`).
- **Aritmética exacta:** `PriceConverter` calcula con `brick/math` (`BigDecimal`), no con `float`. Se eligió sobre BCMath porque no exige ninguna extensión de PHP (usa BCMath o GMP si están disponibles), es la misma librería que Laravel usa internamente para el cast `decimal` y la validación, y su API inmutable obliga a declarar el modo de redondeo. Está declarada explícitamente en `composer.json`.
- **Precio en otra divisa:** si se envía `price`, se respeta ese valor (precio comercial definido por el negocio). Si no, se calcula a partir del precio base y las tasas de cambio.
- **Origen del precio:** `product_prices.origin` guarda un Enum (`App\Enums\PriceOrigin`: `manual` o `calculated`) en lugar de un booleano: se lee solo en la API, admite nuevos orígenes sin otra migración y sigue el patrón de `UserRole`. Los precios previos a la columna se tratan como `manual` (valor por defecto), para que el recálculo nunca sobrescriba un valor que pudo ser comercial. `PriceConverter::priceFor()` devuelve el monto y su origen (`App\ValueObjects\ResolvedPrice`), y es el único lugar donde se decide.
- **Un precio por divisa:** un producto no puede tener dos precios en la misma divisa (índice único `product_id + currency_id` y validación con respuesta 422).
- **Soft deletes en productos:** `DELETE /api/products/{id}` hace un borrado lógico (`deleted_at`). El producto deja de aparecer en el listado, en el detalle y en sus precios (404), pero sus precios se conservan para poder restaurarlo. El borrado definitivo (`/force`) solo acepta productos ya eliminados (si no, 409), lo que obliga a un proceso en dos pasos; ahí la cascada de la base de datos (`cascadeOnDelete`) elimina sus precios. Restaurar un producto que no está eliminado responde 409.
- **Eliminación de divisas:** una divisa en uso por productos (incluidos los eliminados lógicamente, que siguen referenciándola en la BD) o por precios no se puede eliminar: la API lo comprueba antes y responde 409 en JSON, y la llave foránea (`restrictOnDelete`) actúa como respaldo; si se viola por una condición de carrera, también se responde 409 en lugar de 500.
- **Recálculo automático:** `CurrencyObserver` y `ProductObserver` (registrados con `#[ObservedBy]`) despachan un job en cola solo si el campo realmente cambió (`wasChanged`): `RecalculateCurrencyPrices` cuando cambia el `exchange_rate` (recalcula los precios en esa divisa y los de productos cuya divisa base es esa) y `RecalculateProductPrices` cuando cambia el `price` o el `currency_id` de un producto. Solo se tocan los precios `calculated`, incluidos los de productos eliminados lógicamente, para que al restaurarlos estén al día. Se eligieron observers en lugar de Event + Listener porque el disparador es el ciclo de vida del modelo y así funciona con cualquier actualización, no solo con la API.
- **Jobs idempotentes:** los jobs solo guardan el ID y recalculan con los valores vigentes al ejecutarse, así que repetirlos da el mismo resultado (y Eloquent no escribe si el valor no cambió). Usan `afterCommit` para no leer datos sin confirmar y `ShouldBeUniqueUntilProcessing` para no encolar el mismo recálculo varias veces sin perder un cambio que llegue mientras uno se ejecuta.
- **Cambio de divisa base:** si la nueva divisa base de un producto coincide con uno de sus precios, ese precio sobra porque el `price` del producto pasa a ser su valor en esa divisa. Si el precio es `calculated`, se elimina en la misma transacción de la actualización; si es `manual`, la actualización se rechaza con 422 y no se aplica ningún cambio, para no perder un precio comercial sin que el usuario lo decida. Para continuar, se elimina ese precio con `DELETE /api/products/{id}/prices/{priceId}` (ruta con `scopeBindings()`, así que un precio de otro producto responde 404) y se repite la actualización. Por la misma regla, `POST /api/products/{id}/prices` no acepta la divisa base.
- **Filtros del listado:** se implementan como local scopes de `Product` (`#[Scope]`: `search`, `ofCurrency`, `priceBetween`, `sortBy` y `filter`, que los combina), compartidos por el listado y por `trashed`. `sort` usa una lista blanca (`Product::SORTABLE`) y desempata por `id` para que la paginación sea estable.
- **Búsqueda portable:** `search` usa `whereLike(..., caseSensitive: false)` de Laravel, que genera `ilike` en PostgreSQL y `like` en MySQL y SQLite, así que no distingue mayúsculas en los tres motores. Limitaciones conocidas: `%` y `_` en el texto actúan como comodines (solo amplían los resultados; el valor siempre va como binding), y en SQLite la insensibilidad a mayúsculas aplica solo a caracteres ASCII.
- **Creación atómica con precios:** la lógica vive en la clase de acción `App\Actions\CreateProductWithPrices`, que el controlador recibe por inyección. Crea el producto y sus precios dentro de `DB::transaction()`: si falla cualquier precio, no queda ni el producto ni ningún precio. La decisión "precio manual o calculado" está en un único lugar, `PriceConverter::priceFor()`, que usan tanto esta acción como `POST /api/products/{id}/prices`.
- **Montos:** columnas decimales para evitar errores de precisión de los `float`: `decimal(12,2)` para los montos de productos, `decimal(18,2)` para los precios en otras divisas (una conversión a una divisa de tasa alta, como COP, supera el rango de `decimal(12,2)`) y `decimal(18,6)` para la tasa de cambio. Las reglas de validación correspondientes están centralizadas en `App\Rules\AmountRules` (`money()`, `currencyPrice()` y `exchangeRate()`).

## Seguridad

- Autenticación con Laravel Sanctum en todos los endpoints de productos y divisas.
- Autorización por roles (`App\Enums\UserRole`, columna `role` en `users`, por defecto `viewer`):

  | Acción | admin | viewer |
  |---|---|---|
  | Listar y ver productos y sus precios | ✓ | ✓ |
  | Crear, actualizar y eliminar productos | ✓ | 403 |
  | Registrar y eliminar precios en otras divisas | ✓ | 403 |
  | Listar eliminados, restaurar y borrar definitivamente productos | ✓ | 403 |
  | Listar y ver divisas | ✓ | ✓ |
  | Crear, actualizar y eliminar divisas | ✓ | 403 |

- Las reglas viven en `ProductPolicy` y `CurrencyPolicy` y se aplican con el middleware `can` declarado en cada controlador (`HasMiddleware`). El middleware corre antes que los Form Requests, así que un usuario sin permiso recibe 403 y nunca ve los errores de validación (422).
- `role` no está en `$fillable`: no se puede asignar en masa desde una petición, lo que evita la escalada de privilegios.
- Límite de peticiones con limitadores con nombre definidos en `AppServiceProvider` (`RateLimiter::for`): `login` permite 5 intentos por minuto por email e IP y 20 por minuto por IP, y `api` permite 60 peticiones por minuto por usuario autenticado. Responden 429.
- Validación de todas las entradas con Form Requests (tipos, rangos, decimales y existencia de llaves foráneas).
- Protección contra asignación masiva con `$fillable`.
- Respuestas de error en JSON, sin exponer detalles internos.

## Observaciones sobre el enunciado

- El campo `Price` del modelo de productos se implementó como `price` (minúscula), por convención.
- En Divisas, `name` se describe como "Identificador único"; se interpretó como el nombre de la divisa (por ejemplo, USD) y se definió como único.
- En Precios de productos, `id` se describe como "Identificador único de la divisa"; se interpretó como el identificador del precio.

## Estructura

```
app/
├── Actions/               CreateProductWithPrices (creación atómica de producto y precios)
├── Enums/                 UserRole (admin, viewer), PriceOrigin (manual, calculated)
├── Http/
│   ├── Controllers/Api/   AuthController, CurrencyController, ProductController, ProductPriceController
│   ├── Requests/          Validaciones (Index/Store/Update)
│   └── Resources/         Formato de las respuestas JSON
├── Jobs/                  RecalculateCurrencyPrices, RecalculateProductPrices (en cola)
├── Models/                Currency, Product, ProductPrice, User
├── Observers/             CurrencyObserver, ProductObserver (disparan el recálculo)
├── Policies/              CurrencyPolicy, ProductPolicy (permisos por rol)
├── Rules/                 AmountRules (reglas de montos reutilizables)
├── Services/              PriceConverter (conversión exacta), PriceRecalculator (recálculo de precios calculados)
└── ValueObjects/          ResolvedPrice (monto y origen de un precio)
database/
├── factories/
├── migrations/
└── seeders/
docs/                      Colección de Postman
tests/Unit/                Pruebas unitarias de PriceConverter
tests/Feature/             Pruebas de la API
```
