# GalviFintech Portal

Plataforma web de gestión financiera y control de gastos para PyMEs (SaaS B2B).
Proyecto Final de 7° Año — E.E.S.T. N°4 — Especialidad Informática / Programación.

*Autor:* Dylan Galeano

## Tecnologías

- *Frontend:* HTML5, Tailwind CSS, JavaScript Vanilla, Chart.js
- *Backend:* PHP 8.2, Slim Framework 4 (API REST), PDO
- *Servicio complementario:* Node.js + Express (cotizador y reportes CSV)
- *Base de datos:* MySQL / MariaDB (XAMPP)
- *Seguridad:* JWT, bcrypt, consultas preparadas, CORS

## Estructura

| Carpeta | Contenido |
| --- | --- |
| database/ | Script SQL de creación de la base y datos de prueba |
| backend/ | API REST con Slim 4 (solo public/ queda expuesta al navegador) |
| servicio-node/ | Servicio Node.js: cotizador de abonos y exportación CSV |
| frontend/ | Landing, login y panel privado GalviFintech Portal |

## Instalación local (Windows + XAMPP)

*Requisitos:* XAMPP con PHP 8.1 o superior, Composer, Node.js 18 o superior y Git.

1. Clonar el repo dentro de C:\xampp\htdocs\:
   
   git clone https://github.com/dylangaleano446-dotcom/Galvifintech galvifintech
   
2. Iniciar *Apache* y *MySQL* desde el XAMPP Control Panel.
3. Importar database/galvifintech.sql en phpMyAdmin.
4. En backend/ ejecutar composer install.
5. Copiar backend/src/Config/config.example.php como config.php y completar el puerto de MySQL y la clave JWT.
6. Probar la API: http://localhost/galvifintech/backend/public/api/health
7. En servicio-node/ ejecutar npm install, copiar src/config.example.json como src/config.json (misma clave JWT que config.php) y luego npm start.
8. Abrir http://localhost/galvifintech/frontend/

*Cuenta demo:* demo@galvifintech.com / Demo1234! (los datos de prueba son de octubre 2026).

## Estado del desarrollo

- [x] Etapa 1 — Base de datos
- [x] Etapa 2 — API con Slim (health check)
- [x] Etapa 3 — Lectura de transacciones
- [x] Etapa 4 — Alta, edición y baja
- [x] Etapa 5 — Login con JWT
- [x] Etapa 6 — Frontend básico
- [x] Etapa 7 — Dashboard con gráficos
- [x] Etapa 8 — Servicio Node.js: cotizador y exportación CSV
