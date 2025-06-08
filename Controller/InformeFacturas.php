<?php
/**
 * Controlador para generar informes de facturas de venta
 * 
 * @author Tu Nombre
 * @version 1.2
 */

namespace FacturaScripts\Plugins\InformeFacturas\Controller;

use FacturaScripts\Core\Base\Controller;
use FacturaScripts\Core\Base\ControllerPermissions;
use FacturaScripts\Dinamic\Model\FacturaCliente;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\Serie;
use FacturaScripts\Dinamic\Model\LineaFacturaCliente;
use FacturaScripts\Core\Tools;
use FacturaScripts\Dinamic\Lib\ExportManager;

class InformeFacturas extends Controller
{
    const MODEL_NAMESPACE = '\\FacturaScripts\\Dinamic\\Model\\';

    public $facturas = [];
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

    public function privateCore(&$response, $user, $permissions)
    {
        parent::privateCore($response, $user, $permissions);
        
        // Inicializar filtros con valores por defecto
        $this->inicializarFiltros();
        
        // Cargar datos para los filtros
        $this->cargarDatosFiltros();
        
        // Procesar acciones
        $action = $this->request->request->get('action');
        switch ($action) {
            case 'generar':
                $this->procesarFiltros();
                $this->generarInforme();
                break;
            case 'exportar_excel':
                $this->procesarFiltros();
                $this->generarInforme();
                $this->exportarExcel();
                break;
            case 'exportar_pdf':
                $this->procesarFiltros();
                $this->generarInforme();
                $this->exportarPDF();
                break;
        }
    }

    private function inicializarFiltros()
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

    private function procesarFiltros()
    {
        // Procesar filtros desde POST para exportaciones
        $this->fechaDesde = $this->request->request->get('fecha_desde', $this->fechaDesde);
        $this->fechaHasta = $this->request->request->get('fecha_hasta', $this->fechaHasta);
        $this->serieSeleccionada = $this->request->request->get('serie', '');
        $this->clienteSeleccionado = $this->request->request->get('cliente', '');
        $this->empresaSeleccionada = $this->request->request->get('empresa', '');
    }

    private function cargarDatosFiltros()
    {
        // Cargar series para el filtro
        $serieModel = new Serie();
        $this->series = $serieModel->all([], ['codserie' => 'ASC'], 0, 0);

        // Cargar empresas para el filtro
        $empresaModel = new Empresa();
        $this->empresas = $empresaModel->all([], ['nombrecorto' => 'ASC'], 0, 0);

        // Cargar clientes para el filtro (los 100 más recientes)
        $clienteModel = new Cliente();
        $this->clientes = $clienteModel->all([], ['razonsocial' => 'ASC'], 0, 100);
    }

    private function generarInforme()
    {
        $facturaModel = new FacturaCliente();
        
        // Construir filtros WHERE usando DataBaseWhere
        $where = [];

        // Filtro por fechas
        if (!empty($this->fechaDesde)) {
            $where[] = new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('fecha', $this->fechaDesde, '>=');
        }
        
        if (!empty($this->fechaHasta)) {
            $where[] = new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('fecha', $this->fechaHasta, '<=');
        }

        // Filtro por serie
        if (!empty($this->serieSeleccionada)) {
            $where[] = new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('codserie', $this->serieSeleccionada);
        }

        // Filtro por cliente
        if (!empty($this->clienteSeleccionado)) {
            $where[] = new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('codcliente', $this->clienteSeleccionado);
        }

        // Filtro por empresa
        if (!empty($this->empresaSeleccionada)) {
            $where[] = new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('idempresa', $this->empresaSeleccionada);
        }

        // Obtener facturas
        $this->facturas = $facturaModel->all($where, ['fecha' => 'ASC', 'numero' => 'ASC'], 0, 0);

        // Calcular totales
        $this->calcularTotales();

        // Obtener datos de la empresa para el encabezado
        $this->cargarEmpresa();
    }

    private function calcularTotales()
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

    private function cargarEmpresa()
    {
        $empresaModel = new Empresa();
        if (!empty($this->empresaSeleccionada)) {
            $this->empresa = $empresaModel->get($this->empresaSeleccionada);
        } else {
            $idEmpresaDefault = Tools::settings('default', 'idempresa');
            $this->empresa = $empresaModel->get($idEmpresaDefault);
        }
    }

    /**
     * Obtiene el porcentaje real de IVA desde las líneas de la factura
     * Esto soluciona el problema del 20.99% vs 21%
     */
    private function obtenerPorcentajeIVAReal($factura)
    {
        $lineaModel = new LineaFacturaCliente();
        $where = [new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('idfactura', $factura->idfactura)];
        $lineas = $lineaModel->all($where, [], 0, 1); // Solo necesitamos una línea para obtener el porcentaje
        
        if (!empty($lineas)) {
            return round($lineas[0]->iva, 2); // Devolvemos el porcentaje real redondeado
        }
        
        // Si no hay líneas, calculamos como antes pero redondeado
        if ($factura->neto != 0) {
            return round(($factura->totaliva / $factura->neto * 100), 0); // Redondeado a entero para evitar decimales
        }
        
        return 0;
    }

    /**
     * Igual para el recargo de equivalencia
     */
    private function obtenerPorcentajeREReal($factura)
    {
        $lineaModel = new LineaFacturaCliente();
        $where = [new \FacturaScripts\Core\Base\DataBase\DataBaseWhere('idfactura', $factura->idfactura)];
        $lineas = $lineaModel->all($where, [], 0, 1);
        
        if (!empty($lineas)) {
            return round($lineas[0]->recargo ?? 0, 2);
        }
        
        if ($factura->neto != 0) {
            return round(($factura->totalrecargo / $factura->neto * 100), 2);
        }
        
        return 0;
    }

    public function exportarExcel()
    {
        if (empty($this->facturas)) {
            Tools::log()->warning('No hay facturas para exportar');
            return;
        }

        // Configurar headers para descarga
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="informe_facturas_' . date('Y-m-d') . '.xls"');
        header('Cache-Control: max-age=0');

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

        foreach ($this->facturas as $factura) {
            // AQUÍ ESTÁ LA CORRECCIÓN DEL IVA - Usamos el porcentaje real
            $porcentajeIVA = $this->obtenerPorcentajeIVAReal($factura);
            $porcentajeRE = $this->obtenerPorcentajeREReal($factura);
            
            echo '<tr>';
            echo '<td>' . $factura->codserie . '</td>';
            echo '<td>' . $factura->codigo . '</td>';
            echo '<td>' . ($factura->codalbaran ?? '') . '</td>';
            echo '<td>' . date('d/m/Y', strtotime($factura->fecha)) . '</td>';
            echo '<td>' . $factura->nombrecliente . '</td>';
            echo '<td>' . $factura->cifnif . '</td>';
            echo '<td style="text-align: right;">' . number_format($factura->neto, 2, ',', '.') . '</td>';
            echo '<td style="text-align: right;">' . number_format($porcentajeIVA, 0, ',', '.') . '</td>'; // Sin decimales para el %
            echo '<td style="text-align: right;">' . number_format($factura->totaliva, 2, ',', '.') . '</td>';
            echo '<td style="text-align: right;">' . number_format($porcentajeRE, 2, ',', '.') . '</td>';
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
        exit;
    }

    public function exportarPDF()
    {
        if (empty($this->facturas)) {
            Tools::log()->warning('No hay facturas para exportar');
            return;
        }

        try {
            // Crear una instancia del exportador
            $exportManager = new ExportManager();
            
            // Configurar el documento PDF
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
            foreach ($this->facturas as $factura) {
                // CORRECCIÓN DEL IVA TAMBIÉN EN PDF
                $porcentajeIVA = $this->obtenerPorcentajeIVAReal($factura);
                $porcentajeRE = $this->obtenerPorcentajeREReal($factura);
                
                $rows[] = [
                    $factura->codserie,
                    $factura->codigo,
                    $factura->codalbaran ?? '',
                    date('d/m/Y', strtotime($factura->fecha)),
                    mb_substr($factura->nombrecliente, 0, 20),
                    $factura->cifnif,
                    number_format($factura->neto, 2, ',', '.'),
                    number_format($porcentajeIVA, 0, ',', '.'), // Sin decimales
                    number_format($factura->totaliva, 2, ',', '.'),
                    number_format($porcentajeRE, 2, ',', '.'),
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
            Tools::log()->error('Error al generar PDF: ' . $e->getMessage());
            $this->toolBox()->i18nLog()->error('Error al generar el PDF: ' . $e->getMessage());
        }
    }

    protected function getPermissions(): ControllerPermissions
    {
        $permissions = parent::getPermissions();
        $permissions->allowAccess = true;
        $permissions->allowDelete = false;
        $permissions->allowUpdate = false;
        return $permissions;
    }
}