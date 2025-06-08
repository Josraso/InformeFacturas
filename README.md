# Plugin Informe Facturas para FacturaScripts

Este plugin añade un informe detallado de facturas de venta a FacturaScripts, similar al formato mostrado en tu imagen.

## Características

- **Filtros avanzados**: Fecha desde/hasta, Serie, Empresa, Cliente
- **Exportación a Excel**: Descarga el informe en formato .xls
- **Impresión optimizada**: Vista adaptada para impresión
- **Totales automáticos**: Cálculo de totales por columna
- **Diseño responsivo**: Adaptado a diferentes tamaños de pantalla

## Instalación

1. Descarga todos los archivos del plugin
2. Crea la carpeta `Plugins/InformeFacturas/` en tu instalación de FacturaScripts
3. Copia todos los archivos manteniendo la estructura de carpetas:

```
Plugins/
└── InformeFacturas/
    ├── facturascripts.ini
    ├── Init.php
    ├── Controller/
    │   └── InformeFacturas.php
    ├── View/
    │   └── InformeFacturas.html.twig
    └── XMLView/
        └── menu.xml
```

4. Accede al panel de administración de FacturaScripts
5. Ve a **Administración > Plugins**
6. Activa el plugin "InformeFacturas"

## Uso

1. Ve al menú **Informes > Informe Facturas**
2. Configura los filtros deseados:
   - **Fecha Desde/Hasta**: Rango de fechas para el informe
   - **Serie**: Filtra por serie específica (opcional)
   - **Empresa**: Filtra por empresa específica (opcional)
   - **Cliente**: Filtra por cliente específico (opcional)
3. Haz clic en **Generar Informe**
4. El informe mostrará:
   - Lista detallada de facturas
   - Totales por columna
   - Opción de exportar a Excel
   - Opción de imprimir

## Estructura del Informe

El informe incluye las siguientes columnas:

- **SERIE**: Código de la serie de la factura
- **Documento**: Código de la factura
- **Albarán PS**: Código del albarán asociado
- **Fecha**: Fecha de la factura
- **Cliente**: Nombre del cliente
- **CIF/NIF**: Documento de identificación del cliente
- **Neto**: Importe neto
- **%IVA**: Porcentaje de IVA
- **IVA**: Importe del IVA
- **%RE**: Porcentaje de recargo de equivalencia
- **RE**: Importe del recargo de equivalencia
- **IRPF**: Importe del IRPF
- **Total**: Importe total de la factura

## Funcionalidades Adicionales

### Exportación a Excel
- Botón para descargar el informe en formato .xls
- Mantiene el formato y los totales
- Nombre del archivo incluye la fecha de generación

### Impresión
- Vista optimizada para impresión
- Oculta elementos no necesarios en papel
- Ajusta el tamaño de fuente para mejor legibilidad

### Totales
- Cálculo automático de totales por columna
- Mostrados en el pie de la tabla
- Incluidos en la exportación

## Compatibilidad

- FacturaScripts versión 2024.94 o superior
- PHP 7.4 o superior
- Compatible con todas las funcionalidades nativas de filtrado de FacturaScripts

## Personalización

El plugin utiliza las clases CSS de Bootstrap incluidas en FacturaScripts, por lo que respeta el tema configurado en tu instalación.

Puedes personalizar:
- Filtros adicionales modificando el controlador
- Formato de visualización editando la vista Twig
- Campos mostrados en el informe
- Estilo de impresión

## Solución de Problemas

### El plugin no aparece en el menú
1. Verifica que el archivo `menu.xml` esté en la carpeta correcta
2. Limpia la caché de FacturaScripts
3. Verifica los permisos del usuario

### Error al generar el informe
1. Verifica que el usuario tenga permisos para acceder a facturas
2. Comprueba que las fechas estén en formato correcto
3. Revisa los logs de FacturaScripts para errores específicos

### La exportación no funciona
1. Verifica que PHP tenga permisos de escritura
2. Comprueba que no haya caracteres especiales en los datos
3. Asegúrate de que el servidor permita la descarga de archivos

## Soporte

Para reportar errores o sugerir mejoras, contacta al desarrollador o crea un issue en el repositorio del proyecto.

## Licencia

Este plugin se distribuye bajo la misma licencia que FacturaScripts.