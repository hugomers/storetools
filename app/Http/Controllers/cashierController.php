<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\Printer;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

class cashierController extends Controller
{

    public function __construct(){
        $access = env("ACCESS");//conexion a access de sucursal
        if(file_exists($access)){
        try{  $this->conn  = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
            }catch(\PDOException $e){ die($e->getMessage()); }
        }else{ die("$access no es un origen de datos valido."); }
    }

    public function msg($msg,$number){
        $token = env('TOKEN_ULTRAMSG');
        $instance = env('ID_INSTANCE');
        $params=array(
            'token' => $token,
            'to' => $number,
            // 'to' => '5215534217709-1549068988@g.us',
            'body' => $msg
            );
            $curl = curl_init();
            curl_setopt_array($curl, array(
              CURLOPT_URL => "https://api.ultramsg.com/".$instance."/messages/chat",
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => "",
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 30,
              CURLOPT_SSL_VERIFYHOST => 0,
              CURLOPT_SSL_VERIFYPEER => 0,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => "POST",
              CURLOPT_POSTFIELDS => http_build_query($params),
              CURLOPT_HTTPHEADER => array(
                "content-type: application/x-www-form-urlencoded"
              ),
            ));

            $response = curl_exec($curl);
            $err = curl_error($curl);

            curl_close($curl);

            if ($err) {
              return "cURL Error #:" . $err;
            } else {
              return $response;
            }

    }

    // public function opencashier(Request $request){
    //     $numer = env('number');
    //     if($request->has("mensaje")){
    //       $msg= "No se realizo la apertura por falta de firmas";
    //       $this->msg($msg,$numer);
    //     }else{
    //         $caja = $request->caja;
    //         switch($caja){
    //             case 1:
    //               $terminal  = "CAJAUNO";
    //             break;
    //             case 2:
    //               $terminal  = "CAJADOS";
    //             break;
    //             case 3:
    //               $terminal  = "CAJATRES";
    //             break;
    //             case 4:
    //               $terminal  = "CAJACUATRO";
    //             break;
    //             case 5:
    //               $terminal  = "CAJACINCO";
    //             break;
    //             case 6:
    //               $terminal  = "CAJASEIS";
    //             break;
    //             case 7:
    //               $terminal  = "CAJASIETE";
    //             break;
    //             case 8:
    //               $terminal  = "CAJAOCHO";
    //             break;
    //             case 9:
    //               $terminal  = "CAJANUEVE";
    //             break;
    //         }
    //         $cajaob = "SELECT CODTER FROM T_TER WHERE DESTER LIKE "."'"."%%".$terminal."'";
    //         $exec = $this->conn->prepare($cajaob);
    //         $exec->execute();
    //         $cajat=$exec->fetch(\PDO::FETCH_ASSOC);

    //     if($request->tipo_mov == "MAL DEVOLUCION"){
    //       $apertura = "UPDATE T_TER SET FECTER = DATE(), SINTER = 5000, ESTTER = 1, EFETER = 0, HOATER = TIME() WHERE CODTER = ".$cajat['CODTER'];
    //       $exec = $this->conn->prepare($apertura);
    //       $result = $exec->execute();
    //       if($result){
    //           $msg = "la ".$terminal." se abrio por solicitud de: ".$request->solicitante." con la observacion: ".$request->obs." el ticket original: ".$request->tcko." ticket devolucion: ".$request->tckd;
    //           $this->msg($msg,$numer);
    //           return response()->json($result);
    //       }else{
    //           return response()->json("no se pudo abrir la caja");
    //       }
    //     }else if($request->tipo_mov == "RETIRADA MAL"){
    //         if($request->montonuevo == 0 ){
    //             $retob = "UPDATE F_RET SET IMPRET = ".$request->montonuevo.", CONRET = '', PRORET = 0 WHERE CODRET = ".$request->retirada." AND FECRET = DATE() AND CAJRET = ".$cajat['CODTER'];
    //             $exec = $this->conn->prepare($retob);
    //             $result = $exec->execute();
    //             if($result){
    //                 $msg = "La retirada ".$request->retirada." se elimino correctamente";
    //             }else{
    //                 $msg = "La retirada ".$request->retirada." no se elimino correctamente";
    //             }
    //             $this->msg($msg,$number);
    //         }else{
    //             $retob = "UPDATE F_RET SET IMPRET = ".$request->montonuevo." WHERE CODRET = ".$request->retirada." AND FECRET = DATE() AND CAJRET = ".$cajat['CODTER'];
    //             $exec = $this->conn->prepare($retob);
    //             $result = $exec->execute();
    //             if($result){
    //                 $impresora = $this->prinret($terminal,$request->retirada,$request->montonuevo);
    //                 if($impresora === "impreso con exito"){
    //                     $msg = "La retirada ".$request->retirada." se modifico correctamente";
    //                 }else{
    //                     $apertura = "UPDATE T_TER SET FECTER = DATE(), SINTER = 5000, ESTTER = 1, EFETER = 0, HOATER = TIME() WHERE CODTER = ".$cajat['CODTER'];
    //                     $exec = $this->conn->prepare($apertura);
    //                     $result = $exec->execute();
    //                     if($result){
    //                         $msg = "La retirada ".$request->retirada." se modifico correctamente pero no se logro imprimir la caja esta abierta para realizar la impresion";
    //                     }else{
    //                         $msg = "La retirada ".$request->retirada." se modifico chido pero no se abrio tu caja ni se imprimio hablale a Dieguito parito";
    //                     }
    //                 }
    //                 $this->msg($msg,$numer);
    //                 return response()->json($result);
    //             }else{
    //                 return response()->json("no se pudo modificar la retirada");
    //             }
    //         }
    //     }else if($request->tipo_mov == "DESCUADRE"){
    //         $apertura = "UPDATE T_TER SET FECTER = DATE(), SINTER = 5000, ESTTER = 1, EFETER = 0, HOATER = TIME() WHERE CODTER = ".$cajat['CODTER'];
    //         $exec = $this->conn->prepare($apertura);
    //         $result = $exec->execute();
    //         if($result){
    //             $msg = "la ".$terminal." se abrio por para revision de descuadre";
    //             $this->msg($msg,$numer);
    //             return response()->json($result);
    //         }else{
    //             return response()->json("no se pudo abrir la caja");
    //         }
    //     }
    //   }
    // }

    // public function prinret($caja,$retiro,$import){
    //     $store = env('STORE');
    //     $print = DB::table('cash_printers')->where('name',$caja)->where('_store',$store)->value('ip_address');
    //     $empresa = "SELECT TOP 1 * FROM F_EMP";
    //     $exec = $this->conn->prepare($empresa);
    //     $exec->execute();
    //     $emp =$exec->fetch(\PDO::FETCH_ASSOC);
    //     $retirada = "SELECT * FROM F_RET INNER JOIN F_PRO ON F_PRO.CODPRO = F_RET.PRORET WHERE CODRET =".$retiro;
    //     $exec = $this->conn->prepare($retirada);
    //     $exec->execute();
    //     $ret =$exec->fetch(\PDO::FETCH_ASSOC);
    //     $suc = DB::table('stores')->where('id',$store)->first();

    //     $connector = new NetworkPrintConnector($print, 9100, 3);
    //     if($connector){
    //         $printer = new Printer($connector);
    //         $printer->text(" \n");
    //         $printer->text(" \n");
    //         $printer->text("           --MODIFICACION DE RETIRADA--           \n");
    //         $printer->text(" \n");
    //         $printer->text(" \n");
    //         $printer->text($emp['NOMEMP']." \n");
    //         $printer->text($emp['DOMEMP']." (".$emp['POBEMP'].") "." \n");
    //         $printer->text("Tfno:".$emp['TELEMP']." \n");
    //         $printer->text("______________________________________________"." \n");
    //         $printer->text("SALIDA DE TERMINAL: ".$ret['CAJRET']." \n");
    //         $printer->text("Nº:".$ret['CODRET']."   ".date('d/m/Y',strtotime($ret['FECRET']))."-".$ret['HORRET']." \n");
    //         $printer->text("DEPENDIENTE:"."MONDAY"." \n");
    //         $printer->text("______________________________________________"." \n");
    //         $printer->text($ret['NOFPRO']." \n");
    //         $printer->text(" \n");
    //         $printer->text("00000"." \n");
    //         $printer->text(" \n");
    //         $printer->text("GVC:"." \n");
    //         $printer->text("______________________________________________"." \n");
    //         $printer->text("IMPORTE RETIRADO:                   ".$import." \n");
    //         $printer->text("Concepto:"." \n");
    //         $printer->text($ret['CONRET']." \n");
    //         $printer->text("______________________________________________"." \n");
    //         $printer->cut();
    //         $printer->close();
    //         return "impreso con exito";
    //     }else{
    //         return "No se pudo imprimir";
    //     }
    // }

    public function opencash(Request $request){
        $caja = $request->_cash;
        // return intval($caja);
        // switch($caja){
        //     case 1:
        //         $terminal  = "CAJAUNO";
        //     break;
        //     case 2:
        //         $terminal  = "CAJADOS";
        //     break;
        //     case 3:
        //         $terminal  = "CAJATRES";
        //     break;
        //     case 4:
        //         $terminal  = "CAJACUATRO";
        //     break;
        //     case 5:
        //         $terminal  = "CAJACINCO";
        //     break;
        //     case 6:
        //         $terminal  = "CAJASEIS";
        //     break;
        //     case 7:
        //         $terminal  = "CAJASIETE";
        //     break;
        //     case 8:
        //         $terminal  = "CAJAOCHO";
        //     break;
        //     case 9:
        //         $terminal  = "CAJANUEVE";
        //     break;
        // }
        // $cajaob = "SELECT CODTER FROM T_TER WHERE DESTER LIKE "."'"."%%".$terminal."'";
        // $cajaob = "SELECT CODTER FROM T_TER WHERE CODTER = 51";
        // $exec = $this->conn->prepare($cajaob);
        // $exec->execute();
        // $cajat=$exec->fetch(\PDO::FETCH_ASSOC);


        $apertura = "UPDATE T_TER SET FECTER = DATE(), SINTER = 5000, ESTTER = 1, EFETER = 0, HOATER = TIME() WHERE CODTER = $caja ";
        $exec = $this->conn->prepare($apertura);
        $result = $exec->execute();

        if($result){
            return response()->json($result,201);
        }else{
            return response()->json("no se pudo abrir la caja",400);
        }

    }

    public function changewithdrawal(Request $request){
        $caja = $request->_cash;
        // switch($caja){
        //     case 1:
        //         $terminal  = "CAJAUNO";
        //     break;
        //     case 2:
        //         $terminal  = "CAJADOS";
        //     break;
        //     case 3:
        //         $terminal  = "CAJATRES";
        //     break;
        //     case 4:
        //         $terminal  = "CAJACUATRO";
        //     break;
        //     case 5:
        //         $terminal  = "CAJACINCO";
        //     break;
        //     case 6:
        //         $terminal  = "CAJASEIS";
        //     break;
        //     case 7:
        //         $terminal  = "CAJASIETE";
        //     break;
        //     case 8:
        //         $terminal  = "CAJAOCHO";
        //     break;
        //     case 9:
        //         $terminal  = "CAJANUEVE";
        //     break;
        // }
        // $cajaob = "SELECT CODTER FROM T_TER WHERE DESTER LIKE "."'"."%%".$terminal."'";
        $cajaob = "SELECT CODTER FROM T_TER WHERE CODTER = $caja";
        $exec = $this->conn->prepare($cajaob);
        $exec->execute();
        $cajat=$exec->fetch(\PDO::FETCH_ASSOC);
            if(is_null($request->montonuevo)){
                $retob = "UPDATE F_RET SET IMPRET = 0, CONRET = '', PRORET = 0 WHERE CODRET = ".$request->retirada." AND FECRET = DATE() AND CAJRET = ".$cajat['CODTER'];
                $exec = $this->conn->prepare($retob);
                $result = $exec->execute();
                if($result){
                    return response()->json($result,201);
                }else{
                    return response()->json("Hubo un problema al modificar la retirada",500);
                }
            }else{
                $retob = "UPDATE F_RET SET IMPRET = ".$request->montonuevo." WHERE CODRET = ".$request->retirada." AND FECRET = DATE() AND CAJRET = ".$cajat['CODTER'];
                $exec = $this->conn->prepare($retob);
                $result = $exec->execute();
                if($result){
                    $apertura = "UPDATE T_TER SET FECTER = DATE(), SINTER = 5000, ESTTER = 1, EFETER = 0, HOATER = TIME() WHERE CODTER = ".$cajat['CODTER'];
                    $exec = $this->conn->prepare($apertura);
                    $result = $exec->execute();
                    if($result){
                        return response()->json($result,201);
                    }else{
                        return respnse()->json('No se abrio la caja para la reimpresion');
                    }

                }else{
                    return response()->json("no se pudo modificar la retirada");
                }
            }

    }

    public function addWithdrawal(Request $request){
        $date_format = Carbon::now()->format('d/m/Y');//formato fecha factusol
        $date = date("Y/m/d H:i");//horario para la hora
        $hour = "01/01/1900 ".explode(" ", $date)[1];//hora para el ticket
        $horad = explode(" ", $date)[1];
        $idano = Carbon::now()->format('ymd');
        $cash = $request->cash;
        $with = $request->withdrawal;
        $codmax = "SELECT MAX(CODRET) as max FROM F_RET";
        $exec = $this->conn->prepare($codmax);
        $exec->execute();
        $cod=$exec->fetch(\PDO::FETCH_ASSOC);
        $codigo = $cod['max'] + 1;

        $cajaob = "SELECT CODTER FROM T_TER WHERE CODTER = ". $cash['_terminal'];
        $exec = $this->conn->prepare($cajaob);
        $exec->execute();
        $cajat=$exec->fetch(\PDO::FETCH_ASSOC);

        $idterminal = str_pad($cajat['CODTER'], 4, "0", STR_PAD_LEFT)."00".$idano;
        $insert = [
            $codigo,
            $cash['_terminal'],
            $date_format,
            $horad,
            $with['concept'],
            $with['import'],
            $with['providers']['val']['id'],
            $idterminal
        ];
        $ins = "INSERT INTO F_RET (CODRET,CAJRET,FECRET,HORRET,CONRET,IMPRET,PRORET,TPVIDRET) VALUES (?,?,?,?,?,?,?,?)";
        $exec = $this->conn->prepare($ins);
        $res = $exec->execute($insert);
        if($res){
            return response()->json(["folio"=>$codigo],200);
        }else{
            return response()->json('No se logro realizar la retirada',500);
        }
    }

    public function addIngress(Request $request){
        $date_format = Carbon::now()->format('d/m/Y');//formato fecha factusol
        $date = date("Y/m/d H:i");//horario para la hora
        $hour = "01/01/1900 ".explode(" ", $date)[1];//hora para el ticket
        $horad = explode(" ", $date)[1];
        $idano = Carbon::now()->format('ymd');
        $cash = $request->cash;
        $with = $request->ingress;
        $codmax = "SELECT MAX(CODING) as max FROM F_ING";
        $exec = $this->conn->prepare($codmax);
        $exec->execute();
        $cod=$exec->fetch(\PDO::FETCH_ASSOC);
        $codigo = $cod['max'] + 1;

        $cajaob = "SELECT CODTER FROM T_TER WHERE CODTER = ". $cash['_terminal'];
        $exec = $this->conn->prepare($cajaob);
        $exec->execute();
        $cajat=$exec->fetch(\PDO::FETCH_ASSOC);

        $idterminal = str_pad($cajat['CODTER'], 4, "0", STR_PAD_LEFT)."00".$idano;
        $insert = [
            $codigo,
            $cash['_terminal'],
            $date_format,
            $horad,
            $with['concept'],
            $with['import'],
            $with['client']['val']['id'],
            $idterminal
        ];
        $ins = "INSERT INTO F_ING (CODING,CAJING,FECING,HORING,CONING,IMPING,CLIING,TPVIDING) VALUES (?,?,?,?,?,?,?,?)";
        $exec = $this->conn->prepare($ins);
        $res = $exec->execute($insert);
        if($res){
            return response()->json(["folio"=>$codigo],200);
        }else{
            return response()->json('No se logro realizar el ingreso',500);
        }
    }

    public function addAdvance(Request $request){
        $date_format = Carbon::now()->format('d/m/Y');//formato fecha factusol
        $date = date("Y/m/d H:i");//horario para la hora
        $hour = "01/01/1900 ".explode(" ", $date)[1];//hora para el ticket
        $horad = explode(" ", $date)[1];
        $idano = Carbon::now()->format('ymd');
        $cash = $request->cash;
        $advance = $request->advance;

        $codmax = "SELECT MAX(CODANT) as max FROM F_ANT";
        $exec = $this->conn->prepare($codmax);
        $exec->execute();
        $cod=$exec->fetch(\PDO::FETCH_ASSOC);
        $codigo = $cod['max'] + 1;

        $cajaob = "SELECT CODTER FROM T_TER WHERE CODTER = ". $cash['_terminal'];
        $exec = $this->conn->prepare($cajaob);
        $exec->execute();
        $cajat=$exec->fetch(\PDO::FETCH_ASSOC);

        $idterminal = str_pad($cajat['CODTER'], 4, "0", STR_PAD_LEFT)."00".$idano;

        $insert = [
            $codigo,//codant
            $date_format,//fecant
            $advance['client']['id'],//cliant
            $advance['import'],//impant
            0,//estant
            $advance['observacion'],//obsant,
            1,//CRIANT
            $cash['_terminal'],//cajant
            $idterminal//tpvidant
        ];

        $ins = "INSERT INTO F_ANT (CODANT,FECANT,CLIANT,IMPANT,ESTANT,OBSANT,CRIANT,CAJANT,TPVIDANT) VALUES (?,?,?,?,?,?,?,?,?)";
        $exec = $this->conn->prepare($ins);
        $res = $exec->execute($insert);
        if($res){
            return response()->json(["folio"=>$codigo],200);
        }else{
            return response()->json('No se logro realizar el ingreso',500);
        }
    }

    public function repliedSales(Request $request){
        $sales = $request->all();
        $response = [
            "goals"=>[],
            "fails"=>[]
        ];

        foreach($sales as $sale){
            $cobmax = "SELECT MAX(CODCOB) as maxi FROM F_COB";
            $exec = $this->conn->prepare($cobmax);
            $exec->execute();
            $maxcob = $exec->fetch(\PDO::FETCH_ASSOC);
            $cobro = $maxcob['maxi'] + 1;

            $termi = "SELECT * FROM T_TER INNER JOIN T_DOC ON T_DOC.CODDOC = T_TER.DOCTER WHERE CODTER = ". $sale['cashier']['cash']['_terminal'];
            $exec = $this->conn->prepare($termi);
            $exec->execute();
            $codter = $exec->fetch(\PDO::FETCH_ASSOC);
            $nomter = $codter['DESTER'];
            $idterminal = str_pad($codter['CODTER'], 4, "0", STR_PAD_LEFT)."00".Carbon::parse($sale['cashier']['open_date'])->format('ymd');

            $codmax = "SELECT MAX(CODFAC) as maxi FROM F_FAC WHERE TIPFAC = "."'".$codter['TIPDOC']."'";
            $exec = $this->conn->prepare($codmax);
            $exec->execute();
            $max = $exec->fetch(\PDO::FETCH_ASSOC);
            $codigo = $max['maxi'] + 1;

            $resCli = $sale['_client'] == 0 ? 1 : $sale['_client'];


            $client =  "SELECT CODCLI, NOFCLI, DOMCLI, POBCLI, CPOCLI, PROCLI, TELCLI FROM F_CLI WHERE CODCLI = ".$resCli;
            $exec = $this->conn->prepare($client);
            $exec->execute();
            $ncli = $exec->fetch(\PDO::FETCH_ASSOC);

            $column = ["TIPFAC","CODFAC","FECFAC", "ALMFAC","AGEFAC","CLIFAC","CNOFAC","CDOFAC","CPOFAC","CCPFAC","CPRFAC","TELFAC","NET1FAC","BAS1FAC","TOTFAC","FOPFAC","VENFAC","HORFAC","USUFAC","USMFAC","TIVA2FAC","TIVA3FAC","EDRFAC","FUMFAC","BCOFAC","TPVIDFAC","ESTFAC","TERFAC","DEPFAC","EFEFAC","CAMFAC","EFSFAC","EFVFAC"];
            $factura = [
                $codter['TIPDOC'],//
                $codigo,//
                Carbon::parse($sale['created_at'])->format('d/m/Y'),
                "GEN",
                $sale['staff']['id_tpv'],
                $ncli['CODCLI'],
                $ncli['NOFCLI'],
                $ncli['DOMCLI'],
                $ncli['POBCLI'],
                $ncli['CPOCLI'],
                $ncli['PROCLI'],
                $ncli['TELCLI'],
                $sale['total'],
                $sale['total'],
                $sale['total'],
                $sale['pfpa']['alias'] ,
                Carbon::parse($sale['created_at'])->format('d/m/Y'),
                Carbon::parse($sale['created_at'])->format('H:i'),
                27,
                27,
                1,
                2,
                date('Y'),
                Carbon::parse($sale['created_at'])->format('d/m/Y'),
                1,
                $idterminal,
                2,
                intval($codter['CODTER']),
                $sale['staff']['id_tpv'],
                $sale['pfpa_import'],
                $sale['change'],
                $sale['sfpa_import'],
                $sale['val_import'],
            ];
            $impcol = implode(",",$column);
            $signos = implode(",",array_fill(0, count($column),'?'));
            $sql = "INSERT INTO F_FAC ($impcol) VALUES ($signos)";//se crea el query para insertar en la tabla
            $exec = $this->conn->prepare($sql);
            $res = $exec -> execute($factura);
            if($res){
                $contap = 1;
                foreach($sale['bodie'] as $product){
                    $upd = [
                        $product['amount'],
                        $product['amount'],
                        $product['code'],
                    ];

                    $inspro = [
                        $codter['TIPDOC'],
                        $codigo,
                        $contap,
                        $product['code'],
                        $product['description'],
                        intval($product['amount']),
                        doubleval($product['price']),
                        doubleval($product['total']),
                        $product['cost']
                    ];

                    $insertapro = "INSERT INTO F_LFA (TIPLFA,CODLFA,POSLFA,ARTLFA,DESLFA,CANLFA,PRELFA,TOTLFA,COSLFA) VALUES(?,?,?,?,?,?,?,?,?)";
                    $exec = $this->conn->prepare($insertapro);
                    $exec->execute($inspro);

                    $updatesto = "UPDATE F_STO SET DISSTO = DISSTO - ? , ACTSTO = ACTSTO - ? WHERE ALMSTO = 'GEN' AND ARTSTO = ?";
                    $exec = $this->conn->prepare($updatesto);
                    $exec -> execute($upd);
                    $contap++;
                }
                $count = 1;
                foreach($sale['payments'] as $fip){
                    $inspg = [
                        $codter['TIPDOC'],
                        $codigo,
                        $count,
                        Carbon::parse($sale['created_at'])->format('d/m/Y'),
                        $fip['import'],
                        $fip['payment']['name'],
                        $fip['payment']['alias'],
                        $cobro,
                        $idterminal,
                        $codter['CODTER']
                    ];
                    $faclco = "INSERT INTO F_LCO (TFALCO,CFALCO,LINLCO,FECLCO,IMPLCO,CPTLCO,FPALCO,MULLCO,TPVIDLCO,TERLCO) VALUES (?,?,?,?,?,?,?,?,?,?) ";
                    $exec = $this->conn->prepare($faclco);
                    $exec->execute($inspg);

                    $inscob = [
                        $cobro,
                        Carbon::parse($sale['created_at'])->format('d/m/Y'),
                        $fip['import'],
                        $fip['payment']['name']
                    ];
                    $instcob = "INSERT INTO F_COB (CODCOB,FECCOB IMPCOB CPTCOB) VALUES (?,?,?,?)";
                    $exec = $this->conn->prepare($instcob);
                    $exec->execute($inscob);
                    $count++;
                    $cobro++;
                }
                $response['goals'][]=["sale"=>$sale['id'],"fs_id"=>$codter['TIPDOC']."-".str_pad($codigo,6,0,STR_PAD_LEFT),];
            }else{
                $response['fails'][]=$sale['id'];
            }
        }
        return $response;
    }

}
