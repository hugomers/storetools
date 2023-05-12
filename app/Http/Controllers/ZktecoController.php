<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Rats\Zkteco\Lib\ZKTeco;

class ZktecoController extends Controller
{
    public function replyAssist(){
        $report = [];
        $fail = [];
        $ret = [];
        $zkteco = env('ZKTECO');
        $zk = new ZKTeco($zkteco);
        if($zk->connect()){
            $assists = $zk->getAttendance();
            if($assists){
                $serie = ltrim(stristr($zk->serialNumber(),'='),'=');
                $sucursal = DB::table('assist_devices')->where('serial_number',$serie)->first();
                if($sucursal){
                    foreach($assists as $assist){
                        $auid = DB::table('assist')->where('auid',$assist['uid'])->where('_store',$sucursal->_store)->first();
                        if(is_null($auid)){
                            $user = DB::table('staff')->where('id_rc',intval($assist['id']))->value('id');
                            if($user){
                                $report = [
                                "auid" => $assist['uid'],//id checada checador
                                "register" => $assist['timestamp'], //horario
                                "_staff" => $user,//id del usuario
                                "_store"=> $sucursal->_store,
                                "_types"=>$assist['type'],//entrada y salida
                                "_class"=>$assist['state'],
                                "_device"=>$sucursal->id,
                                ];
                                $insert = DB::table('assist')->insert($report);
                                $ret[] = $report;
                            }else{$fail[]= "El id ".$assist['id']." no tiene usuario registro ".$assist['timestamp'];}
                        }
                    }
                }else{$fail[]= "La Sucursal no existe la serie".$serie;}
                $res = ["registros"=>count($ret), "regis"=>$ret, "fail"=>$fail];
                return response()->json($res,201);
            }else{return response()->json("No hay registros por el momento",404);}
        }else{return response()->json("No hay conexion a el checador",501);}
    }
}
