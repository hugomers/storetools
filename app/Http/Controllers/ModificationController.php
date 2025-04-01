<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ModificationController extends Controller
{


    public function __construct(){
        $access = env("ACCESS");
        if(file_exists($access)){
        try{  $this->conn  = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
            }catch(\PDOException $e){ die($e->getMessage()); }
        }else{ die("$access no es un origen de datos valido."); }
      }

      public function deleteProduct(Request $request){
        $products = $request->all();
        foreach($products as $product){
            $delete = "DELETE FROM F_LFA WHERE F_LFA.TIPLFA&'-'&FORMAT(F_LFA.CODLFA,'000000')  = "."'".$product['invoice']."'"." AND F_LFA.ARTLFA = "."'".$product['code']."'";
            $exec = $this->conn->prepare($delete);
            $is = $exec -> execute();
            if($is){
                $updatestock = "UPDATE F_STO SET ACTSTO = ACTSTO + ? , DISSTO = DISSTO + ?  WHERE  ARTSTO = ? AND ALMSTO = ?";//query para actualizar los stock de el almacen recordemos que solo es general
                $exec = $this->conn->prepare($updatestock);
                $exec -> execute([$product['toReceived'],$product['toReceived'],$product['code'], 'GEN']);
                return response()->json('Productos Eliminados',201);//se retorna el folio de la factura
            }
        }

      }

      public function changeDelivered(Request $request){

        $mul = 0;
        $products = $request->all();
        $total = 0;
        $folio = '';
        foreach($products as $product){
            $folio = "'".$product['invoice']."'";
            $select = "SELECT  *  FROM F_LFA WHERE F_LFA.TIPLFA&'-'&FORMAT(F_LFA.CODLFA,'000000')  = "."'".$product['invoice']."'"." AND F_LFA.ARTLFA = "."'".$product['code']."'";
            $exec = $this->conn->prepare($select);
            $is = $exec -> execute();
            $prupd = $exec->fetch(\PDO::FETCH_ASSOC);// se obtiene el articulo que se modificara
            if($prupd){//se actualilza la tabla de stock para agregarle el stock que se cambiara
                $updatestock = "UPDATE F_STO SET ACTSTO = ACTSTO + ? , DISSTO = DISSTO + ?  WHERE  ARTSTO = ? AND ALMSTO = ?";//query para actualizar los stock de el almacen recordemos que solo es general
                $exec = $this->conn->prepare($updatestock);
                $exec -> execute([$prupd['CANLFA'],$prupd['CANLFA'],$prupd['ARTLFA'], 'GEN']);
            }
            switch($product['_supply_by']){
                case 1:
                    $mul = 1;
                break;
                case 2:
                    $mul = 12;
                break;
                case 3:
                    $mul = $product['pxc'];
                break;
            }

            $cantidad = $product['toDelivered'] * $mul;
            $updateSal = "UPDATE  F_LFA SET F_LFA.CANLFA = ".$cantidad.", F_LFA.TOTLFA =".$cantidad * $prupd['PRELFA']." WHERE F_LFA.TIPLFA&'-'&FORMAT(F_LFA.CODLFA,'000000')  = "."'".$product['invoice']."'"." AND F_LFA.ARTLFA = "."'".$product['code']."'";
            $exec = $this->conn->prepare($updateSal);
            $exec -> execute();// se actualiza el producto

            if($is){// se actualiza la tabla de stock real
                $updatestock = "UPDATE F_STO SET ACTSTO = ACTSTO - ? , DISSTO = DISSTO - ?  WHERE  ARTSTO = ? AND ALMSTO = ?";//query para actualizar los stock de el almacen recordemos que solo es general
                $exec = $this->conn->prepare($updatestock);
                $exec -> execute([$cantidad,$cantidad,$product['code'], 'GEN']);

            }
            $total += $cantidad * $prupd['PRELFA'];
        }
        $modify = "UPDATE F_FAC SET TOTFAC = ".$total." WHERE  TIPFAC&'-'&FORMAT(CODFAC,'000000')  = ".$folio;
        $exec = $this->conn->prepare($modify);
        $exec -> execute();

        return response()->json('Productos Cambiados',201);//se retorna el folio de la factura
      }

      public function changeReceived(Request $request){
        $mul = 0;
        $products = $request->all();
        $total = 0;
        $folio = '';
        foreach($products as $product){
            $folio = "'".$product['invoice']."'";
            $select = "SELECT  * FROM F_LFR WHERE F_LFR.TIPLFR&'-'&FORMAT(F_LFR.CODLFR,'000000')  = "."'".$product['invoice']."'"." AND F_LFR.ARTLFR= "."'".$product['code']."'";
            $exec = $this->conn->prepare($select);
            $is = $exec -> execute();
            $prupd = $exec->fetch(\PDO::FETCH_ASSOC);// se obtiene el articulo que se modificara
            if($prupd){//se actualilza la tabla de sstock para agregarle el stock que se cambiara
                $updatestock = "UPDATE F_STO SET ACTSTO = ACTSTO - ? , DISSTO = DISSTO - ?  WHERE  ARTSTO = ? AND ALMSTO = ?";//query para actualizar los stock de el almacen recordemos que solo es general
                $exec = $this->conn->prepare($updatestock);
                $exec -> execute([$prupd['CANLFR'],$prupd['CANLFR'],$prupd['ARTLFR'], 'GEN']);
            }
            switch($product['_supply_by']){
                case 1:
                    $mul = 1;
                break;
                case 2:
                    $mul = 12;
                break;
                case 3:
                    $mul = $product['pxc'];
                break;
            }

            $cantidad = $product['toReceived'] * $mul;
            $updateSal = "UPDATE  F_LFR SET F_LFR.CANLFR = ".$cantidad.", F_LFR.TOTLFR = ".$cantidad * $prupd['PRELFR']."  WHERE F_LFR.TIPLFR&'-'&FORMAT(F_LFR.CODLFR,'000000')  = "."'".$product['invoice']."'"." AND F_LFR.ARTLFR = "."'".$product['code']."'";
            $exec = $this->conn->prepare($updateSal);
            $exec -> execute();// se actualiza el producto

            if($is){// se actualiza la tabla de stock real
                $updatestock = "UPDATE F_STO SET ACTSTO = ACTSTO + ? , DISSTO = DISSTO + ?  WHERE  ARTSTO = ? AND ALMSTO = ?";//query para actualizar los stock de el almacen recordemos que solo es general
                $exec = $this->conn->prepare($updatestock);
                $exec -> execute([$cantidad,$cantidad,$product['code'], 'GEN']);

            }
            $total += $cantidad * $prupd['PRELFR'];
        }
        $modify = "UPDATE F_FRE SET TOTFRE = ".$total." WHERE  TIPFRE&'-'&FORMAT(CODFRE,'000000')  = ".$folio;
        $exec = $this->conn->prepare($modify);
        $exec -> execute();

        return response()->json('Productos Cambiados',201);//se retorna el folio de la factura
      }


}
