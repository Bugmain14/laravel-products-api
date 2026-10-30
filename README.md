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
| GET | `/api/products` | Listar productos (paginado: `page`, `per_page` hasta 100) |
| POST | `/api/products` | Crear producto |
| GET | `/api/products/{id}` | Obtener un producto con sus precios |
| PUT | `/api/products/{id}` | Actualizar producto (actualización parcial permitida) |
| DELETE | `/api/products/{id}` | Eliminar producto (borrado lógico; conserva sus precios) |
| GET | `/api/products/trashed` | Listar productos eliminados (paginado, mismo formato que el listado) |
| POST | `/api/products/{id}/restore` | Restaurar un producto eliminado (409 si no está eliminado) |
| DELETE | `/api/products/{id}/force` | Borrar definitivamente un producto eliminado y sus precios (409 si está activo) |
| GET | `/api/products/{id}/prices` | Listar precios del producto |
| POST | `/api/products/{id}/prices` | Registrar precio en otra divisa |
| GET | `/api/currencies` | Listar divisas (sin paginar, ordenadas por `name`) |
| POST | `/api/currencies` | Crear divisa |
| GET | `/api/currencies/{id}` | Obtener una divisa |
| PUT | `/api/currencies/{id}` | Actualizar divisa (actualización parcial permitida) |
| DELETE | `/api/currencies/{id}` | Eliminar divisa (409 si está en uso) |

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

### Registrar precio en otra divisa

```json
{ "currency_id": 2, "price": 4800000 }
```

`price` es opcional. Si no se envía, se calcula con las tasas de cambio (ver Decisiones de diseño).

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
| 204 | Producto o divisa eliminados |
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

Cubren autenticación, autorización por rol (403 para viewer en acciones de escritura), CRUD de productos y divisas, validaciones (incluida la normalización del código de divisa y la tasa mayor que 0), 404, registro de precios (manual y calculado), divisas duplicadas, borrado lógico, listado de eliminados, restauración y borrado definitivo (con su cascada y sus 409), y el 409 al eliminar una divisa en uso, incluso si solo la usan productos eliminados.

## Decisiones de diseño

- **Tasa de cambio:** `exchange_rate` indica cuántas unidades de la divisa equivalen a 1 unidad de la divisa de referencia (USD = 1). Para convertir de la divisa A a la B: `precio / tasa_A × tasa_B`, redondeado a 2 decimales.
- **Precio en otra divisa:** si se envía `price`, se respeta ese valor (precio comercial definido por el negocio). Si no, se calcula a partir del precio base y las tasas de cambio.
- **Un precio por divisa:** un producto no puede tener dos precios en la misma divisa (índice único `product_id + currency_id` y validación con respuesta 422).
- **Soft deletes en productos:** `DELETE /api/products/{id}` hace un borrado lógico (`deleted_at`). El producto deja de aparecer en el listado, en el detalle y en sus precios (404), pero sus precios se conservan para poder restaurarlo. El borrado definitivo (`/force`) solo acepta productos ya eliminados (si no, 409), lo que obliga a un proceso en dos pasos; ahí la cascada de la base de datos (`cascadeOnDelete`) elimina sus precios. Restaurar un producto que no está eliminado responde 409.
- **Eliminación de divisas:** una divisa en uso por productos (incluidos los eliminados lógicamente, que siguen referenciándola en la BD) o por precios no se puede eliminar: la API lo comprueba antes y responde 409 en JSON, y la llave foránea (`restrictOnDelete`) actúa como respaldo; si se viola por una condición de carrera, también se responde 409 en lugar de 500.
- **Cambio de tasa:** actualizar el `exchange_rate` de una divisa no recalcula los precios ya registrados.
- **Montos:** columnas `decimal(12,2)` para dinero y `decimal(18,6)` para la tasa de cambio, para evitar errores de precisión de los `float`.

## Seguridad

- Autenticación con Laravel Sanctum en todos los endpoints de productos y divisas.
- Autorización por roles (`App\Enums\UserRole`, columna `role` en `users`, por defecto `viewer`):

  | Acción | admin | viewer |
  |---|---|---|
  | Listar y ver productos y sus precios | ✓ | ✓ |
  | Crear, actualizar y eliminar productos | ✓ | 403 |
  | Registrar precios en otra divisa | ✓ | 403 |
  | Listar eliminados, restaurar y borrar definitivamente productos | ✓ | 403 |
  | Listar y ver divisas | ✓ | ✓ |
  | Crear, actualizar y eliminar divisas | ✓ | 403 |

- Las reglas viven en `ProductPolicy` y `CurrencyPolicy` y se aplican con el middleware `can` declarado en cada controlador (`HasMiddleware`). El middleware corre antes que los Form Requests, así que un usuario sin permiso recibe 403 y nunca ve los errores de validación (422).
- `role` no está en `$fillable`: no se puede asignar en masa desde una petición, lo que evita la escalada de privilegios.
- Límite de peticiones: 5 intentos de login por minuto y 60 peticiones por minuto en la API.
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
├── Enums/                 UserRole (admin, viewer)
├── Http/
│   ├── Controllers/Api/   AuthController, CurrencyController, ProductController, ProductPriceController
│   ├── Requests/          Validaciones (Store/Update)
│   └── Resources/         Formato de las respuestas JSON
├── Models/                Currency, Product, ProductPrice, User
├── Policies/              CurrencyPolicy, ProductPolicy (permisos por rol)
└── Services/              PriceConverter (conversión entre divisas)
database/
├── factories/
├── migrations/
└── seeders/
docs/                      Colección de Postman
tests/Feature/             Pruebas de la API
```
