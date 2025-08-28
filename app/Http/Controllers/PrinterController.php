<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Mike42\Escpos\EscposImage;
use App\Requisition;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\Printer;
use NumberFormatter;

class PrinterController extends Controller
{
    public function printck($order,$cash,$folio,$config){

        try{
            $connector = new NetworkPrintConnector($cash['cashier']['print']['ip_address'], 9100, 3);
            $printer = new Printer($connector);
        }catch(\Exception $e){ return null;}
        try {
            try{
                $payments = $order['payments'];
                $imagen = env('IMAGENLOCAL');
                // $imagen = "\\\\192.168.60.253\\c\\Users\\Administrador\\Documents\\TCKPHP.png";//poner en env el servidor de donde sale
                $filtered = array_filter($payments, function($val) {
                    return isset($val['id']) && !is_null($val['id'])  && $val['val'] > 0;
                });
                if ( isset($payments['conditions']['super']) && $payments['conditions']['super']){
                    $cambio = ($payments['PFPA']['val'] + $payments['SFPA']['val'] + $payments['VALE']['val']) - $order['total'];
                    if($cambio > 0){
                        foreach ($filtered as $key => &$payment) {
                            if (isset($payment['id']['id']) && $payment['id']['id'] === 5 ) {
                                $original = floatval($payment['val']);
                                $adjusted = $original - $cambio;
                                $payment['val'] = $adjusted >= 0 ? $adjusted : 0;
                                $descontado = true;
                                break;
                            }
                        }
                    }
                }
                $headers = json_decode($cash['cashier']['cash']['tpv']['herader_tck']);
                $footers = json_decode($cash['cashier']['cash']['tpv']['footer_tck']);

                $formatter = new NumberFormatter("es", NumberFormatter::SPELLOUT);
                $partes = explode('.', number_format($order['total'], 2, '.', ''));
                $pesos = (int)$partes[0];
                $letrasPesos = ucfirst($formatter->format($pesos));
                $totlet = "$letrasPesos pesos M.N.";

                if(file_exists($imagen)){
                    $logo = EscposImage::load($imagen, false);
                    $printer->setJustification(Printer::JUSTIFY_CENTER);
                    $printer->bitImage($logo,0);
                    $printer->feed();
                }
                $printer->setJustification(printer::JUSTIFY_LEFT);
                $printer->text(" \n");
                $printer->text(" \n");
                $printer->text("------------------------------------------------\n");
                $printer->text(" \n");
                foreach($headers  as $header){
                    $printer->text($header->val."\n");
                }
                $printer->text(" \n");
                $printer->text(" \n");
                $printer->text($cash['store']['alias']."-".$cash['name']." \n");
                $printer->text("N° ".$folio." Fecha: ". $order['created_at'] ." \n");
                $printer->text("Forma de Pago: ".mb_convert_encoding($payments["PFPA"]['id']['name'],'UTF-8')." \n");
                $printer->text("Cliente: ".mb_convert_encoding($order["client"]['name'],'UTF-8')." \n");
                $printer->text(str_repeat("_", 48) . "\n");
                $printer->text(
                    str_pad("ARTICULO", 15) .
                    str_pad("UD.", 9) .
                    str_pad("PRECIO", 14) .
                    str_pad("TOTAL", 10). "\n"
                );
                $printer->text(str_repeat("_", 48) . "\n");
                $printer->setFont(Printer::FONT_B);
                foreach ($order['products'] as $product) {
                    $printer->setJustification(Printer::JUSTIFY_LEFT);
                    $printer->text(mb_convert_encoding($product['code'], 'UTF-8') . "   " . mb_convert_encoding($product['description'], 'UTF-8') . " \n");
                    $printer->setJustification(Printer::JUSTIFY_RIGHT);
                    $amount = str_pad(number_format($product['pivot']['amountDelivered'], 2,'.',''), 15);  // UD. (10)
                    $arti [] = $product['pivot']['amountDelivered'];
                    $price  = str_pad(number_format($product['pivot']['price'], 2,'.',''), 18);   // PRECIO (15)
                    $total  = str_pad(number_format( $product['pivot']['total'] , 2,'.',''), 12);   // TOTAL (10)
                    $printer->text($amount . $price . $total . "\n" );

                }
                $printer -> setFont(Printer::FONT_A);
                $printer->text(" \n");
                $printer->text(" \n");
                $printer->setJustification(printer::JUSTIFY_RIGHT);
                $printer->setEmphasis(true);
                if($config['option']){
                    $printer->text("SUBTOTAL: ");
                    $printer->text(str_pad("$".number_format($order["subtotal"],2),13, " " ,STR_PAD_LEFT)." \n");
                    $printer->text("IVA: ");
                    $importeTotalIva = $order['subtotal'] * ($order['iva'] / 100);
                    $printer->text(str_pad("$".number_format($importeTotalIva,2),13, " " ,STR_PAD_LEFT)." \n");
                }
                $printer->text("TOTAL: ");
                $printer->text(str_pad("$".number_format($order["total"],2),13, " " ,STR_PAD_LEFT)." \n");
                $printer->text(" \n");
                $printer->setEmphasis(false);
                foreach($filtered as $pago){
                    $mosPag = $pago['id']['name'] == 'VALE' ? "VALE N. ". $pago['id']['code']   : $pago['id']['name'];
                    $printer->text(mb_convert_encoding($mosPag,'UTF-8').": ");
                    $printer->text(str_pad("$".number_format($pago['val'],2),13, " " ,STR_PAD_LEFT)." \n");
                }
                if($order['change'] <> 0){
                    $printer->text("Cambio: ");
                    $printer->text(str_pad("$".number_format($order['change'],2),13, " " ,STR_PAD_LEFT)." \n");
                }
                $printer->text($totlet." \n");
                $printer->text(" \n");
                $printer->setJustification(printer::JUSTIFY_LEFT);
                $printer->text(" \n");
                $printer->text("N Articulos: ".array_sum($arti)." \n");
                $printer->text(" \n");
                $printer->text("Vendedor :".$order['dependiente']['complete_name']." \n");
                $printer->text("Cajero :".$cash['cashier']['user']['staff']['complete_name']." \n");
                $printer->text(" \n");
                $printer->text(isset($order["observation"]) ? $order["observation"] :"" ." \n");
                $printer->text(" \n");
                $printer->text(" \n");
                // $printer->text("-------------------Grupo-Vizcarra---------------"." \n");
                foreach($footers as $footer){
                    $printer->text(mb_convert_encoding($footer->val,'UTF-8')." \n");
                }
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $printer->text("--------------------------------------------\n");
                $printer->setBarcodeHeight(50);
                $printer->setBarcodeWidth(2);
                $printer->barcode($folio);
                $printer->feed(1);
                $printer->text("GRUPO VIZCARRA\n");
                $printer->feed(1);
                $printer -> cut();
                $printer -> close();
                return true;
            }catch(Exception $e){}
        } finally {
                $printer -> close();
                return true;
        }
            return false;
    }

    public function printret($header){
        // return $header;
        try{
            $connector = new NetworkPrintConnector($header['cash']['cashier']['print']['ip_address'], 9100, 3);
            $printer = new Printer($connector);
        }catch(\Exception $e){ return null;}
        try {
            try{
                $headersTCK = json_decode($header['cash']['cashier']['cash']['tpv']['herader_tck']);
                $printer->setJustification(printer::JUSTIFY_LEFT);
                $printer->text(" \n");
                $printer->text(" \n");
                $printer->text("------------------------------------------------\n");
                $printer->text(" \n");
                foreach($headersTCK  as $headerT){
                    $printer->text($headerT->val."\n");
                }
                $printer->text(" \n");
                $printer->text(" \n");
                $printer->text("------------------------------------------------\n");
                $printer->text("SALIDA DE TERMINAL".$header['cash']['_terminal']." \n");
                $printer->text("N° ".$header['withdrawal']['fs_id']." Fecha: ".now()->format('d/m/Y H:i') ." \n");
                $printer->text("creado Por :".$header['cash']["cashier"]['user']['staff']['complete_name']." \n");
                $printer->text("------------------------------------------------\n");
                $printer->text($header['withdrawal']['providers']['val']['name']." \n");
                $printer->text(" \n");
                $printer->text(" \n");
                $printer->text("00000"." \n");
                $printer->text(" \n");
                $printer->text("GVC"." \n");
                $printer->text("------------------------------------------------\n");
                $printer->text(str_pad("IMPORTE RETIRADO: ",14));
                $printer->text(number_format($header['withdrawal']['import'],2)." \n");
                $printer->text("Concepto:"." \n");
                $printer->text($header['withdrawal']['concept']." \n");
                $printer -> cut();
                $printer -> close();
            }catch(Exception $e){}

        } finally {
            $printer -> close();
            return true;
        }
            return false;
    }

    public function printAdvance($header){
        try{
            $connector = new NetworkPrintConnector($header['cash']['cashier']['print']['ip_address'], 9100, 3);
            $printer = new Printer($connector);
        }catch(\Exception $e){ return null;}
        try {
            try{
                $headersTCK = json_decode($header['cash']['cashier']['cash']['tpv']['herader_tck']);
                $printer->setJustification(printer::JUSTIFY_LEFT);
                $printer->text(" \n");
                $printer->text(" \n");
                $printer->text("------------------------------------------------\n");
                $printer->text(" \n");
                foreach($headersTCK  as $headerT){
                    $printer->text($headerT->val."\n");
                }
                $printer->text(" \n");
                $printer->text(" \n");
                $printer->text("------------------------------------------------\n");
                $printer->text("N° ".$header['advance']['fs_id']." Fecha: ".now()->format('d/m/Y H:i') ." \n");
                $printer->text("creado Por :".$header['cash']["cashier"]['user']['staff']['complete_name']." \n");
                $printer->text("------------------------------------------------\n");
                $printer->text($header['advance']['client']['name']." \n");
                $printer->text(" \n");
                $printer->text(" \n");
                $printer->text("00000"." \n");
                $printer->text(" \n");
                $printer->text("GVC"." \n");
                $printer->text("------------------------------------------------\n");
                $printer->text(str_pad("IMPORTE VALE: ",14));
                $printer->text(number_format($header['advance']['import'],2)." \n");
                $printer->text("Concepto:"." \n");
                $printer->text($header['advance']['observacion']." \n");
                $printer -> cut();
                $printer -> close();
            }catch(Exception $e){}

        } finally {
            $printer -> close();
            return true;
        }
            return false;
    }

    public function printCut($header){
        $connector = new NetworkPrintConnector($header['print'], 9100, 3);
        if($connector){
            $printer = new Printer($connector);
            $printer->text(" \n");
            $printer->text(" \n");
            // $printer->text("           --REIMPRESION DE CORTE--           \n");
            $printer->text(" \n");
            $printer->text(" \n");
            $printer->text(str_repeat("─", 48) . "\n");
            $printer->text("CIERRE DE TERMINAL"." \n");
            $printer->text($header['emp']['DENEMP']." \n");
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
