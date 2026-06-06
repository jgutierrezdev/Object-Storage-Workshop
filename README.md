# Object Storage Workshop

Guía práctica para integrar los principales proveedores de almacenamiento en la nube en distintos lenguajes y frameworks.

---

## ¿Por dónde empiezo?

Elige tu lenguaje y el proveedor que necesitas:

| Lenguaje / Framework | AWS S3 | OCI Object Storage | Azure Blob Storage |
|---|---|---|---|
| Django | [Ver guía](django-app-example/README-AWS.md) | [Ver guía](django-app-example/README-OCI.md) | [Ver guía](django-app-example/README-AZURE.md) |
| Node.js | [Ver guía](nodejs-app-example/README-AWS.md) | [Ver guía](nodejs-app-example/README-OCI.md) | [Ver guía](nodejs-app-example/README-AZURE.md) |
| PHP | [Ver guía](php-app-example/README-AWS.md) | [Ver guía](php-app-example/README-OCI.md) | [Ver guía](php-app-example/README-AZURE.md) |

---

## Estructura del repositorio

```
object-storage-workshop
├── django-app-example/
├── nodejs-app-example/
└── php-app-example/
```

Cada carpeta es un proyecto independiente con su propio setup y dependencias.

---

## Requisitos previos

Antes de comenzar con cualquier guía necesitarás:

- Una cuenta activa en el proveedor de tu elección (AWS / OCI / Azure)
- El lenguaje/runtime correspondiente instalado en tu máquina

---

> **¿Usas AWS Academy o un entorno de laboratorio?** Cada guía incluye una sección específica para credenciales temporales.
