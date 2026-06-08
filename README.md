# Object Storage Masterclass

Aprende a integrar **almacenamiento en la nube** en aplicaciones web reales usando **Django**, **Node.js (Express)** y **PHP**, con soporte para **AWS S3**, **OCI Object Storage** y **Azure Blob Storage**.

```text
De imágenes estáticas de Picsum → Almacenamiento en la nube con CRUD completo
```

---

## ¿De qué trata este workshop?

Cada proyecto comienza como un catálogo de productos con imágenes de prueba (Picsum) y lo transformas paso a paso en una aplicación que:

- **Almacena imágenes en la nube** (sube archivos reales al bucket)
- **Sirve imágenes con URLs prefirmadas** (bucket privado, sin exponer credenciales)
- **Elimina imágenes** desde la interfaz web (CRUD completo)
- **Usa buenas prácticas** (variables de entorno, permisos mínimos, caché)

---

## Elige tu ruta de aprendizaje

| Lenguaje | AWS S3 | OCI Object Storage | Azure Blob Storage |
|:--------:|:------:|:------------------:|:------------------:|
| **Django** | [Guía](django-app-example/README-AWS.md) | [Guía](django-app-example/README-OCI.md) | [Guía](django-app-example/README-AZURE.md) |
| **Node.js** | [Guía](nodejs-app-example/README-AWS.md) | [Guía](nodejs-app-example/README-OCI.md) | [Guía](nodejs-app-example/README-AZURE.md) |
| **PHP** | [Guía](php-app-example/README-AWS.md) | [Guía](php-app-example/README-OCI.md) | [Guía](php-app-example/README-AZURE.md) |

> **¿No sabes por cuál empezar?** Si vienes del mundo Python, empieza por Django. Si prefieres JavaScript, ve por Node.js. ¡Todos llegan al mismo resultado!

---

## Estructura del repositorio

```
object-storage-masterclass/
├── django-app-example/      # Python + Django + django-storages
│   ├── gallery/             #   App con vistas, modelos y templates
│   ├── core/                #   Configuración del proyecto
│   ├── README-AWS.md        #   Guía para Amazon S3
│   ├── README-OCI.md        #   Guía para Oracle Cloud
│   └── README-AZURE.md      #   Guía para Microsoft Azure
│
├── nodejs-app-example/      # Node.js + Express + @aws-sdk/client-s3
│   ├── views/               #   Templates EJS
│   ├── app.js               #   Servidor principal
│   ├── README-AWS.md
│   ├── README-OCI.md
│   └── README-AZURE.md
│
└── php-app-example/         # PHP + SDK aws/aws-sdk-php
    ├── index.php            #   Controlador y vista en un solo archivo
    ├── README-AWS.md
    ├── README-OCI.md
    └── README-AZURE.md
```

Cada carpeta es **independiente**: puedes clonar solo la que te interese o hacerlas todas en orden.

---

## Requisitos previos

| Herramienta | ¿Para qué? |
|-------------|------------|
| Una cuenta en **AWS**, **OCI** o **Azure** | Para crear el bucket y obtener credenciales |
| **Python 3.10+** | Solo para el proyecto Django |
| **Node.js 18+** | Solo para el proyecto Node.js |
| **PHP 8.0+** + Composer | Solo para el proyecto PHP |
| **Editor de código** (VS Code recomendado) | Para seguir las guías cómodamente |

---

## Desafíos (para ir más allá)

¿Terminaste las 6 fases de una guía? ¡Felicidades! Ahora lleva tu aprendizaje al siguiente nivel con estos desafíos:

### Nivel 1 — Fácil

- **CSS personalizado**: Dale tu propio estilo a la galería de productos (colores, animaciones, tipografía).
- **Más campos**: Agrega campos como `descripción`, `categoría` o `stock` al modelo de producto.
- **Búsqueda**: Implementa un campo de búsqueda que filtre productos por nombre.

### Nivel 2 — Intermedio

- **Base de datos real**: Reemplaza SQLite por **PostgreSQL** (o MySQL). Migra los datos existentes.
- **Paginación**: Si hay más de 10 productos, muestra la galería paginada.
- **Múltiples imágenes**: Permite subir **varias imágenes** por producto (galería interna).
- **Miniaturas (thumbnails)**: Al subir una imagen, genera automáticamente una versión redimensionada (por ejemplo 150x150) y almacénala junto a la original.

### Nivel 3 — Avanzado

- **Despliegue**: Sube la aplicación a un servicio en la nube ([Render](https://render.com), [Railway](https://railway.app), [Fly.io](https://fly.io), AWS, OCI o Azure!). La aplicación debe funcionar con variables de entorno en producción.
- **Autenticación**: Protege la subida y eliminación de imágenes con login (solo usuarios autenticados pueden modificar productos).
- **CDN**: Configura un CDN (CloudFront, Cloudflare) frente a tu bucket para servir imágenes con baja latencia global.
- **Caché inteligente**: Implementa un sistema de caché (Redis o similar) para las URLs prefirmadas y evitar regenerarlas en cada petición.

### Nivel 4 — Experto

- **Multi-provider**: Crea una capa de abstracción que permita cambiar entre AWS S3, OCI y Azure sin modificar el código de la aplicación (usando el patrón Strategy o Adapter).
- **CI/CD**: Automatiza el despliegue con GitHub Actions. Cada `push` a `main` debe ejecutar tests y desplegar automáticamente.
- **Pruebas automatizadas**: Escribe tests unitarios y de integración que verifiquen que las imágenes se suben, listan y eliminan correctamente (usa motores S3 falsos como [MinIO](https://min.io/) o [motos](https://github.com/getmoto/moto) para no depender de un bucket real).
- **Modo offline**: Haz que la aplicación funcione en modo degradado (usando el sistema de archivos local) cuando no haya conexión a la nube.

> **Comparte tu solución**: Si completas algún desafío, siéntete libre de compartirlo. ¡Es la mejor forma de aprender!

---

## Checklist de progreso personal

Usa este checklist para seguir tu avance a través del workshop:

```markdown
- [ ] Completé la Fase 1 (entender el proyecto base)
- [ ] Completé la Fase 2 (crear el bucket en la nube)
- [ ] Completé la Fase 3 (generar credenciales)
- [ ] Completé la Fase 4 (configurar el SDK/cliente)
- [ ] Completé la Fase 5 (reemplazar imágenes estáticas por la nube)
- [ ] Completé la Fase 6 (CRUD completo con formulario web)
- [ ] Completé al menos 1 desafío del Nivel 1
- [ ] Completé al menos 1 desafío del Nivel 2
- [ ] Completé al menos 1 desafío del Nivel 3
```

---

## Referencias útiles

- [django-storages — Documentación oficial](https://django-storages.readthedocs.io/)
- [AWS SDK for JavaScript v3 — S3 Client](https://docs.aws.amazon.com/AWSJavaScriptSDK/v3/latest/client/s3/)
- [AWS SDK for PHP — S3 Client](https://docs.aws.amazon.com/aws-sdk-php/v3/api/class-Aws.S3.S3Client.html)
- [OCI Object Storage — API compatible con S3](https://docs.oracle.com/en-us/iaas/Content/Object/Tasks/s3compatibleapi.htm)
- [Azure Blob Storage — SDK para Python](https://learn.microsoft.com/en-us/azure/storage/blobs/storage-quickstart-blobs-python)

---

> **Nota para entornos académicos:** Si usas AWS Academy o un laboratorio con credenciales temporales, cada guía incluye una sección específica para configurar el `AWS_SESSION_TOKEN`. Sin este token, las credenciales temporales serán rechazadas.