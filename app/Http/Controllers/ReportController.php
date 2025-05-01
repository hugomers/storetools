<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\Printer;

class ReportController extends Controller
{
    public function __construct(){
        $access = env("ACCESS");
        if(file_exists($access)){
        try{  $this->conn  = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
            }catch(\PDOException $e){ die($e->getMessage()); }
        }else{ die("$access no es un origen de datos valido."); }
      }

      public function getCuts(){
        $sql = "SELECT
            Format(T.FECATE, 'YYYY/MM/DD') as FECHA,
            T.TERATE,
            TR.DESTER,
            T.SINATE,
            T.EFEATE,
            (SELECT SUM(L.IMPLCO) FROM F_LCO AS L  WHERE L.TERLCO = T.TERATE AND L.FECLCO = T.FECATE  AND L.FPALCO = 'EFE') AS VENTASEFE,
            (SELECT SUM(R.IMPRET) FROM F_RET AS R  WHERE R.CAJRET = T.TERATE AND R.FECRET = T.FECATE ) AS RETIRADAS,
            (SELECT SUM(I.IMPING) FROM F_ING AS I  WHERE I.CAJING = T.TERATE AND I.FECING = T.FECATE ) AS INGRESOS
            FROM T_ATE AS T
            INNER JOIN T_TER AS TR ON T.TERATE = TR.CODTER
            WHERE YEAR(T.FECATE) = 2025";
        $exec = $this->conn->prepare($sql);
        $exec -> execute();
        $cuts = $exec->fetchall(\PDO::FETCH_ASSOC);

        $term = "SELECT CODTER, DESTER FROM T_TER";
        $exec = $this->conn->prepare($term);
        $exec -> execute();
        $terminales = $exec->fetchall(\PDO::FETCH_ASSOC);
        return response()->json(["cuts"=>$cuts, "terminal"=>$terminales]);
      }

      public function printCut(Request $request){
        $terminal = $request->terminal;
        // $terminal = 36;

        $fecha = $request->fecha;
        // $fecha = "2025-04-11";

        $print = $request->print;



        // $print = '192.168.10.100';

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
            WHERE T.TERATE = $terminal AND T.FECATE = "."#".$fecha."#";
        $exec = $this->conn->prepare($sql);
        $exec -> execute();
        $cuts = $exec->fetch(\PDO::FETCH_ASSOC);

        $ingresos = "SELECT * FROM F_ING WHERE CAJING = $terminal AND FECING = "."#".$fecha."#";
        $exec = $this->conn->prepare($ingresos);
        $exec -> execute();
        $ings = $exec->fetchall(\PDO::FETCH_ASSOC);


        $retiradas = "SELECT * FROM F_RET WHERE  CAJRET = $terminal AND FECRET = "."#".$fecha."#";
        $exec = $this->conn->prepare($retiradas);
        $exec -> execute();
        $rets = $exec->fetchall(\PDO::FETCH_ASSOC);

        $vales = "SELECT * FROM F_ANT WHERE CAJANT = $terminal AND FECANT = "."#".$fecha."#";
        $exec = $this->conn->prepare($vales);
        $exec -> execute();
        $vls = $exec->fetchall(\PDO::FETCH_ASSOC);

        $totales = "SELECT CPTLCO, SUM(IMPLCO) AS IMPORTE FROM F_LCO WHERE TERLCO = $terminal AND FECLCO = "."#".$fecha."# GROUP BY CPTLCO";
        $exec = $this->conn->prepare($totales);
        $exec -> execute();
        $tots = $exec->fetchall(\PDO::FETCH_ASSOC);

        $movimientos = "SELECT SUM(TOTFAC) AS TOTAL, COUNT(CODFAC) AS MOVIMIENTOS FROM F_FAC WHERE TERFAC = $terminal AND FECFAC = "."#".$fecha."#";
        $exec = $this->conn->prepare($movimientos);
        $exec -> execute();
        $mov = $exec->fetch(\PDO::FETCH_ASSOC);

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
            $printer->text("SUCURSAL "."APARTADO 1"." \n");
            $printer->text(str_repeat("─", 48) . "\n");
            $printer->text("Terminal: ".$cuts['DESTER']." \n");
            $printer->text("Fecha: ".$cuts['FECHA']." \n");
            $printer->text("Hora: ".$cuts['HORA']." \n");
            $printer->selectPrintMode(Printer::MODE_FONT_B);
            $printer->text(str_repeat("─", 64) . "\n");
            $printer->text(str_pad("Saldo Inicial: ", 47).str_pad(number_format(floatval($cuts['SINATE']),2), 16, ' ', STR_PAD_LEFT)." \n");
            $printer->text(" \n");
            $printer->text(str_pad("Ventas efectivo: ", 47).str_pad(number_format(floatval($cuts['VENTASEFE']),2), 16, ' ', STR_PAD_LEFT)." \n");
            $printer->text(" \n");
            $printer->text(str_pad("Ingresos de efectivo: ", 47).str_pad(number_format(floatval($cuts['INGRESOS']),2), 16, ' ', STR_PAD_LEFT)." \n");
            $printer->text(" \n");
            $printer->text(str_pad("Retiradas de efectivo: ", 47).str_pad(number_format(floatval($cuts['RETIRADAS']) * -1 ,2), 16, ' ', STR_PAD_LEFT)." \n");
            $printer->text(" \n");
            $efetot = (floatval($cuts['VENTASEFE']) +  floatval($cuts['INGRESOS']) +   floatval($cuts['SINATE']) ) - floatval($cuts['RETIRADAS']);
            // $printer->text(str_pad("Efectivo: ", 47). str_pad(number_format((  floatval($cuts['VENTASEFE']) - floatval($cuts['RETIRADAS'])   + floatval($cuts['SINATE'])  ),2), 16, ' ', STR_PAD_LEFT) ." \n");
            $printer->text(str_pad("Efectivo: ", 47). str_pad(number_format($efetot,2), 16, ' ', STR_PAD_LEFT) ." \n");
            $printer->text(" \n");
            $printer->text(str_pad("Declaracion de efectivo: ", 47).str_pad(number_format(floatval($cuts['EFEATE']),2), 16, ' ', STR_PAD_LEFT)." \n");
            $printer->text(" \n");
            $printer->text(str_pad("Descuadre: ", 47). str_pad(number_format((floatval($cuts['EFEATE']) -  $efetot ),2), 16, ' ', STR_PAD_LEFT) ." \n");
            $printer->text(" \n");
            $printer->text(str_pad("Importe Pendiente Cobro: ", 47).str_pad(number_format(floatval($cuts['IMPDC']),2), 16, ' ', STR_PAD_LEFT)." \n");
            $printer->text(str_repeat("─", 64) . "\n");
            $printer->text(" \n");
            $printer->text("Ingresos de efectivo:"." \n");
            $printer->text(str_repeat("─", 64) . "\n");
            if(count($ings) > 0){
                foreach($ings as $ingreso){
                    $textoCortos = mb_strimwidth($ingreso['CONING'], 0, 40, "...");
                    $printer->text(str_pad($textoCortos, 47).str_pad(number_format(floatval($ingreso['IMPING']),2), 16, ' ', STR_PAD_LEFT)." \n");
                }
            }
            $printer->text(" \n");

            $printer->text("Retiradas de efectivo:"." \n");
            $printer->text(str_repeat("─", 64) . "\n");
            if(count($rets) > 0){
                foreach($rets as $retirada){
                    $textoCortod = mb_strimwidth($retirada['CONRET'], 0, 40, "...");
                    $printer->text(str_pad($textoCortod, 47).str_pad(number_format(floatval($retirada['IMPRET']),2), 16, ' ', STR_PAD_LEFT)." \n");
                }
            }
            $printer->text(" \n");

            $printer->text("Vales Creados:"." \n");
            $printer->text(str_repeat("─", 64) . "\n");
            if(count($vls) > 0){
                foreach($vls as $vale){
                    $textoCorto = mb_strimwidth($vale['OBSANT'], 0, 40, "...");
                    $printer->text(str_pad($textoCorto, 47).str_pad(number_format(floatval($vale['IMPANT']),2), 16, ' ', STR_PAD_LEFT)." \n");
                }
            }
            $printer->text(" \n");

            $printer->text("Desglose por forma de pago:"." \n");
            $printer->text(str_repeat("─", 64) . "\n");
            if(count($tots) > 0){
                foreach($tots as $pagos){
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
            if(count($mov) > 0){
                $printer->setJustification(Printer::JUSTIFY_RIGHT);
                $printer->text("N. de operaciones: ". number_format(floatval($mov['MOVIMIENTOS']),2)." \n");
                $printer->text("Total de operaciones: ".number_format(floatval($mov['TOTAL']),2) ." \n");
            }
            $printer->text(" \n");

            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text("Detalle de monedas y billetes:"." \n");
            $printer->text(str_repeat("─", 64) . "\n");
            $printer->text(str_pad('   2: '. str_repeat(' ', 4)  . str_pad(floatval($cuts['MO0ATE']),5) . str_repeat(' ', 20) . '  5: '. str_repeat(' ', 4)  . str_pad(floatval($cuts['BI6ATE']),5), 64, ' ', STR_PAD_BOTH). "\n");
            $printer->text(str_pad('   1: '. str_repeat(' ', 4)  . str_pad(floatval($cuts['MO1ATE']),5) . str_repeat(' ', 20) . ' 10: '. str_repeat(' ', 4)  . str_pad(floatval($cuts['BI5ATE']),5), 64, ' ', STR_PAD_BOTH). "\n");
            $printer->text(str_pad('0.50: '. str_repeat(' ', 4)  . str_pad(floatval($cuts['MO2ATE']),5) . str_repeat(' ', 20) . ' 20: '. str_repeat(' ', 4)  . str_pad(floatval($cuts['BI4ATE']),5), 64, ' ', STR_PAD_BOTH). "\n");
            $printer->text(str_pad('0.20: '. str_repeat(' ', 4)  . str_pad(floatval($cuts['MO3ATE']),5) . str_repeat(' ', 20) . ' 50: '. str_repeat(' ', 4)  . str_pad(floatval($cuts['BI3ATE']),5), 64, ' ', STR_PAD_BOTH). "\n");
            $printer->text(str_pad('0.10: '. str_repeat(' ', 4)  . str_pad(floatval($cuts['MO4ATE']),5) . str_repeat(' ', 20) . '100: '. str_repeat(' ', 4)  . str_pad(floatval($cuts['BI2ATE']),5), 64, ' ', STR_PAD_BOTH). "\n");
            $printer->text(str_pad('0.05: '. str_repeat(' ', 4)  . str_pad(floatval($cuts['MO5ATE']),5) . str_repeat(' ', 20) . '200: '. str_repeat(' ', 4)  . str_pad(floatval($cuts['BI1ATE']),5), 64, ' ', STR_PAD_BOTH). "\n");
            $printer->text(str_pad('0.02: '. str_repeat(' ', 4)  . str_pad(floatval($cuts['MO6ATE']),5) . str_repeat(' ', 20) . '500: '. str_repeat(' ', 4)  . str_pad(floatval($cuts['BI0ATE']),5), 64, ' ', STR_PAD_BOTH). "\n");
            $printer->text(str_pad('0.01: '. str_repeat(' ', 4)  . str_pad(floatval($cuts['MO7ATE']),5) . str_repeat(' ', 35) , 64, ' ', STR_PAD_BOTH). "\n");
            $printer->text(" \n");
            $printer->cut();
            $printer->close();
            return $cuts;
        }else{
            return "No se pudo imprimir";
        }
      }

      public function getWithdrawals(){
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
            WHERE YEAR(R.FECRET) = 2025";
        $exec = $this->conn->prepare($sql);
        $exec -> execute();
        $cuts = $exec->fetchall(\PDO::FETCH_ASSOC);

        $term = "SELECT CODTER, DESTER FROM T_TER";
        $exec = $this->conn->prepare($term);
        $exec -> execute();
        $terminales = $exec->fetchall(\PDO::FETCH_ASSOC);

        $prov = "SELECT CODPRO, NOFPRO FROM F_PRO WHERE CODPRO BETWEEN 800 AND 900";
        $exec = $this->conn->prepare($prov);
        $exec -> execute();
        $proveedor = $exec->fetchall(\PDO::FETCH_ASSOC);
        return response()->json(mb_convert_encoding(["cuts"=>$cuts, "terminal"=>$terminales, "proveedores"=>$proveedor], 'UTF-8'));
      }


      public function printWitrawal(Request $request){

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
        WHERE R.CODRET  = " .$request->codret;

        $exec = $this->conn->prepare($sql);
        $exec -> execute();
        $cuts = $exec->fetch(\PDO::FETCH_ASSOC);


        $header = [
            "print"=>$request->print,
            "proveedor"=>$cuts['PRORET'],
            "retirada"=>$request->codret,
            "terminal"=>$cuts['DESTER'],
            "fecha"=>$cuts['FECHA'],
            "hora"=>$cuts['HORA'],
            // "dependiente"=>$request->by,
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

    public function  modifyWithdrawal(Request $request){
        $codret = $request->CODRET;
        $provider = $request->PROVEEDOR;
        $importe = $request->IMPRET;
        $concepto = $request->CONRET;
        $impresora = $request->Print;

        $sqlupdate = "UPDATE F_RET SET PRORET = ? , IMPRET = ?, CONRET = ? WHERE CODRET = ?";
        $exec = $this->conn->prepare($sqlupdate);
        $res = $exec->execute([$provider['PRORET'],$importe,$concepto,$codret]);
        if($res){
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
                "print"=>$impresora,
                "proveedor"=>$cuts['PRORET'],
                "retirada"=>$codret,
                "terminal"=>$cuts['DESTER'],
                "fecha"=>$cuts['FECHA'],
                "hora"=>$cuts['HORA'],
                // "dependiente"=>$request->by,
                "valor"=>$cuts['IMPRET'],
                "notas"=>$cuts['CONRET']
            ];

            $print = $this->printWith($header);
            return response()->json($cuts,200);

        }else{
            return response()->json('No se modifico la retirada',500);
        }

    }

    public function getAdvances(){
        $sql = "SELECT
            Format(R.FECANT, 'YYYY-MM-DD') as FECHA,
            R.CODANT,
            R.CAJANT,
            TR.DESTER,
            R.OBSANT,
            R.IMPANT,
            R.CLIANT,
            FP.NOFCLI
            FROM ((F_ANT AS R
            INNER JOIN T_TER AS TR ON  TR.CODTER = R.CAJANT)
            INNER JOIN F_CLI AS FP ON FP.CODCLI = R.CLIANT)
            WHERE YEAR(R.FECANT) = 2025 AND R.CRIANT = 0 AND R.ESTANT = 0";
        $exec = $this->conn->prepare($sql);
        $exec -> execute();
        $cuts = $exec->fetchall(\PDO::FETCH_ASSOC);

        $term = "SELECT CODTER, DESTER FROM T_TER";
        $exec = $this->conn->prepare($term);
        $exec -> execute();
        $terminales = $exec->fetchall(\PDO::FETCH_ASSOC);

        $prov = "SELECT CODCLI, NOFCLI FROM F_CLI";
        $exec = $this->conn->prepare($prov);
        $exec -> execute();
        $clientes = $exec->fetchall(\PDO::FETCH_ASSOC);
        return response()->json(mb_convert_encoding(["anticipos"=>$cuts, "terminal"=>$terminales, "clientes"=>$clientes], 'UTF-8'));
      }


      public function printAdvance(Request $request){
        $sql = "SELECT
            Format(R.FECANT, 'YYYY-MM-DD') as FECHA,
            R.CODANT,
            R.CAJANT,
            TR.DESTER,
            R.OBSANT,
            R.IMPANT,
            R.CLIANT,
            FP.NOFCLI
            FROM ((F_ANT AS R
            INNER JOIN T_TER AS TR ON  TR.CODTER = R.CAJANT)
            INNER JOIN F_CLI AS FP ON FP.CODCLI = R.CLIANT)
        WHERE R.CODANT  = " .$request->codant;
        $exec = $this->conn->prepare($sql);
        $exec -> execute();
        $cuts = $exec->fetch(\PDO::FETCH_ASSOC);


        $header = [
            "print"=>$request->print,
            "client"=>$cuts['CLIANT'],
            "retirada"=>$request->codant,
            "terminal"=>$cuts['DESTER'],
            "fecha"=>$cuts['FECHA'],
            // "hora"=>$cuts['HORA'],
            // "dependiente"=>$request->by,
            "valor"=>$cuts['IMPANT'],
            "notas"=>$cuts['OBSANT']
        ];

        $print = $this->printAdv($header);
        return $print;
      }


      public function  modifyAdvances(Request $request){
        $codret = $request->CODANT;
        $provider = $request->CLIENTE;
        $importe = $request->IMPANT;
        $concepto = $request->OBSANT;
        $impresora = $request->Print;

        $sqlupdate = "UPDATE F_ANT SET CLIANT = ? , IMPANT = ?, OBSANT = ? WHERE CODANT = ?";
        $exec = $this->conn->prepare($sqlupdate);
        $res = $exec->execute([$provider['CODCLI'],$importe,$concepto,$codret]);
        if($res){
            $sql = "SELECT
            Format(R.FECANT, 'YYYY-MM-DD') as FECHA,
            R.CODANT,
            R.CAJANT,
            TR.DESTER,
            R.OBSANT,
            R.IMPANT,
            R.CLIANT,
            FP.NOFCLI
            FROM ((F_ANT AS R
            INNER JOIN T_TER AS TR ON  TR.CODTER = R.CAJANT)
            INNER JOIN F_CLI AS FP ON FP.CODCLI = R.CLIANT)
            WHERE R.CODANT  = " .$codret;

            $exec = $this->conn->prepare($sql);
            $exec -> execute();
            $cuts = $exec->fetch(\PDO::FETCH_ASSOC);

            $header = [
                "print"=>$impresora,
                "client"=>$cuts['CLIANT'],
                "retirada"=>$request->codant,
                "terminal"=>$cuts['DESTER'],
                "fecha"=>$cuts['FECHA'],
                // "hora"=>$cuts['HORA'],
                // "dependiente"=>$request->by,
                "valor"=>$cuts['IMPANT'],
                "notas"=>$cuts['OBSANT']
            ];

            $print = $this->printAdv($header);
            return response()->json($cuts,200);

        }else{
            return response()->json('No se modifico la retirada',500);
        }

    }

    public function addAdvances(Request $request){
        $impresora = $request->Print;
        $maxi = "SELECT MAX(CODANT) AS CODIGO FROM F_ANT";
        $exec = $this->conn->prepare($maxi);
        $exec -> execute();
        $codant = $exec->fetch(\PDO::FETCH_ASSOC);
        $codigo = $codant['CODIGO']+ 1;

        $idterminal = str_pad($request->DESTER['CODTER'], 4, "0", STR_PAD_LEFT)."00".date('ymd');

        $insert = "INSERT INTO F_ANT (CODANT,FECANT,CLIANT,IMPANT,ESTANT,DOCANT,TDOANT,CDOANT,SDOANT,OBSANT,CRIANT,CAJANT,TPVIDANT) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $exec = $this->conn->prepare($insert);
        $res = $exec -> execute([
            $codigo,
            date('d-m-Y'),
            $request->CLIENTE['CODCLI'],
            $request->IMPANT,
            0,
            0,
            0,
            0,
            0,
            $request->OBSANT,
            0,
            $request->DESTER['CODTER'],
            $idterminal
        ]);
        if($res){


            $sql = "SELECT
            Format(R.FECANT, 'YYYY-MM-DD') as FECHA,
            R.CODANT,
            R.CAJANT,
            TR.DESTER,
            R.OBSANT,
            R.IMPANT,
            R.CLIANT,
            FP.NOFCLI
            FROM ((F_ANT AS R
            INNER JOIN T_TER AS TR ON  TR.CODTER = R.CAJANT)
            INNER JOIN F_CLI AS FP ON FP.CODCLI = R.CLIANT)
            WHERE R.CODANT  = " .$codigo;

            $exec = $this->conn->prepare($sql);
            $exec -> execute();
            $cuts = $exec->fetch(\PDO::FETCH_ASSOC);

            $header = [
                "print"=>$impresora,
                "client"=>$cuts['CLIANT'],
                "retirada"=>$codigo,
                "terminal"=>$cuts['DESTER'],
                "fecha"=>$cuts['FECHA'],
                // "hora"=>$cuts['HORA'],
                // "dependiente"=>$request->by,
                "valor"=>$cuts['IMPANT'],
                "notas"=>$cuts['OBSANT']
            ];

            $print = $this->printAdv($header);
            return response()->json($cuts,200);


        }else{
            return response()->json('No se realizo el anticipo',500);
        }
    }


      public function printAdv($header){
        $documento = env('DOCUMENTO');
        $printers = $header['print'];

        $pro = "SELECT * FROM F_CLI WHERE CODCLI =". $header['client'];
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
                $printer->text(" \n");
                $printer->text($text["CTT1TPV"]."\n");
                $printer->text($text["CTT3TPV"]." \n");
                $printer->text($text["CTT5TPV"]." \n");
                $printer->text(" \n");
                $printer->text(" \n");
                $printer->text("------------------------------------------------\n");
                $printer->text("VALE DE TERMINAL ".$header['terminal']." \n");
                $printer->text("N° ".$header['retirada']." Fecha: ".$header["fecha"]." \n");
                // $printer->text("Le atendio :".$header["dependiente"]." \n");
                $printer->text("------------------------------------------------\n");
                $printer->text($proveedor['NOFCLI']." \n");
                $printer->text(" \n");
                $printer->text(" \n");
                $printer->text("00000"." \n");
                $printer->text(" \n");
                $printer->text("GVC"." \n");
                $printer->text("------------------------------------------------\n");
                $printer->text(str_pad("IMPORTE DE VALE: ",14));
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
}
