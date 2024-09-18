<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TransferBWController extends Controller
{
    private $conn = null;

    public function __construct(){
      $access = env("ACCESS");
      if(file_exists($access)){
      try{  $this->conn  = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
          }catch(\PDOException $e){ die($e->getMessage()); }
      }else{ die("$access no es un origen de datos valido."); }
    }

    public function addTransfer(Request $request){
        $transfer = $request->all();
        $usfs = "SELECT MAX(DOCTRA) as CODIGO FROM F_TRA";
        $exec = $this->conn->prepare($usfs);
        $exec -> execute();
        $usefac=$exec->fetch(\PDO::FETCH_ASSOC);
        $traspaso = $usefac['CODIGO'] + 1;
        // return response()->json($traspaso,201);
        if($traspaso > 1){
            $ins = [
                $traspaso,
                $transfer['_origin']['alias'],
                $transfer['_destiny']['alias'],
                $transfer['notes']
            ];
            // return response()->json($ins,201);
            $insTra = "INSERT INTO F_TRA (DOCTRA,AORTRA,ADETRA,COMTRA,FECTRA) VALUES (?,?,?,?,date())";
            $exec = $this->conn->prepare($insTra);
            $res = $exec -> execute($ins);
            if($res){
                $ret = [
                    "state"=>true,
                    "traspaso"=>$traspaso
                ];
                return response()->json($ret,201);
            }else{
                $ret = [
                    "state"=>false,
                    "traspaso"=>null
                ];
                return response()->json($ret,201);
            }

        }else{
            return response()->json('No devolvio folio',500);
        }
    }

    public function endTransfer (Request $request){
        $transfer = $request->traspaso;
        $products = $request->products;

        $bodielast = "SELECT * FROM F_LTR WHERE DOCLTR = ".$transfer['code_fs'];
        $exec = $this->conn->prepare($bodielast);
        $exec -> execute();
        $bodie=$exec->fetchall(\PDO::FETCH_ASSOC);
        if(count($bodie) > 0 ){
            foreach($bodie as $bod){
                $updades = "UPDATE F_STO SET ACTSTO = ACTSTO - ? , DISSTO = DISSTO - ?  WHERE  ARTSTO = ? AND ALMSTO = ?";//query para actualizar los stock de el almacen recordemos que solo es general
                $exec = $this->conn->prepare($updades);
                $exec -> execute([$bod['CANLTR'],$bod['CANLTR'],$bod['ARTLTR'], $transfer['destiny']['alias']]);

                $updaori = "UPDATE F_STO SET ACTSTO = ACTSTO + ? , DISSTO = DISSTO + ?  WHERE  ARTSTO = ? AND ALMSTO = ?";//query para actualizar los stock de el almacen recordemos que solo es general
                $exec = $this->conn->prepare($updaori);
                $exec -> execute([$bod['CANLTR'],$bod['CANLTR'],$bod['ARTLTR'], $transfer['origin']['alias']]);
            }
        }

        $usfs = "DELETE * FROM F_LTR WHERE DOCLTR = ".$transfer['code_fs'];
        $exec = $this->conn->prepare($usfs);
        $del = $exec -> execute();
        $count = 1;
        foreach($products as $product){
            $ins = [
                $transfer['code_fs'],
                $count,
                $product['product'],
                $product['amount']
            ];
            $insert = "INSERT INTO F_LTR (DOCLTR,LINLTR,ARTLTR,CANLTR) VALUES (?,?,?,?)";
            $exec = $this->conn->prepare($insert);
            $res = $exec -> execute($ins);
            if($res){
                $count++;

                $updades = "UPDATE F_STO SET ACTSTO = ACTSTO + ? , DISSTO = DISSTO + ?  WHERE  ARTSTO = ? AND ALMSTO = ?";//query para actualizar los stock de el almacen recordemos que solo es general
                $exec = $this->conn->prepare($updades);
                $exec -> execute([$product['amount'],$product['amount'],$product['product'], $transfer['destiny']['alias']]);

                $updaori = "UPDATE F_STO SET ACTSTO = ACTSTO - ? , DISSTO = DISSTO - ?  WHERE  ARTSTO = ? AND ALMSTO = ?";//query para actualizar los stock de el almacen recordemos que solo es general
                $exec = $this->conn->prepare($updaori);
                $exec -> execute([$product['amount'],$product['amount'],$product['product'], $transfer['origin']['alias']]);

            }else{
                return response()->json('Hubo un problema en la insercion',500);
            }
        }
        return response()->json('Traspaso Terminado',201);
    }
}
