# Integración de Azure Blob Storage en Django — Guía Paso a Paso

Esta guía te llevará desde un proyecto Django base que muestra un catálogo de productos con imágenes estáticas de **Picsum**, hasta una aplicación completamente funcional que **almacena, sirve y elimina imágenes en Azure Blob Storage**, utilizando formularios web y buenas prácticas de seguridad.

El proyecto base ya está creado. Solo debes seguir las fases en orden para transformarlo.

> **Nota sobre Azure Blob Storage:** A diferencia de AWS S3 y OCI Object Storage, Azure Blob Storage **no es compatible con la API S3**. Por lo tanto, en lugar de usar el backend `S3Storage` de `django-storages`, utilizaremos el backend nativo `AzureStorage` del paquete `django-storages[azure]`. La configuración es diferente pero igual de simple.

---

## Fase 1: Proyecto Base — Catálogo con Imágenes Estáticas

Antes de integrar Azure Blob Storage, revisa cómo funciona el proyecto en su estado inicial. Esta fase no requiere modificar nada; solo entender la arquitectura actual.

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

> Nota que `producto.imagen` es simplemente una cadena de texto con una URL. En las fases posteriores esto cambiará por un objeto `ImageField` conectado a Azure Blob Storage.

### 1.3. Probar el proyecto base

Ejecuta el servidor para ver el estado inicial:

```bash
python manage.py runserver
```

Abre `http://127.0.0.1:8000/`. Verás 6 tarjetas con imágenes aleatorias de Picsum. Este es el punto de partida.

---

## Fase 2: Creación y Configuración del Contenedor en Azure Blob Storage

Ahora crearemos la cuenta de almacenamiento y el contenedor en Azure que almacenará las imágenes de los productos.

### 2.1. Crear la Cuenta de Almacenamiento

1. Ingresa a [portal.azure.com](https://portal.azure.com) e inicia sesión con tu cuenta (puede ser **Azure for Students**).

2. En la barra de búsqueda superior, escribe **"Cuentas de almacenamiento"** y selecciona el servicio.

3. Haz clic en **Crear** y aplica la siguiente configuración:

   - **Suscripción:** `Azure for Students` (o la que corresponda).
   - **Grupo de recursos:** Crea uno nuevo (ej: `rg-django-azure`) o selecciona uno existente.
     > El grupo de recursos es un contenedor lógico que agrupará todos los recursos relacionados (cuenta de almacenamiento, configuraciones de red, seguridad, etc.).
   - **Nombre de la cuenta de almacenamiento:** Elige un nombre único a nivel global (ej: `almacenamientoprod2026` o tu nombre con un sufijo).
     > El nombre debe tener entre 3 y 24 caracteres, solo letras minúsculas y números. Debe ser único en todo Azure.
   - **Región:** Selecciona la región más cercana a tus usuarios o a tu servidor de aplicaciones.
     > Si tu instancia y base de datos están en EE.UU., lo lógico sería elegir una región en EE.UU. para minimizar la latencia. Para cuentas **Azure for Students**, regiones como **Brasil Sur** (`brazilsouth`) están disponibles si Chile no lo está.
   - **Rendimiento:** `Estándar` (suficiente para imágenes de aplicación web).
     > Premium usa SSD de alto rendimiento, solo se justifica para bases de datos transaccionales directamente sobre el almacenamiento.
   - **Redundancia:** `LRS` (Almacenamiento con redundancia local).
     > LRS mantiene 3 copias dentro del mismo centro de datos. Es la opción más económica y adecuada para desarrollo/pruebas. ZRS replicaría en 3 edificios distintos de la misma región.

4. En la pestaña **Opciones avanzadas**, asegúrate de que el **Tipo de cuenta de almacenamiento** sea `Azure Blob Storage` (o `StorageV2` que incluye Blob).

5. Haz clic en **Revisar + crear** y luego en **Crear**.

### 2.2. Obtener las Credenciales de Acceso

1. Una vez creada la cuenta, haz clic en **Ir al recurso**.

2. En el menú lateral izquierdo, bajo **Seguridad + redes**, selecciona **Claves de acceso**.

3. Verás dos claves (`key1` y `key2`). Cualquiera de las dos funciona. Copia los siguientes valores:

   - **Nombre de la cuenta de almacenamiento** (ej: `almacenamientoprod2026`).
   - **Clave** (haz clic en **Mostrar** junto a `key1` y copia el valor completo).

4. Estos valores los usarás en el archivo `.env` (Fase 4).

### 2.3. Crear el Contenedor "media"

1. En el menú lateral izquierdo, bajo **Almacenamiento de datos**, selecciona **Contenedores**.

2. Haz clic en **+ Contenedor**.

3. Configura:

   - **Nombre:** `media` (allí se almacenarán las imágenes subidas desde la web).
   - **Nivel de acceso público:** `Privado (sin acceso anónimo)`.
     > El contenedor será privado. Django generará URLs con token SAS (Shared Access Signature) para acceder a cada imagen de forma temporal y segura.

4. Haz clic en **Crear**.

> **¿Por qué privado?**  
> Al igual que con AWS S3 y OCI, el bucket/contenedor se mantiene privado. Django genera URLs firmadas temporalmente (SAS tokens en Azure) para que los usuarios puedan ver las imágenes sin exponer las credenciales ni hacer el contenedor público.

---

## Fase 3: Configuración del Entorno Local

Azure Blob Storage se autentica mediante el **nombre de la cuenta** y una **clave de acceso**. No necesitas usuarios IAM ni tokens de sesión.

### 3.1. Valores necesarios

A estas alturas ya deberías tener:

| Variable | Descripción | Ejemplo |
|----------|-------------|---------|
| `AZURE_ACCOUNT_NAME` | Nombre de tu cuenta de almacenamiento | `almacenamientoprod2026` |
| `AZURE_ACCOUNT_KEY` | Clave de acceso (key1) | `7vuWclCpCfrSE+bxkrx26aWnWY3G87z...` |
| `AZURE_CONTAINER` | Nombre del contenedor | `media` |

> **¿Dónde encuentro mi Clave de Acceso?**  
> En el portal de Azure: Cuenta de almacenamiento → **Seguridad + redes** → **Claves de acceso** → Mostrar clave de `key1`.

### 3.2. Estructura del endpoint

Azure Blob Storage tiene un endpoint por defecto con el siguiente formato:

```
https://{nombre-cuenta}.blob.core.windows.net/{contenedor}/
```

Por ejemplo:
```
https://almacenamientoprod2026.blob.core.windows.net/media/
```

Este endpoint no es necesario configurarlo manualmente en la mayoría de los casos, ya que `django-storages` lo construye automáticamente a partir del `AZURE_ACCOUNT_NAME`.

---

## Fase 4: Configuración de Django para Azure Blob Storage

Ahora conectaremos Django con el contenedor de Azure mediante `django-storages[azure]`.

### 4.1. Instalar dependencias

```bash
pip install django-storages[azure] python-dotenv
```

> **Nota:** A diferencia de AWS y OCI, no necesitamos `boto3`. El backend `AzureStorage` de `django-storages` usa la SDK `azure-storage-blob` (se instala automáticamente con `django-storages[azure]`).

### 4.2. Crear archivo `.env`

En la raíz del proyecto (`django-app-example/`), crea un archivo `.env` con las credenciales obtenidas en las fases anteriores:

```ini
AZURE_ACCOUNT_NAME=almacenamientoprod2026
AZURE_ACCOUNT_KEY=7vuWclCpCf....
AZURE_CONTAINER=media
```

> **Importante:** No compartas este archivo. Agrega `.env` a tu `.gitignore` (ya está incluido en el proyecto base).

### 4.3. Agregar `storages` a `INSTALLED_APPS`

En `core/settings.py`, modifica `THIRD_PARTY_APPS` para incluir `'storages'`:

```python
THIRD_PARTY_APPS = [
    'storages',
]
```

### 4.4. Importar `os` y `load_dotenv`

Asegúrate de que al inicio de `settings.py` ya tengas las importaciones necesarias. Debe verse así:

```python
import os
from dotenv import load_dotenv
from pathlib import Path

load_dotenv()

```

### 4.5. Configurar credenciales y almacenamiento en `settings.py`

Agrega el siguiente bloque de configuración en `core/settings.py`. Puedes colocarlo al final del archivo, antes del marcador de AWS o después de `DEFAULT_AUTO_FIELD`:

```python
# --- Configuración de Azure Blob Storage ---

# 1. Credenciales desde variables de entorno
AZURE_ACCOUNT_NAME = os.getenv('AZURE_ACCOUNT_NAME')
AZURE_ACCOUNT_KEY  = os.getenv('AZURE_ACCOUNT_KEY')
AZURE_CONTAINER    = os.getenv('AZURE_CONTAINER')

# 2. Mapeo de almacenamiento (Django 4.2+)
STORAGES = {
    "default": {
        "BACKEND": "storages.backends.azure_storage.AzureStorage",
        "OPTIONS": {
            "account_name": AZURE_ACCOUNT_NAME,
            "account_key": AZURE_ACCOUNT_KEY,
            "azure_container": AZURE_CONTAINER,
            "expiration_secs": 3600,            # URLs SAS válidas por 1 hora
            "overwrite_files": True,            # Sobrescribe archivos con el mismo nombre
            "cache_control": "max-age=86400",   # Caché de 24 h en navegador
        },
    },
    "staticfiles": {
        "BACKEND": "django.contrib.staticfiles.storage.StaticFilesStorage",
    },
}

# --- Fin configuración Azure Blob Storage ---
```

> **¿Qué hace `STORAGES`?**  
> El diccionario `STORAGES` le dice a Django qué backend usar para cada tipo de archivo.  
> - `"default"` → **Archivos multimedia** (los que suben los usuarios): se redirigen a Azure Blob Storage.  
> - `"staticfiles"` → **Archivos estáticos** (CSS, JS del admin): se quedan en el servidor local.  
>
> **Opciones importantes:**
> - `expiration_secs`: Define cuántos segundos es válida la URL SAS (firma de acceso compartido). Con `3600` (1 hora), la URL expira y se genera una nueva al recargar la página.
> - `overwrite_files`: Si es `True`, al subir un archivo con el mismo nombre que uno existente, se sobrescribe automáticamente.
> - `cache_control`: Agrega el encabezado `Cache-Control: max-age=86400` a las imágenes para que el navegador las almacene en caché por 24 horas.

---

## Fase 5: Reemplazar Imágenes Estáticas por Azure Blob Storage

Ahora modificaremos la vista para que, en lugar de usar URLs de Picsum, **lea las imágenes directamente desde Azure Blob Storage**.

### 5.1. Subir imágenes al contenedor manualmente

Antes de probar, necesitas tener imágenes en Azure:

1. Ve al portal de Azure → Cuenta de almacenamiento → **Contenedores** → `media`.
2. Haz clic en **Subir**.
3. Selecciona 3 a 6 imágenes desde tu computador con nombres simples (ej: `producto1.jpg`, `producto2.jpg`, etc.).
4. Asegúrate de que tengan extensiones válidas: `.jpg`, `.jpeg`, `.png`, `.webp`, `.gif` o `.svg`.
5. Haz clic en **Subir**.

> **Alternativa con Azure Storage Explorer:**  
> Puedes usar [Azure Storage Explorer](https://azure.microsoft.com/es-es/products/storage/storage-explorer/) (gratuito) para subir archivos arrastrándolos desde tu explorador de archivos.

### 5.2. Actualizar `gallery/views.py`

Reemplaza el contenido actual por el siguiente código que lista los archivos de Azure Blob Storage y genera URLs SAS:

```python
from django.shortcuts import render
from django.core.files.storage import default_storage

def index(request):
    images = []

    try:
        # 1. Listar objetos en el contenedor "media"
        directories, files = default_storage.listdir('')

        # 2. Filtrar solo archivos de imagen
        valid_extensions = ('.jpg', '.jpeg', '.png', '.webp', '.gif', '.svg')
        image_files = [f for f in files if f.lower().endswith(valid_extensions)]

        # 3. Generar URLs SAS para cada imagen
        images = [default_storage.url(f) for f in image_files]

    except Exception as e:
        print(f"Error al conectar con Azure Blob Storage: {e}")

    return render(request, 'gallery/index.html', {'productos': images})
```

> **¿Qué cambió respecto a la Fase 1?**  
> - Antes: `productos` era una lista de diccionarios con `nombre`, `precio` e `imagen`.  
> - Ahora: `productos` es una lista plana de **URLs SAS** generadas por Azure Blob Storage.  
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
    <title>Catálogo desde Azure</title>
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
    <h1>Catálogo desde Azure Blob Storage</h1>
    <div class="gallery-container">
        {% for imagen in productos %}
            <div class="gallery-item">
                <img src="{{ imagen }}" alt="Imagen desde Azure" loading="lazy">
            </div>
        {% empty %}
            <p style="grid-column: 1 / -1; text-align: center; color: #666;">
                No hay imágenes en el contenedor. Súbelas manualmente a la carpeta <strong>media</strong>.
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

Abre `http://127.0.0.1:8000/`. Ahora deberías ver las imágenes que subiste manualmente a Azure, servidas a través de URLs SAS temporales.

---

## Fase 6: Subir y Eliminar Imágenes desde la Web (CRUD Completo)

Hasta ahora las imágenes se suben manualmente desde el portal de Azure. En esta fase final añadiremos un **formulario web** para que cualquier usuario pueda subir imágenes directamente al contenedor, y un **botón para eliminarlas** cuando ya no sean necesarias.

Para ello necesitamos un **modelo** en la base de datos que asocie cada imagen con metadatos (nombre, precio), y un **ImageField** que, gracias a django-storages, subirá automáticamente el archivo a Azure Blob Storage al guardar el formulario.

### 6.1. Crear el modelo Producto

**`gallery/models.py`:**

```python
from django.db import models

class Producto(models.Model):
    nombre = models.CharField(max_length=100)
    precio = models.DecimalField(max_digits=10, decimal_places=2)
    # ImageField: el archivo se almacena en Azure Blob Storage automáticamente
    # La carpeta base es el contenedor "media" definido en settings.py
    # (configurado en STORAGES["default"]["OPTIONS"]["azure_container"])
    imagen = models.ImageField(upload_to='')

    def __str__(self):
        return self.nombre
```

> **¿Por qué `upload_to=''`?**  
> En `settings.py` definimos `"azure_container": "media"` en el backend AzureStorage. Esto hace que **todos los archivos del modelo se guarden dentro del contenedor `media`** en Azure. Si pusieramos `upload_to='media/'`, el resultado final sería un blob llamado `media/mi-imagen.jpg` dentro del contenedor `media`. Al dejarlo vacío, se almacena directamente como `mi-imagen.jpg` dentro del contenedor `media`.

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
            # Guarda el producto: el ImageField sube la imagen a Azure automáticamente
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
    # Elimina el blob de Azure Blob Storage
    producto.imagen.delete(save=False)   # Llama a Delete Blob de Azure
    producto.delete()                    # Elimina el registro de la BD
    return redirect('index')
```

> **`producto.imagen.delete(save=False)`**  
> El método `.delete()` del `ImageField` ejecuta la operación `Delete Blob` contra Azure Blob Storage, borrando el archivo físico del contenedor. El parámetro `save=False` evita que Django intente guardar el modelo (con la imagen ya eliminada) antes de borrarlo.

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
    <title>Gestión de Productos (Azure Blob Storage)</title>
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

    <h1>Gestión de Productos (Azure Blob Storage)</h1>

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
            <button type="submit" class="btn-submit">Guardar en Azure</button>
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
                          onsubmit="return confirm('¿Eliminar este producto y su imagen de Azure?');">
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

Si no se detectan cambios con `makemigrations`, ejecuta especificando la aplicación:

```bash
python manage.py makemigrations gallery
```

### 6.7. Probar el flujo completo

```bash
python manage.py runserver
```

1. Abre `http://127.0.0.1:8000/`.
2. Completa el formulario con nombre, precio y selecciona una imagen desde tu computador.
3. Haz clic en **Guardar en Azure**.
   - La imagen se sube automáticamente a tu contenedor de Azure Blob Storage (`media`).
   - Los datos se guardan en SQLite.
4. La imagen aparece en el grid con su nombre y precio.
5. Haz clic en **Eliminar** en cualquier producto.
   - El blob se borra de Azure (operación `Delete Blob`).
   - El registro se elimina de la base de datos.
6. Puedes verificar en el portal de Azure que los blobs se crean y eliminan correctamente.

---

## Resumen del Flujo Completo (Fases 1 a 6)

```
Fase 1:  Productos estáticos con Picsum
         ├── views.py → lista hardcoded de URLs
         └── index.html → grid simple con <img src="...">

Fase 2:  Cuenta de almacenamiento + contenedor media/ en Azure

Fase 3:  Credenciales (Account Name + Account Key)

Fase 4:  django-storages[azure] en settings.py
         └── STORAGES["default"] → AzureStorage(container="media")

Fase 5:  Reemplazo de Picsum por Azure
         ├── views.py → default_storage.listdir() + default_storage.url()
         └── index.html → muestra URLs SAS desde Azure

Fase 6:  CRUD completo con formulario web
         ├── models.py → Producto (ImageField → Azure)
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
    │                                       └── AzureStorage genera URL SAS
    │                                           para cada imagen
    │                                       │
    │                                       └── Renderiza HTML con imágenes
    │
    ├── [POST /] (formulario con archivo) ─ Django recibe imagen
    │                                       │
    │                                       ├── Put Blob a Azure (sube al contenedor)
    │                                       └── Guarda registro en SQLite
    │
    └── [POST /eliminar/X] ──────────────── Django busca producto
                                              │
                                              ├── Delete Blob en Azure (borra imagen)
                                              └── DELETE registro en SQLite
```

## Diferencias clave entre AWS S3, OCI Object Storage y Azure Blob Storage

| Concepto | AWS S3 | OCI Object Storage | Azure Blob Storage |
|----------|--------|-------------------|-------------------|
| Backend Django | `S3Storage` | `S3Storage` (compatible S3) | `AzureStorage` (nativo) |
| Paquete | `django-storages` + `boto3` | `django-storages` + `boto3` | `django-storages[azure]` |
| Endpoint | Automático según región | `https://{namespace}.compat.objectstorage.{region}.oraclecloud.com` | Automático (`*.blob.core.windows.net`) |
| Credenciales | IAM User (Access Key + Secret Key) o AWS Academy (con token) | Customer Secret Keys (Access Key + Secret Key) | Account Name + Account Key |
| Session Token | Obligatorio en AWS Academy | No aplica | No aplica |
| Tipo de URL firmada | Pre-signed URL | Pre-signed URL (compatible S3) | SAS Token (Shared Access Signature) |
| Almacenamiento | Bucket | Bucket | Contenedor |
| Carpeta en nube | `location: "media"` | `location: "media/"` | `azure_container: "media"` |

Con esto tienes una aplicación Django completamente integrada con Azure Blob Storage, que parte de un catálogo estático con imágenes de Picsum y evoluciona hasta un **sistema completo de gestión de productos con almacenamiento en la nube de Microsoft Azure**, utilizando URLs SAS para mantener tu contenedor privado y seguro.