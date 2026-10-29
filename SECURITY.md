# Revisión de seguridad

Revisión del propio código, hecha el **2026-10-29** sobre 8 de los 75 archivos del proyecto,
elegidos por riesgo: autenticación, control de usuarios, conexión a base de datos y guardas
de acceso.

Contexto: proyecto formativo, **no desplegado**, con datos de prueba. Se documenta igual
porque encontrar los propios errores y corregirlos es parte del trabajo.

## Alcance

Revisado: `config/bootstrap.php`, `config/connection.php`, `controllers/auth.php`,
`controllers/usuarios.php`, `controllers/delete_user.php`,
`controllers/buscar_clientes_ajax.php`, `views/header.php`, `views/panel_vendedor.php`.

No revisado: los 25 controladores restantes, las 35 vistas y los 7 modelos.

## Resultado

| # | Hallazgo | Severidad | Estado |
|---|---|---|---|
| 1 | El código usaba una columna `estado` que no existía en la base: la gestión de estado de usuarios nunca funcionó, y el login no validaba nada | Alta | ✅ Corregido |
| 2 | Acciones destructivas por `GET` sin token CSRF | Alta | ⏳ Pendiente |
| 3 | Dos controladores que borran usuarios con reglas distintas | Media | ✅ Corregido |
| 4 | La conexión imprimía el error de PDO en el navegador | Media | ✅ Corregido |
| 5 | La validación de "último administrador" no filtraba por estado | Media | ✅ Corregido |
| 6 | No se renovaba el ID de sesión al iniciar sesión | Media | ✅ Corregido |
| 7 | `request()` mezclaba `GET`, `POST` y cookies | Baja | ⏳ Pendiente |
| 8 | Un entero interpolado directamente en SQL | Baja | ✅ Corregido |
| 9 | El CSS se recargaba en cada visita por usar `time()` | Baja | ⏳ Pendiente |
| 10 | La ruta base estaba escrita a mano en más de 20 archivos | Baja | ⏳ Pendiente |

---

## 1. El código usaba una columna que no existía en la base

`controllers/usuarios.php` activa, desactiva y hace soft delete usando `usuarios.estado`. La
tabla real no tenía esa columna (`id, name, lastname, username, password, email, role,
created_at`), así que toda acción de esa pantalla lanzaba una excepción que el `catch` convertía
en `error=error_base_datos`. La gestión de estado de usuarios nunca funcionó.

Y aunque la columna hubiera existido, el login no la consultaba:

```php
SELECT id, username, password, role FROM usuarios WHERE username = ?
```

**Corregido:** se agregó la columna con una migración
(`config/migrate_estado_usuario.php`) con valor por defecto `activo`, para no bloquear cuentas
existentes al desplegar el cambio. El login ahora trae `estado` y lo valida después de
comprobar la contraseña, tratando `NULL` como activo. Se valida después de la contraseña para
no revelar a un tercero si un usuario existe.

## 2. Acciones destructivas por GET (pendiente)

`delete_user.php?id=`, `usuarios.php?action=eliminar` y `action=toggle_estado` se ejecutan con
solo visitar una URL. Un administrador con la sesión abierta que abriera una página maliciosa
podría ejecutar un borrado sin saberlo (CSRF).

**Plan:** mover las acciones destructivas a `POST` y validar un token de sesión. Se hará por
fases, empezando por los borrados, porque afecta formularios de varias vistas.

## 3. Dos controladores para la misma operación

`controllers/delete_user.php` borraba siempre; `controllers/usuarios.php?action=eliminar`
desactiva si el usuario tiene ventas asociadas. La primera ruta podía dejar historial huérfano.

**Corregido:** se eliminó `controllers/delete_user.php` y su redirección en
`views/delete_user.php`. Queda un solo camino, el que protege el historial.

## 4. Error de conexión expuesto

```php
die("Error de conexión a la BD: " . $e->getMessage());
```

Mostraba host, usuario y detalles internos al navegador.

**Corregido:** el detalle va a `error_log()` y al usuario le llega un mensaje genérico.

## 5. Validación inconsistente del último administrador

Al desactivar se filtraba por `estado = 'activo'`; al eliminar no. Con un admin activo y otro
inactivo, se podía eliminar el activo y dejar el sistema sin administradores que pudieran
entrar.

**Corregido:** ambas consultas filtran por estado activo.

## 6. Fijación de sesión

No se llamaba a `session_regenerate_id()` tras autenticar.

**Corregido:** se renueva el ID de sesión antes de guardar los datos del usuario.

## 7. `request()` usaba `$_REQUEST` (pendiente)

`$_REQUEST` incluye cookies, así que difumina el origen de cada dato. Se reemplazará por
funciones `get()` y `post()` explícitas.

## 8. Entero interpolado en SQL

```php
$pdo->query("SELECT COUNT(*) FROM usuarios WHERE role = 'admin' AND id != {$id}");
```

No era explotable (`$id` venía de `intval` y se validaba), pero contradecía el uso de
sentencias preparadas del resto del archivo.

**Corregido:** las dos consultas usan parámetro.

## 9. El CSS se descargaba en cada visita (pendiente)

`style.css?v=<?= time() ?>` genera una URL distinta en cada petición y desactiva la caché del
navegador. Se cambiará por la fecha de modificación del archivo.

## 10. Ruta base repetida (pendiente)

`/unideportes-system` aparece escrito a mano en controladores, vistas y JavaScript. Si cambia
el nombre de la carpeta, se rompe todo. Se reemplazará por una constante.

---

## Lo que falta revisar

- `procesar_venta.php` y `procesar_pedido.php`: ¿usan transacciones? ¿Puede quedar una venta
  registrada a medias si falla el segundo `INSERT`?
- Las 35 vistas: ¿escapan toda la salida con `htmlspecialchars`?
- Los 7 modelos: ¿todas las consultas son preparadas?

Esta revisión no es exhaustiva y no pretende serlo: cubre los puntos donde una falla cuesta
más caro.
