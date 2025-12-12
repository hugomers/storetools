<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillingController extends Controller
{

    public function __construct(){
        $access = env("ACCESS");
        if(file_exists($access)){
        try{  $this->conn  = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
            }catch(\PDOException $e){ die($e->getMessage()); }
        }else{ die("$access no es un origen de datos valido."); }
    }




    public function validateTck(Request $request){
        $folio =  $request->folio;
        $sql = "SELECT
        TIPFAC&'-'&FORMAT(CODFAC,'000000') AS Ticket,
        FECFAC AS Fecha,
        TOTFAC AS Total
        FROM F_FAC WHERE TIPFAC&'-'&FORMAT(CODFAC,'000000') = ?";
        $exec = $this->conn->prepare($sql);
        $exec -> execute([$folio]);
        $ticket = $exec->fetch(\PDO::FETCH_ASSOC);
        if($ticket){
                $fapas = "SELECT FPALCO, CPTLCO, IMPLCO FROM F_LCO WHERE TFALCO&'-'&FORMAT(CFALCO,'000000') = ?";
                $exec = $this->conn->prepare($fapas);
                $exec -> execute([$ticket['Ticket']]);
                $ticket['fpas'] = $exec->fetchall(\PDO::FETCH_ASSOC);

                return response()->json($ticket,201);

        }else{
            return response()->json(["message"=>'No se encuentra el Folio'],404);
        }
    }

    public function getTckBilling(Request $request){
        $folio =  $request->folio;
        $sql = "SELECT
        TIPFAC&'-'&FORMAT(CODFAC,'000000') AS Ticket,
        FECFAC AS Fecha,
        TOTFAC AS Total
        FROM F_FAC WHERE TIPFAC&'-'&FORMAT(CODFAC,'000000') = ?";
        $exec = $this->conn->prepare($sql);
        $exec -> execute([$folio]);
        $ticket = $exec->fetch(\PDO::FETCH_ASSOC);
        if($ticket){
                $prdc = "SELECT ARTLFA, DESLFA, CANLFA, PRELFA, TOTLFA FROM F_LFA WHERE TIPLFA&'-'&FORMAT(CODLFA,'000000') = ?";
                $exec = $this->conn->prepare($prdc);
                $exec -> execute([$ticket['Ticket']]);
                $ticket['products'] = $exec->fetchall(\PDO::FETCH_ASSOC);
                return response()->json($ticket,201);

        }else{
            return response()->json(["message"=>'No se encuentra el Folio'],404);
        }
    }

    public function getServerFac(Request $request){
        $billing = $request->all();
        $cliente = DB::connection($billing['store']['firebird'])->table('CLIE01')->where('RFC',$billing['rfc'])->first();
        if($cliente){
            $billing['nclient']= intval($cliente->CLAVE);
        }else{
            $billing['nclient']=null;
        }
        $products = $billing['ticketSuc']['products'];
        foreach($products as &$product){
            $existProduct = DB::connection($request->store['firebird'])->table('INVE01')->where('CVE_ART',$product['ARTLFA'])->first();
            if($existProduct){
                $product['sat']=[
                    "clave"=>$existProduct->CVE_PRODSERV,
                    "unidad"=>$existProduct->CVE_UNIDAD,
                ];
            }else{
                $product['sat']=[
                    "clave"=>null,
                    "unidad"=>null,
                ];
            }
        }
        $billing['ticketSuc']['products'] = $products;
        return response()->json($billing,200);
        // return response()->json($request->all());
        // $folio = DB::connection($request->store['firebird'])->table('FACTF01')->where('SERIE',$request->store['prefix'])->max('FOLIO');
    }

    public function getFolio(Request $request){
        $billing = $request->all();
        $folio = DB::connection($request->firebird)->table('FACTF01')->where('SERIE',$request->prefix)->max('FOLIO');
        return response()->json($folio,200);
    }


    public function crearFacturaInterna(Request $request){
        $conn = $request->input('store.firebird', 'firebird');

        $nclient = intval($request->input('nclient', 0));
        $totalConIVA = floatval($request->input('total', 0));
        $ticket = $request->input('ticket', '');
        $serie = $request->input('store.prefix', 'N');
        $products = $request->input('ticketSuc.products', []);
        $payments = $request->input('payments', []);
        $almacen =1;
        $rfc = $request->input('rfc', 'XAXX010101000');
        $razon_social = $request->input('razon_social', 'PUBLICO GENERAL');

        $subtotal = round($totalConIVA / 1.16, 2);
        $iva = round($subtotal * 0.16, 2);

        try {
            $result = DB::connection($conn)->transaction(function () use (
                $conn, $serie, $nclient, $subtotal, $iva, $totalConIVA,
                $ticket, $products, $payments, $almacen, $rfc, $razon_social
            ) {
                $row = DB::connection($conn)->selectOne(
                    "SELECT ULT_DOC FROM FOLIOSF01 WHERE TIP_DOC = ? AND SERIE = ?",
                    ['F', $serie]
                );

                $ultimo = $row ? intval($row->ULT_DOC) : 0;
                $folioNum = $ultimo + 1;
                $folioStr = $serie. str_pad($folioNum, 10, '0', STR_PAD_LEFT);

                if (!$row) {
                    DB::connection($conn)->insert(
                        "INSERT INTO FOLIOSF01 (TIP_DOC, SERIE, ULT_DOC) VALUES (?, ?, ?)",
                        ['F', $serie, $folioNum]
                    );
                } else {
                    DB::connection($conn)->update(
                        "UPDATE FOLIOSF01 SET ULT_DOC = ? WHERE TIP_DOC = ? AND SERIE = ?",
                        [$folioNum, 'F', $serie]
                    );
                }
                DB::connection($conn)->insert(
                    "INSERT INTO FACTF01
                    (
                        TIP_DOC,
                        CVE_DOC,
                        CVE_CLPV,
                        STATUS,
                        NUM_ALMA,
                        SERIE,
                        FOLIO,
                        FECHA_DOC,
                        FECHA_ENT,
                        FECHA_VEN,


                        CAN_TOT,
                        IMP_TOT1,
                        IMP_TOT2,
                        IMP_TOT3,

                        IMP_TOT4,
                        DES_TOT,
                        DES_FIN,
                        COM_TOT,
                        CVE_OBS,
                        ACT_CXC,--S
                        ACT_COI,--N
                        ENLAZADO, --O
                        TIP_DOC_E, --O
                        NUM_MONED, --1,
                        TIPCAMB, --1,
                        NUM_PAGOS, --1,
                        FECHAELAB, -- CURRENT_TIMESTAMP,
                        PRIMERPAGO,--MONTO DE EL PAGO ESTE SI SE JALA
                        CTLPOL, --0,
                        ESCFD, -- T,
                        AUTORIZA, --0,
                        CONSTADO, --S
                        IMPORTE,
                        RFC
                    ) VALUES (
                        ?, ? , ?, ?, ?, ?, ?, CURRENT_DATE, CURRENT_DATE, CURRENT_DATE, ?,0,0,0 ?,0,0,0,0, ?, ?
                    )",
                    [
                        'F',
                        $folioStr,
                        $nclient,
                        'E',
                        $almacen,
                        $serie,
                        $folioNum,
                        $subtotal,
                        $iva,
                        $totalConIVA,
                        $rfc,
                        // $razon_social,
                        // $ticket
                    ]
                );
                $numPar = 1;
                foreach ($products as $p) {

                    $cveArt = $p['ARTLFA'];
                    $cantidad = floatval($p['CANLFA']);
                    $precioConIVA = floatval($p['PRELFA']);
                    $precioSinIVA = round($precioConIVA / 1.16, 2);
                    $totalPartida = round($cantidad * $precioSinIVA, 2);

                    DB::connection($conn)->insert(
                        "INSERT INTO PAR_FACTF01
                        (CVE_DOC, NUM_PAR, CVE_ART, CANT, PXS, PREC, TOT_PARTIDA)
                        VALUES ( ?, ?, ?, ?, ?, ?, ?)",
                        [
                            $folioStr, $numPar, $cveArt,
                            $cantidad, $precioSinIVA, $precioSinIVA, $totalPartida
                        ]
                    );

                    $costRow = DB::connection($conn)->selectOne(
                        "SELECT ULT_COSTO FROM INVE01 WHERE CVE_ART = ?",
                        [$cveArt]
                    );
                    $costoActual = $costRow ? floatval($costRow->ULT_COSTO) : $precioSinIVA;

                    $numMovRow = DB::connection($conn)->selectOne("SELECT MAX(NUM_MOV) AS MX FROM MINVE01");
                    $nextNumMov = ($numMovRow && $numMovRow->MX) ? intval($numMovRow->MX) + 1 : 1;

                    DB::connection($conn)->insert(
                        "INSERT INTO MINVE01
                        (CVE_ART, ALMACEN, NUM_MOV, CVE_CPTO, FECHA_DOCU, CANT, COSTO, REFER, SIGNO, CLAVE_CLPV)
                        VALUES (?, ?, ?, ?, CURRENT_DATE, ?, ?, ?, ?, ?)",
                        [
                            $cveArt, $almacen, $nextNumMov, 51,
                            $cantidad, $costoActual, $folioStr, -1, $nclient
                        ]
                    );

                    // DB::connection($conn)->update(
                    //     "UPDATE INVEVT01 SET EXIST = COALESCE(EXIST,0) - ? WHERE CVE_ART = ? AND ALMACEN = ?",
                    //     [$cantidad, $cveArt, $almacen]
                    // );

                    $numPar++;
                }

                $firstPayment = $payments[0] ?? null;

                $importePago = $firstPayment
                    ? floatval($firstPayment['import'])
                    : $totalConIVA;

                $method = $firstPayment
                    ? strval($firstPayment['sat'])     // ← tu valor directo
                    : '01';

                // DB::connection($conn)->insert(
                //     "INSERT INTO FACTP01
                //     (TIP_DOC, FOLIO, NUM_CARGO, IMPORTE, FORMADEPAGOSAT, FECHAPAG)
                //     VALUES (?, ?, ?, ?, ?, CURRENT_DATE)",
                //     ['F', $folioNum, 1, $importePago, $method]
                // );

                // DB::connection($conn)->insert(
                //     "INSERT INTO CXC01 (CVE_CLPV, REFER, IMPORTE, SALDO, FECHA_APLI, FECHA_VENC, CVE_DOC)
                //      VALUES (?, ?, ?, ?, CURRENT_DATE, CURRENT_DATE, ?)",
                //     [$nclient, $folioStr, $importePago, 0, $folioStr]
                // );

                // DB::connection($conn)->insert(
                //     "INSERT INTO CXCM01 (CVE_CLPV, REFER, IMPORTE, IMPORTE_PAG, FECHA_APLI)
                //      VALUES (?, ?, ?, ?, CURRENT_DATE)",
                //     [$nclient, $folioStr, $importePago, $importePago]
                // );

                return [
                    'folio_num' => $folioNum,
                    'folio_str' => $folioStr,
                    'serie' => $serie,
                    'subtotal' => $subtotal,
                    'iva' => $iva,
                    'total' => $totalConIVA,
                    'forma_pago_sat' => $method
                ];
            });

            return response()->json([
                'ok' => true,
                'message' => 'Factura creada internamente en SAE.',
                'data' => $result
            ], 201);

        } catch (Exception $e) {

            return response()->json([
                'ok' => false,
                'message' => 'Error al crear factura en SAE.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
