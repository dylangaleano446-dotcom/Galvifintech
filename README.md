# GalviFintech Portal

Plataforma web de gestión financiera y control de gastos para PyMEs (SaaS B2B).
Proyecto Final de 7° Año - E.E.S.T. N°4 - Especialidad Informática / Programación.

Autor: Dylan Galeano

## Tecnologías

- Frontend: HTML5, Tailwind CSS, JavaScript Vanilla y Chart.js
- Backend: PHP 8.2, Slim Framework 4 (API REST) y PDO
- Servicio complementario: Node.js + Express (cotizador y reportes CSV)
- Base de datos: MySQL / MariaDB (XAMPP)
- Seguridad: JWT, bcrypt, consultas preparadas y CORS

## Estructura

- database: script SQL de la base de datos con datos de prueba
- backend: API REST con Slim 4
- servicio-node: servicio Node.js del cotizador y la exportación CSV
- frontend: landing, login, panel del cliente y panel de administración

## Instalación local (Windows + XAMPP)

1. Clonar el repositorio dentro de C:\xampp\htdocs con el nombre galvifintech.
2. Iniciar Apache y MySQL desde el XAMPP Control Panel.
3. Importar database/galvifintech.sql en phpMyAdmin.
4. En la carpeta backend ejecutar: composer install
5. Copiar backend/src/Config/config.example.php como config.php y completar el puerto de MySQL y la clave JWT.
6. En la carpeta servicio-node ejecutar: npm install
7. Copiar servicio-node/src/config.example.json como config.json (con la misma clave JWT) y ejecutar: npm start
8. Abrir http://localhost/galvifintech/frontend/

## Cuentas de prueba

- Cliente al día: demo@galvifintech.com / Demo1234!
- Cliente suspendido: moroso@galvifintech.com / Moroso1234!
- Administrador: admin@galvifintech.com / Admin1234!

## Etapas del desarrollo

1. Base de datos
2. API con Slim
3. Lectura de transacciones
4. Alta, edición y baja de transacciones
5. Login con JWT
6. Frontend básico
7. Dashboard con gráficos
8. Servicio Node.js: cotizador y exportación CSV
9. Abonos: vencimiento, suspensión por falta de pago y panel de administrador
