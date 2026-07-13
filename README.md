JOSE ALBERTO RODRIGUEZ PANAMEÑO 
----
# Inventario AI - Backend API 📦⚓

Este repositorio contiene la API RESTful para el sistema de gestión logística, control de patios e inventario de contenedores. Está desarrollado en PHP nativo utilizando arquitectura limpia, consultas preparadas con PDO y conectividad optimizada para bases de datos MySQL en entornos de servidores privados (VPS).

## 🚀 Características del Proyecto
* **Endpoints REST nativos:** Controladores optimizados para operaciones de inventario físico.
* **Control de Acceso (CORS):** Configuración de cabeceras seguras adaptadas para peticiones *preflight* (OPTIONS) desde Angular.
* **Persistencia de Datos:** Arquitectura de base de datos MySQL estructurada para el seguimiento de contenedores en patios.
* **Despliegue Automatizado:** Integración continua mediante GitHub Actions y SCP/SSH nativo hacia el VPS.

---

## 🛠️ Requisitos del Sistema
Para correr o desplegar esta API localmente o en producción, necesitas:
* **PHP:** Versión 8.2 o superior (con extensiones `pdo` y `pdo_mysql` habilitadas).
* **Base de Datos:** MySQL / MariaDB.
* **Servidor Web:** Nginx, Apache o el servidor embebido de PHP.

---

## 💻 Configuración Local (Entorno de Desarrollo)

1. **Clonar el repositorio:**
   ```bash
   git clone [https://github.com/Ironman2201-max/inventario-backend.git](https://github.com/Ironman2201-max/inventario-backend.git)
   cd inventario-backend