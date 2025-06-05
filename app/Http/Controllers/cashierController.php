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

    public function opencash(Request $request){
        $caja = $request->_cash;
        $apertura = "UPDATE T_TER SET FECTER = DATE(), SINTER = 5000, ESTTER = 1, EFETER = 0, HOATER = TIME() WHERE CODTER = $caja ";
        $exec = $this->conn->prepare($apertura);
        $result = $exec->execute();
        if($result){
            $response = $this->getCurrenCut($caja);
            return response()->json($response,201);
        }else{
            return response()->json("no se pudo abrir la caja",400);
        }

    }

    public function changewithdrawal(Request $request){
        $caja = $request->_cash;
        $cajaob = "SELECT CODTER FROM T_TER WHERE CODTER = $caja";
        $exec = $this->conn->prepare($cajaob);
        $exec->execute();
        $cajat=$exec->fetch(\PDO::FETCH_ASSOC);
        $aunRet = "SELECT * FROM F_RET WHERE CODRET = ".$request->retirada;
        $exec = $this->conn->prepare($aunRet);
        $exec->execute();
        $retirada =$exec->fetch(\PDO::FETCH_ASSOC);
        $response = $this->getCurrenCut($caja);
            if(is_null($request->montonuevo)){
                $retob = "UPDATE F_RET SET IMPRET = 0, CONRET = '', PRORET = 0 WHERE CODRET = ".$request->retirada." AND FECRET = DATE() AND CAJRET = ".$cajat['CODTER'];
                $exec = $this->conn->prepare($retob);
                $result = $exec->execute();
                $nuevoCorte = $this->getCurrenCut($caja);
                $prNwC = $this->printCut($nuevoCorte,$request->print);
                $prNwC = $this->printCut($nuevoCorte,$request->print);
                if($result){
                    $res = [
                        "monto_original"=>$retirada['IMPRET'],
                        "corte"=>$response,
                    ];
                    return response()->json($res,201);
                }else{
                    return response()->json("Hubo un problema al modificar la retirada",500);
                }
            }else{
                $retob = "UPDATE F_RET SET IMPRET = ".$request->montonuevo." WHERE CODRET = ".$request->retirada." AND FECRET = DATE() AND CAJRET = ".$cajat['CODTER'];
                $exec = $this->conn->prepare($retob);
                $result = $exec->execute();
                if($result){
                    $impresion = $this->printWitrawal($request->print,$request->retirada);
                    $nuevoCorte = $this->getCurrenCut($caja);
                    $prNwC = $this->printCut($nuevoCorte,$request->print);
                    $prNwC = $this->printCut($nuevoCorte,$request->print);
                    $res = [
                        "monto_original"=>$retirada['IMPRET'],
                        "corte"=>$response,
                    ];
                    return response()->json($res,201);
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

    public function printWitrawal($print, $codret){
        $sql = "SELECT
            Format(R.FECRET, 'YYYY-MM-DD') as FECHA,
            Format(R.HORRET, 'HH:mm:ss') as HORA,
            R.CODRET,
            R.CAJRET,
            TR.DESTER,
            R.CONRET,
            R.IMPRET,
            R.PRORET,
            FP.NOFPRO
            FROM ((F_RET AS R
            INNER JOIN T_TER AS TR ON  TR.CODTER = R.CAJRET)
            INNER JOIN F_PRO AS FP ON FP.CODPRO = R.PRORET)
            WHERE R.CODRET  = " .$codret;
        $exec = $this->conn->prepare($sql);
        $exec -> execute();
        $cuts = $exec->fetch(\PDO::FETCH_ASSOC);

        $header = [
            "print"=>$print,
            "proveedor"=>$cuts['PRORET'],
            "retirada"=>$codret,
            "terminal"=>$cuts['DESTER'],
            "fecha"=>$cuts['FECHA'],
            "hora"=>$cuts['HORA'],
            "valor"=>$cuts['IMPRET'],
            "notas"=>$cuts['CONRET']
        ];

        $print = $this->printWith($header);
        return $print;
    }

    public function printWith($header){
        $documento = env('DOCUMENTO');
        $printers = $header['print'];

        $pro = "SELECT * FROM F_PRO WHERE CODPRO =". $header['proveedor'];
        $exec = $this->conn->prepare($pro);
        $exec->execute();
        $proveedor = $exec->fetch(\PDO::FETCH_ASSOC);//OK

        $sql = "SELECT CTT1TPV, CTT2TPV, CTT3TPV, CTT4TPV, CTT5TPV, PTT1TPV, PTT2TPV, PTT3TPV, PTT4TPV, PTT5TPV, PTT6TPV, PTT7TPV, PTT8TPV FROM T_TPV WHERE CODTPV = $documento";
        $exec = $this->conn->prepare($sql);
        $exec->execute();
        $text = $exec->fetch(\PDO::FETCH_ASSOC);//OK

        try{
            $connector = new NetworkPrintConnector($printers, 9100, 3);
            $printer = new Printer($connector);
        }catch(\Exception $e){ return null;}
        try {
            try{
                $printer->setJustification(printer::JUSTIFY_LEFT);
                $printer->text(" \n");
                $printer->text(" \n");
                $printer->text("------------------------------------------------\n");
                $printer->setJustification(printer::JUSTIFY_CENTER);
                $printer->text("Retirada Modificada \n");
                $printer->setJustification(printer::JUSTIFY_LEFT);
                $printer->text(" \n");
                $printer->text($text["CTT1TPV"]."\n");
                $printer->text($text["CTT3TPV"]." \n");
                $printer->text($text["CTT5TPV"]." \n");
                $printer->text(" \n");
                $printer->text(" \n");
                $printer->text("------------------------------------------------\n");
                $printer->text("SALIDA DE TERMINAL ".$header['terminal']." \n");
                $printer->text("N° ".$header['retirada']." Fecha: ".$header["fecha"]." ".$header["hora"] ." \n");
                // $printer->text("Le atendio :".$header["dependiente"]." \n");
                $printer->text("------------------------------------------------\n");
                $printer->text($proveedor['NOFPRO']." \n");
                $printer->text(" \n");
                $printer->text(" \n");
                $printer->text("00000"." \n");
                $printer->text(" \n");
                $printer->text("GVC"." \n");
                $printer->text("------------------------------------------------\n");
                $printer->text(str_pad("IMPORTE RETIRADO: ",14));
                $printer->text(number_format($header['valor'],2)." \n");
                $printer->text("Concepto:"." \n");
                $printer->text($header['notas']." \n");
                $printer -> cut();
                $printer -> close();
            }catch(Exception $e){}

        } finally {
            $printer -> close();
            return true;
        }
            return false;
    }

    public function getCurrenCut($terminal){
        $sql = "SELECT
            Format(T.FECATE, 'YYYY/MM/DD') as FECHA,
            Format(T.HOCATE, 'HH:MM:SS') as HORA,
            T.TERATE,
            TR.DESTER,
            T.SINATE,
            T.EFEATE,
            T.*,
            (SELECT SUM(L.IMPLCO) FROM F_LCO AS L  WHERE L.TERLCO = T.TERATE AND L.FECLCO = T.FECATE  AND L.FPALCO = 'EFE') AS VENTASEFE,
            (SELECT SUM(R.IMPRET) FROM F_RET AS R  WHERE R.CAJRET = T.TERATE AND R.FECRET = T.FECATE ) AS RETIRADAS,
            (SELECT SUM(I.IMPING)  FROM F_ING AS I  WHERE I.CAJING = T.TERATE AND I.FECING = T.FECATE ) AS INGRESOS,
            (SELECT SUM(F.CAMFAC)  * -1  FROM F_FAC AS F  WHERE F.TERFAC= T.TERATE AND F.FECFAC = T.FECATE AND F.ESTFAC =  0  OR   F.ESTFAC = 1) AS IMPDC
            FROM T_ATE AS T
            INNER JOIN T_TER AS TR ON T.TERATE = TR.CODTER
            WHERE T.TERATE = $terminal AND T.FECATE = DATE()";
        $exec = $this->conn->prepare($sql);
        $exec -> execute();
        $cuts = $exec->fetch(\PDO::FETCH_ASSOC);

        $ingresos = "SELECT * FROM F_ING WHERE CAJING = $terminal AND FECING = DATE()";
        $exec = $this->conn->prepare($ingresos);
        $exec -> execute();
        $ings = $exec->fetchall(\PDO::FETCH_ASSOC);


        $retiradas = "SELECT * FROM F_RET WHERE  CAJRET = $terminal AND FECRET = DATE() ";
        $exec = $this->conn->prepare($retiradas);
        $exec -> execute();
        $rets = $exec->fetchall(\PDO::FETCH_ASSOC);

        $vales = "SELECT * FROM F_ANT WHERE CAJANT = $terminal AND FECANT = DATE()";
        $exec = $this->conn->prepare($vales);
        $exec -> execute();
        $vls = $exec->fetchall(\PDO::FETCH_ASSOC);

        $totales = "SELECT CPTLCO, SUM(IMPLCO) AS IMPORTE FROM F_LCO WHERE TERLCO = $terminal AND FECLCO = DATE() GROUP BY CPTLCO";
        $exec = $this->conn->prepare($totales);
        $exec -> execute();
        $tots = $exec->fetchall(\PDO::FETCH_ASSOC);

        $movimientos = "SELECT SUM(TOTFAC) AS TOTAL, COUNT(CODFAC) AS MOVIMIENTOS FROM F_FAC WHERE TERFAC = $terminal AND FECFAC = DATE()";
        $exec = $this->conn->prepare($movimientos);
        $exec -> execute();
        $mov = $exec->fetch(\PDO::FETCH_ASSOC);
        $efetot = (floatval($cuts['VENTASEFE']) +  floatval($cuts['INGRESOS']) +   floatval($cuts['SINATE']) ) - floatval($cuts['RETIRADAS']);

        $res = [
            "descuadre"=>number_format((floatval($cuts['EFEATE']) -  $efetot ),2),
            "totalEfe"=>$efetot,
            "corte"=>$cuts,
            "ingresos"=>$ings,
            "retiradas"=>$rets,
            "vales"=>$vls,
            "totales"=>$tots,
            "movimientos"=>$mov
        ];
        return $res;
    }

    public function printCut($header,$print){

        $empresa = "SELECT DENEMP  FROM F_EMP";
        $exec = $this->conn->prepare($empresa);
        $exec -> execute();
        $emp = $exec->fetch(\PDO::FETCH_ASSOC);

        $connector = new NetworkPrintConnector($print, 9100, 3);
        if($connector){
            $printer = new Printer($connector);
            $printer->text(" \n");
            $printer->text(" \n");
            $printer->text("           --REIMPRESION DE CORTE--           \n");
            $printer->text(" \n");
            $printer->text(" \n");
            $printer->text(str_repeat("─", 48) . "\n");
            $printer->text("CIERRE DE TERMINAL"." \n");
            $printer->text($emp['DENEMP']." \n");
            $printer->text(str_repeat("─", 48) . "\n");
            $printer->text("Terminal: ".$header['corte']['DESTER']." \n");
            $printer->text("Fecha: ".$header['corte']['FECHA']." \n");
            $printer->text("Hora: ".$header['corte']['HORA']." \n");
            $printer->selectPrintMode(Printer::MODE_FONT_B);
            $printer->text(str_repeat("─", 64) . "\n");
            $printer->text(str_pad("Saldo Inicial: ", 47).str_pad(number_format(floatval($header['corte']['SINATE']),2), 16, ' ', STR_PAD_LEFT)." \n");
            $printer->text(" \n");
            $printer->text(str_pad("Ventas efectivo: ", 47).str_pad(number_format(floatval($header['corte']['VENTASEFE']),2), 16, ' ', STR_PAD_LEFT)." \n");
            $printer->text(" \n");
            $printer->text(str_pad("Ingresos de efectivo: ", 47).str_pad(number_format(floatval($header['corte']['INGRESOS']),2), 16, ' ', STR_PAD_LEFT)." \n");
            $printer->text(" \n");
            $printer->text(str_pad("Retiradas de efectivo: ", 47).str_pad(number_format(floatval($header['corte']['RETIRADAS']) * -1 ,2), 16, ' ', STR_PAD_LEFT)." \n");
            $printer->text(" \n");
            $efetot = (floatval($header['corte']['VENTASEFE']) +  floatval($header['corte']['INGRESOS']) +   floatval($header['corte']['SINATE']) ) - floatval($header['corte']['RETIRADAS']);
            // $printer->text(str_pad("Efectivo: ", 47). str_pad(number_format((  floatval($cuts['VENTASEFE']) - floatval($cuts['RETIRADAS'])   + floatval($cuts['SINATE'])  ),2), 16, ' ', STR_PAD_LEFT) ." \n");
            $printer->text(str_pad("Efectivo: ", 47). str_pad(number_format($header['totalEfe'],2), 16, ' ', STR_PAD_LEFT) ." \n");
            $printer->text(" \n");
            $printer->text(str_pad("Declaracion de efectivo: ", 47).str_pad(number_format(floatval($header['corte']['EFEATE']),2), 16, ' ', STR_PAD_LEFT)." \n");
            $printer->text(" \n");
            $printer->text(str_pad("Descuadre: ", 47). str_pad(number_format((floatval($header['corte']['EFEATE']) -  $header['totalEfe'] ),2), 16, ' ', STR_PAD_LEFT) ." \n");
            $printer->text(" \n");
            $printer->text(str_pad("Importe Pendiente Cobro: ", 47).str_pad(number_format(floatval($header['corte']['IMPDC']),2), 16, ' ', STR_PAD_LEFT)." \n");
            $printer->text(str_repeat("─", 64) . "\n");
            $printer->text(" \n");
            $printer->text("Ingresos de efectivo:"." \n");
            $printer->text(str_repeat("─", 64) . "\n");
            if(count($header['ingresos']) > 0){
                foreach($header['ingresos'] as $ingreso){
                    $textoCortos = mb_strimwidth($ingreso['CONING'], 0, 40, "...");
                    $printer->text(str_pad($textoCortos, 47).str_pad(number_format(floatval($ingreso['IMPING']),2), 16, ' ', STR_PAD_LEFT)." \n");
                }
            }
            $printer->text(" \n");
            $printer->text("Retiradas de efectivo:"." \n");
            $printer->text(str_repeat("─", 64) . "\n");
            if(count($header['retiradas']) > 0){
                foreach($header['retiradas'] as $retirada){
                    $textoCortod = mb_strimwidth($retirada['CONRET'], 0, 40, "...");
                    $printer->text(str_pad($textoCortod, 47).str_pad(number_format(floatval($retirada['IMPRET']),2), 16, ' ', STR_PAD_LEFT)." \n");
                }
            }
            $printer->text(" \n");
            $printer->text("Vales Creados:"." \n");
            $printer->text(str_repeat("─", 64) . "\n");
            if(count($header['vales']) > 0){
                foreach($header['vales'] as $vale){
                    $textoCorto = mb_strimwidth($vale['OBSANT'], 0, 40, "...");
                    $printer->text(str_pad($textoCorto, 47).str_pad(number_format(floatval($vale['IMPANT']),2), 16, ' ', STR_PAD_LEFT)." \n");
                }
            }
            $printer->text(" \n");

            $printer->text("Desglose por forma de pago:"." \n");
            $printer->text(str_repeat("─", 64) . "\n");
            if(count($header['totales']) > 0){
                foreach($header['totales'] as $pagos){
                    $textoCortoF = mb_strimwidth($pagos['CPTLCO'], 0, 40, "...");
                    $printer->text(str_pad($textoCortoF, 47).str_pad(number_format(floatval($pagos['IMPORTE']),2), 16, ' ', STR_PAD_LEFT)." \n");
                }
            }
            $printer->text(" \n");
            $printer->text("Desglose de otros cobros de documentos:"." \n");
            $printer->text(str_repeat("─", 64) . "\n");
            $printer->setJustification(Printer::JUSTIFY_RIGHT);
            $printer->text("Total Cobros: 0.00"." \n");
            $printer->text(" \n");
            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text("Detalle de operaciones:"." \n");
            $printer->text(str_repeat("─", 64) . "\n");
            if(count($header['movimientos']) > 0){
                $printer->setJustification(Printer::JUSTIFY_RIGHT);
                $printer->text("N. de operaciones: ". number_format(floatval($header['movimientos']['MOVIMIENTOS']),2)." \n");
                $printer->text("Total de operaciones: ".number_format(floatval($header['movimientos']['TOTAL']),2) ." \n");
            }
            $printer->text(" \n");

            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text("Detalle de monedas y billetes:"." \n");
            $printer->text(str_repeat("─", 64) . "\n");
            $printer->text(str_pad('   2: '. str_repeat(' ', 4)  . str_pad(floatval($header['corte']['MO0ATE']),5) . str_repeat(' ', 20) . '  5: '. str_repeat(' ', 4)  . str_pad(floatval($header['corte']['BI6ATE']),5), 64, ' ', STR_PAD_BOTH). "\n");
            $printer->text(str_pad('   1: '. str_repeat(' ', 4)  . str_pad(floatval($header['corte']['MO1ATE']),5) . str_repeat(' ', 20) . ' 10: '. str_repeat(' ', 4)  . str_pad(floatval($header['corte']['BI5ATE']),5), 64, ' ', STR_PAD_BOTH). "\n");
            $printer->text(str_pad('0.50: '. str_repeat(' ', 4)  . str_pad(floatval($header['corte']['MO2ATE']),5) . str_repeat(' ', 20) . ' 20: '. str_repeat(' ', 4)  . str_pad(floatval($header['corte']['BI4ATE']),5), 64, ' ', STR_PAD_BOTH). "\n");
            $printer->text(str_pad('0.20: '. str_repeat(' ', 4)  . str_pad(floatval($header['corte']['MO3ATE']),5) . str_repeat(' ', 20) . ' 50: '. str_repeat(' ', 4)  . str_pad(floatval($header['corte']['BI3ATE']),5), 64, ' ', STR_PAD_BOTH). "\n");
            $printer->text(str_pad('0.10: '. str_repeat(' ', 4)  . str_pad(floatval($header['corte']['MO4ATE']),5) . str_repeat(' ', 20) . '100: '. str_repeat(' ', 4)  . str_pad(floatval($header['corte']['BI2ATE']),5), 64, ' ', STR_PAD_BOTH). "\n");
            $printer->text(str_pad('0.05: '. str_repeat(' ', 4)  . str_pad(floatval($header['corte']['MO5ATE']),5) . str_repeat(' ', 20) . '200: '. str_repeat(' ', 4)  . str_pad(floatval($header['corte']['BI1ATE']),5), 64, ' ', STR_PAD_BOTH). "\n");
            $printer->text(str_pad('0.02: '. str_repeat(' ', 4)  . str_pad(floatval($header['corte']['MO6ATE']),5) . str_repeat(' ', 20) . '500: '. str_repeat(' ', 4)  . str_pad(floatval($header['corte']['BI0ATE']),5), 64, ' ', STR_PAD_BOTH). "\n");
            $printer->text(str_pad('0.01: '. str_repeat(' ', 4)  . str_pad(floatval($header['corte']['MO7ATE']),5) . str_repeat(' ', 35) , 64, ' ', STR_PAD_BOTH). "\n");
            $printer->text(" \n");
            $printer->cut();
            $printer->close();
            return true;
        }else{
            return "No se pudo imprimir";
        }
    }

}
