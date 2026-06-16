-- Marca todos los productos como afectos a IGV (tbl_productos.igv = 1).
-- Ejecutar en MySQL/phpMyAdmin sobre la base de datos del proyecto.
-- Opcional: agregue AND idPuntoVenta = 9 para un solo punto de venta.

UPDATE tbl_productos
SET igv = 1;

-- Verificación:
-- SELECT idPuntoVenta, COUNT(*) AS total, SUM(CASE WHEN igv = 1 THEN 1 ELSE 0 END) AS con_igv
-- FROM tbl_productos
-- GROUP BY idPuntoVenta;
