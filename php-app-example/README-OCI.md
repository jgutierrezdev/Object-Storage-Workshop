# Integración de Oracle Cloud Infrastructure (OCI) Object Storage en PHP — Guía Paso a Paso

Esta guía te llevará desde un proyecto PHP base que muestra un catálogo de productos con imágenes estáticas de **Picsum**, hasta una aplicación completamente funcional que **almacena, sirve y elimina imágenes en OCI Object Storage**, utilizando formularios web y buenas prácticas de seguridad.

El proyecto base ya está creado. Solo debes seguir las fases en orden para transformarlo.

> **Nota sobre compatibilidad:** OCI Object Storage es compatible con la API de Amazon S3. Por lo tanto, usaremos el mismo SDK `aws/aws-sdk-php` de AWS, pero apuntando al endpoint de OCI. La configuración es casi idéntica a la de AWS, salvo por el endpoint URL y la forma de obtener las credenciales.

---

## Fase 1: Proyecto Base — Catálogo con Imágenes Estáticas

Antes de integrar OCI, revisa cómo funciona el proyecto en su estado inicial. Esta fase no requiere modificar nada; solo entender la arquitectura actual.

### 1.1. Script actual (`index.php`)

El archivo `index.php` contiene una lista fija (hardcoded) de 6 productos con imágenes obtenidas del servicio gratuito **Picsum**, todo en un solo archivo que actúa como controlador y vista:

```php
<?php
$productos = [
    ['nombre' => 'Zapatillas Urbanas', 'precio' => 45000, 'imagen' => 'https://picsum.photos/id/1018/800/600'],
    ['nombre' => 'Mochila Ejecutiva',  'precio' => 32000, 'imagen' => 'https://picsum.photos/id/1015/800/600'],
    ['nombre' => 'Auriculares Pro',    'precio' => 28000, 'imagen' => 'https://picsum.photos/id/1019/800/600'],
    ['nombre' => 'Reloj Deportivo',    'precio' => 55000, 'imagen' => 'https://picsum.photos/id/1016/800/600'],
    ['nombre' => 'Cámara Digital',     'precio' => 89000, 'imagen' => 'https://picsum.photos/id/1020/800/600'],
    ['nombre' => 'Lámpara LED',        'precio' => 15000, 'imagen' => 'https://picsum.photos/id/1021/800/600'],
];
?>
```

Cada producto es un arreglo asociativo con tres claves: `nombre`, `precio` e `imagen` (URL externa).

### 1.2. Template (embebido en `index.php`)

El HTML itera sobre `$productos` y genera un grid responsivo con tarjetas que muestran la imagen, el nombre y el precio:

```php
<div class="gallery-container">
    <?php foreach ($productos as $producto): ?>
        <div class="gallery-item">
            <img src="<?= htmlspecialchars($producto['imagen']) ?>" alt="<?= htmlspecialchars($producto['nombre']) ?>" loading="lazy">
            <div class="item-info">
                <h3><?= htmlspecialchars($producto['nombre']) ?></h3>
                <p>$<?= htmlspecialchars($producto['precio']) ?></p>
            </div>
        </div>
    <?php endforeach; ?>
</div>
```

> Nota que `$producto['imagen']` es simplemente una cadena de texto con una URL. En las fases posteriores esto cambiará por una URL prefirmada de OCI.

### 1.3. Probar el proyecto base

Inicia el servidor embebido de PHP:

```bash
cd php-app-example
php -S localhost:8000
```

Abre `http://localhost:8000/`. Verás 6 tarjetas con imágenes aleatorias de Picsum. Este es el punto de partida.

---

## Fase 2: Creación y Configuración del Bucket en OCI

Ahora crearemos el bucket en Oracle Cloud Infrastructure que almacenará las imágenes de los productos.

1. Ingresa a la consola de Oracle Cloud y abre el **menú hamburguesa** (esquina superior izquierda).

2. Navega a **Storage** → **Buckets**.

3. Selecciona el **Compartimiento** (Compartment) donde deseas crear el bucket.

4. Haz clic en **Crear bucket** y aplica la siguiente configuración:

   - **Nombre del bucket:** Asigna un nombre único (ej: `php-media-bucket`).
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
   - **Región:** La región donde creaste el bucket (ej: `sa-santiago-1`).

7. Crea una carpeta llamada **`media`** dentro del bucket. Allí se almacenarán las imágenes subidas desde la web.

---

## Fase 3: Configuración de Credenciales (Customer Secret Keys)

OCI no usa usuarios IAM como AWS. En su lugar, utilizaremos **Customer Secret Keys**, que son credenciales compatibles con la API S3.

### 3.1. Generar las credenciales

1. En la consola de OCI, haz clic en tu **perfil** (esquina superior derecha) → **User Settings** (o **Mi perfil**).

2. En el menú lateral izquierdo, ve a **Customer Secret Keys** (bajo la sección "Resources").

3. Haz clic en **Generate Secret Key**.

4. Asigna un nombre descriptivo, por ejemplo: `acceso-php-app`.

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
- `{region}` → La región donde creaste el bucket (ej: `sa-santiago-1`).

Ejemplo:
```
https://ax1b2c3d4e5f.compat.objectstorage.sa-santiago-1.oraclecloud.com
```

Este endpoint se usará en el `.env` como `OCI_ENDPOINT_URL`.

> **¿Cómo encuentro mi namespace?**  
> Entra a tu bucket en la consola de OCI. En la página de detalles, busca el campo **"Namespace"** bajo "Bucket Information". No lo confundas con el nombre del bucket.

---

## Fase 4: Configuración de PHP para OCI

Ahora conectaremos PHP con el bucket de OCI mediante el SDK oficial de AWS para PHP, apuntando al endpoint compatible con S3 de OCI.

### 4.1. Inicializar Composer e instalar dependencias

```bash
cd php-app-example
composer init --name="app/php-catalogo" --require="php >=8.0" -n
composer require aws/aws-sdk-php vlucas/phpdotenv
```

### 4.2. Crear archivo `.env`

En la raíz del proyecto (`php-app-example/`), crea o actualiza el archivo `.env` con las credenciales obtenidas en la Fase 3:

```ini
AWS_ACCESS_KEY_ID=8a7b6c5d4e3f2g1h...      # Access Key desde Customer Secret Keys
AWS_SECRET_ACCESS_KEY=7H8j9K0l1M2n3O4p...  # Secret Key desde Customer Secret Keys
AWS_STORAGE_BUCKET_NAME=php-media-bucket
AWS_S3_REGION_NAME=sa-santiago-1
OCI_ENDPOINT_URL=https://ax1b2c3d4e5f.compat.objectstorage.sa-santiago-1.oraclecloud.com
```

> **Nota:** Aunque estemos en OCI, las variables se llaman `AWS_*` porque usamos el SDK de AWS que espera esos nombres. La "magia" que desvía el tráfico a OCI es el `OCI_ENDPOINT_URL` que se pasa como `endpoint` al `S3Client`.

### 4.3. Crear el cliente S3 (apuntando a OCI)

Crea un archivo `s3Client.php` que centralice la configuración del SDK de AWS pero con el endpoint de OCI, y que será reutilizado en todas las fases siguientes:

**`s3Client.php`:**

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use Aws\S3\S3Client;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$s3Client = new S3Client([
    'region'      => $_ENV['AWS_S3_REGION_NAME'],
    'version'     => 'latest',
    'endpoint'    => $_ENV['OCI_ENDPOINT_URL'],     // <--- MAGIA: Esto desvía el tráfico a OCI
    'use_path_style_endpoint' => true,               // Necesario para OCI (path-style en lugar de virtual-hosted)
    'credentials' => [
        'key'    => $_ENV['AWS_ACCESS_KEY_ID'],
        'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'],
        // 'token'  => $_ENV['AWS_SESSION_TOKEN'] ?? null,  // Solo AWS Academy, NO aplica en OCI
    ],
]);

$bucketName = $_ENV['AWS_STORAGE_BUCKET_NAME'];
```

> **¿Qué hace `endpoint`?**  
> Normalmente, `S3Client` apunta a `s3.amazonaws.com`. Al asignarle el valor de `OCI_ENDPOINT_URL`, todo el tráfico se redirige a OCI Object Storage en lugar de AWS S3. Como OCI es compatible con la API S3, el SDK funciona sin modificaciones adicionales.
>
> **`use_path_style_endpoint => true`** es necesario en OCI porque no soporta el formato virtual-hosted (`bucket.endpoint.com/...`) de AWS. Con esta opción, las peticiones se envían como `endpoint.com/bucket/key/...`, que es el formato que OCI espera.

---

## Fase 5: Reemplazar Imágenes Estáticas por OCI

Ahora modificaremos el script para que, en lugar de usar URLs de Picsum, **lea las imágenes directamente desde el bucket de OCI** y genere URLs prefirmadas.

### 5.1. Subir imágenes al bucket manualmente

Antes de probar, necesitas tener imágenes en OCI:

1. Ve a la consola de OCI → **Storage** → **Buckets** → tu bucket → carpeta `media/`.
2. Sube manualmente 3 a 6 imágenes con nombres simples (ej: `producto1.jpg`, `producto2.jpg`, etc.).
3. Asegúrate de que tengan extensiones válidas: `.jpg`, `.jpeg`, `.png`, `.webp`, `.gif` o `.svg`.

### 5.2. Actualizar `index.php`

Reemplaza el contenido de `index.php` por el siguiente código que lista los archivos de OCI y genera URLs prefirmadas:

```php
<?php
require_once __DIR__ . '/s3Client.php';

use Aws\S3\S3Client;

// Extensiones de archivo de imagen válidas
$validExtensions = ['.jpg', '.jpeg', '.png', '.webp', '.gif', '.svg'];

$productos = [];

try {
    // 1. Listar objetos en la carpeta "media" del bucket
    $objects = $s3Client->listObjectsV2([
        'Bucket' => $bucketName,
        'Prefix' => 'media/',
    ]);

    if (isset($objects['Contents'])) {
        foreach ($objects['Contents'] as $object) {
            $key = $object['Key'];
            $ext = strtolower(substr($key, strrpos($key, '.')));

            // 2. Filtrar solo archivos de imagen
            if (in_array($ext, $validExtensions)) {
                // 3. Generar URL prefirmada con expiración de 1 hora
                $cmd = $s3Client->getCommand('GetObject', [
                    'Bucket' => $bucketName,
                    'Key'    => $key,
                ]);
                $request = $s3Client->createPresignedRequest($cmd, '+1 hour');
                $productos[] = (string) $request->getUri();
            }
        }
    }
} catch (Exception $e) {
    error_log('Error al conectar con OCI: ' . $e->getMessage());
}
?>
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
        <?php foreach ($productos as $imagen): ?>
            <div class="gallery-item">
                <img src="<?= htmlspecialchars($imagen) ?>" alt="Imagen desde OCI" loading="lazy">
            </div>
        <?php endforeach; ?>
        <?php if (empty($productos)): ?>
            <p style="grid-column: 1 / -1; text-align: center; color: #666;">
                No hay imágenes en el bucket. Súbelas manualmente a la carpeta <strong>media/</strong>.
            </p>
        <?php endif; ?>
    </div>
</body>
</html>
```

> **`$s3Client->createPresignedRequest()`** genera una URL con firma válida por un tiempo limitado, permitiendo acceder a objetos de un bucket privado sin exponer las credenciales.  
> **`listObjectsV2()`** obtiene la lista de objetos bajo el prefijo `media/`.

### 5.3. Probar

```bash
cd php-app-example
php -S localhost:8000
```

Abre `http://localhost:8000/`. Ahora deberías ver las imágenes que subiste manualmente a OCI, servidas a través de URLs prefirmadas temporales.


>Si llega a ocurrir un error al conectar al S3 como "SSL certificate OpenSSL verify result: unable to get local issuer certificate" tenemos que descargar certificados SSL de `https://curl.se/docs/caextract.html` y buscar nuestra carpeta raiz de php dentro del archivo php.ini y modificar estos datos

```ini
curl.cainfo = "C:\php\cacert.pem" // la ruta del certificado descargado y nombre
openssl.cafile = "C:\php\cacert.pem"
```

---

## Fase 6: Subir y Eliminar Imágenes desde la Web (CRUD Completo)

Hasta ahora las imágenes se suben manualmente desde la consola de OCI. En esta fase final añadiremos un **formulario web** para que cualquier usuario pueda subir imágenes directamente al bucket, y un **botón para eliminarlas** cuando ya no sean necesarias.

Para ello necesitamos:
- **Dos archivos PHP** para separar responsabilidades: `index.php` (vista + listar) y `acciones.php` (subir y eliminar vía AJAX).
- Una **base de datos SQLite** (PDO) para almacenar los metadatos de cada producto.
- Los comandos `PutObject` y `DeleteObject` del SDK de S3 (compatible con OCI).

### 6.1. Configurar la base de datos

Crea un archivo `database.php` que inicialice SQLite con la tabla `productos`:

**`database.php`:**

```php
<?php
$dbPath = __DIR__ . '/productos.db';

$pdo = new PDO("sqlite:$dbPath");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Crear la tabla si no existe
$pdo->exec("
    CREATE TABLE IF NOT EXISTS productos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nombre TEXT NOT NULL,
        precio REAL NOT NULL,
        s3_key TEXT NOT NULL
    )
");

return $pdo;
```

> **`s3_key`** almacena la ruta del archivo dentro del bucket (ej: `media/zapatillas.jpg`). Esto nos permite localizar y eliminar el objeto de OCI cuando sea necesario.

### 6.2. Crear el endpoint de acciones (subir / eliminar)

**`acciones.php`:**

```php
<?php
require_once __DIR__ . '/s3Client.php';

// Respuesta en JSON
header('Content-Type: application/json');

$pdo = require __DIR__ . '/database.php';
$method = $_SERVER['REQUEST_METHOD'];

try {
    // ───────────────────────────────────────────────
    // POST: Subir un nuevo producto
    // ───────────────────────────────────────────────
    if ($method === 'POST') {
        $nombre = $_POST['nombre'] ?? '';
        $precio = $_POST['precio'] ?? '';
        $archivo = $_FILES['imagen'] ?? null;

        if (!$nombre || !$precio) {
            http_response_code(400);
            echo json_encode(['error' => 'Falta el nombre o el precio']);
            exit;
        }

        if (!$archivo || $archivo['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            // UPLOAD_ERR_INI_SIZE (1) significa que pesa más de lo que permite php.ini
            // UPLOAD_ERR_NO_TMP_DIR (6) significa que falta la carpeta temporal
            echo json_encode(['error' => 'Error al procesar la imagen en PHP. Código de error: ' . ($archivo['error'] ?? 'N/A')]);
            exit;
        }

        // Validar extensión
        $validExtensions = ['.jpg', '.jpeg', '.png', '.webp', '.gif', '.svg'];
        $ext = strtolower(substr($archivo['name'], strrpos($archivo['name'], '.')));
        if (!in_array($ext, $validExtensions)) {
            http_response_code(400);
            echo json_encode(['error' => 'Extensión no válida']);
            exit;
        }

        // Generar un nombre único para evitar colisiones en OCI
        $s3Key = 'media/' . time() . '-' . substr(uniqid(), -6) . $ext;

        // Subir archivo a OCI
        $s3Client->putObject([
            'Bucket'      => $bucketName,
            'Key'         => $s3Key,
            'SourceFile'  => $archivo['tmp_name'],
            'ContentType' => $archivo['type'],
        ]);

        // Guardar metadatos en SQLite
        $stmt = $pdo->prepare('INSERT INTO productos (nombre, precio, s3_key) VALUES (?, ?, ?)');
        $stmt->execute([$nombre, (float) $precio, $s3Key]);

        echo json_encode(['success' => true, 'redirect' => 'index.php']);
        exit;
    }

    // ───────────────────────────────────────────────
    // DELETE: Eliminar un producto
    // ───────────────────────────────────────────────
    if ($method === 'DELETE') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $input['id'] ?? null;

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID no proporcionado']);
            exit;
        }

        // Buscar producto en BD
        $stmt = $pdo->prepare('SELECT * FROM productos WHERE id = ?');
        $stmt->execute([$id]);
        $producto = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$producto) {
            http_response_code(404);
            echo json_encode(['error' => 'Producto no encontrado']);
            exit;
        }

        // Eliminar archivo de OCI
        $s3Client->deleteObject([
            'Bucket' => $bucketName,
            'Key'    => $producto['s3_key'],
        ]);

        // Eliminar registro de BD
        $stmt = $pdo->prepare('DELETE FROM productos WHERE id = ?');
        $stmt->execute([$id]);

        echo json_encode(['success' => true]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
```

> **`putObject()`** recibe el archivo desde el formulario y lo envía a OCI.  
> **`deleteObject()`** elimina el archivo del bucket usando la `s3_key` almacenada en la BD.  
> El nombre en OCI incluye un timestamp + hash aleatorio para evitar colisiones entre archivos con el mismo nombre.

### 6.3. Actualizar `index.php` (con formulario y listado desde BD)

Reemplaza todo el contenido de `index.php` con el siguiente código que incluye subida, listado y eliminación de productos:

```php
<?php
require_once __DIR__ . '/s3Client.php';

$pdo = require __DIR__ . '/database.php';

$productos = [];

try {
    // Leer productos desde SQLite
    $stmt = $pdo->query('SELECT * FROM productos ORDER BY id DESC');
    $productosDb = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Generar URL prefirmada para cada producto
    foreach ($productosDb as $producto) {
        $cmd = $s3Client->getCommand('GetObject', [
            'Bucket' => $bucketName,
            'Key'    => $producto['s3_key'],
        ]);
        $request = $s3Client->createPresignedRequest($cmd, '+1 hour');
        $productos[] = [
            'id'     => $producto['id'],
            'nombre' => $producto['nombre'],
            'precio' => $producto['precio'],
            'imagen' => (string) $request->getUri(),
        ];
    }
} catch (Exception $e) {
    error_log('Error al listar productos: ' . $e->getMessage());
}
?>
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
        <form id="formProducto" enctype="multipart/form-data">
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
        <?php foreach ($productos as $producto): ?>
            <div class="gallery-item">
                <img src="<?= htmlspecialchars($producto['imagen']) ?>" alt="<?= htmlspecialchars($producto['nombre']) ?>" loading="lazy">
                <div class="item-info">
                    <h3><?= htmlspecialchars($producto['nombre']) ?></h3>
                    <p>$<?= htmlspecialchars($producto['precio']) ?></p>
                    <button class="btn-delete" data-id="<?= $producto['id'] ?>">Eliminar</button>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($productos)): ?>
            <p style="grid-column: 1 / -1; text-align: center; color: #666;">
                No hay productos. Usa el formulario para agregar uno.
            </p>
        <?php endif; ?>
    </div>

    <script>
        // Subir producto vía fetch POST a acciones.php
        document.getElementById('formProducto').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            try {
                const res = await fetch('acciones.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Desconocido'));
                }
            } catch (err) {
                alert('Error de conexión');
            }
        });

        // Eliminar producto vía fetch DELETE a acciones.php
        document.querySelectorAll('.btn-delete').forEach(btn => {
            btn.addEventListener('click', async function() {
                if (!confirm('¿Eliminar este producto y su imagen de OCI?')) return;
                const id = this.dataset.id;
                try {
                    const res = await fetch('acciones.php', {
                        method: 'DELETE',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: parseInt(id) }),
                    });
                    const data = await res.json();
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + (data.error || 'Desconocido'));
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
> La subida usa `fetch()` con `POST` y `FormData` para enviar el archivo a `acciones.php`.

### 6.4. Probar el flujo completo

```bash
cd php-app-example
php -S localhost:8000
```

1. Abre `http://localhost:8000/index.php`.
2. Completa el formulario con nombre, precio y selecciona una imagen desde tu computador.
3. Haz clic en **Guardar en OCI**.
   - La imagen se sube automáticamente a tu bucket de OCI (carpeta `media/`).
   - Los datos se guardan en SQLite (`productos.db`).
4. La imagen aparece en el grid con su nombre y precio.
5. Haz clic en **Eliminar** en cualquier producto.
   - El archivo se borra de OCI (operación `deleteObject` a través de la API compatible con S3).
   - El registro se elimina de la base de datos.
6. Puedes verificar en la consola de OCI que los archivos se crean y eliminan correctamente.

---

## Resumen del Flujo Completo (Fases 1 a 6)

```
Fase 1:  Productos estáticos con Picsum
         └── index.php → arreglo hardcoded + HTML embebido

Fase 2:  Bucket OCI privado con carpeta media/

Fase 3:  Customer Secret Keys + Endpoint URL de OCI

Fase 4:  SDK de AWS (aws/aws-sdk-php) + endpoint OCI + s3Client.php
         └── S3Client(region, endpoint=OCI, use_path_style_endpoint=true)

Fase 5:  Reemplazo de Picsum por OCI
         ├── index.php → listObjectsV2() + createPresignedRequest()
         └── index.php → muestra URLs prefirmadas desde OCI

Fase 6:  CRUD completo con formulario web
         ├── index.php   → formulario + grid (listado desde BD)
         ├── acciones.php → POST (putObject) + DELETE (deleteObject)
         ├── database.php → SQLite con tabla productos
         ├── s3Client.php → cliente S3 apuntando a OCI
         └── index.php   → JavaScript fetch() para subir y eliminar
```

**Diagrama de flujo de datos (Fase 6):**

```
Usuario (Navegador)
    │
    ├── [GET /index.php] ───── PHP lee productos de SQLite
    │                                   │
    │                                   └── createPresignedRequest() genera URL prefirmada
    │                                       hacia OCI Object Storage
    │                                   │
    │                                   └── Renderiza HTML con imágenes
    │
    ├── [POST /acciones.php] ── PHP recibe archivo del formulario
    │                                   │
    │                                   ├── putObject() a OCI (sube al bucket)
    │                                   └── INSERT en SQLite
    │
    └── [DELETE /acciones.php] ─ PHP busca producto en SQLite
                                        │
                                        ├── deleteObject() en OCI (borra imagen)
                                        └── DELETE en SQLite
```

## Diferencias clave entre AWS S3 y OCI Object Storage

| Concepto | AWS S3 | OCI Object Storage |
|----------|--------|-------------------|
| Endpoint | Automático según región | `https://{namespace}.compat.objectstorage.{region}.oraclecloud.com` |
| Credenciales | IAM User (Access Key + Secret Key) o AWS Academy (con token) | Customer Secret Keys (Access Key + Secret Key) |
| Session Token | Obligatorio en AWS Academy | No aplica |
| Variable clave en `.env` | `AWS_S3_REGION_NAME` | `OCI_ENDPOINT_URL` |
| Configuración S3Client | `new S3Client({region, version, credentials})` | `new S3Client({region, endpoint, use_path_style_endpoint: true, credentials})` |
| Path Style | Virtual-hosted (por defecto) | Path-style (obligatorio, con `use_path_style_endpoint => true`) |

Con esto tienes una aplicación PHP completamente integrada con OCI Object Storage, que parte de un catálogo estático con imágenes de Picsum y evoluciona hasta un **sistema completo de gestión de productos con almacenamiento en la nube de Oracle**, aprovechando la compatibilidad con la API S3 y sin exponer tu bucket al público.