# Integración de Azure Blob Storage en Express (Node.js) — Guía Paso a Paso

Esta guía te llevará desde un proyecto Express base que muestra un catálogo de productos con imágenes estáticas de **Picsum**, hasta una aplicación completamente funcional que **almacena, sirve y elimina imágenes en Azure Blob Storage**, utilizando formularios web y buenas prácticas de seguridad.

El proyecto base ya está creado. Solo debes seguir las fases en orden para transformarlo.

> **Nota sobre Azure Blob Storage:** A diferencia de AWS S3 y OCI Object Storage, Azure Blob Storage **no es compatible con la API S3**. Por lo tanto, en lugar del SDK `@aws-sdk/client-s3`, utilizaremos el SDK nativo `@azure/storage-blob` de Microsoft. La configuración es diferente pero igual de simple.

---

## Fase 1: Proyecto Base — Catálogo con Imágenes Estáticas

Antes de integrar Azure Blob Storage, revisa cómo funciona el proyecto en su estado inicial. Esta fase no requiere modificar nada; solo entender la arquitectura actual.

### 1.1. Servidor actual (`app.js`)

El archivo `app.js` contiene una lista fija (hardcoded) de 6 productos con imágenes obtenidas del servicio gratuito **Picsum**:

```javascript
const express = require('express');
const app = express();
const port = 3000;

app.set('view engine', 'ejs');

const productoList = [
    { nombre: 'Zapatillas Urbanas', precio: 45000, imagen: 'https://picsum.photos/id/1018/800/600' },
    { nombre: 'Mochila Ejecutiva',  precio: 32000, imagen: 'https://picsum.photos/id/1015/800/600' },
    { nombre: 'Auriculares Pro',    precio: 28000, imagen: 'https://picsum.photos/id/1019/800/600' },
    { nombre: 'Reloj Deportivo',    precio: 55000, imagen: 'https://picsum.photos/id/1016/800/600' },
    { nombre: 'Cámara Digital',     precio: 89000, imagen: 'https://picsum.photos/id/1020/800/600' },
    { nombre: 'Lámpara LED',        precio: 15000, imagen: 'https://picsum.photos/id/1021/800/600' },
];

app.get('/', (req, res) => {
    res.render('index', { productos: productoList });
});

app.listen(port, () => {
    console.log(`Servidor corriendo en http://localhost:${port}`);
});
```

Cada producto es un objeto con tres propiedades: `nombre`, `precio` e `imagen` (URL externa).

### 1.2. Template actual (`views/index.ejs`)

El HTML itera sobre `productos` y genera un grid responsivo con tarjetas que muestran la imagen, el nombre y el precio:

```html
<div class="gallery-container">
    <% productos.forEach(function(producto) { %>
        <div class="gallery-item">
            <img src="<%= producto.imagen %>" alt="<%= producto.nombre %>" loading="lazy">
            <div class="item-info">
                <h3><%= producto.nombre %></h3>
                <p>$<%= producto.precio %></p>
            </div>
        </div>
    <% }); %>
</div>
```

> Nota que `producto.imagen` es simplemente una cadena de texto con una URL. En las fases posteriores esto cambiará por una URL SAS (Shared Access Signature) de Azure.

### 1.3. Probar el proyecto base

Ejecuta el servidor para ver el estado inicial:

```bash
node app.js
```

Abre `http://localhost:3000/`. Verás 6 tarjetas con imágenes aleatorias de Picsum. Este es el punto de partida.

---

## Fase 2: Creación y Configuración del Contenedor en Azure Blob Storage

Ahora crearemos la cuenta de almacenamiento y el contenedor en Azure que almacenará las imágenes de los productos.

### 2.1. Crear la Cuenta de Almacenamiento

1. Ingresa a [portal.azure.com](https://portal.azure.com) e inicia sesión con tu cuenta (puede ser **Azure for Students**).

2. En la barra de búsqueda superior, escribe **"Cuentas de almacenamiento"** y selecciona el servicio.

3. Haz clic en **Crear** y aplica la siguiente configuración:

   - **Suscripción:** `Azure for Students` (o la que corresponda).
   - **Grupo de recursos:** Crea uno nuevo (ej: `rg-express-azure`) o selecciona uno existente.
     > El grupo de recursos es un contenedor lógico que agrupará todos los recursos relacionados (cuenta de almacenamiento, configuraciones de red, seguridad, etc.).
   - **Nombre de la cuenta de almacenamiento:** Elige un nombre único a nivel global (ej: `almacenamientoprod2026` o tu nombre con un sufijo).
     > El nombre debe tener entre 3 y 24 caracteres, solo letras minúsculas y números. Debe ser único en todo Azure.
   - **Región:** Selecciona la región más cercana a tus usuarios o a tu servidor de aplicaciones.
     > Si tu instancia está en EE.UU., lo lógico sería elegir una región en EE.UU. Para cuentas **Azure for Students**, regiones como **Brasil Sur** (`brazilsouth`) están disponibles si Chile no lo está.
   - **Rendimiento:** `Estándar` (suficiente para imágenes de aplicación web).
     > Premium usa SSD de alto rendimiento, solo se justifica para bases de datos transaccionales directamente sobre el almacenamiento.
   - **Redundancia:** `LRS` (Almacenamiento con redundancia local).
     > LRS mantiene 3 copias dentro del mismo centro de datos. Es la opción más económica y adecuada para desarrollo/pruebas.

4. En la pestaña **Opciones avanzadas**, asegúrate de que el **Tipo de cuenta de almacenamiento** sea `Azure Blob Storage` (o `StorageV2` que incluye Blob).

5. Haz clic en **Revisar + crear** y luego en **Crear**.

### 2.2. Obtener las Credenciales de Acceso

1. Una vez creada la cuenta, haz clic en **Ir al recurso**.

2. En el menú lateral izquierdo, bajo **Seguridad + redes**, selecciona **Claves de acceso**.

3. Verás dos claves (`key1` y `key2`). Cualquiera de las dos funciona. Copia los siguientes valores:

   - **Nombre de la cuenta de almacenamiento** (ej: `almacenamientoprod2026`).
   - **Clave** (haz clic en **Mostrar** junto a `key1` y copia el valor completo).

4. También puedes copiar la **Cadena de conexión** de cualquiera de las dos claves. Esta cadena incluye toda la información necesaria para conectarte y puede usarse en lugar del nombre y clave por separado.

5. Estos valores los usarás en el archivo `.env` (Fase 4).

### 2.3. Crear el Contenedor "media"

1. En el menú lateral izquierdo, bajo **Almacenamiento de datos**, selecciona **Contenedores**.

2. Haz clic en **+ Contenedor**.

3. Configura:

   - **Nombre:** `media` (allí se almacenarán las imágenes subidas desde la web).
   - **Nivel de acceso público:** `Privado (sin acceso anónimo)`.
     > El contenedor será privado. Express generará URLs SAS (Shared Access Signature) para acceder a cada imagen de forma temporal y segura.

4. Haz clic en **Crear**.

> **¿Por qué privado?**  
> Al igual que con AWS S3 y OCI, el bucket/contenedor se mantiene privado. Express genera URLs SAS con expiración para que los usuarios puedan ver las imágenes sin exponer las credenciales ni hacer el contenedor público.

---

## Fase 3: Configuración del Entorno Local

Azure Blob Storage se autentica mediante el **nombre de la cuenta** y una **clave de acceso**, o mediante una **cadena de conexión**. No necesitas usuarios IAM ni tokens de sesión.

### 3.1. Valores necesarios

A estas alturas ya deberías tener:

| Variable | Descripción | Ejemplo |
|----------|-------------|---------|
| `AZURE_ACCOUNT_NAME` | Nombre de tu cuenta de almacenamiento | `almacenamientoprod2026` |
| `AZURE_ACCOUNT_KEY` | Clave de acceso (key1) | `7vuWclCpCfrSE+bxkrx26aWnWY3G87z...` |
| `AZURE_STORAGE_CONNECTION_STRING` | Cadena de conexión completa | `DefaultEndpointsProtocol=https;AccountName=...;AccountKey=...;EndpointSuffix=core.windows.net` |
| `AZURE_CONTAINER` | Nombre del contenedor | `media` |

> **¿Dónde encuentro mi Clave de Acceso y Cadena de Conexión?**  
> En el portal de Azure: Cuenta de almacenamiento → **Seguridad + redes** → **Claves de acceso** → Mostrar clave de `key1`. Allí también verás la **Cadena de conexión** justo debajo de cada clave.

### 3.2. Estructura del endpoint

Azure Blob Storage tiene un endpoint por defecto con el siguiente formato:

```
https://{nombre-cuenta}.blob.core.windows.net/{contenedor}/
```

Por ejemplo:
```
https://almacenamientoprod2026.blob.core.windows.net/media/
```

Este endpoint lo construye automáticamente el SDK de Azure a partir de la cadena de conexión o del nombre de la cuenta.

---

## Fase 4: Configuración de Express para Azure Blob Storage

Ahora conectaremos Express con el contenedor de Azure mediante el SDK oficial `@azure/storage-blob`.

### 4.1. Instalar dependencias

```bash
npm install dotenv @azure/storage-blob
```

> **Nota:** A diferencia de AWS y OCI, no necesitamos `@aws-sdk/client-s3` ni `@aws-sdk/s3-request-presigner`. Usamos el SDK nativo de Azure `@azure/storage-blob`.

### 4.2. Crear archivo `.env`

En la raíz del proyecto (`nodejs-app-example/`), crea un archivo `.env` con las credenciales obtenidas en las fases anteriores:

```ini
# Reemplaza los valores correspondientes en AZURE_STORAGE_CONNECTION_STRING
AZURE_STORAGE_CONNECTION_STRING=DefaultEndpointsProtocol=https;AccountName=almacenamientoprod2026;AccountKey=7vuWclCpCf....;EndpointSuffix=core.windows.net
AZURE_ACCOUNT_NAME=almacenamientoprod2026
AZURE_ACCOUNT_KEY=7vuWclCpCf....
AZURE_CONTAINER=media
```

> **¿Cadena de conexión o credenciales separadas?**  
> La cadena de conexión incluye todo lo necesario para conectarse. Si prefieres usar el nombre y la clave por separado (más legible en código), también funciona. El SDK de Azure acepta ambos enfoques.

### 4.3. Crear el cliente Azure Blob Storage

Crea un archivo `blobClient.js` que centralice la configuración del SDK de Azure y que será reutilizado en todas las fases siguientes:

**`blobClient.js`:**

```javascript
const { BlobServiceClient, generateBlobSASQueryParameters, StorageSharedKeyCredential, BlobSASPermissions } = require('@azure/storage-blob');
require('dotenv').config();

const connectionString = process.env.AZURE_STORAGE_CONNECTION_STRING;
const accountName = process.env.AZURE_ACCOUNT_NAME;
const accountKey = process.env.AZURE_ACCOUNT_KEY;
const containerName = process.env.AZURE_CONTAINER;

let blobServiceClient;

if (connectionString) {
    blobServiceClient = BlobServiceClient.fromConnectionString(connectionString);
} else if (accountName && accountKey) {
    const sharedKeyCredential = new StorageSharedKeyCredential(accountName, accountKey);
    blobServiceClient = new BlobServiceClient(
        `https://${accountName}.blob.core.windows.net`,
        sharedKeyCredential
    );
} else {
    throw new Error('Configura AZURE_STORAGE_CONNECTION_STRING o AZURE_ACCOUNT_NAME + AZURE_ACCOUNT_KEY en .env');
}

const containerClient = blobServiceClient.getContainerClient(containerName);

module.exports = { blobServiceClient, containerClient, containerName, accountName, accountKey };

```

> **¿Qué hace `BlobServiceClient`?**  
> Es el objeto principal del SDK de Azure para interactuar con Blob Storage. Se encarga de la autenticación y comunicación con la API de Azure. A partir de él obtenemos `ContainerClient`, que usaremos para todas las operaciones (listar, subir, descargar, eliminar blobs).

---

## Fase 5: Reemplazar Imágenes Estáticas por Azure Blob Storage

Ahora modificaremos el servidor para que, en lugar de usar URLs de Picsum, **lea las imágenes directamente desde Azure Blob Storage** y genere URLs SAS.

### 5.1. Subir imágenes al contenedor manualmente

Antes de probar, necesitas tener imágenes en Azure:

1. Ve al portal de Azure → Cuenta de almacenamiento → **Contenedores** → `media`.
2. Haz clic en **Subir**.
3. Selecciona 3 a 6 imágenes desde tu computador con nombres simples (ej: `producto1.jpg`, `producto2.jpg`, etc.).
4. Asegúrate de que tengan extensiones válidas: `.jpg`, `.jpeg`, `.png`, `.webp`, `.gif` o `.svg`.
5. Haz clic en **Subir**.

> **Alternativa con Azure Storage Explorer:**  
> Puedes usar [Azure Storage Explorer](https://azure.microsoft.com/es-es/products/storage/storage-explorer/) (gratuito) para subir archivos arrastrándolos desde tu explorador de archivos.

### 5.2. Actualizar `app.js`

Reemplaza el contenido de `app.js` por el siguiente código que lista los blobs de Azure y genera URLs SAS:

```javascript
const express = require('express');
const { BlobSASPermissions, generateBlobSASQueryParameters, StorageSharedKeyCredential } = require('@azure/storage-blob');
const { containerClient, accountName, accountKey, containerName } = require('./blobClient');

const app = express();
const port = 3000;

app.set('view engine', 'ejs');

const VALID_EXTENSIONS = ['.jpg', '.jpeg', '.png', '.webp', '.gif', '.svg'];

const sharedKeyCredential = new StorageSharedKeyCredential(accountName, accountKey);

app.get('/', async (req, res) => {
    try {
        const images = [];

        for await (const blob of containerClient.listBlobsFlat()) {
            const ext = blob.name.toLowerCase().slice(blob.name.lastIndexOf('.'));
            if (VALID_EXTENSIONS.includes(ext)) {
                // Generar URL SAS con expiración de 1 hora (solo lectura)
                const sasOptions = {
                    containerName,
                    blobName: blob.name,
                    startsOn: new Date(new Date().getTime() - 5 * 60 * 1000),
                    expiresOn: new Date(new Date().getTime() + 60 * 60 * 1000),
                    permissions: BlobSASPermissions.parse("r"),
                };
                const sasToken = generateBlobSASQueryParameters(sasOptions, sharedKeyCredential).toString();
                const sasUrl = `https://${accountName}.blob.core.windows.net/${containerName}/${blob.name}?${sasToken}`;
                images.push(sasUrl);
            }
        }

        res.render('index', { productos: images });

    } catch (error) {
        console.error('Error al conectar con Azure Blob Storage:', error);
        res.render('index', { productos: [] });
    }
});

app.listen(port, () => {
    console.log(`Servidor corriendo en http://localhost:${port}`);
});
```

> **¿Qué cambió respecto a la Fase 1?**  
> - Antes: `productos` era un array de objetos con `nombre`, `precio` e `imagen`.  
> - Ahora: `productos` es un array plano de **URLs de blobs** de Azure.  
>
> El template `index.ejs` itera sobre `productos` y muestra cada imagen. Como ahora solo tenemos URLs (sin nombre ni precio), el template mostrará solo las imágenes.

### 5.3. Actualizar `views/index.ejs`

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
        <% productos.forEach(function(imagen) { %>
            <div class="gallery-item">
                <img src="<%= imagen %>" alt="Imagen desde Azure" loading="lazy">
            </div>
        <% }); %>
        <% if (productos.length === 0) { %>
            <p style="grid-column: 1 / -1; text-align: center; color: #666;">
                No hay imágenes en el contenedor. Súbelas manualmente a la carpeta <strong>media</strong>.
            </p>
        <% } %>
    </div>
</body>
</html>
```

### 5.4. Probar

```bash
node app.js
```

Abre `http://localhost:3000/`. Ahora deberías ver las imágenes que subiste manualmente a Azure, servidas directamente desde Blob Storage.



## Fase 6: Subir y Eliminar Imágenes desde la Web (CRUD Completo)

Hasta ahora las imágenes se suben manualmente desde el portal de Azure. En esta fase final añadiremos un **formulario web** para que cualquier usuario pueda subir imágenes directamente al contenedor, y un **botón para eliminarlas** cuando ya no sean necesarias.

Para ello necesitamos:
- **Multer** para procesar la subida de archivos desde el formulario.
- Una **base de datos ligera** (SQLite con `better-sqlite3`) para almacenar los metadatos de cada producto.
- Los métodos `uploadData` y `delete` del SDK de Azure Blob Storage.
- **SAS tokens** para las URLs firmadas temporalmente.

### 6.1. Instalar dependencias adicionales

```bash
npm install multer better-sqlite3 @azure/storage-blob
```

> `@azure/storage-blob` ya debería estar instalado desde la Fase 4.

### 6.2. Configurar la base de datos

Crea un archivo `database.js` que inicialice SQLite con la tabla `productos`:

**`database.js`:**

```javascript
const Database = require('better-sqlite3');
const path = require('path');

const db = new Database(path.join(__dirname, 'productos.db'));

// Crear la tabla si no existe
db.exec(`
    CREATE TABLE IF NOT EXISTS productos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre TEXT NOT NULL,
        precio REAL NOT NULL,
        blob_name TEXT NOT NULL
    )
`);

module.exports = db;
```

> **`blob_name`** almacena el nombre del blob dentro del contenedor (ej: `producto1.jpg`). Esto nos permite localizar y eliminar el blob de Azure cuando sea necesario.

### 6.3. Actualizar `blobClient.js` (con generación de SAS)

Actualiza el archivo `blobClient.js` para incluir la generación de URLs SAS:

**`blobClient.js`:**

```javascript
const { BlobServiceClient, generateBlobSASQueryParameters, StorageSharedKeyCredential, BlobSASPermissions } = require('@azure/storage-blob');
require('dotenv').config();

const accountName = process.env.AZURE_ACCOUNT_NAME;
const accountKey = process.env.AZURE_ACCOUNT_KEY;
const connectionString = process.env.AZURE_STORAGE_CONNECTION_STRING;
const containerName = process.env.AZURE_CONTAINER;

let blobServiceClient;

if (connectionString) {
    // Usar cadena de conexión (más simple)
    blobServiceClient = BlobServiceClient.fromConnectionString(connectionString);
} else {
    // Usar credenciales separadas
    const sharedKeyCredential = new StorageSharedKeyCredential(accountName, accountKey);
    blobServiceClient = new BlobServiceClient(
        `https://${accountName}.blob.core.windows.net`,
        sharedKeyCredential
    );
}

const containerClient = blobServiceClient.getContainerClient(containerName);
const sharedKeyCredential = new StorageSharedKeyCredential(accountName, accountKey);

/**
 * Genera una URL SAS para un blob con expiración de 1 hora
 * @param {string} blobName - Nombre del blob en el contenedor
 * @returns {string} URL SAS completa
 */
function generateSasUrl(blobName) {
    const sasOptions = {
        containerName,
        blobName,
        startsOn: new Date(new Date().getTime() - 5 * 60 * 1000), // 5 min antes (margen)
        expiresOn: new Date(new Date().getTime() + 60 * 60 * 1000), // 1 hora
        permissions: BlobSASPermissions.parse("r"), // Solo lectura
    };

    const sasToken = generateBlobSASQueryParameters(sasOptions, sharedKeyCredential).toString();
    const blobUrl = `https://${accountName}.blob.core.windows.net/${containerName}/${blobName}?${sasToken}`;
    
    return blobUrl;
}

module.exports = { blobServiceClient, containerClient, containerName, sharedKeyCredential, generateSasUrl };
```

> **`generateBlobSASQueryParameters`** genera un token SAS (Shared Access Signature) que permite acceso temporal y seguro al blob.  
> **`BlobSASPermissions.parse("r")** establece permisos de solo lectura (`r`). También podríamos usar `"rw"` para lectura y escritura, pero para URLs públicas solo necesitamos lectura.  
> El token SAS incluye una fecha de expiración (`expiresOn`) y una de inicio (`startsOn`) con un margen de 5 minutos para evitar problemas de sesgo de reloj.

### 6.4. Actualizar `app.js` (CRUD completo)

Reemplaza todo el contenido de `app.js` con el siguiente código que incluye subida, listado y eliminación de productos:

```javascript
const express = require('express');
const multer = require('multer');
const path = require('path');
const { containerClient, generateSasUrl } = require('./blobClient');
const db = require('./database');

const app = express();
const port = 3000;

app.set('view engine', 'ejs');
app.use(express.urlencoded({ extended: true }));

// Configurar Multer para archivos temporales en memoria
const storage = multer.memoryStorage();
const upload = multer({
    storage,
    limits: { fileSize: 15 * 1024 * 1024 }, // 15 MB máximo
    fileFilter: (req, file, cb) => {
        const validExtensions = ['.jpg', '.jpeg', '.png', '.webp', '.gif', '.svg'];
        const ext = path.extname(file.originalname).toLowerCase();
        if (validExtensions.includes(ext)) {
            cb(null, true);
        } else {
            cb(new Error('Solo se permiten imágenes (jpg, jpeg, png, webp, gif, svg)'));
        }
    },
});

// Middleware para parsear JSON en las peticiones de eliminación
app.use(express.json());

// ───────────────────────────────────────────────
// Ruta principal: Listar productos desde BD + Azure
// ───────────────────────────────────────────────
app.get('/', async (req, res) => {
    try {
        const productos = db.prepare('SELECT * FROM productos').all();

        // Generar URL SAS para cada producto
        const productosConUrl = productos.map((producto) => {
            const sasUrl = generateSasUrl(producto.blob_name);
            return {
                id: producto.id,
                nombre: producto.nombre,
                precio: producto.precio,
                imagen: sasUrl,
            };
        });

        res.render('index', { productos: productosConUrl });

    } catch (error) {
        console.error('Error al listar productos:', error);
        res.render('index', { productos: [] });
    }
});

// ───────────────────────────────────────────────
// Ruta POST: Subir un nuevo producto
// ───────────────────────────────────────────────
app.post('/', upload.single('imagen'), async (req, res) => {
    try {
        const { nombre, precio } = req.body;
        const archivo = req.file;

        if (!nombre || !precio || !archivo) {
            return res.status(400).send('Faltan campos requeridos');
        }

        // Generar un nombre único para evitar colisiones en Azure
        const ext = path.extname(archivo.originalname);
        const blobName = `${Date.now()}-${Math.random().toString(36).substring(2, 8)}${ext}`;

        // Subir archivo a Azure Blob Storage
        const blockBlobClient = containerClient.getBlockBlobClient(blobName);
        await blockBlobClient.uploadData(archivo.buffer, {
            blobHTTPHeaders: { blobContentType: archivo.mimetype },
        });

        // Guardar metadatos en SQLite
        db.prepare('INSERT INTO productos (nombre, precio, blob_name) VALUES (?, ?, ?)').run(
            nombre,
            parseFloat(precio),
            blobName
        );

        res.redirect('/');

    } catch (error) {
        console.error('Error al subir producto:', error);
        res.status(500).send('Error al subir el producto');
    }
});

// ───────────────────────────────────────────────
// Ruta DELETE: Eliminar un producto
// ───────────────────────────────────────────────
app.delete('/eliminar/:id', async (req, res) => {
    try {
        const { id } = req.params;

        // Buscar producto en BD
        const producto = db.prepare('SELECT * FROM productos WHERE id = ?').get(id);
        if (!producto) {
            return res.status(404).json({ error: 'Producto no encontrado' });
        }

        // Eliminar blob de Azure Blob Storage
        const blockBlobClient = containerClient.getBlockBlobClient(producto.blob_name);
        await blockBlobClient.delete();

        // Eliminar registro de BD
        db.prepare('DELETE FROM productos WHERE id = ?').run(id);

        res.json({ success: true });

    } catch (error) {
        console.error('Error al eliminar producto:', error);
        res.status(500).json({ error: 'Error al eliminar el producto' });
    }
});

// ───────────────────────────────────────────────
// Middleware de errores para Multer
// ───────────────────────────────────────────────
app.use((err, req, res, next) => {
    if (err instanceof multer.MulterError) {
        return res.status(400).send(`Error de subida: ${err.message}`);
    }
    if (err) {
        return res.status(400).send(err.message);
    }
    next();
});

app.listen(port, () => {
    console.log(`Servidor corriendo en http://localhost:${port}`);
});
```

> **`blockBlobClient.uploadData()`** recibe el archivo desde el buffer de Multer y lo sube a Azure Blob Storage.  
> **`blockBlobClient.delete()`** elimina el blob del contenedor usando el `blob_name` almacenado en la BD.  
> **`multer.memoryStorage()`** mantiene el archivo en RAM (no en disco), ideal para reenviarlo directamente a Azure.  
> **`generateSasUrl()`** genera una URL SAS temporal para cada imagen, permitiendo acceso seguro sin exponer el contenedor.  
> El nombre en Azure incluye un timestamp + hash aleatorio para evitar colisiones entre archivos con el mismo nombre.

### 6.5. Actualizar el template (con formulario y botón eliminar)

**`views/index.ejs`:**

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
        <form method="POST" action="/" enctype="multipart/form-data">
            <div class="form-group">
                <label for="nombre">Nombre del Producto</label>
                <input type="text" class="form-control" id="nombre" name="nombre" placeholder="Ej. Zapatillas" required>
            </div>
            <div class="form-group">
                <label for="precio">Precio</label>
                <input type="number" step="0.01" class="form-control" id="precio" name="precio" placeholder="Ej. 25000" required>
            </div>
            <div class="form-group">
                <label for="imagen">Imagen</label>
                <input type="file" class="form-control" id="imagen" name="imagen" accept="image/*" required>
            </div>
            <button type="submit" class="btn-submit">Guardar en Azure</button>
        </form>
    </div>

    <hr style="border: 1px solid #ddd; max-width: 1200px; margin-bottom: 40px;">

    <div class="gallery-container">
        <% productos.forEach(function(producto) { %>
            <div class="gallery-item">
                <img src="<%= producto.imagen %>" alt="<%= producto.nombre %>" loading="lazy">
                <div class="item-info">
                    <h3><%= producto.nombre %></h3>
                    <p>$<%= producto.precio %></p>
                    <button class="btn-delete" data-id="<%= producto.id %>">Eliminar</button>
                </div>
            </div>
        <% }); %>
        <% if (productos.length === 0) { %>
            <p style="grid-column: 1 / -1; text-align: center; color: #666;">
                No hay productos. Usa el formulario para agregar uno.
            </p>
        <% } %>
    </div>

    <script>
        // Eliminar producto vía fetch DELETE
        document.querySelectorAll('.btn-delete').forEach(btn => {
            btn.addEventListener('click', async function() {
                if (!confirm('¿Eliminar este producto y su imagen de Azure?')) return;

                const id = this.dataset.id;
                try {
                    const res = await fetch(`/eliminar/${id}`, { method: 'DELETE' });
                    if (res.ok) {
                        location.reload();
                    } else {
                        alert('Error al eliminar el producto');
                    }
                } catch (err) {
                    alert('Error de conexión');
                }
            });
        });
    </script>

</body>
</html>
```

> **`enctype="multipart/form-data"`** es obligatorio cuando el formulario incluye un campo de tipo archivo. Sin este atributo, el navegador no enviaría el contenido binario de la imagen.  
> El botón "Eliminar" usa JavaScript `fetch()` con método `DELETE` para enviar la petición al servidor sin necesidad de un formulario tradicional.

### 6.6. Probar el flujo completo

```bash
node app.js
```

1. Abre `http://localhost:3000/`.
2. Completa el formulario con nombre, precio y selecciona una imagen desde tu computador.
3. Haz clic en **Guardar en Azure**.
   - La imagen se sube automáticamente a tu contenedor de Azure Blob Storage (`media`).
   - Los datos se guardan en SQLite (`productos.db`).
4. La imagen aparece en el grid con su nombre y precio.
5. Haz clic en **Eliminar** en cualquier producto.
   - El blob se borra de Azure (operación `delete()` del SDK de Azure).
   - El registro se elimina de la base de datos.
6. Puedes verificar en el portal de Azure que los blobs se crean y eliminan correctamente.

---

## Resumen del Flujo Completo (Fases 1 a 6)

```
Fase 1:  Productos estáticos con Picsum
         ├── app.js → array hardcoded de objetos
         └── index.ejs → grid simple con <img src="...">

Fase 2:  Cuenta de almacenamiento + contenedor media en Azure

Fase 3:  Credenciales (Account Name + Account Key + Connection String)

Fase 4:  SDK de Azure (@azure/storage-blob) + dotenv + blobClient.js
         └── BlobServiceClient.fromConnectionString()

Fase 5:  Reemplazo de Picsum por Azure
         ├── app.js → listBlobsFlat() + URLs directas
         └── index.ejs → muestra URLs desde Azure

Fase 6:  CRUD completo con formulario web
         ├── app.js → multer + uploadData() + delete()
         ├── blobClient.js → generateSasUrl() + SAS tokens
         ├── database.js → SQLite con tabla productos
         └── index.ejs → formulario + grid + botón eliminar (fetch DELETE)
```

**Diagrama de flujo de datos (Fase 6):**

```
Usuario (Navegador)
    │
    ├── [GET /] ────────────────── Express lee productos de SQLite
    │                                      │
    │                                      └── generateSasUrl() genera URL SAS
    │                                          para cada blob_name
    │                                      │
    │                                      └── Renderiza EJS con imágenes
    │
    ├── [POST /] (multipart/form-data) ── Multer recibe archivo en memoria
    │                                      │
    │                                      ├── blockBlobClient.uploadData() a Azure
    │                                      └── INSERT en SQLite
    │
    └── [DELETE /eliminar/X] ──────────── Express busca producto en SQLite
                                            │
                                            ├── blockBlobClient.delete() en Azure
                                            └── DELETE en SQLite
```

## Diferencias clave entre AWS S3, OCI Object Storage y Azure Blob Storage

| Concepto | AWS S3 | OCI Object Storage | Azure Blob Storage |
|----------|--------|-------------------|-------------------|
| SDK de Node.js | `@aws-sdk/client-s3` | `@aws-sdk/client-s3` | `@azure/storage-blob` |
| Endpoint | Automático según región | `https://{namespace}.compat.objectstorage.{region}.oraclecloud.com` | Automático (`*.blob.core.windows.net`) |
| Credenciales | IAM User (Access Key + Secret Key) o AWS Academy (con token) | Customer Secret Keys (Access Key + Secret Key) | Account Name + Account Key o Connection String |
| Session Token | Obligatorio en AWS Academy | No aplica | No aplica |
| Tipo de URL firmada | Pre-signed URL | Pre-signed URL (compatible S3) | SAS Token (Shared Access Signature) |
| Almacenamiento | Bucket | Bucket | Contenedor |
| Carpeta en nube | `Prefix: 'media/'` | `Prefix: 'media/'` | Blobs con prefijo en el nombre |
| Path Style | Virtual-hosted (por defecto) | Path-style (`forcePathStyle: true`) | Virtual-hosted (por defecto) |
| Comando listar | `ListObjectsV2Command` | `ListObjectsV2Command` | `containerClient.listBlobsFlat()` |
| Comando subir | `PutObjectCommand` | `PutObjectCommand` | `blockBlobClient.uploadData()` |
| Comando eliminar | `DeleteObjectCommand` | `DeleteObjectCommand` | `blockBlobClient.delete()` |
| Cliente principal | `S3Client` | `S3Client` (con `endpoint` OCI) | `BlobServiceClient` / `ContainerClient` |

Con esto tienes una aplicación Express completamente integrada con Azure Blob Storage, que parte de un catálogo estático con imágenes de Picsum y evoluciona hasta un **sistema completo de gestión de productos con almacenamiento en la nube de Microsoft Azure**, utilizando URLs SAS para mantener tu contenedor privado y seguro.