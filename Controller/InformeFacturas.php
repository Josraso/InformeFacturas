<?php
/**
 * Controlador para generar informes de facturas de venta
 * Optimizado para FacturaScripts 2025
 *
 * @author InformeFacturas Plugin
 * @version 2.0
 */

namespace FacturaScripts\Plugins\InformeFacturas\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\Serie;
use FacturaScripts\Dinamic\Model\LineaFacturaCliente;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\ExportManager;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;

class InformeFacturas extends Controller
{
    public $facturas = [];
    public $facturasConIVA = []; // Facturas con porcentajes de IVA/RE calculados
    public $empresa;
    public $totalNeto = 0;
    public $totalIVA = 0;
    public $totalRecargo = 0;
    public $totalIRPF = 0;
    public $totalGeneral = 0;

    // Filtros
    public $fechaDesde;
    public $fechaHasta;
    public $serieSeleccionada;
    public $clienteSeleccionado;
    public $empresaSeleccionada;

    // Para los selectores
    public $series = [];
    public $empresas = [];
    public $clientes = [];

    public function getPageData(): array
    {
        $data = parent::getPageData();
        $data['menu'] = 'reports';
        $data['title'] = 'Informe de Facturas';
        $data['icon'] = 'fas fa-file-invoice';
        return $data;
    }

    /**
     * Ejecuta la lógica privada del controlador
     * Arquitectura FacturaScripts 2025 (compatible con privateCore)
     */
    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);

        // Inicializar filtros con valores por defecto
        $this->inicializarFiltros();

        // Cargar datos para los filtros
        $this->cargarDatosFiltros();

        // Procesar acciones POST
        $action = $this->request->request->get('action', '');

        if ($this->request->isMethod('POST') && !empty($action)) {
            // Validar token CSRF
            if (!$this->validateFormToken()) {
                Tools::log()->warning('csrf-token-invalid');
                return;
            }

            switch ($action) {
                case 'generar':
                    $this->procesarFiltros();
                    $this->generarInforme();
                    break;

                case 'exportar_excel':
                    $this->procesarFiltros();
                    $this->generarInforme();
                    $this->exportarExcel();
                    return; // No renderizar vista

                case 'exportar_pdf':
                    $this->procesarFiltros();
                    $this->generarInforme();
                    $this->exportarPDF();
                    return; // No renderizar vista
            }
        }
    }

    /**
     * Inicializa los filtros con valores por defecto
     */
    private function inicializarFiltros(): void
    {
        // Fecha desde (primer día del mes actual)
        $this->fechaDesde = $this->request->request->get('fecha_desde', date('Y-m-01'));

        // Fecha hasta (último día del mes actual)
        $this->fechaHasta = $this->request->request->get('fecha_hasta', date('Y-m-t'));

        // Otros filtros
        $this->serieSeleccionada = $this->request->request->get('serie', '');
        $this->clienteSeleccionado = $this->request->request->get('cliente', '');
        $this->empresaSeleccionada = $this->request->request->get('empresa', '');
    }

    /**
     * Procesa los filtros desde el formulario POST
     */
    private function procesarFiltros(): void
    {
        $this->fechaDesde = $this->request->request->get('fecha_desde', $this->fechaDesde);
        $this->fechaHasta = $this->request->request->get('fecha_hasta', $this->fechaHasta);
        $this->serieSeleccionada = $this->request->request->get('serie', '');
        $this->clienteSeleccionado = $this->request->request->get('cliente', '');
        $this->empresaSeleccionada = $this->request->request->get('empresa', '');

        // Validar fechas
        if (!$this->validarFechas()) {
            Tools::log()->warning('invalid-date-range');
        }
    }

    /**
     * Valida el rango de fechas
     */
    private function validarFechas(): bool
    {
        if (empty($this->fechaDesde) || empty($this->fechaHasta)) {
            return false;
        }

        $desde = strtotime($this->fechaDesde);
        $hasta = strtotime($this->fechaHasta);

        if ($desde === false || $hasta === false) {
            return false;
        }

        if ($desde > $hasta) {
            Tools::log()->warning('La fecha desde no puede ser posterior a la fecha hasta');
            return false;
        }

        return true;
    }

    /**
     * Carga los datos para los filtros (series, empresas, clientes)
     */
    private function cargarDatosFiltros(): void
    {
        try {
            // Cargar TODAS las series
            $serieModel = new Serie();
            $this->series = $serieModel->all([], ['codserie' => 'ASC'], 0, 0);

            // Cargar TODAS las empresas
            $empresaModel = new Empresa();
            $this->empresas = $empresaModel->all([], ['nombrecorto' => 'ASC'], 0, 0);

            // Cargar TODOS los clientes (sin límite)
            $clienteModel = new Cliente();
            $this->clientes = $clienteModel->all([], ['nombre' => 'ASC'], 0, 0);
        } catch (\Exception $e) {
            Tools::log()->error('Error cargando datos de filtros: ' . $e->getMessage());
        }
    }

    /**
     * Genera el informe de facturas según los filtros seleccionados
     */
    private function generarInforme(): void
    {
        try {
            $facturaModel = new FacturaCliente();

            // Construir filtros WHERE
            $where = $this->construirFiltrosWhere();

            // Obtener facturas
            $this->facturas = $facturaModel->all($where, ['fecha' => 'ASC', 'numero' => 'ASC'], 0, 0);

            // OPTIMIZACIÓN: Calcular porcentajes de IVA/RE de todas las facturas en una sola consulta
            $this->calcularPorcentajesIVA();

            // Calcular totales
            $this->calcularTotales();

            // Obtener datos de la empresa para el encabezado
            $this->cargarEmpresa();

            if (empty($this->facturas)) {
                Tools::log()->info('No se encontraron facturas con los criterios seleccionados');
            }
        } catch (\Exception $e) {
            Tools::log()->error('Error generando informe: ' . $e->getMessage());
            $this->facturas = [];
        }
    }

    /**
     * Construye los filtros WHERE para la consulta de facturas
     */
    private function construirFiltrosWhere(): array
    {
        $where = [];

        // Filtro por fechas
        if (!empty($this->fechaDesde)) {
            $where[] = new DataBaseWhere('fecha', $this->fechaDesde, '>=');
        }

        if (!empty($this->fechaHasta)) {
            $where[] = new DataBaseWhere('fecha', $this->fechaHasta, '<=');
        }

        // Filtro por serie
        if (!empty($this->serieSeleccionada)) {
            $where[] = new DataBaseWhere('codserie', $this->serieSeleccionada);
        }

        // Filtro por cliente
        if (!empty($this->clienteSeleccionado)) {
            $where[] = new DataBaseWhere('codcliente', $this->clienteSeleccionado);
        }

        // Filtro por empresa
        if (!empty($this->empresaSeleccionada)) {
            $where[] = new DataBaseWhere('idempresa', $this->empresaSeleccionada);
        }

        return $where;
    }

    /**
     * OPTIMIZACIÓN CRÍTICA: Calcula los porcentajes de IVA y RE para todas las facturas
     * en UNA SOLA consulta SQL en lugar de una consulta por factura
     */
    private function calcularPorcentajesIVA(): void
    {
        if (empty($this->facturas)) {
            $this->facturasConIVA = [];
            return;
        }

        // Obtener todos los IDs de facturas
        $idsFacturas = array_map(function($f) { return $f->idfactura; }, $this->facturas);

        // Consulta SQL optimizada: obtener el IVA y RE de la primera línea de cada factura
        $sql = "SELECT idfactura, iva, recargo
                FROM lineasfacturascli
                WHERE idfactura IN (" . implode(',', $idsFacturas) . ")
                GROUP BY idfactura";

        $porcentajes = [];
        foreach ($this->dataBase->select($sql) as $row) {
            $porcentajes[$row['idfactura']] = [
                'iva' => round((float)$row['iva'], 0), // Redondear a entero (21.00 → 21)
                'recargo' => round((float)$row['recargo'], 2)
            ];
        }

        // Combinar facturas con sus porcentajes
        $this->facturasConIVA = [];
        foreach ($this->facturas as $factura) {
            $facturaConIVA = (object)[
                'factura' => $factura,
                'porcentajeIVA' => 0,
                'porcentajeRE' => 0
            ];

            if (isset($porcentajes[$factura->idfactura])) {
                $facturaConIVA->porcentajeIVA = $porcentajes[$factura->idfactura]['iva'];
                $facturaConIVA->porcentajeRE = $porcentajes[$factura->idfactura]['recargo'];
            } else {
                // Fallback: calcular desde los totales si no hay líneas
                if ($factura->neto != 0) {
                    $facturaConIVA->porcentajeIVA = round(($factura->totaliva / $factura->neto * 100), 0);
                    $facturaConIVA->porcentajeRE = round(($factura->totalrecargo / $factura->neto * 100), 2);
                }
            }

            $this->facturasConIVA[] = $facturaConIVA;
        }
    }

    /**
     * Calcula los totales del informe
     */
    private function calcularTotales(): void
    {
        $this->totalNeto = 0;
        $this->totalIVA = 0;
        $this->totalRecargo = 0;
        $this->totalIRPF = 0;
        $this->totalGeneral = 0;

        foreach ($this->facturas as $factura) {
            $this->totalNeto += $factura->neto;
            $this->totalIVA += $factura->totaliva;
            $this->totalRecargo += $factura->totalrecargo;
            $this->totalIRPF += $factura->totalirpf;
            $this->totalGeneral += $factura->total;
        }
    }

    /**
     * Carga los datos de la empresa para el encabezado
     */
    private function cargarEmpresa(): void
    {
        try {
            $empresaModel = new Empresa();
            if (!empty($this->empresaSeleccionada)) {
                $this->empresa = $empresaModel->get($this->empresaSeleccionada);
            } else {
                $idEmpresaDefault = Tools::settings('default', 'idempresa', 1);
                $this->empresa = $empresaModel->get($idEmpresaDefault);
            }
        } catch (\Exception $e) {
            Tools::log()->error('Error cargando empresa: ' . $e->getMessage());
            $this->empresa = null;
        }
    }

    /**
     * Exporta el informe a Excel
     */
    private function exportarExcel(): void
    {
        if (empty($this->facturas)) {
            Tools::log()->warning('No hay facturas para exportar');
            return;
        }

        try {
            // Desactivar la plantilla
            $this->setTemplate(false);

            // Configurar headers para descarga
            $this->response->headers->set('Content-Type', 'application/vnd.ms-excel; charset=utf-8');
            $this->response->headers->set('Content-Disposition', 'attachment; filename="informe_facturas_' . date('Y-m-d') . '.xls"');
            $this->response->headers->set('Cache-Control', 'max-age=0');

            ob_start();
            echo '<html><head><meta charset="UTF-8"></head><body>';

            // Encabezado con datos de la empresa
            echo '<h2>' . ($this->empresa ? $this->empresa->nombre : 'Empresa') . '</h2>';
            echo '<h3>Facturas de venta del ' . date('d/m/Y', strtotime($this->fechaDesde)) . ' al ' . date('d/m/Y', strtotime($this->fechaHasta)) . ', divisa: EUR.</h3>';
            echo '<br>';

            echo '<table border="1" cellpadding="3" cellspacing="0">';
            echo '<tr style="background-color: #cccccc; font-weight: bold;">';
            echo '<td>SERIE</td>';
            echo '<td>Documento</td>';
            echo '<td>Albarán PS</td>';
            echo '<td>Fecha</td>';
            echo '<td>Cliente</td>';
            echo '<td>CIF/NIF</td>';
            echo '<td>Neto</td>';
            echo '<td>%IVA</td>';
            echo '<td>IVA</td>';
            echo '<td>%RE</td>';
            echo '<td>RE</td>';
            echo '<td>IRPF</td>';
            echo '<td>Total</td>';
            echo '</tr>';

            foreach ($this->facturasConIVA as $item) {
                $factura = $item->factura;

                echo '<tr>';
                echo '<td>' . $factura->codserie . '</td>';
                echo '<td>' . $factura->codigo . '</td>';
                echo '<td>' . ($factura->codalbaran ?? '') . '</td>';
                echo '<td>' . date('d/m/Y', strtotime($factura->fecha)) . '</td>';
                echo '<td>' . $factura->nombrecliente . '</td>';
                echo '<td>' . $factura->cifnif . '</td>';
                echo '<td style="text-align: right;">' . number_format($factura->neto, 2, ',', '.') . '</td>';
                echo '<td style="text-align: right;">' . number_format($item->porcentajeIVA, 0, ',', '.') . '</td>';
                echo '<td style="text-align: right;">' . number_format($factura->totaliva, 2, ',', '.') . '</td>';
                echo '<td style="text-align: right;">' . number_format($item->porcentajeRE, 2, ',', '.') . '</td>';
                echo '<td style="text-align: right;">' . number_format($factura->totalrecargo, 2, ',', '.') . '</td>';
                echo '<td style="text-align: right;">' . number_format($factura->totalirpf, 2, ',', '.') . '</td>';
                echo '<td style="text-align: right; font-weight: bold;">' . number_format($factura->total, 2, ',', '.') . '</td>';
                echo '</tr>';
            }

            // Fila de totales
            echo '<tr style="background-color: #eeeeee; font-weight: bold;">';
            echo '<td colspan="6">TOTALES:</td>';
            echo '<td style="text-align: right;">' . number_format($this->totalNeto, 2, ',', '.') . '</td>';
            echo '<td></td>';
            echo '<td style="text-align: right;">' . number_format($this->totalIVA, 2, ',', '.') . '</td>';
            echo '<td></td>';
            echo '<td style="text-align: right;">' . number_format($this->totalRecargo, 2, ',', '.') . '</td>';
            echo '<td style="text-align: right;">' . number_format($this->totalIRPF, 2, ',', '.') . '</td>';
            echo '<td style="text-align: right;">' . number_format($this->totalGeneral, 2, ',', '.') . '</td>';
            echo '</tr>';

            echo '</table>';
            echo '<br><p>Total de facturas: ' . count($this->facturas) . '</p>';
            echo '</body></html>';

            $this->response->setContent(ob_get_clean());
        } catch (\Exception $e) {
            Tools::log()->error('Error exportando a Excel: ' . $e->getMessage());
        }
    }

    /**
     * Exporta el informe a PDF
     */
    private function exportarPDF(): void
    {
        if (empty($this->facturas)) {
            Tools::log()->warning('No hay facturas para exportar');
            return;
        }

        try {
            // Crear una instancia del exportador
            $exportManager = new ExportManager();

            // Configurar el documento PDF (usar PDF por defecto como el original)
            $exportManager->newDoc('PDF');

            // Título del documento
            $nombreEmpresa = $this->empresa ? $this->empresa->nombre : 'Empresa';
            $titulo = $nombreEmpresa . ' - Informe de Facturas';
            $subtitulo = 'Del ' . date('d/m/Y', strtotime($this->fechaDesde)) .
                        ' al ' . date('d/m/Y', strtotime($this->fechaHasta));

            // Definir las columnas
            $columns = [
                'SERIE', 'Doc.', 'Albarán', 'Fecha', 'Cliente', 'CIF/NIF',
                'Neto', '%IVA', 'IVA', '%RE', 'RE', 'IRPF', 'Total'
            ];

            // Preparar los datos
            $rows = [];
            foreach ($this->facturasConIVA as $item) {
                $factura = $item->factura;

                $rows[] = [
                    $factura->codserie,
                    $factura->codigo,
                    $factura->codalbaran ?? '',
                    date('d/m/Y', strtotime($factura->fecha)),
                    mb_substr($factura->nombrecliente, 0, 20),
                    $factura->cifnif,
                    number_format($factura->neto, 2, ',', '.'),
                    number_format($item->porcentajeIVA, 0, ',', '.'),
                    number_format($factura->totaliva, 2, ',', '.'),
                    number_format($item->porcentajeRE, 2, ',', '.'),
                    number_format($factura->totalrecargo, 2, ',', '.'),
                    number_format($factura->totalirpf, 2, ',', '.'),
                    number_format($factura->total, 2, ',', '.')
                ];
            }

            // Agregar fila de totales
            $rows[] = [
                '', '', '', '', '', 'TOTALES:',
                number_format($this->totalNeto, 2, ',', '.'),
                '',
                number_format($this->totalIVA, 2, ',', '.'),
                '',
                number_format($this->totalRecargo, 2, ',', '.'),
                number_format($this->totalIRPF, 2, ',', '.'),
                number_format($this->totalGeneral, 2, ',', '.')
            ];

            // Configurar opciones
            $options = [
                'title' => $titulo,
                'subtitle' => $subtitulo
            ];

            // Generar la tabla
            $exportManager->addTablePage($columns, $rows, $options);

            // SOLUCIÓN PARA ABRIR EN NUEVA PESTAÑA
            $this->setTemplate(false);

            // Configurar headers para nueva pestaña
            $filename = 'informe_facturas_' . date('Y-m-d_H-i-s') . '.pdf';
            $this->response->headers->set('Content-Type', 'application/pdf');
            $this->response->headers->set('Content-Disposition', 'inline; filename="' . $filename . '"');
            $this->response->headers->set('Cache-Control', 'private, max-age=0, must-revalidate');
            $this->response->headers->set('Pragma', 'public');

            // Mostrar el PDF
            $exportManager->show($this->response);

        } catch (\Exception $e) {
            Tools::log()->error('Error generando PDF: ' . $e->getMessage());
        }
    }
}
