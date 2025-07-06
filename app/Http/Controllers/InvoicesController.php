<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

class InvoicesController extends Controller
{
    private $conn = null;

    public function __construct(){
      $access = env("ACCESS");
      if(file_exists($access)){
      try{  $this->conn  = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
          }catch(\PDOException $e){ die($e->getMessage()); }
      }else{ die("$access no es un origen de datos valido."); }
    }

    public function invoice(Request $request){ //metodo para crear la salida a la sucursal
        try{
            $id = $request->id;
            $supply = $request->supply;
            $date = date("Y/m/d H:i");
            $date_format = Carbon::now()->format('d/m/Y');
            $hour = "01/01/1900 ".explode(" ", $date)[1];
            $status = $request->_status;
            if($status >= 6){//SE VALIDA QUE LA REQUISICION ESTE EN ESTATUS 6 validando
                $count =  collect($request->products)->filter(function ($product) {
                    return $product['pivot']['toDelivered'] > 0 && $product['pivot']['checkout'] == 1;
                })->count();//se cuentan cuantos articulos se validaron
                if($count > 0){//SE VALIDA QUE LA REQUISICION CONTENGA AL MENOS 1 ARTICULO CONTADO
                    $clien = $request->requisition['from']['_client'];//se obtiene el cliente de el query que es el numero de cliente de la sucursal que pide la mercancia
                    $not = $request->requisition['notes'];
                    switch($request->requisition['to']['id']){
                        case 1://cedis
                            $almfrom = 'GEN';
                        break;
                        case 2://texcoco
                            $almfrom = 'STC';
                        break;
                        case 16://brasil
                            $almfrom = 'BRA';
                        break;
                        case 21://pantaco
                            $almfrom = 'PAN';
                        break;
                        case 24://bolivia
                            $almfrom = 'BOL';
                        break;
                    }
                    $client = "SELECT * FROM F_CLI WHERE CODCLI = $clien";//query para obtener los datos de el cliente directamente de factusol
                    $exec = $this->conn->prepare($client);
                    $exec -> execute();
                    $roles= $exec->fetch(\PDO::FETCH_ASSOC);
                        $rol = $roles["DOCCLI"];//tipo de documento que se debe de crear en factusol
                        $nofcli = $roles["NOFCLI"];//nombre de el cliente
                        $dom = $roles["DOMCLI"];//domicilio
                        $pob = $roles["POBCLI"];//poblacion
                        $cpo = $roles["CPOCLI"];//codigo postal
                        $pro = $roles["PROCLI"];//providencia
                        $tel = $roles["TELCLI"];//telefono
                    $max = "SELECT max(CODFAC) as CODIGO FROM F_FAC WHERE TIPFAC = '".$rol."'";//query para sacar el numero de factura maximo de el tipo(serie)
                    $exec = $this->conn->prepare($max);
                    $exec -> execute();
                    $maxcode=$exec->fetch(\PDO::FETCH_ASSOC);//averS
                    $codfac = intval($maxcode["CODIGO"])+ 1;//se obtiene el nuevo numero de factura que se inserara
                    $prouduct = $this->productrequired($request->products,$rol,$codfac,$almfrom);//se envian datos id de la requisision, tipo de factura(serie) y codigo de factura a insertar hacia el metodo
                        $fac = [//se crea el arrego para insertar en factusol
                            $rol,//tipo(serie) de factura
                            $codfac,//codigo de factura
                            "P-".$request->id."N-".$not,//codigo de requisision de la aplicacion
                            $date_format,//fecha actual en formato
                            $almfrom,//almacen de donde sale la mercancia siempre sera GEN
                            500,//agente que atiende la factura siempre sera 500 cuando es de cedis
                            $clien,//numero de cliente
                            $nofcli,//nombre de cliente
                            $dom,//domicilio
                            $pob,//poblacion
                            $cpo,//codigo postal
                            $pro,//providencia
                            $tel,//telefono
                            $prouduct,//el metodo productrequired me devuelve el total o sea que este es el total de la factura compas xd
                            $prouduct,//el metodo productrequired me devuelve el total o sea que este es el total de la factura compas xd
                            $prouduct,//el metodo productrequired me devuelve el total o sea que este es el total de la factura compas xd
                            "C30",//la forma de pago siempre esta en credito 30 dias
                            $not,//observaciones
                            $date_format,//fecha actual en formato
                            $hour,//hora
                            $request->verified['id_fs'],//quien hizo la factura en este caso vizapp
                            $request->verified['id_fs'],//quien modifico simpre sera el mismo cuando se insertan
                            1,//iva2
                            2,//iva3
                            "02-01-00",//fehca operacion contable simpre esa cambia hasta que se traspasa a contasol
                            2025,//ano de ejercicio
                            $date_format,//fecha actual en formato
                            1//no se xd pero se requiere para mostrar la factura
                        ];//termino de arreglo de insercion

                    $sql = "INSERT INTO F_FAC (TIPFAC,CODFAC,REFFAC,FECFAC,ALMFAC,AGEFAC,CLIFAC,CNOFAC,CDOFAC,CPOFAC,CCPFAC,CPRFAC,TELFAC,NET1FAC,BAS1FAC,TOTFAC,FOPFAC,OB1FAC,VENFAC,HORFAC,USUFAC,USMFAC,TIVA2FAC,TIVA3FAC,FROFAC,EDRFAC,FUMFAC,BCOFAC) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";//se crea el query para insertar en la tabla
                    $exec = $this->conn->prepare($sql);
                    $exec -> execute($fac);
                    $folio = $rol."-".str_pad($codfac, 6, "0", STR_PAD_LEFT);//se obtiene el folio de la factura
                    return response()->json(["folio"=>$folio],201);//se retorna el folio de la factura
                }else{return response()->json("NO SE PUEDE PROCESAR YA QUE NO HAY ARTICULOS VALIDADOS (LOS PRODUCTOS ESTAN EN 0)",400);}
            }else{return response()->json("NO SE CREA LA FACTURA LA REQUISICION AUN NO ES VALIDADA",400);}
        }catch (\PDOException $e){ die($e->getMessage());}
    }


    public function productrequired($products,$rol,$codfac,$alm){//metoro de insercion de productos en factusol
        $product_require = $products;
        $pos= 1;//inicio contador de posision
        $ttotal=0;//inicio contador de total

        foreach($product_require as $pro){//inicio de cliclo para obtener productos
            $precio = $pro['prices'][6]['pivot']['price'];//se optiene el precio de cada producto
            $bull = null;
            if($pro['pivot']['_supply_by'] == 1)
                {$canti = $pro['pivot']['toDelivered'] ;}
            elseif($pro['pivot']['_supply_by'] == 2){
                $canti = $pro['pivot']['toDelivered'] * 12;
            }elseif($pro['pivot']['_supply_by'] == 3){
                $canti = $pro['pivot']['toDelivered'] * $pro['pieces'] ; $bull = $pro['pivot']['toDelivered'];
            }elseif($pro['pivot']['_supply_by'] == 4){
                $canti = ($pro['pivot']['toDelivered'] * ($pro['pieces'] / 2)) ;
            }//se valida la unidad de medida de el surtio

            // $bul = $bull > 0 ? $bull : null;
            $total = $precio * $canti ;//se obtiene el total de la linea
            $ttotal = $ttotal + $total ;//se obtiene el total de la requisision
            $values = [//se genera el arreglo para la insercion a factusol
                $rol,//tipo de documento
                $codfac,//codigo de documento
                $pos,//posision de la linea
                $pro['code'],//codigo de el articulo
                $pro['description'],//descripcion de el articulo
                $canti,//cantidad contada
                $precio,//precio de el articulo
                $total,//total de la linea
                $pro['cost'],//costo actual de el articulo
                $bull//cajas ponidas
            ];
            $insert = "INSERT INTO F_LFA (TIPLFA,CODLFA,POSLFA,ARTLFA,DESLFA,CANLFA,PRELFA,TOTLFA,COSLFA,BULLFA) VALUES (?,?,?,?,?,?,?,?,?,?)";//query para insertar las lineas de la factura creada en factusol
            $exec = $this->conn->prepare($insert);
            $exec -> execute($values);//envia el arreglo

            $updatestock = "UPDATE F_STO SET ACTSTO = ACTSTO - ? , DISSTO = DISSTO - ?  WHERE  ARTSTO = ? AND ALMSTO = ?";//query para actualizar los stock de el almacen recordemos que solo es general
            $exec = $this->conn->prepare($updatestock);
            $exec -> execute([$canti,$canti,$pro['code'], $alm]);
            $pos++;//contador
        }
        return $ttotal;//se retorna el total para el uso en el encabezado de la factura
    }

    public function transfer(Request $request){ //metodo para crear la salida a la sucursal
        $aor = null;//almacen de origen
        $ade = null;
        try{
            $id = $request->id;//se recibe por metodo post el id de la requisicion
            $supply = $request->supply;
            $date = date("Y/m/d H:i");//se gerera la fecha de el dia de hoy con  formato de fecha y hora
            $date_format = Carbon::now()->format('d/m/Y');
            // $date_format = date("d/m/Y");//se formatea la fecha de el dia con el formato solo de fecha
            $hour = "01/01/1900 ".explode(" ", $date)[1];//se formatea la fecha de el dia de hoy poniendo solo la hora en la que se genera
            $status = $request->_status;//se obtiene el status de el la requisicion
            if($status >= 6){//SE VALIDA QUE LA REQUISICION ESTE EN ESTATUS 6 validando
                $count =  collect($request->products)->filter(function ($product) {
                    return $product['pivot']['toDelivered'] > 0 && $product['pivot']['checkout'] == 1;
                })->count();//se cuentan cuantos articulos se validaron
                if($count > 0){//SE VALIDA QUE LA REQUISICION CONTENGA AL MENOS 1 ARTICULO CONTADO
                    $comments = $request->requisition['notes'];
                    switch($request->requisition['to']['id']){
                        case 1://cedis
                            $aor = 'GEN';
                        break;
                        case 2://texcoco
                            $aor = 'STC';
                        break;
                        case 16://brasil
                            $aor = 'BRA';
                        break;
                        case 21://pantaco
                            $aor = 'PAN';
                        break;
                        case 24://bolivia
                            $aor = 'BOL';
                        break;
                    }
                    $ade = 'RTA';//almacen de ruta

                    $max = "SELECT max(DOCTRA) as CODIGO FROM F_TRA";//query para encontrar el maximo codigo de traspas
                    $exec = $this->conn->prepare($max);
                    $exec -> execute();
                    $maxcode=$exec->fetch(\PDO::FETCH_ASSOC);//averS
                        $codtra = intval($maxcode["CODIGO"])+ 1;//se obtiene el nuevo codigo de traspaso
                    $prouduct = $this->productrequiredTr($codtra,$request->products,$ade,$aor);//se envian datos id de la requisision, tipo de factura(serie) y codigo de factura a insertar hacia el metodo
                        $tra = [//se crea el arrego para insertar en factusol
                            $codtra,//codigo traspaso
                            $date_format,//fecha actual en formato
                            $aor,//almacen de origen
                            $ade,//almacen destino
                            $comments,//comentarios
                        ];//termino de arreglo de insercion

                    $sql = "INSERT INTO F_TRA (DOCTRA,FECTRA, AORTRA,ADETRA,COMTRA) VALUES (?,?,?,?,?)";//se crea el query para insertar en la tabla
                    $exec = $this->conn->prepare($sql);
                    $exec -> execute($tra);
                    $folio = $codtra;//se obtiene el folio de el traspaso
                    return response()->json(["folio"=>$folio],201);
                }else{return response()->json("NO SE PUEDE PROCESAR YA QUE NO HAY ARTICULOS VALIDADOS (LOS PRODUCTOS ESTAN EN 0)",400);}
            }else{return response()->json("NO SE CREA LA FACTURA LA REQUISICION AUN NO ES VALIDADA",400);}
        }catch (\PDOException $e){ die($e->getMessage());}
    }

    public function productrequiredTr($codtra,$product_require,$ade,$aor){//metodo de insercion de productos en factusol
        $pos= 1;//inicio contador de posision
        $ttotal=0;//inicio contador de total

        foreach($product_require as $pro){//inicio de cliclo para obtener productos
            $bull = 0;
            if($pro['pivot']['_supply_by'] == 1)
                {$canti = $pro['pivot']['toDelivered'] ;}
            elseif($pro['pivot']['_supply_by'] == 2){
                $canti = $pro['pivot']['toDelivered'] * 12;
            }elseif($pro['pivot']['_supply_by'] == 3){
                $canti = $pro['pivot']['toDelivered'] * $pro['pieces'] ; $bull = $pro['pivot']['toDelivered'];
            }elseif($pro['pivot']['_supply_by'] == 4){
                $canti = ($pro['pivot']['toDelivered'] * ($pro['pieces'] / 2)) ;
            }//se valida la unidad de medida de el surtio
            $values = [//se genera el arreglo para la insercion a factusol
                $codtra,//codigo de documento
                $pos,//posision de la linea
                $pro['code'],//codigo de el articulo
                $canti,//cantidad contada
                $bull//cajas ponidas
            ];
            $insert = "INSERT INTO F_LTR (DOCLTR,LINLTR,ARTLTR,CANLTR,BULLTR) VALUES (?,?,?,?,?)";//query para insertar las lineas de el traspaso  creada en factusol
            $exec = $this->conn->prepare($insert);
            $exec -> execute($values);//envia el arreglo

            $updatestockori = "UPDATE F_STO SET ACTSTO = ACTSTO - ? , DISSTO = DISSTO - ?  WHERE  ARTSTO = ? AND ALMSTO = ?";//query para actualizar los stock de el almacen origen
            $exec = $this->conn->prepare($updatestockori);
            $exec -> execute([$canti,$canti,$pro['code'], $aor]);

            $updatestockdes = "UPDATE F_STO SET ACTSTO = ACTSTO + ? , DISSTO = DISSTO + ?  WHERE  ARTSTO = ? AND ALMSTO = ?";//query para actualizar los stock de el almacen destino
            $exec = $this->conn->prepare($updatestockdes);
            $exec -> execute([$canti,$canti,$pro['code'], $ade]);

            $pos++;//contador
        }

        return $ttotal;//se retorna el total para el uso en el encabezado de la factura

    }


    public function endTransfer(Request $request){ //metodo para crear la salida a la sucursal
        $aor = null;//almacen de origen
        $ade = null;//almacen destino
        $rut = 'RTA';

        try{
            $id = $request->id;//se recibe por metodo post el id de la requisicion
            $supply = $request->supply;
            $date = date("Y/m/d H:i");//se gerera la fecha de el dia de hoy con  formato de fecha y hora
            $date_format = Carbon::now()->format('d/m/Y');
            // $date_format = date("d/m/Y");//se formatea la fecha de el dia con el formato solo de fecha
            $hour = "01/01/1900 ".explode(" ", $date)[1];//se formatea la fecha de el dia de hoy poniendo solo la hora en la que se genera
            $status =  $request->_status;//se obtiene el status de el la requisicion
                if($status == 10){//SE VALIDA QUE LA REQUISICION ESTE EN ESTATUS 6 validando
                    $count =  collect($request->products)->filter(function ($product) {
                    return $product['pivot']['toReceived'] > 0 && $product['pivot']['checkout'] == 1;
                    })->count();//se cuentan cuantos articulos se validaron
                    if($count > 0){//SE VALIDA QUE LA REQUISICION CONTENGA AL MENOS 1 ARTICULO CONTADO
                        // $requisitions = DB::connection('vizapi')->table('requisition AS R')->where('R.id', $id)->first();//se realiza el query para pasar los datos de la requisicion con la condicion de el id recibido
                        // $partition = DB::connection('vizapi')->table('requisition_partitions')->where([['_requisition',$id],['_suplier_id',$supply]])->first();//se realiza el query para pasar los datos de la requisicion con la condicion de el id recibido

                        // $comments = $requisitions->notes;
                        switch($request->requisition['from']['id']){
                            case 1://cedis
                                $ade = 'GEN';
                            break;
                            case 2://texcoco
                                $ade = 'STC';
                            break;
                            case 16://brasil
                                $ade = 'BRA';
                            break;
                            case 21://pantaco
                                $ade = 'PAN';
                            break;
                            case 24://bolivia
                                $ade = 'BOL';
                            break;
                        }
                        // $aor = 'RTA';//almacen de ruta
                        $prouduct = $this->productrequiredRec($request->products,$request->invoice,$ade,$rut);//se envian datos id de la requisision, tipo de factura(serie) y codigo de factura a insertar hacia el metodo
                        $updt = [
                            $ade,
                            $request->invoice
                        ];

                        $sql = "UPDATE F_TRA SET ADETRA  = ? WHERE DOCTRA = ?";//se crea el query para insertar en la tabla
                        $exec = $this->conn->prepare($sql);
                        $exec -> execute($updt);
                        return response()->json([
                            "folio"=>$request->invoice],201);//se retorna el folio de la factura
                    }else{return response()->json("NO SE PUEDE PROCESAR YA QUE NO HAY ARTICULOS VALIDADOS (LOS PRODUCTOS ESTAN EN 0)",400);}
                }else{return response()->json("NO SE CREA LA FACTURA LA REQUISICION AUN NO ES VALIDADA",400);}
        }catch (\PDOException $e){ die($e->getMessage());}
    }

    public function productrequiredRec($products,$codtra,$ade,$rut){//metodo de insercion de productos en factusol
        $product_require = $products;
        $pos= 1;//inicio contador de posision
        $ttotal=0;//inicio contador de total

        foreach($product_require as $pro){//inicio de cliclo para obtener productos
            $bull = 0;
            if($pro['pivot']['_supply_by'] == 1)
                {$canti = $pro['pivot']['toReceived'] ;}
            elseif($pro['pivot']['_supply_by'] == 2){
                $canti = $pro['pivot']['toReceived'] * 12;
            }elseif($pro['pivot']['_supply_by'] == 3){
                $canti = $pro['pivot']['toReceived'] * $pro['pieces'] ; $bull = $pro['pivot']['toReceived'];
            }elseif($pro['pivot']['_supply_by'] == 4){
                $canti = ($pro['pivot']['toReceived'] * ($pro['pieces'] / 2)) ;
            }
            $values = [
                $pro['code'],//codigo de el articulo
                $canti,//cantidad contada
                $bull,//cajas ponidas
                $codtra//codigo de documento
            ];

            $update = "UPDATE F_LTR SET ARTLTR = ?,CANLTR = ? ,BULLTR = ? WHERE DOCLTR = ?";//query para insertar las lineas de el traspaso  creada en factusol
            $exec = $this->conn->prepare($update);
            $exec -> execute($values);//envia el arreglo

            $updatestockori = "UPDATE F_STO SET ACTSTO = ACTSTO - ? , DISSTO = DISSTO - ?  WHERE  ARTSTO = ? AND ALMSTO = ?";//query para actualizar los stock de el almacen origen
            $exec = $this->conn->prepare($updatestockori);
            $exec -> execute([$canti,$canti,$pro['code'], $rut]);

            $updatestockdes = "UPDATE F_STO SET ACTSTO = ACTSTO + ? , DISSTO = DISSTO + ?  WHERE  ARTSTO = ? AND ALMSTO = ?";//query para actualizar los stock de el almacen destino
            $exec = $this->conn->prepare($updatestockdes);
            $exec -> execute([$canti,$canti,$pro['code'], $ade]);
            $pos++;//contador
        }

        return $ttotal;//se retorna el total para el uso en el encabezado de la factura

    }



    public function entry(Request $request){ //metodo para crear la salida a la sucursal
        try{
            $token = env('TOKEN_ULTRAMSG');
            $id = $request->id;//se recibe por metodo post el id de la requisicion
            $suply = $request->suply;
            $date = date("Y/m/d H:i");//se gerera la fecha de el dia de hoy con  formato de fecha y hora
            $date_format = Carbon::now()->format('d/m/Y');//se obtiene el dia que ocurre
            // $date_format = date("d/m/Y");//se formatea la fecha de el dia con el formato solo de fecha
            $hour = "01/01/1900 ".explode(" ", $date)[1];//se formatea la fecha de el dia de hoy poniendo solo la hora en la que se genera
            $status = $request->_status;
                if($status == 10){//SE VALIDA QUE LA REQUISICION ESTE EN ESTATUS 10 COMPLETO
                    // $count =DB::connection('vizapi')->table('product_required')->wherenotnull('toDelivered')->where([['_requisition',$id],['_suplier_id', $suply],['toDelivered','>',0]])->count('_product');conteo de productos recibidos
                    // $count =DB::connection('vizapi')->table('product_required')->wherenotnull('toReceived')->where([['_requisition',$id],['_suplier_id', $suply],['toReceived','>',0]])->count('_product');
                    $count =  collect($request->products)->filter(function ($product) {
                        return $product['pivot']['toReceived'] > 0 && $product['pivot']['checkout'] == 1;
                    })->count();

                    if($count > 0){//SE VALIDA QUE LA REQUISICION CONTENGA AL MENOS 1 ARTICULO CONTADO
                        // $requisitions = DB::connection('vizapi')->table('requisition_partitions AS R')->where([['_requisition',$id],['_suplier_id',$suply]])->first();//se realiza el query para pasar los datos de la requisicion con la condicion de el id recibido
                        // $not = $requisitions->notes;//se obtiene las notas de la requisision
                        $rol = "1";
                        $warehouse = $request->_warehouse;
                        $max = "SELECT max(CODFRE) as CODIGO FROM F_FRE WHERE TIPFRE = '".$rol."'";//query para sacar el numero de factura maximo de el tipo(serie)
                        $exec = $this->conn->prepare($max);
                        $exec -> execute();
                        $maxcode=$exec->fetch(\PDO::FETCH_ASSOC);//averS
                        $codfac = intval($maxcode["CODIGO"])+ 1;//se obtiene el nuevo numero de factura que se inserara
                        $product = $this->productreceived($request->products,$rol,$codfac,$warehouse);//se envian datos id de la requisision, tipo de factura(serie) y codigo de factura a insertar hacia el metodo
                        // $request->products,$rol,$codfac,$almfrom
                        $fac = [//se crea el arrego para insertar en factusol
                                $rol,//tipo(serie) de factura
                                $codfac,//codigo de factura
                                "FAC ".$request->invoice,//codigo de factura de salida
                                "P-".$request->_requisition,//codigo de requisision de la aplicacion
                                $date_format,//fecha actual en formato
                                $date_format,//fecha actual en formato
                                5,
                                "BODEGA SAN PABLO 10",
                                "AV SAN PABLO 10 LOCAL G",
                                "Centro",
                                "06090",
                                "DEL. CUAUHTEMOC CD",
                                $product,
                                $product,
                                $product,
                                "02-01-00",
                                "02-01-00",
                                27,
                                27,
                                $warehouse,//almacen de donde sale la mercancia siempre sera GEN jaja ya no jijitl
                                "MEXICO",
                                100,
                                1,
                                2,
                                "02-01-00",
                            ];//termino de arreglo de insercion

                        $sql = "INSERT INTO F_FRE (TIPFRE,CODFRE,FACFRE,REFFRE,FECFRE,FUMFRE,PROFRE,PNOFRE,PDOFRE,PPOFRE,PCPFRE,PPRFRE,NET1FRE,BAS1FRE,TOTFRE,FENFRE,FROFRE,USUFRE,USMFRE,ALMFRE,PPAFRE,PDEFRE,TIVA2FRE,TIVA3FRE,FRCFRE) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";//se crea el query para insertar en la tabla
                        $exec = $this->conn->prepare($sql);
                        $exec -> execute($fac);
                        $folio = $rol."-".str_pad($codfac, 6, "0", STR_PAD_LEFT);//se obtiene el folio de la factura
                        // DB::connection('vizapi')->table('requisition_partitions')->where([['_requisition',$id],['_suplier_id',$suply]])->update(['invoice_received'=>$folio]);//se actualiza la columna invoice con el numero de la factura
                        // $sumcase = DB::connection('vizapi')->table('product_required AS PR')->select(DB::raw('SUM(CASE WHEN PR._supply_by = 1 THEN PR.toReceived  WHEN PR._supply_by = 2  THEN PR.toReceived * 12  WHEN PR._supply_by = 3  THEN PR.toReceived * PR.ipack   WHEN PR._supply_by = 4    THEN (PR.toReceived * (PR.ipack / 2))  ELSE 0  END) AS CASESUM'))->where([['PR._requisition', $id],['PR._suplier_id',$suply]])->first(); //se cuenta cuantas piezas se validaron
                        // $sum = $sumcase->CASESUM;
                        // $countde =DB::connection('vizapi')->table('product_required')->where('_requisition',$id)->wherenotnull('checkout')->count('_product');//suma de conteo de productos enviadas
                        // $sumcasede = DB::connection('vizapi')->table('product_required AS PR')->select(DB::raw('SUM(CASE WHEN PR._supply_by = 1 THEN PR.checkout  WHEN PR._supply_by = 2  THEN PR.checkout * 12  WHEN PR._supply_by = 3  THEN PR.checkout * PR.ipack   WHEN PR._supply_by = 4    THEN (PR.checkout * (PR.ipack / 2))  ELSE 0  END) AS CASESUM'))->where([['PR._requisition', $id],['PR._suplier_id',$suply]])->first(); //se cuenta cuantas piezas se validaron
                        // $sumde = $sumcasede->CASESUM;
                        // $difmod =  $count - $countde;//diferencia de conteos
                        // $difcan = $sum - $sumde;//diferencias en cantidad

                        return response()->json([
                            "folio"=>$folio,
                        // "art_contados"=>$count,
                        // "can_contada"=>$sum
                        ],201);//se retorna el folio de la factura
                    }else{return response()->json("NO SE PUEDE PROCESAR YA QUE NO HAY ARTICULOS VALIDADOS",400);}
                }else{return response()->json("NO SE CREA LA FACTURA LA REQUISICION AUN NO ES VALIDADA",400);}
        }catch (\PDOException $e){ die($e->getMessage());}
    }

    public function productreceived($products,$rol,$codfac,$alm){//metoro de insercion de productos en factusol
        $product_require = $products;
        $pos= 1;//inicio contador de posision
        $ttotal=0;//inicio contador de total
        foreach($product_require as $pro){//inicio de cliclo para obtener productos
            $precio = $pro['prices'][6]['pivot']['price'];//se optiene el precio de cada producto
            if($pro['pivot']['_supply_by'] == 1)
                {$canti = $pro['pivot']['toReceived'] ;}
            elseif($pro['pivot']['_supply_by'] == 2){
                $canti = $pro['pivot']['toReceived'] * 12;
            }elseif($pro['pivot']['_supply_by'] == 3){
                $canti = $pro['pivot']['toReceived'] * $pro['pivot']['ipack'] ; $bull = $pro['pivot']['toReceived'];
            }elseif($pro['pivot']['_supply_by'] == 4){
                $canti = ($pro['pivot']['toReceived'] * ($pro['pivot']['ipack'] / 2)) ;
            }//se valida la unidad de medida de el surtio
            // $cantidad = $pro['']['cantidad'];//se obtine la cantidad de cada producto
            $total = $precio * $canti ;//se obtiene el total de la linea
            $ttotal = $ttotal + $total ;//se obtiene el total de la requisision
            $values = [//se genera el arreglo para la insercion a factusol
                $rol,//tipo de documento
                $codfac,//codigo de documento
                $pos,//posision de la linea
                $pro['code'],//codigo de el articulo
                $pro['description'],//descripcion de el articulo
                $canti,//cantidad contada
                $precio,//precio de el articulo
                $total//total de la linea
            ];
            $insert = "INSERT INTO F_LFR (TIPLFR,CODLFR,POSLFR,ARTLFR,DESLFR,CANLFR,PRELFR,TOTLFR) VALUES (?,?,?,?,?,?,?,?)";//query para insertar las lineas de la factura creada en factusol
            $exec = $this->conn->prepare($insert);
            $exec -> execute($values);//envia el arreglo

            $updatestock = "UPDATE F_STO SET ACTSTO = ACTSTO + ? , DISSTO = DISSTO + ?  WHERE  ARTSTO = ? AND ALMSTO = ?";//query para actualizar los stock de el almacen recordemos que solo es general
            $exec = $this->conn->prepare($updatestock);
            $exec -> execute([$canti,$canti,$pro['code'], $alm]);

            $pos++;//contador
        }
        return $ttotal;//se retorna el total para el uso en el encabezado de la factura

    }


}
