<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

class TransferController extends Controller
{
    private $conn = null;

    public function __construct(){
      $access = env("ACCESS");
      if(file_exists($access)){
      try{  $this->conn  = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
          }catch(\PDOException $e){ die($e->getMessage()); }
      }else{ die("$access no es un origen de datos valido."); }
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
            $status = DB::connection('vizapi')->table('requisition_partitions')->where([['_requisition',$id],['_suplier_id',$supply]])->value('_status');//se obtiene el status de el la requisicion
            $udb = DB::connection('vizapi')->table('requisition_partitions')->where([['_requisition',$id],['_suplier_id',$supply]])->value('id');//se verifica que exista
            if($udb){//SE VALIDA QUE LA REQUISICION EXISTA
                if($status == 6){//SE VALIDA QUE LA REQUISICION ESTE EN ESTATUS 6 validando
                    $count =DB::connection('vizapi')->table('product_required')->where([['_requisition',$id],['_suplier_id',$supply] ])->wherenotnull('toDelivered')->where([['toDelivered','>',0],['checkout',1]])->count('_product');//se cuentan cuantos articulos se validaron
                    $sumcase = DB::connection('vizapi')->table('product_required AS PR')->select(DB::raw('SUM(CASE WHEN PR._supply_by = 1 THEN PR.toDelivered  WHEN PR._supply_by = 2  THEN PR.toDelivered * 12  WHEN PR._supply_by = 3  THEN PR.toDelivered * PR.ipack   WHEN PR._supply_by = 4    THEN (PR.toDelivered * (PR.ipack / 2))  ELSE 0  END) AS CASESUM'))->where([['PR._requisition', $id],['PR._suplier_id',$supply]])->first(); //se cuenta cuantas piezas se validaron
                     $sum = $sumcase->CASESUM;
                    if($count > 0){//SE VALIDA QUE LA REQUISICION CONTENGA AL MENOS 1 ARTICULO CONTADO
                        $requisitions = DB::connection('vizapi')->table('requisition AS R')->where('R.id', $id)->first();//se realiza el query para pasar los datos de la requisicion con la condicion de el id recibido
                        $comments = $requisitions->notes;
                        switch($requisitions->_workpoint_to){
                            case 1://cedis
                                $aor = 'GEN';
                            break;
                            case 2://texcoco
                                $aor = 'STC';
                            break;
                            // case 16://brasil
                            //     $aor = 'GEN'
                            // break;
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
                        $prouduct = $this->productrequired($id,$codtra,$supply,$ade,$aor);//se envian datos id de la requisision, tipo de factura(serie) y codigo de factura a insertar hacia el metodo
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
                        DB::connection('vizapi')->table('requisition_partitions')->where([['_requisition',$id],['_suplier_id',$supply]])->update(['invoice'=>$folio]);//se actualiza la columna invoice traspaso inicial
                        return response()->json([
                            "folio"=>$folio,
                            "art_contados"=>$count,
                            "can_contada"=>$sum],201);//se retorna el folio de la factura
                    }else{return response()->json("NO SE PUEDE PROCESAR YA QUE NO HAY ARTICULOS VALIDADOS (LOS PRODUCTOS ESTAN EN 0)",400);}
                }else{return response()->json("NO SE CREA LA FACTURA LA REQUISICION AUN NO ES VALIDADA",400);}
            }else{return response()->json("EL CODIGO DE REQUISICION NO EXITE",404);}
        }catch (\PDOException $e){ die($e->getMessage());}
    }


    public function productrequired($id,$codtra,$supply,$ade,$aor){//metodo de insercion de productos en factusol


        $product_require = DB::connection('vizapi')->table('product_required AS PR')//se crea el query para obteener los productos de la requisision
            ->join('products AS P','P.id','=','PR._product')
            ->leftjoin('prices_product AS PP','PP._product','=','P.id')
            ->where('PR._requisition',$id)
            ->where('PR._suplier_id',$supply)
            ->wherenotnull('PR.toDelivered')
            ->where([['PR.toDelivered','>',0],['checkout',1]])
            ->select('P.code AS codigo','P.description AS descripcion','PR.toDelivered AS cantidad','PP.AAA AS precio' ,'P.cost as costo','PR._supply_by AS medida','PR.ipack AS PXC')
            ->get();

        $pos= 1;//inicio contador de posision
        $ttotal=0;//inicio contador de total

        foreach($product_require as $pro){//inicio de cliclo para obtener productos
            $precio = $pro->precio;//se optiene el precio de cada producto
            $bull = null;
            if($pro->medida == 1){$canti = $pro->cantidad ;}elseif($pro->medida == 2){$canti = $pro->cantidad * 12; }elseif($pro->medida == 3){$canti = $pro->cantidad * $pro->PXC ; $bull = $pro->cantidad; }elseif($pro->medida == 4){$canti = ($pro->cantidad * ($pro->PXC / 2)) ;}//se valida la unidad de medida de el surtio
            // $bul = $bull > 0 ? $bull : null;
            // $total = $precio * $canti ;//se obtiene el total de la linea
            // $ttotal = $ttotal + $total ;//se obtiene el total de la requisision
            $values = [//se genera el arreglo para la insercion a factusol
                $codtra,//codigo de documento
                $pos,//posision de la linea
                $pro->codigo,//codigo de el articulo
                $canti,//cantidad contada
                $bull//cajas ponidas
            ];
            $insert = "INSERT INTO F_LTR (DOCLTR,LINLTR,ARTLTR,CANLTR,BULLTR) VALUES (?,?,?,?,?)";//query para insertar las lineas de el traspaso  creada en factusol
            $exec = $this->conn->prepare($insert);
            $exec -> execute($values);//envia el arreglo

            $updatestockori = "UPDATE F_STO SET ACTSTO = ACTSTO - ? , DISSTO = DISSTO - ?  WHERE  ARTSTO = ? AND ALMSTO = ?";//query para actualizar los stock de el almacen origen
            $exec = $this->conn->prepare($updatestockori);
            $exec -> execute([$canti,$canti,$pro->codigo, $aor]);

            $updatestockdes = "UPDATE F_STO SET ACTSTO = ACTSTO + ? , DISSTO = DISSTO + ?  WHERE  ARTSTO = ? AND ALMSTO = ?";//query para actualizar los stock de el almacen destino
            $exec = $this->conn->prepare($updatestockdes);
            $exec -> execute([$canti,$canti,$pro->codigo, $ade]);

            $pos++;//contador
        }

        return $ttotal;//se retorna el total para el uso en el encabezado de la factura

    }


    public function transferRec(Request $request){ //metodo para crear la salida a la sucursal
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
            $status = DB::connection('vizapi')->table('requisition_partitions')->where([['_requisition',$id],['_suplier_id',$supply]])->value('_status');//se obtiene el status de el la requisicion
            $udb = DB::connection('vizapi')->table('requisition_partitions')->where([['_requisition',$id],['_suplier_id',$supply]])->value('id');//se verifica que exista
            if($udb){//SE VALIDA QUE LA REQUISICION EXISTA
                if($status == 10){//SE VALIDA QUE LA REQUISICION ESTE EN ESTATUS 6 validando
                    $count =DB::connection('vizapi')->table('product_required')->where([['_requisition',$id],['_suplier_id',$supply] ])->wherenotnull('toDelivered')->where([['toDelivered','>',0],['checkout',1]])->count('_product');//se cuentan cuantos articulos se validaron
                    $sumcase = DB::connection('vizapi')->table('product_required AS PR')->select(DB::raw('SUM(CASE WHEN PR._supply_by = 1 THEN PR.toDelivered  WHEN PR._supply_by = 2  THEN PR.toDelivered * 12  WHEN PR._supply_by = 3  THEN PR.toDelivered * PR.ipack   WHEN PR._supply_by = 4    THEN (PR.toDelivered * (PR.ipack / 2))  ELSE 0  END) AS CASESUM'))->where([['PR._requisition', $id],['PR._suplier_id',$supply]])->first(); //se cuenta cuantas piezas se validaron
                     $sum = $sumcase->CASESUM;
                    if($count > 0){//SE VALIDA QUE LA REQUISICION CONTENGA AL MENOS 1 ARTICULO CONTADO
                        $requisitions = DB::connection('vizapi')->table('requisition AS R')->where('R.id', $id)->first();//se realiza el query para pasar los datos de la requisicion con la condicion de el id recibido
                        $partition = DB::connection('vizapi')->table('requisition_partitions')->where([['_requisition',$id],['_suplier_id',$supply]])->first();//se realiza el query para pasar los datos de la requisicion con la condicion de el id recibido

                        // $comments = $requisitions->notes;
                        switch($requisitions->_workpoint_from){
                            case 1://cedis
                                $ade = 'GEN';
                            break;
                            case 2://texcoco
                                $ade = 'STC';
                            break;
                            // case 16://brasil
                            //     $aor = 'GEN'
                            // break;
                            case 21://pantaco
                                $ade = 'PAN';
                            break;
                            case 24://bolivia
                                $ade = 'BOL';
                            break;
                        }
                        $aor = 'RTA';//almacen de ruta

                        // $max = "SELECT max(DOCTRA) as CODIGO FROM F_TRA";//query para encontrar el maximo codigo de traspas
                        // $exec = $this->conn->prepare($max);
                        // $exec -> execute();
                        // $maxcode=$exec->fetch(\PDO::FETCH_ASSOC);//averS
                            // $codtra = intval($maxcode["CODIGO"])+ 1;//se obtiene el nuevo codigo de traspaso
                        $prouduct = $this->productrequiredRec($id,$partition->invoice,$supply,$ade,$rut);//se envian datos id de la requisision, tipo de factura(serie) y codigo de factura a insertar hacia el metodo
                            // $tra = [//se crea el arrego para insertar en factusol
                            //     $codtra,//codigo traspaso
                            //     $date_format,//fecha actual en formato
                            //     $aor,//almacen de origen
                            //     $ade,//almacen destino
                            //     $comments,//comentarios
                            // ];//termino de arreglo de insercion

                            $updt = [
                                $ade,
                                $partition->invoice
                            ];

                        $sql = "UPDATE F_TRA SET ADETRA  = ? WHERE DOCTRA = ?";//se crea el query para insertar en la tabla
                        $exec = $this->conn->prepare($sql);
                        $exec -> execute($updt);
                        DB::connection('vizapi')->table('requisition_partitions')->where([['_requisition',$id],['_suplier_id',$supply]])->update(['invoice_received'=>$partition->invoice]);//se actualiza la columna invoice traspaso inicial
                        return response()->json([
                            "folio"=>$partition->invoice,
                            "art_contados"=>$count,
                            "can_contada"=>$sum],201);//se retorna el folio de la factura
                    }else{return response()->json("NO SE PUEDE PROCESAR YA QUE NO HAY ARTICULOS VALIDADOS (LOS PRODUCTOS ESTAN EN 0)",400);}
                }else{return response()->json("NO SE CREA LA FACTURA LA REQUISICION AUN NO ES VALIDADA",400);}
            }else{return response()->json("EL CODIGO DE REQUISICION NO EXITE",404);}
        }catch (\PDOException $e){ die($e->getMessage());}
    }


    public function productrequiredRec($id,$codtra,$supply,$ade,$rut){//metodo de insercion de productos en factusol


        $product_require = DB::connection('vizapi')->table('product_required AS PR')//se crea el query para obteener los productos de la requisision
            ->join('products AS P','P.id','=','PR._product')
            ->leftjoin('prices_product AS PP','PP._product','=','P.id')
            ->where('PR._requisition',$id)
            ->where('PR._suplier_id',$supply)
            ->wherenotnull('PR.toDelivered')
            ->where([['PR.toDelivered','>',0],['checkout',1]])
            ->select('P.code AS codigo','P.description AS descripcion','PR.toDelivered AS cantidad','PP.AAA AS precio' ,'P.cost as costo','PR._supply_by AS medida','PR.ipack AS PXC')
            ->get();

        $pos= 1;//inicio contador de posision
        $ttotal=0;//inicio contador de total

        foreach($product_require as $pro){//inicio de cliclo para obtener productos
            // $precio = $pro->precio;//se optiene el precio de cada producto
            // $bull = null;
            if($pro->medida == 1){$canti = $pro->cantidad ;}elseif($pro->medida == 2){$canti = $pro->cantidad * 12; }elseif($pro->medida == 3){$canti = $pro->cantidad * $pro->PXC ; $bull = $pro->cantidad; }elseif($pro->medida == 4){$canti = ($pro->cantidad * ($pro->PXC / 2)) ;}//se valida la unidad de medida de el surtio
            // $bul = $bull > 0 ? $bull : null;
            // $total = $precio * $canti ;//se obtiene el total de la linea
            // $ttotal = $ttotal + $total ;//se obtiene el total de la requisision
            // $values = [//se genera el arreglo para la insercion a factusol
            //     $codtra,//codigo de documento
            //     $pos,//posision de la linea
            //     $pro->codigo,//codigo de el articulo
            //     $canti,//cantidad contada
            //     $bull//cajas ponidas
            // ];
            // $insert = "INSERT INTO F_LTR (DOCLTR,LINLTR,ARTLTR,CANLTR,BULLTR) VALUES (?,?,?,?,?)";//query para insertar las lineas de el traspaso  creada en factusol
            // $exec = $this->conn->prepare($insert);
            // $exec -> execute($values);//envia el arreglo

            $updatestockori = "UPDATE F_STO SET ACTSTO = ACTSTO - ? , DISSTO = DISSTO - ?  WHERE  ARTSTO = ? AND ALMSTO = ?";//query para actualizar los stock de el almacen origen
            $exec = $this->conn->prepare($updatestockori);
            $exec -> execute([$canti,$canti,$pro->codigo, $rut]);

            $updatestockdes = "UPDATE F_STO SET ACTSTO = ACTSTO + ? , DISSTO = DISSTO + ?  WHERE  ARTSTO = ? AND ALMSTO = ?";//query para actualizar los stock de el almacen destino
            $exec = $this->conn->prepare($updatestockdes);
            $exec -> execute([$canti,$canti,$pro->codigo, $ade]);

            $pos++;//contador
        }

        return $ttotal;//se retorna el total para el uso en el encabezado de la factura

    }
}
