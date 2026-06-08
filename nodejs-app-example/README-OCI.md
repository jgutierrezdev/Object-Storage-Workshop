# Integración de Oracle Cloud Infrastructure (OCI) Object Storage en Express (Node.js) — Guía Paso a Paso

Esta guía te llevará desde un proyecto Express base que muestra un catálogo de productos con imágenes estáticas de **Picsum**, hasta una aplicación completamente funcional que **almacena, sirve y elimina imágenes en OCI Object Storage**, utilizando formularios web y buenas prácticas de seguridad.

El proyecto base ya está creado. Solo debes seguir las fases en orden para transformarlo.

> **Nota sobre compatibilidad:** OCI Object Storage es compatible con la API de Amazon S3. Por lo tanto, usaremos el mismo SDK `@aws-sdk/client-s3` de AWS, pero apuntando al endpoint de OCI. La configuración es casi idéntica a la de AWS, salvo por el endpoint URL y la forma de obtener las credenciales.

---

## Fase 1: Proyecto Base — Catálogo con Imágenes Estáticas

Antes de integrar OCI, revisa cómo funciona el proyecto en su estado inicial. Esta fase no requiere modificar nada; solo entender la arquitectura actual.

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

> Nota que `producto.imagen` es simplemente una cadena de texto con una URL. En las fases posteriores esto cambiará por una URL prefirmada de OCI.

### 1.3. Probar el proyecto base

Ejecuta el servidor para ver el estado inicial:

```bash
node app.js
```

Abre `http://localhost:3000/`. Verás 6 tarjetas con imágenes aleatorias de Picsum. Este es el punto de partida.

---

## Fase 2: Creación y Configuración del Bucket en OCI

Ahora crearemos el bucket en Oracle Cloud Infrastructure que almacenará las imágenes de los productos.

1. Ingresa a la consola de Oracle Cloud y abre el **menú hamburguesa** (esquina superior izquierda).

2. Navega a **Storage** → **Buckets**.

3. Selecciona el **Compartimiento** (Compartment) donde deseas crear el bucket.

4. Haz clic en **Crear bucket** y aplica la siguiente configuración:

   - **Nombre del bucket:** Asigna un nombre único (ej: `express-media-bucket`).
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

4. Asigna un nombre descriptivo, por ejemplo: `acceso-express-app`.

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

## Fase 4: Configuración de Express para OCI

Ahora conectaremos Express con el bucket de OCI mediante el SDK oficial de AWS para JavaScript (`@aws-sdk/client-s3` y `@aws-sdk/s3-request-presigner`), apuntando al endpoint compatible con S3 de OCI.

### 4.1. Instalar dependencias

```bash
npm install dotenv @aws-sdk/client-s3 @aws-sdk/s3-request-presigner
```

### 4.2. Crear archivo `.env`

En la raíz del proyecto (`nodejs-app-example/`), crea un archivo `.env` con las credenciales obtenidas en la Fase 3:

```ini
AWS_ACCESS_KEY_ID=8a7b6c5d4e3f2g1h...      # Access Key desde Customer Secret Keys
AWS_SECRET_ACCESS_KEY=7H8j9K0l1M2n3O4p...  # Secret Key desde Customer Secret Keys
AWS_STORAGE_BUCKET_NAME=express-media-bucket
AWS_S3_REGION_NAME=us-ashburn-1
OCI_ENDPOINT_URL=https://ax1b2c3d4e5f.compat.objectstorage.us-ashburn-1.oraclecloud.com
```

> **Nota:** Aunque estemos en OCI, las variables se llaman `AWS_*` porque usamos el SDK de AWS que espera esos nombres. La "magia" que desvía el tráfico a OCI es el `OCI_ENDPOINT_URL` que se pasa como `endpoint` al `S3Client`.

### 4.3. Crear el cliente S3 (apuntando a OCI)

Crea un archivo `s3Client.js` que centralice la configuración del SDK de AWS pero con el endpoint de OCI, y que será reutilizado en todas las fases siguientes:

**`s3Client.js`:**

```javascript
const { S3Client } = require('@aws-sdk/client-s3');
require('dotenv').config();

const s3Client = new S3Client({
    region: process.env.AWS_S3_REGION_NAME,
    endpoint: process.env.OCI_ENDPOINT_URL,    // <--- MAGIA: Esto desvía el tráfico a OCI
    forcePathStyle: true,                      // Necesario para OCI (usa path-style en lugar de virtual-hosted)
    credentials: {
        accessKeyId: process.env.AWS_ACCESS_KEY_ID,
        secretAccessKey: process.env.AWS_SECRET_ACCESS_KEY,
        // sessionToken: process.env.AWS_SESSION_TOKEN,  // Solo AWS Academy, NO aplica en OCI
    },
});

const BUCKET_NAME = process.env.AWS_STORAGE_BUCKET_NAME;

module.exports = { s3Client, BUCKET_NAME };
```

> **¿Qué hace `endpoint`?**  
> Normalmente, `S3Client` apunta a `s3.amazonaws.com`. Al asignarle el valor de `OCI_ENDPOINT_URL`, todo el tráfico se redirige a OCI Object Storage en lugar de AWS S3. Como OCI es compatible con la API S3, el SDK funciona sin modificaciones adicionales.
>
> **`forcePathStyle: true`** es necesario en OCI porque no soporta el formato virtual-hosted (`bucket.endpoint.com/...`) de AWS. Con `forcePathStyle`, las peticiones se envían como `endpoint.com/bucket/key/...`, que es el formato que OCI espera.

---

## Fase 5: Reemplazar Imágenes Estáticas por OCI

Ahora modificaremos el servidor para que, en lugar de usar URLs de Picsum, **lea las imágenes directamente desde el bucket de OCI** y genere URLs prefirmadas.

### 5.1. Subir imágenes al bucket manualmente

Antes de probar, necesitas tener imágenes en OCI:

1. Ve a la consola de OCI → **Storage** → **Buckets** → tu bucket → carpeta `media/`.
2. Sube manualmente 3 a 6 imágenes con nombres simples (ej: `producto1.jpg`, `producto2.jpg`, etc.).
3. Asegúrate de que tengan extensiones válidas: `.jpg`, `.jpeg`, `.png`, `.webp`, `.gif` o `.svg`.

### 5.2. Actualizar `app.js`

Reemplaza el contenido de `app.js` por el siguiente código que lista los archivos de OCI y genera URLs prefirmadas:

```javascript
const express = require('express');
const { ListObjectsV2Command } = require('@aws-sdk/client-s3');
const { getSignedUrl } = require('@aws-sdk/s3-request-presigner');
const { GetObjectCommand } = require('@aws-sdk/client-s3');
const { s3Client, BUCKET_NAME } = require('./s3Client');

const app = express();
const port = 3000;

app.set('view engine', 'ejs');

// Extensiones de archivo de imagen válidas
const VALID_EXTENSIONS = ['.jpg', '.jpeg', '.png', '.webp', '.gif', '.svg'];

app.get('/', async (req, res) => {
    try {
        // 1. Listar objetos en la carpeta "media" del bucket
        const command = new ListObjectsV2Command({
            Bucket: BUCKET_NAME,
            Prefix: 'media/',
        });
        const response = await s3Client.send(command);

        if (!response.Contents) {
            return res.render('index', { productos: [] });
        }

        // 2. Filtrar solo archivos de imagen y generar URLs prefirmadas
        const imageUrls = await Promise.all(
            response.Contents
                .filter(item => {
                    const ext = item.Key.toLowerCase().slice(item.Key.lastIndexOf('.'));
                    return VALID_EXTENSIONS.includes(ext);
                })
                .map(async (item) => {
                    const getCommand = new GetObjectCommand({
                        Bucket: BUCKET_NAME,
                        Key: item.Key,
                    });
                    // 3. Generar URL prefirmada con expiración de 1 hora
                    const url = await getSignedUrl(s3Client, getCommand, { expiresIn: 3600 });
                    return url;
                })
        );

        res.render('index', { productos: imageUrls });

    } catch (error) {
        console.error('Error al conectar con OCI:', error);
        res.render('index', { productos: [] });
    }
});

app.listen(port, () => {
    console.log(`Servidor corriendo en http://localhost:${port}`);
});
```

> **¿Qué cambió respecto a la Fase 1?**  
> - Antes: `productos` era un array de objetos con `nombre`, `precio` e `imagen`.  
> - Ahora: `productos` es un array plano de **URLs prefirmadas** generadas por OCI.  
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
        <% productos.forEach(function(imagen) { %>
            <div class="gallery-item">
                <img src="<%= imagen %>" alt="Imagen desde OCI" loading="lazy">
            </div>
        <% }); %>
        <% if (productos.length === 0) { %>
            <p style="grid-column: 1 / -1; text-align: center; color: #666;">
                No hay imágenes en el bucket. Súbelas manualmente a la carpeta <strong>media/</strong>.
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

Abre `http://localhost:3000/`. Ahora deberías ver las imágenes que subiste manualmente a OCI, servidas a través de URLs prefirmadas temporales.

---

## Fase 6: Subir y Eliminar Imágenes desde la Web (CRUD Completo)

Hasta ahora las imágenes se suben manualmente desde la consola de OCI. En esta fase final añadiremos un **formulario web** para que cualquier usuario pueda subir imágenes directamente al bucket, y un **botón para eliminarlas** cuando ya no sean necesarias.

Para ello necesitamos:
- **Multer** para procesar la subida de archivos desde el formulario.
- Una **base de datos ligera** (SQLite con `better-sqlite3`) para almacenar los metadatos de cada producto.
- Los comandos `PutObjectCommand` y `DeleteObjectCommand` del SDK de S3 (compatible con OCI).

### 6.1. Instalar dependencias adicionales

```bash
npm install multer better-sqlite3
```

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
        s3_key TEXT NOT NULL
    )
`);

module.exports = db;
```

> **`s3_key`** almacena la ruta del archivo dentro del bucket (ej: `media/zapatillas.jpg`). Esto nos permite localizar y eliminar el objeto de OCI cuando sea necesario.

### 6.3. Actualizar `app.js` (CRUD completo)

Reemplaza todo el contenido de `app.js` con el siguiente código que incluye subida, listado y eliminación de productos:

```javascript
const express = require('express');
const multer = require('multer');
const path = require('path');
const {
    PutObjectCommand,
    GetObjectCommand,
    DeleteObjectCommand,
    ListObjectsV2Command,
} = require('@aws-sdk/client-s3');
const { getSignedUrl } = require('@aws-sdk/s3-request-presigner');
const { s3Client, BUCKET_NAME } = require('./s3Client');
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
// Ruta principal: Listar productos desde BD + OCI
// ───────────────────────────────────────────────
app.get('/', async (req, res) => {
    try {
        const productos = db.prepare('SELECT * FROM productos').all();

        // Generar URL prefirmada para cada producto
        const productosConUrl = await Promise.all(
            productos.map(async (producto) => {
                const getCommand = new GetObjectCommand({
                    Bucket: BUCKET_NAME,
                    Key: producto.s3_key,
                });
                const url = await getSignedUrl(s3Client, getCommand, { expiresIn: 3600 });
                return {
                    id: producto.id,
                    nombre: producto.nombre,
                    precio: producto.precio,
                    imagen: url,
                };
            })
        );

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

        // Generar un nombre único para evitar colisiones en OCI
        const ext = path.extname(archivo.originalname);
        const s3Key = `media/${Date.now()}-${Math.random().toString(36).substring(2, 8)}${ext}`;

        // Subir archivo a OCI
        const putCommand = new PutObjectCommand({
            Bucket: BUCKET_NAME,
            Key: s3Key,
            Body: archivo.buffer,
            ContentType: archivo.mimetype,
        });
        await s3Client.send(putCommand);

        // Guardar metadatos en SQLite
        db.prepare('INSERT INTO productos (nombre, precio, s3_key) VALUES (?, ?, ?)').run(
            nombre,
            parseFloat(precio),
            s3Key
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

        // Eliminar archivo de OCI
        const deleteCommand = new DeleteObjectCommand({
            Bucket: BUCKET_NAME,
            Key: producto.s3_key,
        });
        await s3Client.send(deleteCommand);

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

> **`PutObjectCommand`** recibe el archivo desde el buffer de Multer y lo envía a OCI.  
> **`DeleteObjectCommand`** elimina el archivo del bucket usando la `s3_key` almacenada en la BD.  
> **`multer.memoryStorage()`** mantiene el archivo en RAM (no en disco), ideal para reenviarlo directamente a OCI.  
> El nombre en OCI incluye un timestamp + hash aleatorio para evitar colisiones entre archivos con el mismo nombre.

### 6.4. Actualizar el template (con formulario y botón eliminar)

**`views/index.ejs`:**

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
            <button type="submit" class="btn-submit">Guardar en OCI</button>
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
                if (!confirm('¿Eliminar este producto y su imagen de OCI?')) return;

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

### 6.5. Probar el flujo completo

```bash
node app.js
```

1. Abre `http://localhost:3000/`.
2. Completa el formulario con nombre, precio y selecciona una imagen desde tu computador.
3. Haz clic en **Guardar en OCI**.
   - La imagen se sube automáticamente a tu bucket de OCI (carpeta `media/`).
   - Los datos se guardan en SQLite (`productos.db`).
4. La imagen aparece en el grid con su nombre y precio.
5. Haz clic en **Eliminar** en cualquier producto.
   - El archivo se borra de OCI (operación `DeleteObjectCommand` a través de la API compatible con S3).
   - El registro se elimina de la base de datos.
6. Puedes verificar en la consola de OCI que los archivos se crean y eliminan correctamente.

---

## Resumen del Flujo Completo (Fases 1 a 6)

```
Fase 1:  Productos estáticos con Picsum
         ├── app.js → array hardcoded de objetos
         └── index.ejs → grid simple con <img src="...">

Fase 2:  Bucket OCI privado con carpeta media/

Fase 3:  Customer Secret Keys + Endpoint URL de OCI

Fase 4:  SDK de AWS (@aws-sdk/client-s3) + endpoint OCI + s3Client.js
         └── S3Client(region, endpoint=OCI, forcePathStyle=true)

Fase 5:  Reemplazo de Picsum por OCI
         ├── app.js → ListObjectsV2Command + getSignedUrl()
         └── index.ejs → muestra URLs prefirmadas desde OCI

Fase 6:  CRUD completo con formulario web
         ├── app.js → multer + PutObjectCommand + DeleteObjectCommand
         ├── database.js → SQLite con tabla productos
         ├── s3Client.js → cliente S3 apuntando a OCI
         └── index.ejs → formulario + grid + botón eliminar (fetch DELETE)
```

**Diagrama de flujo de datos (Fase 6):**

```
Usuario (Navegador)
    │
    ├── [GET /] ────────────────── Express lee productos de SQLite
    │                                      │
    │                                      └── getSignedUrl() genera URL prefirmada
    │                                          hacia OCI Object Storage
    │                                      │
    │                                      └── Renderiza EJS con imágenes
    │
    ├── [POST /] (multipart/form-data) ── Multer recibe archivo en memoria
    │                                      │
    │                                      ├── PutObjectCommand a OCI (sube al bucket)
    │                                      └── INSERT en SQLite
    │
    └── [DELETE /eliminar/X] ──────────── Express busca producto en SQLite
                                            │
                                            ├── DeleteObjectCommand en OCI (borra imagen)
                                            └── DELETE en SQLite
```

## Diferencias clave entre AWS S3 y OCI Object Storage

| Concepto | AWS S3 | OCI Object Storage |
|----------|--------|-------------------|
| Endpoint | Automático según región | `https://{namespace}.compat.objectstorage.{region}.oraclecloud.com` |
| Credenciales | IAM User (Access Key + Secret Key) o AWS Academy (con token) | Customer Secret Keys (Access Key + Secret Key) |
| Session Token | Obligatorio en AWS Academy | No aplica |
| Variable clave en `.env` | `AWS_S3_REGION_NAME` | `OCI_ENDPOINT_URL` |
| Configuración S3Client | `new S3Client({region, credentials})` | `new S3Client({region, endpoint, forcePathStyle: true, credentials})` |
| Path Style | Virtual-hosted (por defecto) | Path-style (obligatorio, con `forcePathStyle: true`) |

Con esto tienes una aplicación Express completamente integrada con OCI Object Storage, que parte de un catálogo estático con imágenes de Picsum y evoluciona hasta un **sistema completo de gestión de productos con almacenamiento en la nube de Oracle**, aprovechando la compatibilidad con la API S3 y sin exponer tu bucket al público.