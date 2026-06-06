<?php
// index.php — Proyecto Base (Fase 1)
// En las fases siguientes, las imágenes estáticas de Picsum
// serán reemplazadas por archivos almacenados en Amazon S3.

// Lista estática de productos con imágenes de Picsum (fase inicial)
$productos = [
    ['nombre' => 'Zapatillas Urbanas', 'precio' => 45000, 'imagen' => 'https://picsum.photos/id/1018/800/600'],
    ['nombre' => 'Mochila Ejecutiva',  'precio' => 32000, 'imagen' => 'https://picsum.photos/id/1015/800/600'],
    ['nombre' => 'Auriculares Pro',    'precio' => 28000, 'imagen' => 'https://picsum.photos/id/1019/800/600'],
    ['nombre' => 'Reloj Deportivo',    'precio' => 55000, 'imagen' => 'https://picsum.photos/id/1016/800/600'],
    ['nombre' => 'Cámara Digital',     'precio' => 89000, 'imagen' => 'https://picsum.photos/id/1020/800/600'],
    ['nombre' => 'Lámpara LED',        'precio' => 15000, 'imagen' => 'https://picsum.photos/id/1021/800/600'],
];

$productosJson = htmlspecialchars(json_encode($productos), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catáculo MultiCloud - Fase Base</title>
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
        .item-info p { margin: 0; font-size: 18px; color: #666; font-weight: bold; }
    </style>
</head>
<body>

    <h1>Catáculo de Productos</h1>

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
        <?php if (empty($productos)): ?>
            <p style="grid-column: 1 / -1; text-align: center; color: #666;">No hay productos disponibles.</p>
        <?php endif; ?>
    </div>

</body>
</html>