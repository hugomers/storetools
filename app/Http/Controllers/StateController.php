<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StateController extends Controller
{
    function __construct(){
        $access = env("ACCESS");//conexion a access de sucursal
        if(file_exists($access)){
        try{  $this->conn  = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
            }catch(\PDOException $e){ die($e->getMessage()); }
        }else{ die("$access no es un origen de datos valido."); }

        if(env("STORE") == 1){
            $contasol = env("CONTASOLDB");//conexion a access de sucursal
            if(file_exists($contasol)){
            try{  $this->con  = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$contasol."; Uid=; Pwd=;");
                }catch(\PDOException $e){ die($e->getMessage()); }
            }else{ die("$contasol no es un origen de datos valido."); }
        }
    }

    public function sales(){
        $store = env("STORE");
        if($store == 1){
            $fac = "SELECT
            TIPFAC&'-'&CODFAC AS ticket,
            TOTFAC AS total,
            CNOFAC AS cliente,
            FORMAT(FECFAC,'YYYY-mm-dd')&' '&FORMAT(HORFAC,'HH:mm:ss') AS created_at,
            FORMAT(FUMFAC,'YYYY-mm-dd')&' '&FORMAT(HORFAC,'HH:mm:ss') AS update_at
            FROM F_FAC
            WHERE TIPFAC = '8' AND REFFAC  NOT LIKE '%CREDITO%' AND REFFAC NOT LIKE '%OCUPAR%'";
            $exec = $this->conn->prepare($fac);
            $exec->execute();
            $invoices = $exec->fetchall(\PDO::FETCH_ASSOC);
            foreach($invoices as $invoice){
                $ptick [] = "'".$invoice['ticket']."'";
                $ticket = [
                    "client_name"=>utf8_encode($invoice['cliente']),
                    "created_at"=>$invoice['created_at'],
                    "update_at"=>$invoice['update_at'],
                    "ticket"=>$invoice['ticket'],
                    "total"=>round($invoice['total'],2),
                    "_store"=>$store
                ];
                $insert = DB::table('sales')->insertGetId($ticket);
                if($insert){
                    $sale =  $insert;
                    $prday = "SELECT
                    TIPLFA&'-'&CODLFA AS TICKET,
                    ARTLFA AS ARTICULO,
                    CANLFA AS CANTIDAD,
                    PRELFA AS PRECIO,
                    TOTLFA AS TOTAL,
                    COSLFA AS COSTO
                    FROM F_LFA WHERE TIPLFA&'-'&CODLFA = "."'".$invoice['ticket']."'";
                    $exec = $this->conn->prepare($prday);
                    $exec -> execute();
                    $profac=$exec->fetchall(\PDO::FETCH_ASSOC);
                    foreach($profac as $pro){
                        $costo= DB::connection('vizapi')->table('products')->where('code',$pro['ARTICULO'])->value('cost');
                        $cost = $costo == null ? 0 : $costo;
                        $produ  = [
                            "_sale"=>$sale,
                            "_product"=>$pro['ARTICULO'],
                            "amount"=>$pro['CANTIDAD'],
                            "price"=>$pro['PRECIO'],
                            "total"=>$pro['TOTAL'],
                            "cost"=>$cost
                        ];
                        $insert = DB::table('sale_body')->insert($produ);
                    }
                    $paday = "SELECT
                    TFALCO&'-'&CFALCO AS TICKET,
                    IMPLCO AS IMPORTE,
                    CP.DESCRIPCION AS PAGO,
                    MULLCO AS IDPAG
                    FROM F_LCO
                    LEFT JOIN (SELECT CODCNP AS CODIGO, DESCNP AS DESCRIPCION FROM F_CNP WHERE F_CNP.TIPCNP = 0) AS CP ON CP.CODIGO = F_LCO.CPALCO
                    WHERE TFALCO&'-'&CFALCO = "."'".$invoice['ticket']."'";
                    $exec = $this->conn->prepare($paday);
                    $exec -> execute();
                    $payfac=$exec->fetchall(\PDO::FETCH_ASSOC);
                    foreach($payfac as $pay){
                        $pag = $pay['PAGO'] == null ? "EFECTIVO (CEDIS)" : utf8_encode($pay['PAGO']);
                        $pays = [
                            "_sale"=>$sale,
                            "import"=>$pay['IMPORTE'],
                            "way_to_pay"=>utf8_encode($pag),
                            "id_mul"=>$pay['IDPAG']
                        ];
                        $insert = DB::table('sales_payment')->insert($pays);
                    }
                }
            }
            return response()->json(["id"=>$sale,"cuantos"=>count($ptick)]);
        }else{
            $fac = "SELECT
            TIPFAC&'-'&CODFAC AS ticket,
            TOTFAC AS total,
            CNOFAC AS cliente,
            FORMAT(FECFAC,'YYYY-mm-dd')&' '&FORMAT(HORFAC,'HH:mm:ss') AS created_at,
            FORMAT(FUMFAC,'YYYY-mm-dd')&' '&FORMAT(HORFAC,'HH:mm:ss') AS update_at
            FROM F_FAC";
            $exec = $this->conn->prepare($fac);
            $exec->execute();
            $invoices = $exec->fetchall(\PDO::FETCH_ASSOC);
            foreach($invoices as $invoice){
                $ptick [] = "'".$invoice['ticket']."'";
                $ticket = [
                    "client_name"=>utf8_encode($invoice['cliente']),
                    "created_at"=>$invoice['created_at'],
                    "update_at"=>$invoice['update_at'],
                    "ticket"=>$invoice['ticket'],
                    "total"=>round($invoice['total'],2),
                    "_store"=>$store
                ];
                $insert = DB::table('sales')->insertGetId($ticket);
                if($insert){
                    $sale = $insert;
                    $prday = "SELECT
                    TIPLFA&'-'&CODLFA AS TICKET,
                    ARTLFA AS ARTICULO,
                    CANLFA AS CANTIDAD,
                    PRELFA AS PRECIO,
                    TOTLFA AS TOTAL,
                    COSLFA AS COSTO
                    FROM F_LFA WHERE TIPLFA&'-'&CODLFA = "."'".$invoice['ticket']."'";
                    $exec = $this->conn->prepare($prday);
                    $exec -> execute();
                    $profac=$exec->fetchall(\PDO::FETCH_ASSOC);
                    foreach($profac as $pro){
                        $costo= DB::connection('vizapi')->table('products')->where('code',$pro['ARTICULO'])->value('cost');
                        $cost = $costo == null ? 0 : $costo;

                        $produ  = [
                            "_sale"=>$sale,
                            "_product"=>$pro['ARTICULO'],
                            "amount"=>$pro['CANTIDAD'],
                            "price"=>$pro['PRECIO'],
                            "total"=>$pro['TOTAL'],
                            "cost"=>$cost
                        ];
                        $insert = DB::table('sale_body')->insert($produ);
                    }
                    $paday = "SELECT
                    TFALCO&'-'&CFALCO AS TICKET,
                    IMPLCO AS IMPORTE,
                    CPTLCO AS PAGO,
                    MULLCO AS IDPAG
                    FROM F_LCO
                    WHERE TFALCO&'-'&CFALCO = "."'".$invoice['ticket']."'";
                    $exec = $this->conn->prepare($paday);
                    $exec -> execute();
                    $payfac=$exec->fetchall(\PDO::FETCH_ASSOC);
                    foreach($payfac as $pay){
                        $pag = $pay['PAGO'] == null ? "CONTADO EFECTIVO" : utf8_encode($pay['PAGO']);
                        $pays = [
                            "_sale"=>$sale,
                            "import"=>$pay['IMPORTE'],
                            "way_to_pay"=>utf8_encode($pag),
                            "id_mul"=>$pay['IDPAG']
                        ];
                        $insert = DB::table('sales_payment')->insert($pays);
                    }
                }
            }
            return response()->json(count($ptick));
        }

    }

    public function bills(){
        $store = env("STORE");
        if($store == 1){
            try{
            echo 'Inicio de replicacion de cedis';
            $account = "SELECT
            F_APU.ASIAPU AS ASIENTO,
            F_APU.CUEAPU AS CUENTA,
            F_MAE.NOMMAE AS NAMECUEN,
            FORMAT(F_APU.FCRAPU,'YYYY-mm-dd')&' '&'00:00:00' AS CREATED,
            SUM(F_APU.IMPAPU) AS IMPORTE
            FROM F_APU
            INNER JOIN F_MAE ON F_MAE.CODMAE = F_APU.CUEAPU
            WHERE [D-HAPU] = 'D' AND CUEAPU LIKE '410%'
            GROUP BY
                F_APU.ASIAPU,
                F_APU.CUEAPU,
                F_MAE.NOMMAE,
                FORMAT(F_APU.FCRAPU,'YYYY-mm-dd')&' '&'00:00:00'";
            // return $account;

            $exec = $this->con->prepare($account);
        }catch(\PDOException $e){ die($e->getMessage()); }
            $exec->execute();
            $entries = $exec->fetchall(\PDO::FETCH_ASSOC);
            foreach($entries as $entrie){
                $exist = DB::table('accounting_entries')->where([['entrie',$entrie['ASIENTO']],['_account',$entrie['CUENTA']],['created_at',$entrie['CREATED']]])->first();
                if($exist){

                }else{
                    $inscon = [
                        "_stores"=>$store,
                        "entrie"=>$entrie['ASIENTO'],
                        "_account"=>$entrie['CUENTA'],
                        "name_account"=>utf8_encode($entrie['NAMECUEN']),
                        "concept"=>utf8_encode(''),
                        "created_at"=>$entrie['CREATED'],
                        "import"=>$entrie['IMPORTE']
                    ];
                    $insert = DB::table('accounting_entries')->insert($inscon);
                }
            }
            echo 'Finalizacion de replicacion de cedis';
        }else{
            echo 'Inicio de replicacion de sucursal';
            $with = "SELECT
            CODRET,
            IIF( HORRET = '', FORMAT(FECRET,'YYYY-mm-dd')&' '&'00:00:00' ,FORMAT(FECRET,'YYYY-mm-dd')&' '&FORMAT(HORRET,'HH:mm:ss')) AS CREACION,
            CONRET,
            IMPRET,
            F_PRO.NOFPRO as nombre
            FROM F_RET
            INNER JOIN F_PRO ON F_PRO.CODPRO = F_RET.PRORET
            WHERE PRORET >800 AND YEAR(FECRET) = 2024";
            $exec = $this->conn->prepare($with);
            $exec->execute();
            $withdrawals = $exec->fetchall(\PDO::FETCH_ASSOC);
            // return mb_convert_encoding($withdrawals,'UTF-8');
            foreach($withdrawals as $withdrawal){
                $exisw = DB::table('withdrawals')->where([['_store',$store],['code',$withdrawal['CODRET']]])->first();
                if(!$exisw){
                    $inse = [
                        "code"=>$withdrawal['CODRET'],
                        "created_at"=>$withdrawal['CREACION'],
                        "concept"=>utf8_encode($withdrawal['CONRET']),
                        "import"=>$withdrawal['IMPRET'],
                        "provider_name"=>utf8_encode($withdrawal['nombre']),
                        "_store"=>$store,
                    ];
                $insret = DB::table('withdrawals')->insert($inse);
                }else{
                    $upd = DB::table('withdrawals')->where([['_store',$store],['code',$withdrawal['CODRET']]])->update(['import'=>$withdrawal['IMPRET']]);
                }
            }
            echo 'Finalizacion de replicacion de sucursal';
        }
    }
}
