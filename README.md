# UniDeportes — Sistema de gestión para tienda deportiva

Sistema de gestión para una tienda de artículos deportivos: inventario, ventas directas y
mayoristas, pedidos con abonos, entregas a domicilio, clientes y reportes.

Proyecto formativo del programa **ADSO (SENA)**, desarrollado en equipo y validado con datos
de prueba. No llegó a producción.

![PHP lint](https://github.com/joelcastrodev/unideportes-system/actions/workflows/php-lint.yml/badge.svg)

## Qué resuelve

Una tienda deportiva maneja tres cosas a la vez y en papeles distintos: el inventario por
tallas, las ventas (que no son iguales al detal que al por mayor) y los pedidos que se pagan
por partes. Este sistema las unifica en una sola aplicación web con roles distintos para
administrador, colaborador y vendedor.

## Módulos

**Autenticación y roles**
Login con sesiones PHP y redirección según rol: administrador, colaborador y vendedor, cada
uno con su propio panel. Incluye recuperación de contraseña y migración automática de
contraseñas antiguas en texto plano a `password_hash`.

**Inventario**
Productos con variantes por talla, control de stock, alertas de mínimo, precios y registro
de productos nuevos.

**Ventas**
Venta directa y venta mayorista, cada una con su flujo de ticket. Cálculo de totales y
registro de la venta por vendedor.

**Pedidos y abonos**
Creación de pedidos, pagos parciales (abonos), cambio de estado y seguimiento. Un pedido
puede quedar pagado por partes sin bloquear la entrega.

**Clientes**
Registro, edición, historial y búsqueda por AJAX sin recargar la página.

**Entregas a domicilio**
Confirmación de entrega, procesamiento y estados de despacho.

**Reportes**
Panel con indicadores de ventas y cartera: pedidos pendientes, abonos recientes, productos
más vendidos y ventas detalladas.

**Soporte técnico**
Tickets de soporte con vista aparte para el vendedor.

## Stack

| Capa | Tecnología |
|---|---|
| Backend | PHP 8+ con PDO |
| Base de datos | MySQL 8 |
| Vistas | PHP, HTML5, CSS3, JavaScript |
| Módulo frontend | React + Vite (gestión de productos) |
| Pruebas E2E | Cypress |
| CI | GitHub Actions (PHP lint) |

## Requisitos

- XAMPP con PHP 8.0 o superior y MySQL
- Node.js 18+ (solo para Cypress y el módulo React)
- Navegador moderno

## Instalación

**1. Clonar en `htdocs` con el nombre exacto de la carpeta**

```bash
cd C:\xampp\htdocs
git clone https://github.com/joelcastrodev/unideportes-system.git
```

> La carpeta **debe** llamarse `unideportes-system`. Las rutas internas están construidas
> sobre `/unideportes-system/public`, así que si la renombras, la aplicación no encuentra
> los archivos.

**2. Crear la base de datos e importar el esquema**

En phpMyAdmin (`http://localhost/phpmyadmin`):

1. Crear una base de datos llamada `unideportes`
2. Importar `database/unideportes_db.sql`
3. Aplicar las migraciones de `database/migrations/` en orden. Si el volcado es anterior al
   2026-10-29, hace falta la que agrega `usuarios.estado`:

   ```bash
   php config/migrate_estado_usuario.php
   ```

**3. Revisar la conexión**

`config/connection.php` viene configurado para el entorno por defecto de XAMPP
(`127.0.0.1`, usuario `root`, sin contraseña). Ajusta esos valores si tu MySQL es distinto.

**4. Abrir la aplicación**

```
http://localhost/unideportes-system/public/index.php
```

**5. (Opcional) Módulo React**

```bash
cd frontend
npm install
npm run dev
```

## Estructura del proyecto

```
unideportes-system/
├── .github/workflows/       PHP lint en cada push
├── config/                  Conexión PDO, bootstrap y migraciones
├── controllers/             Lógica de cada acción (ventas, pedidos, clientes, auth)
├── models/                  Clases de acceso a datos por entidad
├── views/                   Paneles y pantallas
│   └── partials/            Componentes reutilizables (KPIs, listados)
├── public/                  Punto de entrada y JavaScript del cliente
│   ├── index.php            Login
│   └── js/                  Ventas, pedidos, venta mayorista, confección
├── frontend/                Módulo React + Vite
├── database/                Esquema y datos de ejemplo (.sql)
├── cypress/                 Pruebas end-to-end
└── tests/node/              Pruebas de endpoints
```

## Pruebas

**End-to-end con Cypress**

```bash
npx cypress open
```

Casos incluidos: login exitoso, login fallido, gestión de clientes, venta directa y venta
mayorista.

**Endpoints**

```bash
node tests/node/test-endpoints.js
```

**Integración continua**

Cada push ejecuta `php-lint` sobre todo el código PHP. El estado se ve en la pestaña
**Actions**.

## Estado de seguridad

Lo que está implementado y lo que falta, sin adornos:

| Medida | Estado |
|---|---|
| Contraseñas con `password_hash` / `password_verify` | ✅ Implementado |
| Consultas con PDO y sentencias preparadas | ✅ Implementado en autenticación |
| Migración de contraseñas en texto plano a hash | ✅ Implementado |
| Tokens CSRF en formularios | ⚠️ Pendiente |
| Regeneración del ID de sesión tras el login | ⚠️ Pendiente |
| Credenciales en variables de entorno | ⚠️ Pendiente (hoy van en `config/connection.php`) |
| HTTPS en producción | ⚠️ No aplica (el proyecto no se desplegó) |

Esta sección existe porque un sistema académico que reconoce sus límites es más confiable
que uno que declara ser perfecto.

## Acceso de demostración

Después de importar el volcado, la cuenta de administrador es:

| Usuario | Contraseña |
|---|---|
| `admin` | `demo1234` |

Cámbiala si algún día despliegas esto en un servidor. Las demás cuentas del volcado tienen
contraseñas de prueba que no se documentan.

## Datos de ejemplo

El volcado `database/unideportes_db.sql` contiene el esquema completo y datos **ficticios**
creados para probar el sistema: nombres, cédulas, teléfonos, correos y direcciones fueron
inventados y no corresponden a personas ni empresas reales.

## Contexto del proyecto

Trabajo de formación del programa **Análisis y Desarrollo de Software** del SENA, realizado
en equipo. El objetivo era diseñar y construir un sistema completo con base de datos,
autenticación y reportes, aplicando arquitectura por capas.

El proyecto **no fue desplegado**: se validó en entorno local con datos de prueba.
