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