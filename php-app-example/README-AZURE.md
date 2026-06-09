# Integración de Azure Blob Storage en PHP — Guía Paso a Paso

Esta guía te llevará desde un proyecto PHP base que muestra un catálogo de productos con imágenes estáticas de **Picsum**, hasta una aplicación completamente funcional que **almacena, sirve y elimina imágenes en Azure Blob Storage**, utilizando formularios web y buenas prácticas de seguridad.

El proyecto base ya está creado. Solo debes seguir las fases en orden para transformarlo.

> **Nota sobre Azure Blob Storage:** A diferencia de AWS S3 y OCI Object Storage, Azure Blob Storage **no es compatible con la API S3**. Por lo tanto, en lugar del SDK `aws/aws-sdk-php`, utilizaremos el SDK nativo `microsoft/azure-storage-blob` de Microsoft. La configuración es diferente pero igual de simple.

---

## Fase 1: Proyecto Base — Catálogo con Imágenes Estáticas

Antes de integrar Azure Blob Storage, revisa cómo funciona el proyecto en su estado inicial. Esta fase no requiere modificar nada; solo entender la arquitectura actual.

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

> Nota que `$producto['imagen']` es simplemente una cadena de texto con una URL. En las fases posteriores esto cambiará por una URL SAS (Shared Access Signature) de Azure.

### 1.3. Probar el proyecto base

Inicia el servidor embebido de PHP:

```bash
cd php-app-example
php -S localhost:8000
```

Abre `http://localhost:8000/`. Verás 6 tarjetas con imágenes aleatorias de Picsum. Este es el punto de partida.

---

## Fase 2: Creación y Configuración del Contenedor en Azure Blob Storage

Ahora crearemos la cuenta de almacenamiento y el contenedor en Azure que almacenará las imágenes de los productos.

### 2.1. Crear la Cuenta de Almacenamiento

1. Ingresa a [portal.azure.com](https://portal.azure.com) e inicia sesión con tu cuenta (puede ser **Azure for Students**).

2. En la barra de búsqueda superior, escribe **"Cuentas de almacenamiento"** y selecciona el servicio.

3. Haz clic en **Crear** y aplica la siguiente configuración:

   - **Suscripción:** `Azure for Students` (o la que corresponda).
   - **Grupo de recursos:** Crea uno nuevo (ej: `rg-php-azure`) o selecciona uno existente.
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
     > El contenedor será privado. PHP generará URLs SAS (Shared Access Signature) para acceder a cada imagen de forma temporal y segura.

4. Haz clic en **Crear**.

> **¿Por qué privado?**  
> Al igual que con AWS S3 y OCI, el bucket/contenedor se mantiene privado. PHP genera URLs SAS con expiración para que los usuarios puedan ver las imágenes sin exponer las credenciales ni hacer el contenedor público.

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

## Fase 4: Configuración de PHP para Azure Blob Storage

Ahora conectaremos PHP con el contenedor de Azure mediante el SDK oficial `microsoft/azure-storage-blob`.

### 4.1. Inicializar Composer e instalar dependencias

```bash
cd php-app-example
composer init --name="app/php-azure-catalogo" --require="php >=8.0" -n
composer require microsoft/azure-storage-blob vlucas/phpdotenv
```

> **Nota:** A diferencia de AWS y OCI, no necesitamos `aws/aws-sdk-php`. Usamos el SDK nativo de Azure `microsoft/azure-storage-blob`.

### 4.2. Crear archivo `.env`

En la raíz del proyecto (`php-app-example/`), crea o actualiza el archivo `.env` con las credenciales obtenidas en las fases anteriores:

```ini
# Opción 1: Cadena de conexión (recomendada)
AZURE_STORAGE_CONNECTION_STRING=DefaultEndpointsProtocol=https;AccountName=almacenamientoprod2026;AccountKey=7vuWclCpCf....;EndpointSuffix=core.windows.net

# Opción 2: Credenciales separadas
AZURE_ACCOUNT_NAME=almacenamientoprod2026
AZURE_ACCOUNT_KEY=7vuWclCpCf....
AZURE_CONTAINER=media
```

> **¿Cadena de conexión o credenciales separadas?**  
> La cadena de conexión incluye todo lo necesario para conectarse. Si prefieres usar el nombre y la clave por separado (más legible en código), también funciona. El SDK de Azure acepta ambos enfoques.

### 4.3. Crear el cliente Azure Blob Storage

Crea un archivo `blobClient.php` que centralice la configuración del SDK de Azure y que será reutilizado en todas las fases siguientes:

**`blobClient.php`:**

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Blob\BlobSharedAccessSignatureHelper;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Obtener credenciales desde variables de entorno
$connectionString = $_ENV['AZURE_STORAGE_CONNECTION_STRING'] ?? null;
$accountName = $_ENV['AZURE_ACCOUNT_NAME'] ?? '';
$accountKey = $_ENV['AZURE_ACCOUNT_KEY'] ?? '';
$containerName = $_ENV['AZURE_CONTAINER'] ?? 'media';

// Crear el cliente BlobRestProxy
if ($connectionString) {
    // Usar cadena de conexión (más simple)
    $blobClient = BlobRestProxy::createBlobService($connectionString);
} elseif ($accountName && $accountKey) {
    // Usar credenciales separadas
    $connectionString = "DefaultEndpointsProtocol=https;AccountName={$accountName};AccountKey={$accountKey};EndpointSuffix=core.windows.net";
    $blobClient = BlobRestProxy::createBlobService($connectionString);
} else {
    throw new Exception('Configura AZURE_STORAGE_CONNECTION_STRING o AZURE_ACCOUNT_NAME + AZURE_ACCOUNT_KEY en .env');
}

// Helper para generar URLs SAS
$sasHelper = new BlobSharedAccessSignatureHelper($accountName, $accountKey);
```

> **¿Qué hace `BlobRestProxy`?**  
> Es el objeto principal del SDK de Azure para PHP. Se encarga de la autenticación y comunicación con la API de Azure Blob Storage. Todas las operaciones (listar, subir, descargar, eliminar) se realizan a través de este cliente.

---

## Fase 5: Reemplazar Imágenes Estáticas por Azure Blob Storage

Ahora modificaremos el script para que, en lugar de usar URLs de Picsum, **lea las imágenes directamente desde el contenedor de Azure** y genere URLs SAS.

### 5.1. Subir imágenes al contenedor manualmente

Antes de probar, necesitas tener imágenes en Azure:

1. Ve al portal de Azure → Cuenta de almacenamiento → **Contenedores** → `media`.
2. Haz clic en **Subir**.
3. Selecciona 3 a 6 imágenes desde tu computador con nombres simples (ej: `producto1.jpg`, `producto2.jpg`, etc.).
4. Asegúrate de que tengan extensiones válidas: `.jpg`, `.jpeg`, `.png`, `.webp`, `.gif` o `.svg`.
5. Haz clic en **Subir**.

> **Alternativa con Azure Storage Explorer:**  
> Puedes usar [Azure Storage Explorer](https://azure.microsoft.com/es-es/products/storage/storage-explorer/) (gratuito) para subir archivos arrastrándolos desde tu explorador de archivos.

### 5.2. Actualizar `index.php`

Reemplaza el contenido de `index.php` por el siguiente código que lista los blobs de Azure y genera URLs SAS:

```php
<?php
require_once __DIR__ . '/blobClient.php';

use MicrosoftAzure\Storage\Blob\Models\ListBlobsOptions;

// Extensiones de archivo de imagen válidas
$validExtensions = ['.jpg', '.jpeg', '.png', '.webp', '.gif', '.svg'];

$productos = [];

try {
    // 1. Listar blobs en el contenedor "media"
    $listBlobsOptions = new ListBlobsOptions();
    $listBlobsOptions->setPrefix(''); // Sin prefijo, lista todo el contenedor
    $blobs = $blobClient->listBlobs($containerName, $listBlobsOptions);

    // 2. Generar URL SAS para cada blob
    foreach ($blobs->getBlobs() as $blob) {
        $blobName = $blob->getName();
        $ext = strtolower(substr($blobName, strrpos($blobName, '.')));

        // 3. Filtrar solo archivos de imagen
        if (in_array($ext, $validExtensions)) {
            // Es crucial usar UTC para las firmas de Azure
            $zonaHoraria = new DateTimeZone('UTC');
            $ahora = new DateTimeImmutable('now', $zonaHoraria);
            
            // Azure espera las fechas como strings en formato ISO 8601 (ej: 2026-06-09T08:30:00Z)
            $fechaExpiracion = $ahora->modify('+1 hour')->format('Y-m-d\TH:i:s\Z');
            $fechaInicio = $ahora->modify('-5 minutes')->format('Y-m-d\TH:i:s\Z');

            // Generar token SAS con los strings formateados
            $sasToken = $sasHelper->generateBlobServiceSharedAccessSignatureToken(
                'b',                               // 1. Tipo ('b' para blob)
                "{$containerName}/{$blobName}",    // 2. Ruta
                'r',                               // 3. Permisos ('r' para lectura)
                $fechaExpiracion,                  // 4. Expiración (ahora es un string)
                $fechaInicio,                      // 5. Inicio (ahora es un string)
                '',                                // 6. IP
                'https'                            // 7. Protocolo
            );

            // Construir URL completa con SAS
            $sasUrl = "https://{$accountName}.blob.core.windows.net/{$containerName}/{$blobName}?{$sasToken}";
            $productos[] = $sasUrl;
        }
    }
} catch (Exception $e) {
    error_log('Error al conectar con Azure Blob Storage: ' . $e->getMessage());
}
?>
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
        <?php foreach ($productos as $imagen): ?>
            <div class="gallery-item">
                <img src="<?= htmlspecialchars($imagen) ?>" alt="Imagen desde Azure" loading="lazy">
            </div>
        <?php endforeach; ?>
        <?php if (empty($productos)): ?>
            <p style="grid-column: 1 / -1; text-align: center; color: #666;">
                No hay imágenes en el contenedor. Súbelas manualmente a la carpeta <strong>media</strong>.
            </p>
        <?php endif; ?>
    </div>
</body>
</html>
```

> **`generateBlobSharedAccessSignatureToken()`** genera un token SAS (Shared Access Signature) que permite acceso temporal y seguro al blob.  
> **`listBlobs()`** obtiene la lista de blobs dentro del contenedor especificado.  
> El token SAS incluye una fecha de expiración (`+1 hour`) y una de inicio con un margen de 5 minutos para evitar problemas de sesgo de reloj.

### 5.3. Probar

```bash
cd php-app-example
php -S localhost:8000
```

Abre `http://localhost:8000/`. Ahora deberías ver las imágenes que subiste manualmente a Azure, servidas a través de URLs SAS temporales.

---

## Fase 6: Subir y Eliminar Imágenes desde la Web (CRUD Completo)

Hasta ahora las imágenes se suben manualmente desde el portal de Azure. En esta fase final añadiremos un **formulario web** para que cualquier usuario pueda subir imágenes directamente al contenedor, y un **botón para eliminarlas** cuando ya no sean necesarias.

Para ello necesitamos:
- **Dos archivos PHP** para separar responsabilidades: `index.php` (vista + listar) y `acciones.php` (subir y eliminar vía AJAX).
- Una **base de datos SQLite** (PDO) para almacenar los metadatos de cada producto.
- Los métodos `createBlockBlob` y `deleteBlob` del SDK de Azure Blob Storage.

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
        blob_name TEXT NOT NULL
    )
");

return $pdo;
```

> **`blob_name`** almacena el nombre del blob dentro del contenedor (ej: `producto1.jpg`). Esto nos permite localizar y eliminar el blob de Azure cuando sea necesario.

### 6.2. Crear el endpoint de acciones (subir / eliminar)

**`acciones.php`:**

```php
<?php
require_once __DIR__ . '/blobClient.php';

use MicrosoftAzure\Storage\Blob\Models\CreateBlockBlobOptions;

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

        // Generar un nombre único para evitar colisiones en Azure
        $blobName = time() . '-' . substr(uniqid(), -6) . $ext;

        // Configurar opciones del blob (Content-Type)
        $options = new CreateBlockBlobOptions();
        $options->setContentType($archivo['type']);

        // Subir archivo a Azure Blob Storage
        $blobClient->createBlockBlob(
            $containerName,
            $blobName,
            file_get_contents($archivo['tmp_name']),
            $options
        );

        // Guardar metadatos en SQLite
        $stmt = $pdo->prepare('INSERT INTO productos (nombre, precio, blob_name) VALUES (?, ?, ?)');
        $stmt->execute([$nombre, (float) $precio, $blobName]);

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

        // Eliminar blob de Azure Blob Storage
        $blobClient->deleteBlob($containerName, $producto['blob_name']);

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

> **`createBlockBlob()`** recibe el contenido del archivo desde el formulario y lo envía a Azure Blob Storage.  
> **`deleteBlob()`** elimina el blob del contenedor usando el `blob_name` almacenado en la BD.  
> El nombre en Azure incluye un timestamp + hash aleatorio para evitar colisiones entre archivos con el mismo nombre.

### 6.3. Actualizar `blobClient.php` (con generación de SAS para CRUD)

Actualiza el archivo `blobClient.php` para incluir la generación de URLs SAS y exponer las variables necesarias:

**`blobClient.php`:**

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Blob\BlobSharedAccessSignatureHelper;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Obtener credenciales desde variables de entorno
$connectionString = $_ENV['AZURE_STORAGE_CONNECTION_STRING'] ?? null;
$accountName = $_ENV['AZURE_ACCOUNT_NAME'] ?? '';
$accountKey = $_ENV['AZURE_ACCOUNT_KEY'] ?? '';
$containerName = $_ENV['AZURE_CONTAINER'] ?? 'media';

// Crear el cliente BlobRestProxy
if ($connectionString) {
    $blobClient = BlobRestProxy::createBlobService($connectionString);
} elseif ($accountName && $accountKey) {
    $connectionString = "DefaultEndpointsProtocol=https;AccountName={$accountName};AccountKey={$accountKey};EndpointSuffix=core.windows.net";
    $blobClient = BlobRestProxy::createBlobService($connectionString);
} else {
    throw new Exception('Configura AZURE_STORAGE_CONNECTION_STRING o AZURE_ACCOUNT_NAME + AZURE_ACCOUNT_KEY en .env');
}

// Helper para generar URLs SAS
$sasHelper = new BlobSharedAccessSignatureHelper($accountName, $accountKey);

/**
 * Genera una URL SAS para un blob con expiración de 1 hora
 * @param string $blobName - Nombre del blob en el contenedor
 * @return string URL SAS completa
 */
function generateSasUrl($blobName) {
    global $accountName, $containerName, $sasHelper;

    // Configurar UTC
    $zonaHoraria = new DateTimeZone('UTC');
    $ahora = new DateTimeImmutable('now', $zonaHoraria);
    
    // Formatear fechas a ISO 8601 string
    $fechaExpiracion = $ahora->modify('+1 hour')->format('Y-m-d\TH:i:s\Z');
    $fechaInicio = $ahora->modify('-5 minutes')->format('Y-m-d\TH:i:s\Z');

    // Usar el método correcto y el orden correcto de parámetros
    $sasToken = $sasHelper->generateBlobServiceSharedAccessSignatureToken(
        'b',                               // 1. Tipo de recurso (blob)
        "{$containerName}/{$blobName}",    // 2. Ruta concatenada
        'r',                               // 3. Permisos (lectura)
        $fechaExpiracion,                  // 4. Expiración
        $fechaInicio,                      // 5. Inicio
        '',                                // 6. IP
        'https'                            // 7. Protocolo
    );

    return "https://{$accountName}.blob.core.windows.net/{$containerName}/{$blobName}?{$sasToken}";
}
```

> **`generateBlobSharedAccessSignatureToken()`** genera un token SAS con permisos de solo lectura (`r`).  
> La función `generateSasUrl()` expone este token como una URL completa lista para usar en el navegador.

### 6.4. Actualizar `index.php` (con formulario y listado desde BD)

Reemplaza todo el contenido de `index.php` con el siguiente código que incluye subida, listado y eliminación de productos:

```php
<?php
require_once __DIR__ . '/blobClient.php';

$pdo = require __DIR__ . '/database.php';

$productos = [];

try {
    // Leer productos desde SQLite
    $stmt = $pdo->query('SELECT * FROM productos ORDER BY id DESC');
    $productosDb = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Generar URL SAS para cada producto
    foreach ($productosDb as $producto) {
        $productos[] = [
            'id'     => $producto['id'],
            'nombre' => $producto['nombre'],
            'precio' => $producto['precio'],
            'imagen' => generateSasUrl($producto['blob_name']),
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
            <button type="submit" class="btn-submit">Guardar en Azure</button>
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
                if (!confirm('¿Eliminar este producto y su imagen de Azure?')) return;
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

### 6.5. Probar el flujo completo

```bash
cd php-app-example
php -S localhost:8000
```

1. Abre `http://localhost:8000/index.php`.
2. Completa el formulario con nombre, precio y selecciona una imagen desde tu computador.
3. Haz clic en **Guardar en Azure**.
   - La imagen se sube automáticamente a tu contenedor de Azure Blob Storage (`media`).
   - Los datos se guardan en SQLite (`productos.db`).
4. La imagen aparece en el grid con su nombre y precio.
5. Haz clic en **Eliminar** en cualquier producto.
   - El blob se borra de Azure (operación `deleteBlob()` del SDK de Azure).
   - El registro se elimina de la base de datos.
6. Puedes verificar en el portal de Azure que los blobs se crean y eliminan correctamente.

---

## Resumen del Flujo Completo (Fases 1 a 6)

```
Fase 1:  Productos estáticos con Picsum
         └── index.php → arreglo hardcoded + HTML embebido

Fase 2:  Cuenta de almacenamiento + contenedor media en Azure

Fase 3:  Credenciales (Account Name + Account Key + Connection String)

Fase 4:  SDK de Azure (microsoft/azure-storage-blob) + dotenv + blobClient.php
         └── BlobRestProxy::createBlobService()

Fase 5:  Reemplazo de Picsum por Azure
         ├── index.php → listBlobs() + URLs SAS directas
         └── index.php → muestra URLs SAS desde Azure

Fase 6:  CRUD completo con formulario web
         ├── index.php       → formulario + grid (listado desde BD)
         ├── acciones.php    → POST (createBlockBlob) + DELETE (deleteBlob)
         ├── database.php    → SQLite con tabla productos
         ├── blobClient.php  → cliente Azure + generateSasUrl()
         └── index.php       → JavaScript fetch() para subir y eliminar
```

**Diagrama de flujo de datos (Fase 6):**

```
Usuario (Navegador)
    │
    ├── [GET /index.php] ──── PHP lee productos de SQLite
    │                                   │
    │                                   └── generateSasUrl() genera URL SAS
    │                                       para cada blob_name
    │                                   │
    │                                   └── Renderiza HTML con imágenes
    │
    ├── [POST /acciones.php] ─ PHP recibe archivo del formulario
    │                                   │
    │                                   ├── createBlockBlob() a Azure (sube al contenedor)
    │                                   └── INSERT en SQLite
    │
    └── [DELETE /acciones.php] PHP busca producto en SQLite
                                        │
                                        ├── deleteBlob() en Azure (borra blob)
                                        └── DELETE en SQLite
```

## Diferencias clave entre AWS S3, OCI Object Storage y Azure Blob Storage

| Concepto | AWS S3 | OCI Object Storage | Azure Blob Storage |
|----------|--------|-------------------|-------------------|
| SDK de PHP | `aws/aws-sdk-php` | `aws/aws-sdk-php` | `microsoft/azure-storage-blob` |
| Endpoint | Automático según región | `https://{namespace}.compat.objectstorage.{region}.oraclecloud.com` | Automático (`*.blob.core.windows.net`) |
| Credenciales | IAM User (Access Key + Secret Key) o AWS Academy (con token) | Customer Secret Keys (Access Key + Secret Key) | Account Name + Account Key o Connection String |
| Session Token | Obligatorio en AWS Academy | No aplica | No aplica |
| Tipo de URL firmada | Pre-signed URL | Pre-signed URL (compatible S3) | SAS Token (Shared Access Signature) |
| Almacenamiento | Bucket | Bucket | Contenedor |
| Carpeta en nube | `Prefix: 'media/'` | `Prefix: 'media/'` | Blobs con prefijo en el nombre |
| Cliente principal | `S3Client` | `S3Client` (con `endpoint` OCI) | `BlobRestProxy` |
| Comando listar | `listObjectsV2()` | `listObjectsV2()` | `listBlobs()` |
| Comando subir | `putObject()` | `putObject()` | `createBlockBlob()` |
| Comando eliminar | `deleteObject()` | `deleteObject()` | `deleteBlob()` |
| URL firmada | `createPresignedRequest()` | `createPresignedRequest()` | `generateBlobSharedAccessSignatureToken()` |

Con esto tienes una aplicación PHP completamente integrada con Azure Blob Storage, que parte de un catálogo estático con imágenes de Picsum y evoluciona hasta un **sistema completo de gestión de productos con almacenamiento en la nube de Microsoft Azure**, utilizando URLs SAS para mantener tu contenedor privado y seguro.