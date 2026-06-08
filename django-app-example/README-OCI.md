# Integración de Oracle Cloud Infrastructure (OCI) Object Storage en Django — Guía Paso a Paso

Esta guía te llevará desde un proyecto Django base que muestra un catálogo de productos con imágenes estáticas de **Picsum**, hasta una aplicación completamente funcional que **almacena, sirve y elimina imágenes en OCI Object Storage**, utilizando formularios web y buenas prácticas de seguridad.

El proyecto base ya está creado. Solo debes seguir las fases en orden para transformarlo.

> **Nota sobre compatibilidad:** OCI Object Storage es compatible con la API de Amazon S3. Por lo tanto, usaremos el mismo backend `S3Storage` de `django-storages`, pero apuntando al endpoint de OCI. La configuración es casi idéntica a la de AWS, salvo por el endpoint URL y la forma de obtener las credenciales.

---

## Fase 1: Proyecto Base — Catálogo con Imágenes Estáticas

Antes de integrar OCI, revisa cómo funciona el proyecto en su estado inicial. Esta fase no requiere modificar nada; solo entender la arquitectura actual.

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

> Nota que `producto.imagen` es simplemente una cadena de texto con una URL. En las fases posteriores esto cambiará por un objeto `ImageField` conectado a OCI.

### 1.3. Probar el proyecto base

Ejecuta el servidor para ver el estado inicial:

```bash
python manage.py runserver
```

Abre `http://127.0.0.1:8000/`. Verás 6 tarjetas con imágenes aleatorias de Picsum. Este es el punto de partida.

---

## Fase 2: Creación y Configuración del Bucket en OCI

Ahora crearemos el bucket en Oracle Cloud Infrastructure que almacenará las imágenes de los productos.

1. Ingresa a la consola de Oracle Cloud y abre el **menú hamburguesa** (esquina superior izquierda).

2. Navega a **Storage** → **Buckets**.

3. Selecciona el **Compartimiento** (Compartment) donde deseas crear el bucket.

4. Haz clic en **Crear bucket** y aplica la siguiente configuración:

   - **Nombre del bucket:** Asigna un nombre único (ej: `django-media-bucket`).
   - **Ámbito del bucket (Bucket Scope):**
     - **Namespace (por defecto):** El nombre del bucket debe ser único dentro de tu namespace (tu cuenta).
     - **Regional:** El nombre debe ser único en toda la región.
   - **Nivel de almacenamiento (Default Storage Tier):**
     - **Standard:** Acceso casi instantáneo, ideal para una aplicación web. Es más costoso.
     - **Archive:** Exclusivo para respaldos a largo plazo. La descarga puede demorar pero es más barato.
       > Para este proyecto, selecciona **Standard**.
   - **Auto-Tiering:** Déjalo desactivado.
   - **Versioning de objetos:** Desactivado (opcional).
   - **Emit Object Events:** Desactivado.
   - **Uncommitted Multipart Uploads Cleanup:** Desactivado.

5. Haz clic en **Crear bucket**.

6. Una vez creado, entra al bucket y anota los siguientes datos (los necesitarás más adelante):

   - **Namespace del Object Storage:** Lo encuentras en la página de detalles del bucket, bajo la sección "Bucket Information". Se ve como un identificador corto (ej: `ax1b2c3d4e5f`).
   - **Región:** La región donde creaste el bucket (ej: `us-ashburn-1`, `sa-santiago-1`, etc.).

7. Crea una carpeta llamada **`media`** dentro del bucket. Allí se almacenarán las imágenes subidas desde la web.

---

## Fase 3: Configuración de Credenciales (Customer Secret Keys)

OCI no usa usuarios IAM como AWS. En su lugar, utilizaremos **Customer Secret Keys**, que son credenciales compatibles con la API S3.

### 3.1. Generar las credenciales

1. En la consola de OCI, haz clic en tu **perfil** (esquina superior derecha) → **User Settings** (o **Mi perfil**).

2. En el menú lateral izquierdo, ve a **Customer Secret Keys** (bajo la sección "Resources").

3. Haz clic en **Generate Secret Key**.

4. Asigna un nombre descriptivo, por ejemplo: `acceso-django-app`.

5. Haz clic en **Generate Secret Key**.

6. **¡IMPORTANTE!** Se mostrará el **Secret Key** una sola vez. Cópialo y guárdalo de inmediato en un lugar seguro (como tu archivo `.env`).

7. En la lista de "Customer Secret Keys" verás la clave que acabas de crear. Haz clic en los **3 puntitos** a la derecha y selecciona **Copy Access Key**. Ahora tienes ambos valores:
   - **Access Key:** Un identificador público (similar a `AWS_ACCESS_KEY_ID`).
   - **Secret Key:** La clave secreta (similar a `AWS_SECRET_ACCESS_KEY`).

### 3.2. Construir el Endpoint URL de OCI

OCI Object Storage expone un endpoint compatible con S3 con el siguiente formato:

```
https://{namespace}.compat.objectstorage.{region}.oraclecloud.com
```

Reemplaza los valores:
- `{namespace}` → El namespace de Object Storage que anotaste en la Fase 2.
- `{region}` → La región donde creaste el bucket (ej: `us-ashburn-1`).

Ejemplo:
```
https://ax1b2c3d4e5f.compat.objectstorage.us-ashburn-1.oraclecloud.com
```

Este endpoint se usará en el `.env` como `OCI_ENDPOINT_URL`.

> **¿Cómo encuentro mi namespace?**  
> Entra a tu bucket en la consola de OCI. En la página de detalles, busca el campo **"Namespace"** bajo "Bucket Information". No lo confundas con el nombre del bucket.

---

## Fase 4: Configuración de Django para OCI

Ahora conectaremos Django con el bucket de OCI mediante `django-storages` y `boto3`, usando el endpoint compatible con S3.

### 4.1. Instalar dependencias

```bash
pip install django-storages boto3 python-dotenv
```

### 4.2. Crear archivo `.env`

En la raíz del proyecto (`django-app-example/`), crea un archivo `.env` con las credenciales obtenidas en la Fase 3:

```ini
AWS_ACCESS_KEY_ID=8a7b6c5d4e3f2g1h...       # Access Key desde Customer Secret Keys
AWS_SECRET_ACCESS_KEY=7H8j9K0l1M2n3O4p...   # Secret Key desde Customer Secret Keys
AWS_STORAGE_BUCKET_NAME=django-media-bucket # Nombre de tu bucket
AWS_S3_REGION_NAME=sa-santiago-1
OCI_ENDPOINT_URL=https://ax1b2c3d4e5f.compat.objectstorage.sa-santiago-1.oraclecloud.com
```

> **Nota:** Aunque estemos en OCI, las variables se llaman `AWS_*` porque usamos el backend `S3Storage` que espera esos nombres. La "magia" que desvía el tráfico a OCI es el `OCI_ENDPOINT_URL` que se asigna a `AWS_S3_ENDPOINT_URL` en `settings.py`.

### 4.3. Actualizar `core/settings.py`

Reemplaza el contenido de `settings.py` con la configuración completa que incluye las credenciales y el mapeo de almacenamiento:

En la base del proyecto importaremos `os`, `dotenv` e inicializaremos la carga de variables de entorno.

```python
import os
from dotenv import load_dotenv
from botocore.config import Config

load_dotenv()
```

Luego de precargar las variables, vamos a declarar las credenciales en `settings.py`:

```python

# --- Configuración de OCI Object Storage (compatible con S3) ---

# 1. Credenciales desde variables de entorno
AWS_ACCESS_KEY_ID        = os.getenv('AWS_ACCESS_KEY_ID')
# AWS_SESSION_TOKEN        = os.getenv('AWS_SESSION_TOKEN')   # Solo AWS Academy, NO aplica en OCI
AWS_SECRET_ACCESS_KEY    = os.getenv('AWS_SECRET_ACCESS_KEY')
AWS_STORAGE_BUCKET_NAME  = os.getenv('AWS_STORAGE_BUCKET_NAME')
AWS_S3_REGION_NAME       = os.getenv('AWS_S3_REGION_NAME')
AWS_S3_ENDPOINT_URL      = os.getenv('OCI_ENDPOINT_URL')     # ¡NUEVO Y OBLIGATORIO PARA OCI!

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
            "location": "media/",            # Carpeta raíz en el bucket
            "querystring_auth": True,        # URLs prefirmadas (bucket privado)
            "region_name": AWS_S3_REGION_NAME,
            "endpoint_url": AWS_S3_ENDPOINT_URL,  # <--- Esto desvía el tráfico a OCI
            "client_config": Config(
                signature_version="s3v4",
                s3={"payload_signing_enabled": False},         # 1. Apaga la firma por pedazos
                request_checksum_calculation="when_required",  # 2. Apaga el chunked por checksum (Nuevo)
                response_checksum_validation="when_required",  
            ),
        },
    },
    "staticfiles": {
        "BACKEND": "django.contrib.staticfiles.storage.StaticFilesStorage",
    },
}

# --- Fin configuración OCI ---
```



> **¿Qué hace `AWS_S3_ENDPOINT_URL`?**  
> Normalmente, `S3Storage` apunta a `s3.amazonaws.com`. Al asignarle el valor de `OCI_ENDPOINT_URL`, todo el tráfico de archivos se redirige a OCI Object Storage en lugar de AWS S3. Como OCI es compatible con la API S3, el backend funciona sin modificaciones adicionales.
>
> **¿Qué hace `client_config`?**  
> Esta sección configura el comportamiento interno del cliente `boto3` (el SDK de AWS que usa `django-storages` bajo el capó). Con OCI, tres ajustes son necesarios:
> - **`signature_version = 's3v4'`**: Usa la versión 4 del algoritmo de firma (la misma que AWS), que es la que OCI espera para las peticiones autenticadas.  
> - **`s3 = {"payload_signing_enabled": False}`**: Deshabilita el *payload chunked signing* (firma por fragmentos). OCI no soporta que el contenido del archivo se firme en partes durante la subida. Al apagarlo, el SDK envía el archivo completo con una sola firma, lo que OCI sí acepta.  
> - **`request_checksum_calculation = "when_required"` y `response_checksum_validation = "when_required"`**: Controlan el cálculo de checksums (CRC32, SHA256, etc.) durante la transferencia. Desde versiones recientes de `boto3`, el SDK intenta calcular checksums de forma agresiva incluso cuando el endpoint no los soporta, lo que causa errores como *"InvalidRequest: The request signature does not conform to required standards"*. Con `"when_required"`, el SDK solo calcula checksums cuando el servidor los exige explícitamente, evitando este error en OCI.  
>
> **¿Qué hace `STORAGES`?**  
> El diccionario `STORAGES` le dice a Django qué backend usar para cada tipo de archivo.  
> - `"default"` → **Archivos multimedia** (los que suben los usuarios): se redirigen a OCI.  
> - `"staticfiles"` → **Archivos estáticos** (CSS, JS del admin): se quedan en el servidor local.  
> 
> `"location": "media/"` indica que todos los archivos se almacenarán dentro de la carpeta `media/` del bucket.  
> `"querystring_auth": True` genera URLs prefirmadas con tiempo de expiración, manteniendo el bucket privado.

---

## Fase 5: Reemplazar Imágenes Estáticas por OCI

Ahora modificaremos la vista para que, en lugar de usar URLs de Picsum, **lea las imágenes directamente desde el bucket de OCI**.

### 5.1. Subir imágenes al bucket manualmente

Antes de probar, necesitas tener imágenes en OCI:

1. Ve a la consola de OCI → **Storage** → **Buckets** → tu bucket → carpeta `media/`.
2. Sube manualmente 3 a 6 imágenes con nombres simples (ej: `producto1.jpg`, `producto2.jpg`, etc.).
3. Asegúrate de que tengan extensiones válidas: `.jpg`, `.jpeg`, `.png`, `.webp`, `.gif` o `.svg`.

### 5.2. Actualizar `gallery/views.py`

Reemplaza el contenido actual por el siguiente código que lista los archivos de OCI y genera URLs prefirmadas:

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
        print(f"Error al conectar con OCI: {e}")

    return render(request, 'gallery/index.html', {'productos': images})
```

> **¿Qué cambió respecto a la Fase 1?**  
> - Antes: `productos` era una lista de diccionarios con `nombre`, `precio` e `imagen`.  
> - Ahora: `productos` es una lista plana de **URLs prefirmadas** generadas por OCI.  
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
    <title>Catálogo desde OCI</title>
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
    <h1>Catálogo desde OCI Object Storage</h1>
    <div class="gallery-container">
        {% for imagen in productos %}
            <div class="gallery-item">
                <img src="{{ imagen }}" alt="Imagen desde OCI" loading="lazy">
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

Abre `http://127.0.0.1:8000/`. Ahora deberías ver las imágenes que subiste manualmente a OCI, servidas a través de URLs prefirmadas temporales.

---

## Fase 6: Subir y Eliminar Imágenes desde la Web (CRUD Completo)

Hasta ahora las imágenes se suben manualmente desde la consola de OCI. En esta fase final añadiremos un **formulario web** para que cualquier usuario pueda subir imágenes directamente al bucket, y un **botón para eliminarlas** cuando ya no sean necesarias.

Para ello necesitamos un **modelo** en la base de datos que asocie cada imagen con metadatos (nombre, precio), y un **ImageField** que, gracias a django-storages, subirá automáticamente el archivo a OCI al guardar el formulario.

### 6.1. Crear el modelo Producto

**`gallery/models.py`:**

```python
from django.db import models

class Producto(models.Model):
    nombre = models.CharField(max_length=100)
    precio = models.DecimalField(max_digits=10, decimal_places=2)
    # ImageField: el archivo se almacena en OCI automáticamente
    # El upload_to está vacío porque "location": "media" ya antepone la carpeta
    # (definido en settings.py en la sección de STORAGES)
    imagen = models.ImageField(upload_to='')

    def __str__(self):
        return self.nombre
```

> **¿Por qué `upload_to=''`?**  
> En `settings.py` definimos `"location": "media/"` en el backend S3Storage apuntando a OCI. Esto hace que **todos los archivos del modelo se guarden dentro de `media/`** en el bucket. Si pusieramos `upload_to='media/'`, el resultado final sería `media/media/mi-imagen.jpg`. Al dejarlo vacío, se almacena como `media/mi-imagen.jpg`.

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
            # Guarda el producto: el ImageField sube la imagen a OCI automáticamente
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
    # Elimina el archivo del bucket OCI
    producto.imagen.delete(save=False)   # Llama a DeleteObject de la API S3 compatible
    producto.delete()                    # Elimina el registro de la BD
    return redirect('index')
```

> **`producto.imagen.delete(save=False)`**  
> El método `.delete()` del `ImageField` ejecuta la operación `DeleteObject` contra OCI Object Storage, borrando el archivo físico del bucket. El parámetro `save=False` evita que Django intente guardar el modelo (con la imagen ya eliminada) antes de borrarlo.

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
    <title>Gestión de Productos (OCI Object Storage)</title>
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

    <h1>Gestión de Productos (OCI Object Storage)</h1>

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
            <button type="submit" class="btn-submit">Guardar en OCI</button>
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
                          onsubmit="return confirm('¿Eliminar este producto y su imagen de OCI?');">
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

De no ejecutarse ninguna migración con "makemigrations" agregar al final el nombre de la aplicación, en este caso gallery

```bash
python manage.py makemigrations gallery
```

### 6.7. Probar el flujo completo

```bash
python manage.py runserver
```

1. Abre `http://127.0.0.1:8000/`.
2. Completa el formulario con nombre, precio y selecciona una imagen desde tu computador.
3. Haz clic en **Guardar en OCI**.
   - La imagen se sube automáticamente a tu bucket de OCI (carpeta `media/`).
   - Los datos se guardan en SQLite.
4. La imagen aparece en el grid con su nombre y precio.
5. Haz clic en **Eliminar** en cualquier producto.
   - El archivo se borra de OCI (operación `DeleteObject` a través de la API compatible con S3).
   - El registro se elimina de la base de datos.
6. Puedes verificar en la consola de OCI que los archivos se crean y eliminan correctamente.

---

## Resumen del Flujo Completo (Fases 1 a 6)

```
Fase 1:  Productos estáticos con Picsum
         ├── views.py → lista hardcoded de URLs
         └── index.html → grid simple con <img src="...">

Fase 2:  Bucket OCI privado con carpeta media/

Fase 3:  Customer Secret Keys + Endpoint URL de OCI

Fase 4:  django-storages + boto3 en settings.py
         └── STORAGES["default"] → S3Storage(location="media", endpoint_url=OCI)

Fase 5:  Reemplazo de Picsum por OCI
         ├── views.py → default_storage.listdir() + default_storage.url()
         └── index.html → muestra URLs prefirmadas desde OCI

Fase 6:  CRUD completo con formulario web
         ├── models.py → Producto (ImageField → OCI)
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
    │                                           hacia OCI Object Storage
    │                                       │
    │                                       └── Renderiza HTML con imágenes
    │
    ├── [POST /] (formulario con archivo) ─ Django recibe imagen
    │                                       │
    │                                       ├── PutObject a OCI (sube al bucket)
    │                                       └── Guarda registro en SQLite
    │
    └── [POST /eliminar/X] ──────────────── Django busca producto
                                              │
                                              ├── DeleteObject en OCI (borra imagen)
                                              └── DELETE registro en SQLite
```

## Diferencias clave entre AWS S3 y OCI Object Storage

| Concepto | AWS S3 | OCI Object Storage |
|----------|--------|-------------------|
| Endpoint | Automático según región | `https://{namespace}.compat.objectstorage.{region}.oraclecloud.com` |
| Credenciales | IAM User (Access Key + Secret Key) o AWS Academy (con token) | Customer Secret Keys (Access Key + Secret Key) |
| Session Token | Obligatorio en AWS Academy | No aplica |
| Variable clave en `.env` | `AWS_S3_REGION_NAME` | `OCI_ENDPOINT_URL` |
| Backend Django | `S3Storage` (mismo) | `S3Storage` con `endpoint_url` apuntando a OCI |

Con esto tienes una aplicación Django completamente integrada con OCI Object Storage, que parte de un catálogo estático con imágenes de Picsum y evoluciona hasta un **sistema completo de gestión de productos con almacenamiento en la nube de Oracle**, aprovechando la compatibilidad con la API S3 y sin exponer tu bucket al público.