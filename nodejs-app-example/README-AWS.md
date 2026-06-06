# Integración de Amazon S3 en Express (Node.js) — Guía Paso a Paso

Esta guía te llevará desde un proyecto Express base que muestra un catálogo de productos con imágenes estáticas de **Picsum**, hasta una aplicación completamente funcional que **almacena, sirve y elimina imágenes en Amazon S3**, utilizando formularios web y buenas prácticas de seguridad.

El proyecto base ya está creado. Solo debes seguir las fases en orden para transformarlo.

---

## Fase 1: Proyecto Base — Catálogo con Imágenes Estáticas

Antes de integrar S3, revisa cómo funciona el proyecto en su estado inicial. Esta fase no requiere modificar nada; solo entender la arquitectura actual.

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

> Nota que `producto.imagen` es simplemente una cadena de texto con una URL. En las fases posteriores esto cambiará por una URL prefirmada de S3.

### 1.3. Probar el proyecto base

Ejecuta el servidor para ver el estado inicial:

```bash
node app.js
```

Abre `http://localhost:3000/`. Verás 6 tarjetas con imágenes aleatorias de Picsum. Este es el punto de partida.

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
     > El bucket será privado. Express generará URLs prefirmadas temporales para acceder a cada imagen.
   - **Cifrado predeterminado:** SSE-S3.
   - **Clave de bucket:** Habilitar.

3. Haz clic en **Crear bucket**.

4. Entra al bucket y crea una carpeta llamada **`media`**. Allí se almacenarán las imágenes subidas desde la web.

---

## Fase 3: Configuración de Credenciales (IAM)

### 3.1. Para cuentas propias de AWS

Crea un usuario IAM con permisos mínimos para S3:

1. En IAM > **Usuarios** > **Crear usuario** (ej: `s3-express-user`).
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

4. Nombra la política (ej: `S3-Express-Policy`) y créala.
5. Asigna la política al usuario y finaliza.
6. Genera y guarda las credenciales: `AWS_ACCESS_KEY_ID` y `AWS_SECRET_ACCESS_KEY`.

### 3.2. Para AWS Academy / Learner Labs

En AWS Academy no puedes crear usuarios IAM. Usa las **credenciales temporales de la sesión**:

1. En el laboratorio, haz clic en **AWS Details** > **Show** (junto a "AWS CLI").
2. Copia las tres variables: `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` y `AWS_SESSION_TOKEN`.

> El `AWS_SESSION_TOKEN` es obligatorio en Academy. Sin él, AWS rechazará la conexión. El laboratorio expira (~3 horas), por lo que deberás renovar las credenciales periódicamente.

---

## Fase 4: Configuración de Express para S3

Ahora conectaremos Express con el bucket S3 mediante el SDK oficial de AWS para JavaScript (`@aws-sdk/client-s3` y `@aws-sdk/s3-request-presigner`).

### 4.1. Instalar dependencias

```bash
npm install dotenv @aws-sdk/client-s3 @aws-sdk/s3-request-presigner
```

### 4.2. Crear archivo `.env`

En la raíz del proyecto (`nodejs-app-example/`), crea un archivo `.env` con las credenciales obtenidas en la Fase 3:

```ini
AWS_ACCESS_KEY_ID=AKIA...
AWS_SECRET_ACCESS_KEY=8UdXkbsDT/nbi...
AWS_SESSION_TOKEN=IQoJb3...   # Solo para AWS Academy
AWS_STORAGE_BUCKET_NAME=mi-bucket-123456789012-us-east-1
AWS_S3_REGION_NAME=us-east-1
```

### 4.3. Crear el cliente S3

Crea un archivo `s3Client.js` que centralice la configuración del SDK de AWS y que será reutilizado en todas las fases siguientes:

**`s3Client.js`:**

```javascript
const { S3Client } = require('@aws-sdk/client-s3');
require('dotenv').config();

const s3Client = new S3Client({
    region: process.env.AWS_S3_REGION_NAME,
    credentials: {
        accessKeyId: process.env.AWS_ACCESS_KEY_ID,
        secretAccessKey: process.env.AWS_SECRET_ACCESS_KEY,
        sessionToken: process.env.AWS_SESSION_TOKEN,  // Solo para AWS Academy
    },
});

const BUCKET_NAME = process.env.AWS_STORAGE_BUCKET_NAME;

module.exports = { s3Client, BUCKET_NAME };
```

> **¿Qué hace `S3Client`?**  
> Es el objeto principal del SDK de AWS v3 para interactuar con S3. Se encarga de firmar las peticiones, manejar la autenticación y comunicarse con la API de AWS. Todas las operaciones (listar, subir, descargar, eliminar) se realizan a través de este cliente.

---

## Fase 5: Reemplazar Imágenes Estáticas por S3

Ahora modificaremos el servidor para que, en lugar de usar URLs de Picsum, **lea las imágenes directamente desde el bucket S3** y genere URLs prefirmadas.

### 5.1. Subir imágenes al bucket manualmente

Antes de probar, necesitas tener imágenes en S3:

1. Ve a la consola de AWS S3 → tu bucket → carpeta `media/`.
2. Sube manualmente 3 a 6 imágenes con nombres simples (ej: `producto1.jpg`, `producto2.jpg`, etc.).
3. Asegúrate de que tengan extensiones válidas: `.jpg`, `.jpeg`, `.png`, `.webp`, `.gif` o `.svg`.

### 5.2. Actualizar `app.js`

Reemplaza el contenido de `app.js` por el siguiente código que lista los archivos de S3 y genera URLs prefirmadas:

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
        console.error('Error al conectar con S3:', error);
        res.render('index', { productos: [] });
    }
});

app.listen(port, () => {
    console.log(`Servidor corriendo en http://localhost:${port}`);
});
```

> **¿Qué cambió respecto a la Fase 1?**  
> - Antes: `productos` era un array de objetos con `nombre`, `precio` e `imagen`.  
> - Ahora: `productos` es un array plano de **URLs prefirmadas** generadas por S3.  
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
        <% productos.forEach(function(imagen) { %>
            <div class="gallery-item">
                <img src="<%= imagen %>" alt="Imagen desde S3" loading="lazy">
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

Abre `http://localhost:3000/`. Ahora deberías ver las imágenes que subiste manualmente a S3, servidas a través de URLs prefirmadas temporales.

---

## Fase 6: Subir y Eliminar Imágenes desde la Web (CRUD Completo)

Hasta ahora las imágenes se suben manualmente desde la consola de AWS. En esta fase final añadiremos un **formulario web** para que cualquier usuario pueda subir imágenes directamente al bucket, y un **botón para eliminarlas** cuando ya no sean necesarias.

Para ello necesitamos:
- **Multer** para procesar la subida de archivos desde el formulario.
- Una **base de datos ligera** (SQLite con `better-sqlite3`) para almacenar los metadatos de cada producto.
- Los comandos `PutObjectCommand` y `DeleteObjectCommand` del SDK de S3.

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

> **`s3_key`** almacena la ruta del archivo dentro del bucket (ej: `media/zapatillas.jpg`). Esto nos permite localizar y eliminar el objeto de S3 cuando sea necesario.

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
    limits: { fileSize: 5 * 1024 * 1024 }, // 5 MB máximo
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
// Ruta principal: Listar productos desde BD + S3
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

        // Generar un nombre único para evitar colisiones en S3
        const ext = path.extname(archivo.originalname);
        const s3Key = `media/${Date.now()}-${Math.random().toString(36).substring(2, 8)}${ext}`;

        // Subir archivo a S3
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

        // Eliminar archivo de S3
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

> **`PutObjectCommand`** recibe el archivo desde el buffer de Multer y lo envía a S3.  
> **`DeleteObjectCommand`** elimina el archivo del bucket usando la `s3_key` almacenada en la BD.  
> **`multer.memoryStorage()`** mantiene el archivo en RAM (no en disco), ideal para reenviarlo directamente a S3.  
> El nombre en S3 incluye un timestamp + hash aleatorio para evitar colisiones entre archivos con el mismo nombre.

### 6.4. Actualizar el template (con formulario y botón eliminar)

**`views/index.ejs`:**

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
            <button type="submit" class="btn-submit">Guardar en S3</button>
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
                if (!confirm('¿Eliminar este producto y su imagen de S3?')) return;

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
3. Haz clic en **Guardar en S3**.
   - La imagen se sube automáticamente a tu bucket S3 (carpeta `media/`).
   - Los datos se guardan en SQLite (`productos.db`).
4. La imagen aparece en el grid con su nombre y precio.
5. Haz clic en **Eliminar** en cualquier producto.
   - El archivo se borra de S3 (operación `DeleteObjectCommand`).
   - El registro se elimina de la base de datos.
6. Puedes verificar en la consola de AWS S3 que los archivos se crean y eliminan correctamente.

---

## Resumen del Flujo Completo (Fases 1 a 6)

```
Fase 1:  Productos estáticos con Picsum
         ├── app.js → array hardcoded de objetos
         └── index.ejs → grid simple con <img src="...">

Fase 2:  Bucket S3 privado con carpeta media/

Fase 3:  Credenciales IAM (usuario propio o AWS Academy)

Fase 4:  SDK de AWS (@aws-sdk/client-s3) + dotenv + s3Client.js
         └── S3Client(region, credentials)

Fase 5:  Reemplazo de Picsum por S3
         ├── app.js → ListObjectsV2Command + getSignedUrl()
         └── index.ejs → muestra URLs prefirmadas desde S3

Fase 6:  CRUD completo con formulario web
         ├── app.js → multer + PutObjectCommand + DeleteObjectCommand
         ├── database.js → SQLite con tabla productos
         ├── s3Client.js → cliente S3 centralizado
         └── index.ejs → formulario + grid + botón eliminar (fetch DELETE)
```

**Diagrama de flujo de datos (Fase 6):**

```
Usuario (Navegador)
    │
    ├── [GET /] ────────────────── Express lee productos de SQLite
    │                                      │
    │                                      └── getSignedUrl() genera URL prefirmada
    │                                          para cada s3_key
    │                                      │
    │                                      └── Renderiza EJS con imágenes
    │
    ├── [POST /] (multipart/form-data) ── Multer recibe archivo en memoria
    │                                      │
    │                                      ├── PutObjectCommand a S3 (sube al bucket)
    │                                      └── INSERT en SQLite
    │
    └── [DELETE /eliminar/X] ──────────── Express busca producto en SQLite
                                           │
                                           ├── DeleteObjectCommand en S3 (borra imagen)
                                           └── DELETE en SQLite
```

Con esto tienes una aplicación Express completamente integrada con Amazon S3, que parte de un catálogo estático con imágenes de Picsum y evoluciona hasta un **sistema completo de gestión de productos con almacenamiento en la nube**, todo sin exponer tu bucket al público y utilizando URLs prefirmadas para cada imagen.