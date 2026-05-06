<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotaVenta;
use App\Models\Propiedad;
use App\Models\PlanPago;
use App\Models\Cuota;
use App\Models\Pago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class NotaVentaController extends Controller
{
    // 1. Listar todas las ventas
    // 1. Listar ventas (CON FILTROS AVANZADOS)
    public function index(Request $request) {
        $query = NotaVenta::with(['asesor', 'cliente', 'propiedad.propietario', 'propiedad.zona.ciudad', 'pagos.metodoPago']);

        // Filtro por Fechas
        if ($request->filled('fecha_inicio') && $request->filled('fecha_fin')) {
            $query->whereBetween('fecha', [$request->fecha_inicio, $request->fecha_fin]);
        }
        // Filtro por Cliente
        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }
        // Filtro por Asesor
        if ($request->filled('asesor_id')) {
            $query->where('asesor_id', $request->asesor_id);
        }
        // Filtro por Tipo de Venta
        if ($request->filled('tipo_venta') && $request->tipo_venta !== 'TODOS') {
            $query->where('tipo_venta', $request->tipo_venta);
        }

        $perPage = $request->input('per_page', 10);
        $ventas = $query->orderBy('fecha', 'desc')->orderBy('id', 'desc')->paginate($perPage);

        return response()->json($ventas, 200);
    }

    // 2. Registrar Nueva Venta
    public function store(Request $request) {
        $validatedData = $request->validate([
            'asesor_id'      => 'required|exists:asesores,id',
            'cliente_id'     => 'required|exists:clientes,id',
            'propiedad_id'   => 'required|exists:propiedades,id',
            'fecha'          => 'required|date',
            'monto_total'    => 'required|numeric|min:0',
            'monto_comision' => 'nullable|numeric|min:0',
            'tipo_venta'     => 'required|in:CONTADO,CREDITO',

            // Contado
            'descuento'      => 'nullable|numeric|min:0',
            'monto_liquido'  => 'required_if:tipo_venta,CONTADO|nullable|numeric|min:0',

            // Crédito (Agregamos validaciones para el plan de pagos)
            'cuota_inicial'  => 'required_if:tipo_venta,CREDITO|nullable|numeric|min:0',
            'saldo_credito'  => 'required_if:tipo_venta,CREDITO|nullable|numeric|min:0',
            'numero_cuotas'  => 'required_if:tipo_venta,CREDITO|nullable|integer|min:1',
            'tasa_interes'   => 'required_if:tipo_venta,CREDITO|nullable|numeric|min:0',
            'fecha_inicio_pago' => 'required_if:tipo_venta,CREDITO|nullable|date',
        ]);

        try {
            DB::beginTransaction();

            $propiedad = Propiedad::findOrFail($validatedData['propiedad_id']);
            if ($propiedad->estado === 'Vendido') {
                return response()->json(['message' => 'Esta propiedad ya fue vendida.'], 400);
            }

            // 1. Creamos la Nota de Venta (Quitamos los campos del plan para que no fallen en el create masivo)
            $ventaData = collect($validatedData)->except(['numero_cuotas', 'tasa_interes', 'fecha_inicio_pago'])->toArray();
            $venta = NotaVenta::create($ventaData);

            $propiedad->update(['estado' => 'Vendido']);

            // 2. 🌟 REGISTRAR PAGO COMO PENDIENTE DE PAGO
            if ($venta->tipo_venta === 'CONTADO') {
                Pago::create([
                    'nota_venta_id'  => $venta->id,
                    'cuota_id'       => null,
                    'metodo_pago_id' => null,
                    'cuenta_id'      => null,
                    'concepto_pago'  => 'VENTA_CONTADO',
                    'fecha_pago'     => null,
                    'monto'          => $validatedData['monto_liquido'],
                    'estado'         => 'PENDIENTE_PAGO',
                    'observaciones'  => 'Pago pendiente de registrar'
                ]);
            } elseif ($venta->tipo_venta === 'CREDITO') {
                Pago::create([
                    'nota_venta_id'  => $venta->id,
                    'cuota_id'       => null,
                    'metodo_pago_id' => null,
                    'cuenta_id'      => null,
                    'concepto_pago'  => 'CUOTA_INICIAL',
                    'fecha_pago'     => null,
                    'monto'          => $validatedData['cuota_inicial'],
                    'estado'         => 'PENDIENTE_PAGO',
                    'observaciones'  => 'Cuota inicial pendiente de pago'
                ]);
            }

            // 3. SI ES CRÉDITO, GENERAMOS EL PLAN DE PAGOS AUTOMÁTICAMENTE
            if ($venta->tipo_venta === 'CREDITO') {
                $this->generarPlanDePagos(
                    $venta->id,
                    $validatedData['saldo_credito'],
                    $validatedData['numero_cuotas'],
                    $validatedData['tasa_interes'],
                    $validatedData['fecha_inicio_pago']
                );
            }

            DB::commit();
            return response()->json(['message' => 'Venta registrada con éxito', 'data' => $venta->load('planPago.cuotas', 'pagos')], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al registrar la venta: ' . $e->getMessage()], 500);
        }
    }

    // 3. Ver detalle de una venta
    public function show($id) {
        $venta = NotaVenta::with([
            'asesor',
            'cliente',
            'propiedad.propietario',
            'propiedad.zona.ciudad',
            'planPago.cuotas',
            'pagos.metodoPago'
        ])->findOrFail($id);

        return response()->json($venta, 200);
    }

    // 4. Anular Venta (No se borra físicamente, se cambia estado)
    public function anular($id) {
        try {
            DB::beginTransaction();

            $venta = NotaVenta::findOrFail($id);
            
            if ($venta->estado === 'Anulada') {
                return response()->json(['message' => 'La venta ya se encuentra anulada'], 400);
            }

            // 1. Anulamos la venta
            $venta->update(['estado' => 'Anulada']);

            // 2. Liberamos la propiedad
            $propiedad = Propiedad::findOrFail($venta->propiedad_id);
            $propiedad->update(['estado' => 'Disponible']);

            DB::commit();
            return response()->json(['message' => 'Venta anulada y propiedad liberada correctamente'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error al anular la venta: ' . $e->getMessage()], 500);
        }
    }

    private function generarPlanDePagos($ventaId, $capitalPrestado, $meses, $tasaAnual, $fechaInicio) {
        
        $fechaFinal = Carbon::parse($fechaInicio)->addMonths($meses - 1); // -1 porque la primera cuota es el mes 1

        // 1. Crear la cabecera del Plan
        $plan = PlanPago::create([
            'nota_venta_id' => $ventaId,
            'monto'         => $capitalPrestado,
            'numero_cuotas' => $meses,
            'fecha_inicio'  => $fechaInicio,
            'fecha_final'   => $fechaFinal,
            'plazo'         => $meses . ' Meses',
            'tasa_interes'  => $tasaAnual,
        ]);

        // 2. Cálculos Matemáticos
        $tasaMensual = ($tasaAnual / 100) / 12;
        $saldoSobrante = $capitalPrestado;
        $fechaVencimiento = Carbon::parse($fechaInicio);

        // Si la tasa es > 0 aplicamos fórmula francesa, si es 0 es división simple
        if ($tasaMensual > 0) {
            $cuotaFija = $capitalPrestado * ($tasaMensual * pow(1 + $tasaMensual, $meses)) / (pow(1 + $tasaMensual, $meses) - 1);
        } else {
            $cuotaFija = $capitalPrestado / $meses;
        }

        // 3. Generar e insertar las cuotas mes a mes
        $cuotasInsert = [];
        for ($i = 1; $i <= $meses; $i++) {
            
            $interesMes = $saldoSobrante * $tasaMensual;
            $capitalMes = $cuotaFija - $interesMes;
            
            // Ajuste por redondeo en la última cuota
            if ($i == $meses) {
                $capitalMes = $saldoSobrante;
                $cuotaFija = $capitalMes + $interesMes;
            }

            $saldoSobrante -= $capitalMes;

            $cuotasInsert[] = [
                'plan_pago_id'      => $plan->id,
                'numero_cuota'      => $i,
                'fecha_vencimiento' => $fechaVencimiento->format('Y-m-d'),
                'monto_cuota'       => round($cuotaFija, 2),
                'monto_interes'     => round($interesMes, 2),
                'monto_capital'     => round($capitalMes, 2),
                'saldo_capital'     => round(max(0, $saldoSobrante), 2),
                'estado'            => 'Pendiente',
                'created_at'        => now(),
                'updated_at'        => now()
            ];

            // Sumamos un mes para la siguiente cuota
            $fechaVencimiento->addMonth(); 
        }

        // Inserción masiva para máxima eficiencia en la BD
        Cuota::insert($cuotasInsert);
    }
}
