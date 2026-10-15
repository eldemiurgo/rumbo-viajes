# Rumbo · Agenda de traslados

Prototipo en Laravel para que un conductor particular registre y administre sus servicios de traslado. Cada cuenta puede crear, consultar, actualizar y eliminar únicamente sus propios registros.

## Funcionalidades

- Registro, inicio y cierre de sesión mediante tokens de Laravel Sanctum.
- CRUD de traslados con dirección de recogida, dirección de destino, fecha y hora, importe y estado.
- Estados: pendiente, completado y cancelado.
- Autocompletado de direcciones de Perú mediante Geoapify.
- Interfaz en Blade, CSS y JavaScript que consume la API con `fetch`.
- Validación de datos, restricciones por propietario y límites de peticiones en registro, login y búsqueda de direcciones.

El precio lo ingresa el conductor: no se calcula automáticamente. El prototipo no realiza reservas, pagos, seguimiento GPS ni búsquedas de empresas de transporte.

## Tecnologías

PHP 8.3 o superior compatible con las dependencias, Laravel 13, Eloquent, Sanctum 4, SQLite y PHPUnit. Composer administra las dependencias. Desarrollado y probado con PHP 8.5.

## Ejecutar localmente

Requisitos: PHP, Composer y las extensiones de PHP necesarias para Laravel, SQLite y peticiones HTTP.

```bash
git clone https://github.com/eldemiurgo/rumbo-viajes.git
cd rumbo-viajes
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan serve
```

Abrir http://127.0.0.1:8000 y crear una cuenta desde **Crear cuenta**. No se incluyen usuarios ni viajes reales en el repositorio.

La interfaz actual carga directamente `public/trips.css` y `public/trips.js`; no requiere compilar assets con npm para esta demostración.

### Configurar Geoapify

Crear una clave propia en https://myprojects.geoapify.com/ y añadirla al archivo `.env`:

```dotenv
DB_CONNECTION=sqlite
GEOAPIFY_API_KEY=tu_clave_personal
```

Después ejecutar `php artisan config:clear`. El backend consulta Geoapify y devuelve hasta cinco sugerencias de Perú. Se muestran la dirección y la localidad/distrito disponible según los datos del proveedor. Es necesario tener Internet. Si no hay clave o el proveedor falla, se puede escribir la dirección manualmente.

La clave, el archivo `.env`, la base SQLite, los logs y las dependencias no se publican. Las direcciones existentes no se actualizan automáticamente cuando cambia el formato de las sugerencias.

## API

Base local: `http://127.0.0.1:8000/api`. Enviar `Accept: application/json`; para cuerpos JSON, `Content-Type: application/json`.

| Método | Ruta | Función | Token |
|---|---|---|---|
| POST | `/register` | Crear una cuenta | No |
| POST | `/login` | Obtener un token | No |
| POST | `/logout` | Revocar el token actual | Sí |
| GET | `/user` | Consultar el usuario autenticado | Sí |
| GET | `/trips` | Listar los traslados del usuario | Sí |
| POST | `/trips` | Crear un traslado | Sí |
| GET | `/trips/{id}` | Consultar un traslado propio | Sí |
| PATCH | `/trips/{id}` | Actualizar un traslado propio | Sí |
| DELETE | `/trips/{id}` | Eliminar un traslado propio | Sí |
| GET | `/addresses?q=texto` | Autocompletar direcciones | Sí |

Registro:

```json
{
  "name": "Conductor de prueba",
  "email": "conductor@example.com",
  "password": "elige-una-clave-segura",
  "password_confirmation": "elige-una-clave-segura"
}
```

Login: enviar `email` y `password` a `/login`. Copiar el token de la respuesta y usarlo en `Authorization: Bearer TU_TOKEN`. En Postman, seleccionar **Authorization → Bearer Token**.

Crear un traslado (sin `user_id`, que se obtiene del token):

```json
{
  "origin": "Avenida Arequipa 1234, Lima",
  "destination": "Aeropuerto Jorge Chávez, Callao",
  "departure_at": "2026-11-20 09:00:00",
  "price": 80
}
```

`origin` y `destination` conservan sus nombres internos y representan las direcciones. La fecha y hora de la interfaz se trabajan en UTC. El precio se ingresa manualmente y el estado inicial es `pending`. Para actualizar el estado se admiten `pending`, `completed` y `cancelled`.

Respuestas principales: `200` operación correcta, `201` creado, `401` sin autenticación válida, `404` traslado inexistente o ajeno, `422` validación y `429` límite de peticiones. Registro y login permiten cinco peticiones por minuto; direcciones, sesenta por minuto.

## Estructura relevante

- `app/Models`: usuarios, traslados y sus relaciones.
- `app/Http/Controllers/Api`: autenticación, CRUD y consulta de Geoapify.
- `routes/api.php`: endpoints y middleware.
- `database/migrations`: estructura de tablas.
- `resources/views/trips.blade.php`: interfaz web.
- `public/trips.js`: peticiones a la API y tabla interactiva.
- `tests/Feature`: pruebas de autenticación, permisos, CRUD e integración externa simulada.

## Pruebas

```bash
php artisan test --compact
```

Las pruebas utilizan SQLite en memoria. Las llamadas a Geoapify se simulan y no requieren una clave real.

## Recorrido de demostración

1. Registrar una cuenta e iniciar sesión.
2. Crear un traslado usando las sugerencias de direcciones.
3. Consultarlo, editar su importe/estado y eliminarlo.
4. En Postman, mostrar que una petición sin token devuelve `401`.
5. Cerrar sesión y comprobar que el token queda revocado.

## Alcance

Proyecto de aprendizaje y demostración. El servidor `php artisan serve` es de desarrollo. Para publicar una aplicación de producción se requiere configurar HTTPS, el servidor, la depuración y el ciclo de vida de los tokens. La sesión de la interfaz se mantiene en memoria: recargar la página requiere iniciar sesión otra vez.
