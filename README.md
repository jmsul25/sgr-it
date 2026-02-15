Empleado: lidia@sgr-it.local
Admin: jmsierra@sgr-it.local
pass : Cursos1
SGR-IT

Sistema de Gestión de Recursos IT

SGR-IT es una aplicación web desarrollada en PHP y MySQL que permite gestionar y reservar recursos tecnológicos en un entorno educativo u oficina.

El sistema permite diferenciar entre administradores y empleados, organizar recursos por categorías y evitar conflictos temporales en las reservas.

Objetivo del Proyecto

El objetivo principal es ofrecer una solución estructurada para la gestión de recursos como:

Portátiles

Salas

Webcams

Micrófonos

Otros dispositivos tecnológicos

El sistema evita reservas duplicadas, permite gestión por categorías y mantiene historial de uso.

Funcionalidades
Autenticación y Roles

Inicio de sesión con email y contraseña.

Separación de permisos entre administrador y empleado.

Control de sesiones.

Gestión de Recursos (Administrador)

Crear nuevos recursos.

Asignar categorías.

Cambiar estado (disponible / en reparación).

Visualizar historial de reservas por recurso.

Reservas (Empleado)

Reserva individual de recursos.

Reserva múltiple por categoría (cantidad).

Control automático de solapamientos.

Cancelación de reservas.

Visualización de historial personal.

Validaciones

Control de fechas.

Prevención de solapamientos.

Exclusión automática de recursos en reparación.

Validación en backend mediante consultas preparadas.

Tecnologías Utilizadas

PHP 8

MySQL

Apache (XAMPP)

Bootstrap 5

HTML5 / CSS3

Git y GitHub

Estructura del Proyecto
sgr-it/
│
├── admin/
├── user/
├── config/
├── assets/
├── database/
│   └── sgr_it.sql
├── login.php
├── logout.php
└── README.md

Instalación

Instalar XAMPP.

Copiar la carpeta sgr-it dentro de htdocs.

Crear una base de datos en phpMyAdmin.

Importar el archivo database/sgr_it.sql.

Configurar credenciales en config/db.php.

Acceder a:
http://localhost/sgr-it

Seguridad Aplicada

Uso de password_hash() y password_verify().

Consultas preparadas para evitar inyección SQL.

Control de sesiones por rol.

Validaciones en servidor.

Posibles Mejoras Futuras

Vista de calendario.

Sistema de notificaciones.

API REST.

Arquitectura MVC.

Auditoría completa de cancelaciones.

Autores

Aarón Enrique – Diseño de base de datos.

Jesús Manzanero – Desarrollo web y lógica backend.

José María Sierra – Documentación técnica y guía de usuario.
