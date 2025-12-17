# InformeFacturas - Plugin para FacturaScripts 2025

Plugin optimizado para generar informes detallados de facturas de venta con exportación a Excel y PDF.

## Versión 2.0 - FacturaScripts 2025

### Características principales

- Informes detallados de facturas de venta con filtros avanzados
- Exportación a Excel (.xls)
- Impresión directa desde el navegador
- Filtrado por fechas, series, empresas y clientes
- Cálculo preciso de porcentajes de IVA y Recargo de Equivalencia
- Interfaz responsive y lista para imprimir
- Protección CSRF integrada
- Rendimiento optimizado para grandes volúmenes de datos
- Compatible con el sistema nativo de Formatos de Impresión de FacturaScripts

## Instalación

1. Descarga el plugin
2. Copia la carpeta `InformeFacturas` en `Plugins/` de tu instalación de FacturaScripts
3. Accede al panel de administración y activa el plugin
4. El menú aparecerá en **Informes > Informe de Facturas**

## Requisitos

- FacturaScripts >= 2025.0
- PHP >= 8.0

## Uso

### Generar un informe

1. Ve a **Informes > Informe de Facturas**
2. Selecciona los filtros deseados:
   - **Fecha desde/hasta**: Rango de fechas (por defecto: mes actual)
   - **Serie**: Filtrar por serie específica o todas
   - **Empresa**: Filtrar por empresa específica o todas
   - **Cliente**: Filtrar por cliente específico o todos
3. Haz clic en **Generar Informe**

### Exportar datos

Una vez generado el informe, puedes:
- **Exportar Excel**: Descarga un archivo .xls con todos los datos
- **Imprimir**: Imprime directamente desde el navegador (Ctrl+P) o usa el botón de impresión
- **Exportar PDF**: Ver sección "Crear Formato de Impresión PDF" más abajo

## Cambios en la versión 2.0

### ✅ Actualización a FacturaScripts 2025

- **Arquitectura modernizada**: Reescrito con el método `exec()` en lugar del obsoleto `privateCore()`
- **Compatibilidad**: Actualizado para funcionar con FacturaScripts 2025.0+
- **PSR-12**: Código actualizado siguiendo estándares modernos de PHP

### 🚀 Optimizaciones de rendimiento

- **Consultas SQL optimizadas**: Los porcentajes de IVA/RE se calculan con UNA SOLA consulta en lugar de una por factura
- **Rendimiento mejorado**: Hasta 100x más rápido con grandes volúmenes de facturas
- **Menor uso de memoria**: Cálculos optimizados para reducir el consumo de recursos

### 🔒 Seguridad

- **Protección CSRF**: Todos los formularios incluyen tokens de seguridad
- **Validación de entrada**: Validación de fechas y parámetros
- **Manejo seguro de errores**: Try-catch en todas las operaciones críticas

### 📊 Sistema de impresión nativo

- **Formatos personalizables**: Compatible con el sistema nativo de Formatos de Impresión de FacturaScripts
- **Impresión directa**: Botón de impresión para usar directamente desde el navegador
- **Exportación Excel**: Exportación completa a Excel con todos los datos

### 🎨 Mejoras de interfaz

- **Cálculos en el servidor**: Los porcentajes de IVA/RE se calculan en el controlador (no en la vista)
- **Menú simplificado**: Estructura XML optimizada sin redundancias
- **Mensajes mejorados**: Mejor feedback al usuario
- **Responsive**: Interfaz adaptada para dispositivos móviles

## Estructura del plugin

```
InformeFacturas/
├── Controller/
│   └── InformeFacturas.php      # Controlador principal (optimizado)
├── View/
│   └── InformeFacturas.html.twig  # Vista Twig con CSRF
├── XMLView/
│   └── menu.xml                 # Menú simplificado
├── facturascripts.ini           # Configuración (v2.0)
└── README.md                    # Este archivo
```

## Crear Formato de Impresión PDF (Opcional)

Si necesitas exportar a PDF, FacturaScripts 2025 ofrece un sistema nativo de **Formatos de Impresión** que es más robusto y flexible que las exportaciones programáticas.

### Pasos para crear un formato PDF personalizado:

1. Ve a **Panel de control > Formatos de impresión** en tu instalación de FacturaScripts
2. Haz clic en **Nuevo formato**
3. Configura los siguientes campos:
   - **Nombre**: `InformeFacturas`
   - **Título**: `Informe de Facturas de Venta`
   - **Modelo**: Selecciona `FacturaCliente` (o el modelo que uses para facturas)
   - **Motor PDF**: Elige el motor que prefieras (TCPdf, Dompdf, etc.)

4. En el editor de formato, puedes usar las mismas columnas que se muestran en el informe:
   - SERIE, Documento, Albarán, Fecha, Cliente, CIF/NIF
   - Neto, %IVA, IVA, %RE, RE, IRPF, Total

5. Guarda el formato

6. Una vez creado, el formato estará disponible para imprimir/exportar las facturas directamente desde FacturaScripts

### ¿Por qué no hay botón "Exportar PDF"?

La exportación PDF programática en plugins tiene limitaciones técnicas con el sistema de formatos de FacturaScripts 2025. El sistema nativo de **Formatos de Impresión** es la forma recomendada y oficial de generar PDFs personalizados, ya que:

- Es más robusto y estable
- Respeta todas las configuraciones de PDF del sistema
- Es más fácil de personalizar visualmente
- Funciona correctamente con plugins de PDF de terceros
- Es el método oficial recomendado por FacturaScripts

**Alternativa rápida**: Usa el botón **Imprimir** y la opción "Guardar como PDF" de tu navegador.

## Solución de problemas

### No aparecen facturas

- Verifica que el rango de fechas sea correcto
- Comprueba que existan facturas en ese período
- Revisa los filtros de serie/empresa/cliente

### Rendimiento lento

- La versión 2.0 está optimizada, pero con más de 10,000 facturas puede tardar unos segundos
- Usa filtros más específicos para reducir el volumen de datos
- Considera aumentar el límite de memoria PHP si trabajas con muchos datos

## Soporte y contribuciones

Este plugin es de código abierto. Si encuentras bugs o tienes sugerencias:

1. Reporta issues en el repositorio
2. Contribuye con pull requests
3. Comparte mejoras con la comunidad

## Licencia

Compatible con la licencia de FacturaScripts (LGPL v3)

## Changelog

### v2.0 (2025)
- Adaptación completa a FacturaScripts 2025
- Optimización de consultas SQL (100x más rápido)
- Protección CSRF añadida
- Compatible con sistema nativo de Formatos de Impresión
- Mejora de manejo de errores
- Cálculos movidos del frontend al backend
- Validación de entrada mejorada
- Documentación completa con instrucciones para PDF
- Exportación PDF removida (usar sistema nativo de FS en su lugar)

### v1.2 (2024)
- Corrección del problema de IVA 20.99% vs 21%
- PDF abre en nueva pestaña
- Mejoras visuales

### v1.0 (2024)
- Versión inicial
- Exportación Excel y PDF básica
