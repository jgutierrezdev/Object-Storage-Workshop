# Integración de Amazon S3 en PHP — Guía Paso a Paso

Esta guía te llevará desde un proyecto PHP base que muestra un catálogo de productos con imágenes estáticas de **Picsum**, hasta una aplicación completamente funcional que **almacena, sirve y elimina imágenes en Amazon S3**, utilizando formularios web y buenas prácticas de seguridad.

El proyecto base ya está creado. Solo debes seguir las fases en orden para transformarlo.

---

## Fase 1: Proyecto Base — Catálogo con Imágenes Estáticas

Antes de integrar S3, revisa cómo funciona el proyecto en su estado inicial. Esta fase no requiere modificar nada; solo entender la arquitectura actual.

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

> Nota que `$producto['imagen']` es simplemente una cadena de texto con una URL. En las fases posteriores esto cambiará por una URL prefirmada de S3.

### 1.3. Probar el proyecto base

Inicia el servidor embebido de PHP:

```bash
cd php-app-example
php -S localhost:8000
```

Abre `http://localhost:8000/`. Verás 6 tarjetas con imágenes aleatorias de Picsum. Este es el punto de partida.

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
     > El bucket será privado. PHP generará URLs prefirmadas temporales para acceder a cada imagen.
   - **Cifrado predeterminado:** SSE-S3.
   - **Clave de bucket:** Habilitar.

3. Haz clic en **Crear bucket**.

4. Entra al bucket y crea una carpeta llamada **`media`**. Allí se almacenarán las imágenes subidas desde la web.

---

## Fase 3: Configuración de Credenciales (IAM)

### 3.1. Para cuentas propias de AWS

Crea un usuario IAM con permisos mínimos para S3:

1. En IAM > **Usuarios** > **Crear usuario** (ej: `s3-php-user`).
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

4. Nombra la política (ej: `S3-PHP-Policy`) y créala.
5. Asigna la política al usuario y finaliza.
6. Genera y guarda las credenciales: `AWS_ACCESS_KEY_ID` y `AWS_SECRET_ACCESS_KEY`.

### 3.2. Para AWS Academy / Learner Labs

En AWS Academy no puedes crear usuarios IAM. Usa las **credenciales temporales de la sesión**:

1. En el laboratorio, haz clic en **AWS Details** > **Show** (junto a "AWS CLI").
2. Copia las tres variables: `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` y `AWS_SESSION_TOKEN`.

> El `AWS_SESSION_TOKEN` es obligatorio en Academy. Sin él, AWS rechazará la conexión. El laboratorio expira (~3 horas), por lo que deberás renovar las credenciales periódicamente.

---

## Fase 4: Configuración de PHP para S3

Ahora conectaremos PHP con el bucket S3 mediante el SDK oficial de AWS para PHP.

### 4.1. Inicializar Composer e instalar dependencias

```bash
cd php-app-example
composer init --name="app/s3-catalogo" --require="php >=8.0" -n
composer require aws/aws-sdk-php vlucas/phpdotenv
```

### 4.2. Crear archivo `.env`

En la raíz del proyecto (`php-app-example/`), crea o actualiza el archivo `.env` con las credenciales obtenidas en la Fase 3:

```ini
AWS_ACCESS_KEY_ID=AKIA...
AWS_SECRET_ACCESS_KEY=8UdXkbsDT/nbi...
AWS_SESSION_TOKEN=IQoJb3...   # Solo para AWS Academy
AWS_STORAGE_BUCKET_NAME=mi-bucket-123456789012-us-east-1
AWS_S3_REGION_NAME=us-east-1
```

### 4.3. Crear el cliente S3

Crea un archivo `s3Client.php` que centralice la configuración del SDK de AWS y que será reutilizado en todas las fases siguientes:

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
    'credentials' => [
        'key'    => $_ENV['AWS_ACCESS_KEY_ID'],
        'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'],
        'token'  => $_ENV['AWS_SESSION_TOKEN'] ?? null,  // Solo para AWS Academy
    ],
]);

$bucketName = $_ENV['AWS_STORAGE_BUCKET_NAME'];
```

> **¿Qué hace `S3Client`?**  
> Es el objeto principal del SDK de AWS para PHP. Se encarga de firmar las peticiones, manejar la autenticación y comunicarse con la API de AWS. Todas las operaciones (listar, subir, descargar, eliminar) se realizan a través de este cliente.

---

## Fase 5: Reemplazar Imágenes Estáticas por S3

Ahora modificaremos el script para que, en lugar de usar URLs de Picsum, **lea las imágenes directamente desde el bucket S3** y genere URLs prefirmadas.

### 5.1. Subir imágenes al bucket manualmente

Antes de probar, necesitas tener imágenes en S3:

1. Ve a la consola de AWS S3 → tu bucket → carpeta `media/`.
2. Sube manualmente 3 a 6 imágenes con nombres simples (ej: `producto1.jpg`, `producto2.jpg`, etc.).
3. Asegúrate de que tengan extensiones válidas: `.jpg`, `.jpeg`, `.png`, `.webp`, `.gif` o `.svg`.

### 5.2. Actualizar `index.php`

Reemplaza el contenido de `index.php` por el siguiente código que lista los archivos de S3 y genera URLs prefirmadas:

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
    error_log('Error al conectar con S3: ' . $e->getMessage());
}
?>
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
        <?php foreach ($productos as $imagen): ?>
            <div class="gallery-item">
                <img src="<?= htmlspecialchars($imagen) ?>" alt="Imagen desde S3" loading="lazy">
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

Abre `http://localhost:8000/`. Ahora deberías ver las imágenes que subiste manualmente a S3, servidas a través de URLs prefirmadas temporales.


>Si llega a ocurrir un error al conectar al S3 como "SSL certificate OpenSSL verify result: unable to get local issuer certificate" tenemos que descargar certificados SSL de `https://curl.se/docs/caextract.html` y buscar nuestra carpeta raiz de php dentro del archivo php.ini y modificar estos datos

```ini
curl.cainfo = "C:\php\cacert.pem" // la ruta del certificado descargado y nombre
openssl.cafile = "C:\php\cacert.pem"
```

---

## Fase 6: Subir y Eliminar Imágenes desde la Web (CRUD Completo)

Hasta ahora las imágenes se suben manualmente desde la consola de AWS. En esta fase final añadiremos un **formulario web** para que cualquier usuario pueda subir imágenes directamente al bucket, y un **botón para eliminarlas** cuando ya no sean necesarias.

Para ello necesitamos:
- **Dos archivos PHP** para separar responsabilidades: `index.php` (vista + listar) y `acciones.php` (subir y eliminar vía AJAX).
- Una **base de datos SQLite** (PDO) para almacenar los metadatos de cada producto.
- Los comandos `PutObject` y `DeleteObject` del SDK de S3.

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

> **`s3_key`** almacena la ruta del archivo dentro del bucket (ej: `media/zapatillas.jpg`). Esto nos permite localizar y eliminar el objeto de S3 cuando sea necesario.

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

        // Generar un nombre único para evitar colisiones en S3
        $s3Key = 'media/' . time() . '-' . substr(uniqid(), -6) . $ext;

        // Subir archivo a S3
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

        // Eliminar archivo de S3
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

> **`putObject()`** recibe el archivo desde el formulario y lo envía a S3.  
> **`deleteObject()`** elimina el archivo del bucket usando la `s3_key` almacenada en la BD.  
> El nombre en S3 incluye un timestamp + hash aleatorio para evitar colisiones entre archivos con el mismo nombre.

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
            <button type="submit" class="btn-submit">Guardar en S3</button>
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
                if (!confirm('¿Eliminar este producto y su imagen de S3?')) return;
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
3. Haz clic en **Guardar en S3**.
   - La imagen se sube automáticamente a tu bucket S3 (carpeta `media/`).
   - Los datos se guardan en SQLite (`productos.db`).
4. La imagen aparece en el grid con su nombre y precio.
5. Haz clic en **Eliminar** en cualquier producto.
   - El archivo se borra de S3 (operación `deleteObject`).
   - El registro se elimina de la base de datos.
6. Puedes verificar en la consola de AWS S3 que los archivos se crean y eliminan correctamente.

---

## Resumen del Flujo Completo (Fases 1 a 6)

```
Fase 1:  Productos estáticos con Picsum
         └── index.php → arreglo hardcoded + HTML embebido

Fase 2:  Bucket S3 privado con carpeta media/

Fase 3:  Credenciales IAM (usuario propio o AWS Academy)

Fase 4:  SDK de AWS (aws/aws-sdk-php) + dotenv + s3Client.php
         └── S3Client(region, version, credentials)

Fase 5:  Reemplazo de Picsum por S3
         ├── index.php → listObjectsV2() + createPresignedRequest()
         └── index.php → muestra URLs prefirmadas desde S3

Fase 6:  CRUD completo con formulario web
         ├── index.php   → formulario + grid (listado desde BD)
         ├── acciones.php → POST (putObject) + DELETE (deleteObject)
         ├── database.php → SQLite con tabla productos
         ├── s3Client.php → cliente S3 centralizado
         └── index.php   → JavaScript fetch() para subir y eliminar
```

**Diagrama de flujo de datos (Fase 6):**

```
Usuario (Navegador)
    │
    ├── [GET /index.php] ───── PHP lee productos de SQLite
    │                                   │
    │                                   └── createPresignedRequest() genera URL prefirmada
    │                                       para cada s3_key
    │                                   │
    │                                   └── Renderiza HTML con imágenes
    │
    ├── [POST /acciones.php] ── PHP recibe archivo del formulario
    │                                   │
    │                                   ├── putObject() a S3 (sube al bucket)
    │                                   └── INSERT en SQLite
    │
    └── [DELETE /acciones.php] ─ PHP busca producto en SQLite
                                        │
                                        ├── deleteObject() en S3 (borra imagen)
                                        └── DELETE en SQLite
```

Con esto tienes una aplicación PHP completamente integrada con Amazon S3, que parte de un catálogo estático con imágenes de Picsum y evoluciona hasta un **sistema completo de gestión de productos con almacenamiento en la nube**, todo sin exponer tu bucket al público y utilizando URLs prefirmadas para cada imagen.