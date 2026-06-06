# Integración de Amazon S3 en Django — Guía Paso a Paso

Esta guía te llevará desde un proyecto Django base que muestra un catálogo de productos con imágenes estáticas de **Picsum**, hasta una aplicación completamente funcional que **almacena, sirve y elimina imágenes en Amazon S3**, utilizando formularios web y buenas prácticas de seguridad.

El proyecto base ya está creado. Solo debes seguir las fases en orden para transformarlo.

---

## Fase 1: Proyecto Base — Catálogo con Imágenes Estáticas

Antes de integrar S3, revisa cómo funciona el proyecto en su estado inicial. Esta fase no requiere modificar nada; solo entender la arquitectura actual.

### 1.1. Vista actual (`gallery/views.py`)

El archivo `views.py` contiene una lista fija (hardcoded) de 6 productos con imágenes obtenidas del servicio gratuito **Picsum**:

```python
from django.shortcuts import render

def index(request):
    producto_list = [
        {'nombre': 'Zapatillas Urbanas', 'precio': 45000, 'imagen': 'https://picsum.photos/id/1018/800/600'},
        {'nombre': 'Mochila Ejecutiva',   'precio': 32000, 'imagen': 'https://picsum.photos/id/1015/800/600'},
        {'nombre': 'Auriculares Pro',     'precio': 28000, 'imagen': 'https://picsum.photos/id/1019/800/600'},
        {'nombre': 'Reloj Deportivo',     'precio': 55000, 'imagen': 'https://picsum.photos/id/1016/800/600'},
        {'nombre': 'Cámara Digital',      'precio': 89000, 'imagen': 'https://picsum.photos/id/1020/800/600'},
        {'nombre': 'Lámpara LED',         'precio': 15000, 'imagen': 'https://picsum.photos/id/1021/800/600'},
    ]
    return render(request, 'gallery/index.html', {'productos': producto_list})
```

Cada producto es un diccionario con tres claves: `nombre`, `precio` e `imagen` (URL externa).

### 1.2. Template actual (`gallery/templates/gallery/index.html`)

El HTML itera sobre `productos` y genera un grid responsivo con tarjetas que muestran la imagen, el nombre y el precio:

```html
<div class="gallery-container">
    {% for producto in productos %}
        <div class="gallery-item">
            <img src="{{ producto.imagen }}" alt="{{ producto.nombre }}" loading="lazy">
            <div class="item-info">
                <h3>{{ producto.nombre }}</h3>
                <p>${{ producto.precio }}</p>
            </div>
        </div>
    {% endfor %}
</div>
```

> Nota que `producto.imagen` es simplemente una cadena de texto con una URL. En las fases posteriores esto cambiará por un objeto `ImageField` conectado a S3.

### 1.3. Probar el proyecto base

Ejecuta el servidor para ver el estado inicial:

```bash
python manage.py runserver
```

Abre `http://127.0.0.1:8000/`. Verás 6 tarjetas con imágenes aleatorias de Picsum. Este es el punto de partida.

---

## Fase 2: Creación y Configuración del Bucket S3

Ahora crearemos el bucket en AWS que almacenará las imágenes de los productos.

1. Ingresa a la consola de AWS y dirígete al servicio **S3**.

2. Haz clic en **Crear bucket** y aplica la siguiente configuración:

   - **Región de AWS:** `us-east-1` (Norte de Virginia).
   - **Tipo de bucket:** Uso general *(General purpose)*.
   - **Espacio de nombres del bucket:** Regional de la cuenta *(Account regional)*.
     > El nombre se generará automáticamente con un sufijo único. Cópialo para usarlo en el `.env`.
   - **Propiedad de objetos:** ACL deshabilitadas *(recomendado)*.
   - **Configuración de bloqueo de acceso público:** Activar *(Bloquear todo el acceso público)*.
     > El bucket será privado. Django generará URLs prefirmadas temporales para acceder a cada imagen.
   - **Cifrado predeterminado:** SSE-S3.
   - **Clave de bucket:** Habilitar.

3. Haz clic en **Crear bucket**.

4. Entra al bucket y crea una carpeta llamada **`media`**. Allí se almacenarán las imágenes subidas desde la web.

---

## Fase 3: Configuración de Credenciales (IAM)

### 3.1. Para cuentas propias de AWS

Crea un usuario IAM con permisos mínimos para S3:

1. En IAM > **Usuarios** > **Crear usuario** (ej: `s3-django-user`).
2. En permisos, selecciona **Adjuntar políticas directamente** > **Crear política**.
3. En la pestaña **JSON**, pega la siguiente política (reemplaza `mi-bucket` por el nombre real de tu bucket):

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "s3:PutObject",
                "s3:GetObject",
                "s3:DeleteObject",
                "s3:ListBucket"
            ],
            "Resource": [
                "arn:aws:s3:::mi-bucket",
                "arn:aws:s3:::mi-bucket/*"
            ]
        }
    ]
}
```

4. Nombra la política (ej: `S3-Django-Policy`) y créala.
5. Asigna la política al usuario y finaliza.
6. Genera y guarda las credenciales: `AWS_ACCESS_KEY_ID` y `AWS_SECRET_ACCESS_KEY`.

### 3.2. Para AWS Academy / Learner Labs

En AWS Academy no puedes crear usuarios IAM. Usa las **credenciales temporales de la sesión**:

1. En el laboratorio, haz clic en **AWS Details** > **Show** (junto a "AWS CLI").
2. Copia las tres variables: `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` y `AWS_SESSION_TOKEN`.

> El `AWS_SESSION_TOKEN` es obligatorio en Academy. Sin él, AWS rechazará la conexión. El laboratorio expira (~3 horas), por lo que deberás renovar las credenciales periódicamente.

---

## Fase 4: Configuración de Django para S3

Ahora conectaremos Django con el bucket S3 mediante `django-storages` y `boto3`.

### 4.1. Instalar dependencias

```bash
pip install django-storages boto3 python-dotenv
```

### 4.2. Crear archivo `.env`

En la raíz del proyecto (`django-app-example/`), crea un archivo `.env` con las credenciales obtenidas en la Fase 3:

```ini
AWS_ACCESS_KEY_ID=AKIA...
AWS_SECRET_ACCESS_KEY=8UdXkbsDT/nbi...
AWS_SESSION_TOKEN=IQoJb3...   # Solo para AWS Academy
AWS_STORAGE_BUCKET_NAME=mi-bucket-123456789012-us-east-1
AWS_S3_REGION_NAME=us-east-1
```

### 4.3. Actualizar `core/settings.py`

Reemplaza el contenido de `settings.py` con la configuración completa que incluye las credenciales y el mapeo de almacenamiento:

En la base del proyecto importaremos os, dotenv e inicializaremos la carga de variables de entorno.
```python
import os
from dotenv import load_dotenv

load_dotenv()

```

Luego de precargar las variables, vamos a declarar las credenciales en settings.py

```python

# --- Configuración de Amazon S3 ---

# 1. Credenciales desde variables de entorno
AWS_ACCESS_KEY_ID        = os.getenv('AWS_ACCESS_KEY_ID')
AWS_SESSION_TOKEN        = os.getenv('AWS_SESSION_TOKEN')   # Solo AWS Academy
AWS_SECRET_ACCESS_KEY    = os.getenv('AWS_SECRET_ACCESS_KEY')
AWS_STORAGE_BUCKET_NAME  = os.getenv('AWS_STORAGE_BUCKET_NAME')
AWS_S3_REGION_NAME       = os.getenv('AWS_S3_REGION_NAME')

# 2. Firmas y seguridad
AWS_S3_SIGNATURE_VERSION = 's3v4'          # Algoritmo de firma moderno
AWS_DEFAULT_ACL = None                     # Deshabilita ACL (usamos políticas)
AWS_S3_OBJECT_PARAMETERS = {
    'CacheControl': 'max-age=86400',       # Caché de 24 h en navegador
}

# 3. Mapeo de almacenamiento (Django 4.2+)
STORAGES = {
    "default": {
        "BACKEND": "storages.backends.s3.S3Storage",
        "OPTIONS": {
            "location": "media",            # Carpeta raíz en el bucket
            "querystring_auth": True,       # URLs prefirmadas (bucket privado)
            "region_name": AWS_S3_REGION_NAME,
        },
    },
    "staticfiles": {
        "BACKEND": "django.contrib.staticfiles.storage.StaticFilesStorage",
    },
}

# --- Fin configuración S3 ---

```

> **¿Qué hace `STORAGES`?**  
> El diccionario `STORAGES` le dice a Django qué backend usar para cada tipo de archivo.  
> - `"default"` → **Archivos multimedia** (los que suben los usuarios): se redirigen a S3.  
> - `"staticfiles"` → **Archivos estáticos** (CSS, JS del admin): se quedan en el servidor local.  
> 
> `"location": "media"` indica que todos los archivos se almacenarán dentro de la carpeta `media/` del bucket.  
> `"querystring_auth": True` genera URLs prefirmadas con tiempo de expiración, manteniendo el bucket privado.

---

## Fase 5: Reemplazar Imágenes Estáticas por S3

Ahora modificaremos la vista para que, en lugar de usar URLs de Picsum, **lea las imágenes directamente desde el bucket S3**.

### 5.1. Subir imágenes al bucket manualmente

Antes de probar, necesitas tener imágenes en S3:

1. Ve a la consola de AWS S3 → tu bucket → carpeta `media/`.
2. Sube manualmente 3 a 6 imágenes con nombres simples (ej: `producto1.jpg`, `producto2.jpg`, etc.).
3. Asegúrate de que tengan extensiones válidas: `.jpg`, `.jpeg`, `.png`, `.webp`, `.gif` o `.svg`.

### 5.2. Actualizar `gallery/views.py`

Reemplaza el contenido actual por el siguiente código que lista los archivos de S3 y genera URLs prefirmadas:

```python
from django.shortcuts import render
from django.core.files.storage import default_storage

def index(request):
    images = []

    try:
        # 1. Listar objetos en la carpeta "media" del bucket
        directories, files = default_storage.listdir('')

        # 2. Filtrar solo archivos de imagen
        valid_extensions = ('.jpg', '.jpeg', '.png', '.webp', '.gif', '.svg')
        image_files = [f for f in files if f.lower().endswith(valid_extensions)]

        # 3. Generar URLs prefirmadas para cada imagen
        images = [default_storage.url(f) for f in image_files]

    except Exception as e:
        print(f"Error al conectar con S3: {e}")

    return render(request, 'gallery/index.html', {'productos': images})
```

> **¿Qué cambió respecto a la Fase 1?**  
> - Antes: `productos` era una lista de diccionarios con `nombre`, `precio` e `imagen`.  
> - Ahora: `productos` es una lista plana de **URLs prefirmadas** generadas por S3.  
> 
> El template `index.html` itera sobre `productos` y muestra cada imagen. Como ahora solo tenemos URLs (sin nombre ni precio), el template mostrará solo las imágenes.

### 5.3. Actualizar `gallery/templates/gallery/index.html`

Modifica el template para que funcione con la lista plana de URLs:

```html
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo desde S3</title>
    <style>
        body { font-family: sans-serif; background-color: #f4f4f9; margin: 0; padding: 20px; }
        h1 { text-align: center; color: #333; }
        .gallery-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .gallery-item {
            background: white;
            overflow: hidden;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .gallery-item:hover { transform: scale(1.02); }
        .gallery-item img {
            width: 100%;
            height: 250px;
            object-fit: cover;
            display: block;
        }
    </style>
</head>
<body>
    <h1>Catálogo desde Amazon S3</h1>
    <div class="gallery-container">
        {% for imagen in productos %}
            <div class="gallery-item">
                <img src="{{ imagen }}" alt="Imagen desde S3" loading="lazy">
            </div>
        {% empty %}
            <p style="grid-column: 1 / -1; text-align: center; color: #666;">
                No hay imágenes en el bucket. Súbelas manualmente a la carpeta <strong>media/</strong>.
            </p>
        {% endfor %}
    </div>
</body>
</html>
```

### 5.4. Probar

```bash
python manage.py runserver
```

Abre `http://127.0.0.1:8000/`. Ahora deberías ver las imágenes que subiste manualmente a S3, servidas a través de URLs prefirmadas temporales.

---

## Fase 6: Subir y Eliminar Imágenes desde la Web (CRUD Completo)

Hasta ahora las imágenes se suben manualmente desde la consola de AWS. En esta fase final añadiremos un **formulario web** para que cualquier usuario pueda subir imágenes directamente al bucket, y un **botón para eliminarlas** cuando ya no sean necesarias.

Para ello necesitamos un **modelo** en la base de datos que asocie cada imagen con metadatos (nombre, precio), y un **ImageField** que, gracias a django-storages, subirá automáticamente el archivo a S3 al guardar el formulario.

### 6.1. Crear el modelo Producto

**`gallery/models.py`:**

```python
from django.db import models

class Producto(models.Model):
    nombre = models.CharField(max_length=100)
    precio = models.DecimalField(max_digits=10, decimal_places=2)
    # ImageField: el archivo se almacena en S3 automáticamente
    # El upload_to está vacío porque "location": "media" ya antepone la carpeta
    # (definido en settings.py en la sección de STORAGES)
    imagen = models.ImageField(upload_to='')

    def __str__(self):
        return self.nombre
```

> **¿Por qué `upload_to=''`?**  
> En `settings.py` definimos `"location": "media"` en el backend S3. Esto hace que **todos los archivos del modelo se guarden dentro de `media/`** en el bucket. Si pusieramos `upload_to='media/'`, el resultado final sería `media/media/mi-imagen.jpg`. Al dejarlo vacío, se almacena como `media/mi-imagen.jpg`.

### 6.2. Crear el formulario

**`gallery/forms.py`:**

```python
from django import forms
from .models import Producto

class ProductoForm(forms.ModelForm):
    class Meta:
        model = Producto
        fields = ['nombre', 'precio', 'imagen']
        widgets = {
            'nombre': forms.TextInput(attrs={'class': 'form-control', 'placeholder': 'Ej. Zapatillas'}),
            'precio': forms.NumberInput(attrs={'class': 'form-control', 'placeholder': 'Ej. 25000'}),
            'imagen': forms.ClearableFileInput(attrs={'class': 'form-control'}),
        }
```

### 6.3. Actualizar las vistas

**`gallery/views.py`:** (reemplaza todo el contenido)

```python
from django.shortcuts import render, redirect, get_object_or_404
from .models import Producto
from .forms import ProductoForm

def index(request):
    if request.method == 'POST':
        form = ProductoForm(request.POST, request.FILES)
        if form.is_valid():
            # Guarda el producto: el ImageField sube la imagen a S3 automáticamente
            form.save()
            return redirect('index')
    else:
        form = ProductoForm()

    productos = Producto.objects.all()
    return render(request, 'gallery/index.html', {
        'form': form,
        'productos': productos,
    })

def eliminar_producto(request, producto_id):
    producto = get_object_or_404(Producto, id=producto_id)
    # Elimina el archivo del bucket S3
    producto.imagen.delete(save=False)   # Llama a DeleteObject de AWS
    producto.delete()                    # Elimina el registro de la BD
    return redirect('index')
```

> **`producto.imagen.delete(save=False)`**  
> El método `.delete()` del `ImageField` ejecuta la operación `DeleteObject` contra AWS S3, borrando el archivo físico del bucket. El parámetro `save=False` evita que Django intente guardar el modelo (con la imagen ya eliminada) antes de borrarlo.

### 6.4. Actualizar las URLs

**`gallery/urls.py`:**

```python
from django.urls import path
from . import views

urlpatterns = [
    path('', views.index, name='index'),
    path('eliminar/<int:producto_id>/', views.eliminar_producto, name='eliminar_producto'),
]
```

### 6.5. Actualizar el template (con formulario y botón eliminar)

**`gallery/templates/gallery/index.html`:**

```html
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos (AWS S3)</title>
    <style>
        body { font-family: sans-serif; background-color: #f4f4f9; margin: 0; padding: 20px; }
        h1, h2 { text-align: center; color: #333; }

        /* Formulario */
        .form-container {
            max-width: 500px;
            margin: 0 auto 40px auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-control { width: 100%; padding: 8px; box-sizing: border-box; }
        .btn-submit {
            width: 100%; padding: 10px; background-color: #28a745; color: white;
            border: none; border-radius: 4px; cursor: pointer; font-size: 16px;
        }
        .btn-submit:hover { background-color: #218838; }

        /* Grid */
        .gallery-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .gallery-item {
            background: white;
            overflow: hidden;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            display: flex;
            flex-direction: column;
        }
        .gallery-item:hover { transform: scale(1.02); }
        .gallery-item img {
            width: 100%;
            height: 250px;
            object-fit: cover;
            display: block;
        }
        .item-info { padding: 15px; text-align: center; flex-grow: 1; }
        .item-info h3 { margin: 0 0 10px 0; color: #333; }
        .item-info p { margin: 0 0 15px 0; font-size: 18px; color: #666; font-weight: bold; }

        /* Botón Eliminar */
        .btn-delete {
            background-color: #dc3545; color: white; border: none;
            padding: 8px 15px; border-radius: 4px; cursor: pointer; width: 100%;
        }
        .btn-delete:hover { background-color: #c82333; }
    </style>
</head>
<body>

    <h1>Gestión de Productos (AWS S3)</h1>

    <div class="form-container">
        <h2>Subir Nuevo Producto</h2>
        <form method="POST" enctype="multipart/form-data">
            {% csrf_token %}
            <div class="form-group">
                <label for="{{ form.nombre.id_for_label }}">Nombre del Producto</label>
                {{ form.nombre }}
            </div>
            <div class="form-group">
                <label for="{{ form.precio.id_for_label }}">Precio</label>
                {{ form.precio }}
            </div>
            <div class="form-group">
                <label for="{{ form.imagen.id_for_label }}">Imagen</label>
                {{ form.imagen }}
            </div>
            <button type="submit" class="btn-submit">Guardar en S3</button>
        </form>
    </div>

    <hr style="border: 1px solid #ddd; max-width: 1200px; margin-bottom: 40px;">

    <div class="gallery-container">
        {% for producto in productos %}
            <div class="gallery-item">
                <img src="{{ producto.imagen.url }}" alt="{{ producto.nombre }}" loading="lazy">
                <div class="item-info">
                    <h3>{{ producto.nombre }}</h3>
                    <p>${{ producto.precio }}</p>
                    <form method="POST" action="{% url 'eliminar_producto' producto.id %}"
                          onsubmit="return confirm('¿Eliminar este producto y su imagen de S3?');">
                        {% csrf_token %}
                        <button type="submit" class="btn-delete">Eliminar</button>
                    </form>
                </div>
            </div>
        {% empty %}
            <p style="grid-column: 1 / -1; text-align: center; color: #666;">
                No hay productos. Usa el formulario para agregar uno.
            </p>
        {% endfor %}
    </div>

</body>
</html>
```

> **`enctype="multipart/form-data"`** es obligatorio cuando el formulario incluye un campo de tipo archivo. Sin este atributo, el navegador no enviaría el contenido binario de la imagen.

### 6.6. Ejecutar migraciones

Crea la tabla `Producto` en la base de datos:

```bash
python manage.py makemigrations
python manage.py migrate
```

### 6.7. Probar el flujo completo

```bash
python manage.py runserver
```

1. Abre `http://127.0.0.1:8000/`.
2. Completa el formulario con nombre, precio y selecciona una imagen desde tu computador.
3. Haz clic en **Guardar en S3**.
   - La imagen se sube automáticamente a tu bucket S3 (carpeta `media/`).
   - Los datos se guardan en SQLite.
4. La imagen aparece en el grid con su nombre y precio.
5. Haz clic en **Eliminar** en cualquier producto.
   - El archivo se borra de S3 (operación `DeleteObject`).
   - El registro se elimina de la base de datos.
6. Puedes verificar en la consola de AWS S3 que los archivos se crean y eliminan correctamente.

---

## Resumen del Flujo Completo (Fases 1 a 6)

```
Fase 1:  Productos estáticos con Picsum
         ├── views.py → lista hardcoded de URLs
         └── index.html → grid simple con <img src="...">

Fase 2:  Bucket S3 privado con carpeta media/

Fase 3:  Credenciales IAM (usuario propio o AWS Academy)

Fase 4:  django-storages + boto3 en settings.py
         └── STORAGES["default"] → S3Storage(location="media")

Fase 5:  Reemplazo de Picsum por S3
         ├── views.py → default_storage.listdir() + default_storage.url()
         └── index.html → muestra URLs prefirmadas desde S3

Fase 6:  CRUD completo con formulario web
         ├── models.py → Producto (ImageField → S3)
         ├── forms.py → ProductoForm (ModelForm)
         ├── views.py → index (GET+POST) + eliminar_producto
         ├── urls.py → ruta eliminar/<id>
         └── index.html → formulario + grid + botón eliminar
```

**Diagrama de flujo de datos (Fase 6):**

```
Usuario (Navegador)
    │
    ├── [GET /] ──────────────────── Django lee productos de BD
    │                                       │
    │                                       └── S3Storage genera URL prefirmada
    │                                           para cada imagen
    │                                       │
    │                                       └── Renderiza HTML con imágenes
    │
    ├── [POST /] (formulario con archivo) ─ Django recibe imagen
    │                                       │
    │                                       ├── PutObject a S3 (sube al bucket)
    │                                       └── Guarda registro en SQLite
    │
    └── [POST /eliminar/X] ──────────────── Django busca producto
                                            │
                                            ├── DeleteObject en S3 (borra imagen)
                                            └── DELETE registro en SQLite
```

Con esto tienes una aplicación Django completamente integrada con Amazon S3, que parte de un catálogo estático con imágenes de Picsum y evoluciona hasta un **sistema completo de gestión de productos con almacenamiento en la nube**, todo sin exponer tu bucket al público y utilizando URLs prefirmadas para cada imagen.