<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

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

    public function opencashier(Request $request){
        $numer = env('number');
        if($request->has("mensaje")){
          $msg= "No se realizo la apertura por falta de firmas";
          $this->msg($msg,$numer);
        }else{
            $caja = $request->caja;
            switch($caja){
                case 1:
                  $terminal  = "CAJAUNO";
                break;
                case 2:
                  $terminal  = "CAJADOS";
                break;
                case 3:
                  $terminal  = "CAJATRES";
                break;
                case 4:
                  $terminal  = "CAJACUATRO";
                break;
                case 5:
                  $terminal  = "CAJACINCO";
                break;
                case 6:
                  $terminal  = "CAJASEIS";
                break;
                case 7:
                  $terminal  = "CAJASIETE";
                break;
                case 8:
                  $terminal  = "CAJAOCHO";
                break;
                case 9:
                  $terminal  = "CAJANUEVE";
                break;
            }
            $cajaob = "SELECT CODTER FROM T_TER WHERE DESTER LIKE "."'"."%%".$terminal."'";
            $exec = $this->conn->prepare($cajaob);
            $exec->execute();
            $cajat=$exec->fetch(\PDO::FETCH_ASSOC);

        if($request->tipo_mov == "MAL DEVOLUCION"){
          $apertura = "UPDATE T_TER SET FECTER = DATE(), SINTER = 5000, ESTTER = 1, EFETER = 0, HOATER = TIME() WHERE CODTER = ".$cajat['CODTER'];
          $exec = $this->conn->prepare($apertura);
          $result = $exec->execute();
          if($result){
              $msg = "la ".$terminal." se abrio por solicitud de: ".$request->solicitante." con la observacion: ".$request->obs." el ticket original: ".$request->tcko." ticket devolucion: ".$request->tckd;
              $this->msg($msg,$numer);
              return response()->json($result);
          }else{
              return response()->json("no se pudo abrir la caja");
          }
        }else if($request->tipo_mov == "RETIRADA MAL"){
            if($request->montonuevo == 0 ){
                $retob = "UPDATE F_RET SET IMPRET = ".$request->montonuevo.", CONRET = '', PRORET = 0 WHERE CODRET = ".$request->retirada." AND FECRET = DATE() AND CAJRET = ".$cajat['CODTER'];
                $exec = $this->conn->prepare($retob);
                $result = $exec->execute();
            }else{
                $retob = "UPDATE F_RET SET IMPRET = ".$request->montonuevo." WHERE CODRET = ".$request->retirada." AND FECRET = DATE() AND CAJRET = ".$cajat['CODTER'];
                $exec = $this->conn->prepare($retob);
                $result = $exec->execute();
            }
            if($result){
                $msg = "La retirada ".$request->retirada." se modifico correctamente";
                $this->msg($msg,$numer);
                return response()->json($result);
            }else{
                return response()->json("no se pudo modificar la retirada");
            }
        }else if($request->tipo_mov == "DESCUADRE"){
            $apertura = "UPDATE T_TER SET FECTER = DATE(), SINTER = 5000, ESTTER = 1, EFETER = 0, HOATER = TIME() WHERE CODTER = ".$cajat['CODTER'];
            $exec = $this->conn->prepare($apertura);
            $result = $exec->execute();
            if($result){
                $msg = "la ".$terminal." se abrio por para revision de descuadre";
                $this->msg($msg,$numer);
                return response()->json($result);
            }else{
                return response()->json("no se pudo abrir la caja");
            }
        }


      }
    }
}