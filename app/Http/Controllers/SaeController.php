<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaeController extends Controller
{
    // public function testFirebird() {
    //     $clientes = DB::connection('firebird')
    //         ->select("SELECT * FROM CLIE01 WHERE RFC = 'BRE831202JM2'");
    //     return $clientes;
    // }
    // public function testFirebird() {
    //     $clientes = DB::connection('firebird')
    //         ->select("SELECT * FROM INVE01 WHERE CVE_ART = 'JL1009' ");
    //     return $clientes;
    // }

    // public function testFirebird() {
    //     $clientes = DB::connection('firebird')
    //         ->select("SELECT MAX(FOLIO) + 1 AS MAX FROM FACTF01 WHERE SERIE = 'A' ");
    //     return $clientes;
    // }

    public function readRFC(Request $request){
        $cliente = DB::connection($request->firebird)->table('CLIE01')->where('RFC',$request->rfc)->first();
        if($cliente){
            return response()->json([
                "success"=>true,
                "client"=>$cliente,
                "message"=>'Cliente Registrado'
            ]);
        }else{
            return response()->json([
                "success"=>false,
                "client"=>$cliente,
                "message"=>'Cliente No Registrado'
            ]);
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
        $billing = $request->all();
        $conn = DB::connection($billing['store']['firebird']);
        $conn->beginTransaction();
        $emp = $billing['store']['firebird'] == 'lluvia' ? '01' : '02';

        try {
            $folio = $conn->table($this->t('FOLIOSF', $emp))
                ->where('TIP_DOC', 'F')
                ->where('SERIE', $billing['store']['prefix'])
                ->lockForUpdate()
                ->first();
            $folioActual = $folio->ULT_DOC + 1;

            $conn->table($this->t('FOLIOSF', $emp))
                ->where('TIP_DOC', 'F')
                ->where('SERIE', $billing['store']['prefix'])
                ->update([
                    'ULT_DOC' => $folioActual,
                    'FECH_ULT_DOC' => now()->format('Y-m-d')
            ]);

            $cveDoc = $billing['store']['prefix'] . str_pad($folioActual, 10, '0', STR_PAD_LEFT);
            $claveCliente = str_pad($billing['nclient'], 10, ' ', STR_PAD_LEFT);

            $client = $conn->table($this->t('CLIE', $emp))->where('CLAVE',$claveCliente)->first();
            $conn->table($this->t('CLIE', $emp))->where('CLAVE',$claveCliente)->update([
                'ULT_PAGOM'=> $billing['total'] ,
                'ULT_VENTAD'=> $cveDoc,
                'ULT_COMPM'=> $billing['total'],
                'VENTAS'=> $client->VENTAS + $billing['total'] ,
                'VERSION_SINC'=>now()->format('Y-m-d H:i:s')
            ]);

            $ids = [32, 44, 62, 70];

            $controles = $conn->table($this->t('TBLCONTROL', $emp))
            ->whereIn('ID_TABLA', $ids)
            ->lockForUpdate()
            ->get()
            ->keyBy('ID_TABLA');

            // Obtener siguientes valores
            $siguientes = [];

            foreach ($ids as $id) {
            $siguientes[$id] = ($controles[$id]->ULT_CVE ?? 0) + 1;
            }

            // Actualizar TBLCONTROL01
            foreach ($siguientes as $id => $valor) {
                $conn->table($this->t('TBLCONTROL', $emp))
                ->where('ID_TABLA', $id)
                ->update(['ULT_CVE' => $valor]);
            }

            $cveFact   = $siguientes[32];
            $cvePar    = $siguientes[44];
            $cveBita   = $siguientes[62];
            $cveAuto   = $siguientes[70];

            $conn->table($this->t('PAR_FACTF_CLIB', $emp))->insert([
                'CLAVE_DOC'=> $cveDoc,
                'NUM_PART'=> 1,
            ]);

            $conn->table($this->t('FACTF_CLIB', $emp))->insert([
                'CLAVE_DOC'=> $cveDoc,
            ]);


            $conn->table($this->t('BITA', $emp))->insert([
                'CVE_BITA' => $cveBita,
                'CVE_CLIE' => $claveCliente,
                'CVE_CAMPANIA' => '_SAE_',
                'CVE_ACTIVIDAD'=> str_pad(2, 5, ' ', STR_PAD_LEFT),
                'FECHAHORA' => now()->format('Y-m-d H:i:s'),
                'CVE_USUARIO' => 0,
                'OBSERVACIONES'=>"No. [ ".$cveDoc." ] $ ".$billing['total'],
                'STATUS' => 'F',
                'NOM_USUARIO' => 'Administrador'
            ]);

            $totalSIVA =  round($billing['total']/1.16,6);
            $imps = round($billing['total']*0.16,6);

            $conn->table($this->t('FACTF', $emp))->insert([
                'CVE_DOC'   => $cveDoc,
                'TIP_DOC'   => 'F',
                'CVE_CLPV'  => $claveCliente,
                'STATUS'    => 'E',
                'DAT_MOSTR' => 0,
                'CVE_VEND'  => '',
                'CVE_PEDI'  => '',
                'CAN_TOT'  =>  $totalSIVA,
                'IMP_TOT1' => 0,
                'IMP_TOT2' => 0,
                'IMP_TOT3' => 0,
                'IMP_TOT4' => $imps,
                'IMP_TOT5' => 0,
                'IMP_TOT6' => 0,
                'IMP_TOT7' => 0,
                'IMP_TOT8' => 0,
                'DES_TOT' => 0,
                'DES_FIN' => 0,
                'COM_TOT' => 0,
                'CVE_OBS' => 0,
                'NUM_ALMA' => 1,
                'ACT_CXC' => 'S',
                'ACT_COI' => 'N',
                'ENLAZADO' => 'O',
                'TIP_DOC_E' => 'O',
                'NUM_PAGOS' => 1,
                'PRIMERPAGO' => $billing['total'],
                'CTLPOL' => 0,
                'ESCFD' => 'P',
                'AUTORIZA' => 0,
                'SERIE' => $billing['store']['prefix'],
                'FOLIO' => $folioActual,
                'AUTOANIO' => '',
                'DAT_ENVIO' => 0,
                'CONTADO'=> 'S',
                'CVE_BITA'  => $cveBita,
                'BLOQ' => 'N',
                'DES_FIN_PORC' => 0,
                'DES_TOT_PORC' => 0,
                'IMPORTE'   => $billing['total'],
                'COM_TOT_PORC' => 0,
                'NUMCTAPAGO' => '',
                'TIP_DOC_ANT' => '',
                'DOC_ANT' => '',
                'FECHA_DOC' => now()->format('Y-m-d'),
                'FECHA_ENT' => now()->format('Y-m-d'),
                'FECHA_VEN' => now()->format('Y-m-d'),
                'RFC'       => $billing['rfc'],
                'USO_CFDI'  => $billing['cfdi']['alias'],
                'METODODEPAGO' => 'PUE',
                'FORMADEPAGOSAT' => $billing['payments'][0]['sat'],
                'NUM_MONED' => 1,
                'TIPCAMB' => 1,
                'FECHAELAB'=> now()->format('Y-m-d H:i:s'),
                'TIP_FAC' => 'U',
                'REG_FISC' => $client->REG_FISC
            ]);
            $i = 1;
            $tableProd = $conn->table($this->t('PAR_FACTF', $emp))->max('NUM_MOV');
            $num_mov = $tableProd + 1;
            foreach ($billing['ticketSuc']['products'] as $prod) {
                $siniva = round($prod['PRELFA'] / 1.16,6);
                $impusto = round(($siniva * $prod['CANLFA']) * 0.16,6);
                $totalSinIva  = round($siniva * $prod['CANLFA'], 2);

                $conn->table($this->t('PAR_FACTF', $emp))->insert([
                    'CVE_DOC' => $cveDoc,
                    'NUM_PAR' => $i++,
                    'CVE_ART' => $prod['ARTLFA'],
                    'CANT' => $prod['CANLFA'],
                    'PXS' => $prod['CANLFA'],
                    'PREC' => $siniva ,
                    'COST'=>0,
                    'IMPU1'=>0,
                    'IMPU2'=>0,
                    'IMPU3'=>0,
                    'IMPU4'=>163,
                    'IMP1APLA'=>6,
                    'IMP2APLA'=>6,
                    'IMP3APLA'=>6,
                    'IMP4APLA'=>0,
                    'TOTIMP1'=>0,
                    'TOTIMP2'=>0,
                    'TOTIMP3'=>0,
                    'TOTIMP4'=>$impusto,
                    'DESC1'=>0,
                    'DESC2'=>0,
                    'DESC3'=>0,
                    'COMI'=>0,
                    'APAR'=>0,
                    'ACT_INV'=>'N',
                    'TIP_CAM'=>1,
                    'CVE_OBS'=>0,
                    'REG_SERIE'=>0,
                    'E_LTPD'=>0,
                    'TIPO_ELEM'=>'N',
                    'NUM_MOV'=> $num_mov++,//SE TIENE QUE SACAR CUAL SIGUE
                    'IMPRIMIR' => 'S',
                    'MAN_IEPS' => 'N',
                    'APL_MAN_IMP'=>1,
                    'CUOTA_IEPS'=>0,
                    'APL_MAN_IEPS'=>'C',
                    'MTO_PORC'=>0,
                    'MTO_CUOTA'=>0,
                    'CVE_ESQ'=>1,
                    'IMPU5' => 0,
                    'IMPU6' => 0,
                    'IMPU7'=> 0,
                    'TOTIMP8'=> 0,
                    'IMP8APLA'=> 6,
                    'IMPU8'=> 0,
                    'IMP5APLA'=> 6,
                    'IMP6APLA'=> 6,
                    'IMP7APLA'=> 6,
                    'TOTIMP7'=> 0,
                    'TOTIMP5'=> 0,
                    'TOTIMP6'=> 0,
                    'POLIT_APLI' => '',
                    'TOT_PARTIDA' =>$totalSinIva ,
                    'NUM_ALM' => 1,
                    'TIPO_PROD' => 'P',
                    'UNI_VENTA' => 'pz',
                    'CVE_PRODSERV' => $prod['sat']['clave'] ?? null,
                    'CVE_UNIDAD'   => $prod['sat']['unidad'] ?? null,
                ]);

                $articule = $conn->table($this->t('INVE', $emp))
                ->where('CVE_ART',$prod['ARTLFA'])->first();

                $conn->table($this->t('INVE', $emp))
                ->where('CVE_ART',$prod['ARTLFA'])
                ->update([
                    'VTAS_ANL_C' => $articule->VTAS_ANL_C +  $prod['CANLFA'] ,
                    'VTAS_ANL_M' => $articule->VTAS_ANL_M + $totalSinIva,
                    'VERSION_SINC' => now()->format('Y-m-d H:i:s'),
                ]);

                $conn->table($this->t('PAR_FACTF_CLIB', $emp))->insert([
                    'CLAVE_DOC'=> $cveDoc,
                    'NUM_PART'=> $i++,
                ]);
            }

            $conn->table($this->t('CUEN_M', $emp))->insert([
                'CVE_CLIE' => $claveCliente,
                'NUM_CPTO' => 1,
                'NUM_CARGO' => 1,
                'REFER' => $cveDoc,
                'NO_FACTURA' => $cveDoc,
                'DOCTO' => $cveDoc,
                'IMPORTE' => $billing['total'],
                'FECHA_APLI' => now()->format('Y-m-d'),
                'FECHA_VENC' => now()->format('Y-m-d'),
                'TIPO_MOV' => 'C',
                'SIGNO' => 1,
                'STATUS' => 'A'
            ]);

            $afac = $conn->table($this->t('AFACT', $emp))->where('CVE_AFACT',37)->first();



            $conn->table($this->t('AFACT', $emp))->where('CVE_AFACT',37)->update([
                    'FVTA_COM' => $afac->FVTA_COM + $totalSIVA , //AQUI DEBE DE IR EL TOTAL SIN IMPUESTO
                    'FIMP' => $afac->FIMP + $imps, //AQUI DEBE DE IR EL IMPUESTO
                    'PER_ACUM' => now()->format('Y-m-d H:i:s')
            ]);

            $conn->table($this->t('CFDI', $emp))->insert([
                'TIPO_DOC'=>'F',
                'CVE_DOC' => $cveDoc,
                'VERSION' => 1.1,
                'UUID'=>'',
                'NO_SERIE'=>'',
                'FECHA_CERT'=>'',
                'FECHA_CANCELA'=>'',
                'DESGLOCEIMP1'=>'N',
                'DESGLOCEIMP2'=>'N',
                'DESGLOCEIMP3'=>'N',
                'DESGLOCEIMP4'=>'S',
                'PENDIENTE'=>'T',
                'EN_TABLERO'=>'S',
                'CVE_USUARIO'=>0,
            ]);

            $empresa = [
                "RFC"=>"LLI1210184G8",
                "NOMBRE"=>"LLuvia Light",
                "REGIMEN"=>"601"
            ];


            $xml = $this->generarXmlPrefactura($billing, $cveDoc, $folioActual,$client,$empresa);
            $this->insertarXmlCFDI($conn, $cveDoc, $xml);

            $conn->commit();

        } catch (\Throwable $e) {
            $conn->rollBack();
            throw $e;
               return response()->json([
                    'ok' => false,
                    'message' => 'Error al crear factura en SAE',
                    'error' => $e->getMessage()
                ], 500);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Factura creada correctamente en SAE',
            'data' => [
                'serie'     => $billing['store']['prefix'],
                'folio'     => $folioActual,
                'cve_doc'   => $cveDoc,
                'cve_bita'  => $cveBita,
                'cliente'  => trim($claveCliente),
                'total'     => $billing['total'],
                'fecha'     => now()->format('Y-m-d H:i:s')
            ]
        ], 201);
    }
    private function insertarXmlCFDI($conn, string $cveDoc, string $xml){
        $pdo = $conn->getPdo();
        $sql = "
            UPDATE CFDI01
            SET XML_DOC = CAST(? AS BLOB SUB_TYPE 0)
            WHERE CVE_DOC = ?
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(1, $xml, \PDO::PARAM_LOB);
        $stmt->bindParam(2, $cveDoc);
        $stmt->execute();
    }

    private function generarXmlPrefactura(array $billing, string $cveDoc, int $folio, $cliente, $empresa){
        $clave = env('NO_CERTIFICADO');
        $certificado = env('CERTIFICADO');
        $fecha = now()->format('Y-m-d\TH:i:s');

        $subTotal = 0;
        $iva = 0;

        foreach ($billing['ticketSuc']['products'] as $prod) {
            $importe = round($prod['PRELFA']/1.16,2) * $prod['CANLFA'];
            $subTotal += $importe;
            $iva += round($importe * 0.16, 2);
        }

        $total = $subTotal + $iva;

        $xml = <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <cfdi:Comprobante
            xsi:schemaLocation="http://www.sat.gob.mx/cfd/4
            http://www.sat.gob.mx/sitio_internet/cfd/4/cfdv40.xsd"
            Version="4.0"
            Serie="{$billing['store']['prefix']}"
            Folio="{$folio}"
            Fecha="{$fecha}"
            FormaPago="{$billing['payments'][0]['sat']}"
            NoCertificado=''
            Certificado=''
            Sello=''
            SubTotal="{$subTotal}"
            Moneda="MXN"
            Exportacion="01"
            Total="{$total}"
            TipoDeComprobante="I"
            MetodoPago="PUE"
            LugarExpedicion="06020"
            xmlns:xs="http://www.w3.org/2001/XMLSchema"
            xmlns:cfdi="http://www.sat.gob.mx/cfd/4"
            xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
            >
            <!-- datos de la empresa -->
            <cfdi:Emisor
                Rfc="{$empresa['RFC']}"
                Nombre="{$empresa['NOMBRE']}"
                RegimenFiscal="{$empresa['REGIMEN']}"/>
            <!-- datos cliente -->
            <cfdi:Receptor
                Rfc="{$cliente->RFC}"
                Nombre="{$cliente->NOMBRE}"
                DomicilioFiscalReceptor="{$cliente->CODIGO}"
                RegimenFiscalReceptor="{$cliente->REG_FISC}"
                UsoCFDI="{$billing['cfdi']['alias']}"/>
            <cfdi:Conceptos>
        XML;

            foreach ($billing['ticketSuc']['products'] as $prod) {
                $precio = round($prod['PRELFA']/1.16,2);
                $importe = round($prod['TOTLFA']/1.16);
                $ivaProd = round($precio * 0.16, 2);

                $xml .= <<<XML
                <cfdi:Concepto
                    ClaveProdServ="{$prod['sat']['clave']}"
                    Cantidad="{$prod['CANLFA']}"
                    ClaveUnidad="{$prod['sat']['unidad']}"
                    Descripcion="{$prod['DESLFA']}"
                    ValorUnitario="{$precio}"
                    Importe="{$importe}"
                    ObjetoImp="02">
                    <cfdi:Impuestos>
                        <cfdi:Traslados>
                            <cfdi:Traslado
                                Base="{$importe}"
                                Impuesto="002"
                                TipoFactor="Tasa"
                                TasaOCuota="0.160000"
                                Importe="{$ivaProd}"/>
                        </cfdi:Traslados>
                    </cfdi:Impuestos>
                </cfdi:Concepto>
        XML;
            }

            $xml .= <<<XML
            </cfdi:Conceptos>
            <cfdi:Impuestos TotalImpuestosTrasladados="{$iva}">
                <cfdi:Traslados>
                    <cfdi:Traslado
                        Base="{$subTotal}"
                        Impuesto="002"
                        TipoFactor="Tasa"
                        TasaOCuota="0.160000"
                        Importe="{$iva}"/>
                </cfdi:Traslados>
            </cfdi:Impuestos>
        </cfdi:Comprobante>
        XML;

            return $xml;
    }
    private function t(string $base, string $emp): string{
        return $base . $emp;
    }

}
