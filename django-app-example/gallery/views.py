from django.shortcuts import render

# Lista estática de imágenes de Picsum (fase inicial)
# En fases posteriores, estas imágenes se reemplazarán por archivos
# almacenados en Amazon S3.
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