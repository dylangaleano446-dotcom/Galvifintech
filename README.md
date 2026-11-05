# GalviFintech Portal

Plataforma web de gestión financiera y control de gastos para PyMEs (SaaS B2B).
Proyecto Final de 7° Año — E.E.S.T. N°4 — Especialidad Informática / Programación.

**Autor:** Dylan Galeano

## Tecnologías

- **Frontend:** HTML5, Tailwind CSS, JavaScript Vanilla, Chart.js
- **Backend:** PHP 8.1+, Slim Framework 4 (API REST), PDO
- **Base de datos:** MySQL / MariaDB (XAMPP)

## Estructura

| Carpeta | Contenido |
| --- | --- |
| `database/` | Script SQL de creación de la base y datos de prueba |
| `backend/` | API REST con Slim 4 (solo `public/` queda expuesta al navegador) |
| `frontend/` | Landing, login y panel privado GalviFintech Portal |

## Instalación local (XAMPP)

1. Clonar el repo dentro de `C:\xampp\htdocs\`.
2. Importar `database/galvifintech.sql` en phpMyAdmin.
3. En `backend/` ejecutar `composer install`.
4. Copiar `backend/src/Config/config.example.php` como `config.php` y completar los datos.
5. Abrir `http://localhost/galvifintech/frontend/`.

## Estado del desarrollo

- [ ] Etapa 1 — Base de datos
- [ ] Etapa 2 — API con Slim (health check)
- [ ] Etapa 3 — Lectura de transacciones
- [ ] Etapa 4 — Alta, edición y baja
- [ ] Etapa 5 — Login con JWT
- [ ] Etapa 6 — Frontend básico
- [ ] Etapa 7 — Dashboard con gráficos
- [ ] Etapa 8 — Exportación CSV, cotizador y landing
