<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class OutputsController extends Controller
{
    private $conn = null;

    public function __construct(){
      $access = env("ACCESS");
      if(file_exists($access)){
      try{  $this->conn  = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
          }catch(\PDOException $e){ die($e->getMessage()); }
      }else{ die("$access no es un origen de datos valido."); }
    }


    public function addOutputs(Request $request){
        $transfer = $request->all();
        $usfs = "SELECT MAX(CODSAL) as CODIGO FROM F_SAL";
        $exec = $this->conn->prepare($usfs);
        $exec -> execute();
        $usefac=$exec->fetch(\PDO::FETCH_ASSOC);
        $salida =  $usefac['CODIGO']  + 1;
        // return response()->json($salida,201);
        if($salida){
            $ins = [
                $salida,
                $transfer['warehouse']['alias'],
                $transfer['notes']
            ];
            // return response()->json($ins,201);
            $insTra = "INSERT INTO F_SAL (CODSAL,ALMSAL,OBSSAL,FECSAL) VALUES (?,?,?,date())";
            $exec = $this->conn->prepare($insTra);
            $res = $exec -> execute($ins);
            if($res){
                $ret = [
                    "state"=>true,
                    "salida"=>$salida
                ];
                return response()->json($ret,201);
            }else{
                $ret = [
                    "state"=>false,
                    "salida"=>null
                ];
                return response()->json($ret,201);
            }

        }else{
            return response()->json('No devolvio folio',500);
        }
    }

    public function endOutput (Request $request){
        $output = $request->output;
        $products = $request->products;

        $bodielast = "SELECT * FROM F_LSA WHERE CODLSA = ".$output['code_fs'];
        $exec = $this->conn->prepare($bodielast);
        $exec -> execute();
        $bodie=$exec->fetchall(\PDO::FETCH_ASSOC);
        if(count($bodie) > 0 ){
            foreach($bodie as $bod){

                $updaori = "UPDATE F_STO SET ACTSTO = ACTSTO + ? , DISSTO = DISSTO + ?  WHERE  ARTSTO = ? AND ALMSTO = ?";//query para actualizar los stock de el almacen recordemos que solo es general
                $exec = $this->conn->prepare($updaori);
                $exec -> execute([$bod['UNILSA'],$bod['UNILSA'],$bod['ARTLSA'], $output['warehouse']['alias']]);
            }
        }

        $usfs = "DELETE * FROM F_LSA WHERE CODLSA = ".$output['code_fs'];
        $exec = $this->conn->prepare($usfs);
        $del = $exec -> execute();
        $count = 1;
        foreach($products as $product){
            $ins = [
                $output['code_fs'],
                $count,
                $product['product'],
                $product['description'],
                $product['amount']
            ];
            $insert = "INSERT INTO F_LSA (CODLSA,POSLSA,ARTLSA,DESLSA,UNILSA) VALUES (?,?,?,?,?)";
            $exec = $this->conn->prepare($insert);
            $res = $exec -> execute($ins);
            if($res){
                $count++;
                $updaori = "UPDATE F_STO SET ACTSTO = ACTSTO - ? , DISSTO = DISSTO - ?  WHERE  ARTSTO = ? AND ALMSTO = ?";//query para actualizar los stock de el almacen recordemos que solo es general
                $exec = $this->conn->prepare($updaori);
                $exec -> execute([$product['amount'],$product['amount'],$product['product'], $output['warehouse']['alias']]);

            }else{
                return response()->json('Hubo un problema en la insercion',500);
            }
        }
        return response()->json('Traspaso Terminado',201);
    }

}
