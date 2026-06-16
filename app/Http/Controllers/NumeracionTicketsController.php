<?php

namespace App\Http\Controllers;

use App\Models\NumeracionTickets;
use App\Models\PuntoVenta;
use App\Models\Recibos;
use App\Models\Series;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class NumeracionTicketsController extends Controller
{
    public function index(){

        try {
            if (!$user = JWTAuth::parseToken()->authenticate()) {
                return response()->json(['user_not_found'], 404);
            }
        } catch (TokenExpiredException $e) {
            return response()->json(['token_expired', 'message' => $e->getMessage()], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid', 'message' => $e->getMessage()], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent', 'message' => $e->getMessage()], 401);
        }

        $numeracionTickets = NumeracionTickets::get()->load('series');
            $data = array(
                'numeracionTickets' => $numeracionTickets,
                'total' => @count($numeracionTickets),
                'status' => 200
            );

        return response()->json($data, $data['status']);
    }

    public function show($id, Request $request){

        try {
            if (!$user = JWTAuth::parseToken()->authenticate()) {
                return response()->json(['user_not_found'], 404);
            }
        } catch (TokenExpiredException $e) {
            return response()->json(['token_expired', 'message' => $e->getMessage()], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid', 'message' => $e->getMessage()], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent', 'message' => $e->getMessage()], 401);
        }

        if ($puntosVenta = PuntoVenta::find($id)) {

            $series = Series::where('idPuntoVenta', $puntosVenta->id)->get();
            if ($series->isNotEmpty()) {
                $idsSeries = $series->pluck('id');
                $numeracionTickets = NumeracionTickets::whereIn('idSeriesTickets', $idsSeries)->with('series')->get();
                $this->alinearNumeroActualTicketPosConRecibos((int) $puntosVenta->id, $numeracionTickets);
            } else {
                $numeracionTickets = [];
            }
            $data = array(
                'numeracionTickets' => $numeracionTickets,
                'status' => 200
            );

        }else{
            $data = array(
                'message' => 'Codigo no encontrado',
                'status' => 404
            );
        }

        return response()->json($data, $data['status']);
    }

    /**
     * Para la respuesta JSON: alinea `numeroActual` con el máximo en `tbl_recibos` cuando el numerador
     * va adelantado (+1 antiguo) o desincronizado (p. ej. muestra 199 pero el último ticket es 198).
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, NumeracionTickets>|\Illuminate\Support\Collection  $items
     */
    private function alinearNumeroActualTicketPosConRecibos(int $idPuntoVenta, $items): void
    {
        foreach ($items as $n) {
            $serieRel = $n->relationLoaded('series') ? $n->getRelation('series') : null;
            if (! $serieRel) {
                continue;
            }
            $serie = trim((string) ($serieRel->serie ?? ''));
            if ($serie === '') {
                continue;
            }
            $maxR = Recibos::maxNumeracionParaSeriePuntoVenta($idPuntoVenta, $serie);
            $d = (int) ($n->numeroActual ?? 0);
            if ($maxR > 0) {
                if ($d !== $maxR) {
                    $n->numeroActual = $maxR;
                }
            } elseif ($d === 1) {
                $n->numeroActual = 0;
            }
        }
    }

    public function store(Request $request){

        try {
            if (!$user = JWTAuth::parseToken()->authenticate()) {
                return response()->json(['user_not_found'], 404);
            }
        } catch (TokenExpiredException $e) {
            return response()->json(['token_expired', 'message' => $e->getMessage()], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid', 'message' => $e->getMessage()], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent', 'message' => $e->getMessage()], 401);
        }

    $params = $request->all();

    $numeracionTickets = new NumeracionTickets();
    $numeracionTickets->idSeriesTickets = $params['idSeriesTickets'] ?? null;
    $numeracionTickets->numeroInicio = $params['numeroInicio'] ?? null;
    $numeracionTickets->numeroFin = $params['numeroFin'] ?? null;
    $numeracionTickets->numeroActual = $params['numeroActual'] ?? null;
    $numeracionTickets->observaciones = $params['observaciones'] ?? null;
    $numeracionTickets->status = $params['status'] ?? null;
    $numeracionTickets->save();

        $data = array(
            'numeracionTickets' => $numeracionTickets,
            'message' => 'Registro agregado correctamente',
            'status' => 200
        );

        return response()->json($data, $data['status']);
    }

    public function update($id, Request $request){

        try {
            if (!$user = JWTAuth::parseToken()->authenticate()) {
                return response()->json(['user_not_found'], 404);
            }
        } catch (TokenExpiredException $e) {
            return response()->json(['token_expired', 'message' => $e->getMessage()], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid', 'message' => $e->getMessage()], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent', 'message' => $e->getMessage()], 401);
        }

        $params = $request->all();

        if($numeracionTickets = NumeracionTickets::find($id)){
            $numeracionTickets->idSeriesTickets = $params['idSeriesTickets'] ?? $numeracionTickets->idSeriesTickets;
            $numeracionTickets->numeroInicio = $params['numeroInicio'] ?? $numeracionTickets->numeroInicio;
            $numeracionTickets->numeroFin = $params['numeroFin'] ?? $numeracionTickets->numeroFin;
            $numeracionTickets->numeroActual = $params['numeroActual'] ?? $numeracionTickets->numeroActual;
            $numeracionTickets->observaciones = $params['observaciones'] ?? $numeracionTickets->observaciones;
            $numeracionTickets->status = $params['status'] ?? $numeracionTickets->status;
            $numeracionTickets->save();

            $data = array(
                'numeracionTickets' => $numeracionTickets,
                'message' => 'Registro actualizado correctamente.',
                'status' => 200
            );

        }else{

            $data = array(
                'message' => 'Codigo no encontrado',
                'status' => 404
            );
        }

        return response()->json($data, $data['status']);
    }

    public function destroy($id, Request $request){

        try {
            if (!$user = JWTAuth::parseToken()->authenticate()) {
                return response()->json(['user_not_found'], 404);
            }
        } catch (TokenExpiredException $e) {
            return response()->json(['token_expired', 'message' => $e->getMessage()], 401);
        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid', 'message' => $e->getMessage()], 401);
        } catch (JWTException $e) {
            return response()->json(['token_absent', 'message' => $e->getMessage()], 401);
        }

        if($numeracionTickets = NumeracionTickets::find($id)){

            $numeracionTickets->delete();

            $data = array(
                'numeracionTickets' => $numeracionTickets,
                'message' => 'Registro eliminado correctamente.',
                'status' => 200
            );

        }else{
            $data = array(
                'message' => 'Codigo no encontrado',
                'status' => 404
            );
        }

        return response()->json($data, $data['status']);
    }
}
