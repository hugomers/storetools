<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class UserController extends Controller
{
    public function __construct(){
        $general = env('GENERAL');//conexion a access de sucursal
        if(file_exists($general)){
        try{  $this->con  = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$general."; Uid=; Pwd=;");
            }catch(\PDOException $e){ die($e->getMessage()); }
        }else{ die("$general no es un origen de datos valido."); }

        $access = env("ACCESS");//conexion a access de sucursal
        if(file_exists($access)){
        try{  $this->conn  = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
            }catch(\PDOException $e){ die($e->getMessage()); }
        }else{ die("$access no es un origen de datos valido."); }
    }

    public function createUser(Request $request){
        $year = date('Y');
        $res=[];

        $altas = $request->all();

        foreach($altas as $alta){
            $nombre = $alta['nombre'];
            // $rol = $request->puesto;
            $accessfile = env('DBALIAS');

            try{
                $usfs = "SELECT MAX(CODUSU) as CODIGO FROM F_USU";
                $exec = $this->con->prepare($usfs);
                $exec -> execute();
                $usefac=$exec->fetch(\PDO::FETCH_ASSOC);
                $codigo = $usefac['CODIGO']+1;
            }catch (\PDOException $e){ die($e->getMessage());}

            $usuario = [
                $codigo,
                $nombre,
                '55T65H75P85U95G363E68I94C82D87U87A103T11',//12345
                'FS'.$accessfile.$year,
                1,
                1,
                1,
                'GEN',
                1,
                10001,
                10001,
                10001,
                10001,
                10001,
                10001,
                10001,
                10001,
                10001,
                10001,
                1,
                1
            ];

            $insertusu = "INSERT INTO F_USU (CODUSU,NOMUSU,CLAUSU,EMPUSU,GESUSU,CONUSU,LABUSU,ALMARTUSU,APPUSU,ALBUSU,FACUSU,PREUSU,PPRUSU,FREUSU,PCLUSU,RECUSU,ENTUSU,FABUSU,FRDUSU,IDIUSU,ELIUSU) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $exec = $this->con->prepare($insertusu);
            $exec -> execute($usuario);

            $permisos = [
                "PermisosTipoFactuSOL_".$codigo,
                1
            ];
            $program = [
                "PermisosFactuSOL_".$codigo,
                '',
                1
            ];

            try{
                $insertper = "INSERT INTO F_CFG (CODCFG,NUMCFG) VALUES (?,?)";
                $exec = $this->con->prepare($insertper);
                $exec -> execute($permisos);

                $insertpro = "INSERT INTO F_CFG (CODCFG,TEXCFG,TIPCFG) VALUES (?,?,?)";
                $exec = $this->con->prepare($insertpro);
                $exec -> execute($program);
            }catch (\PDOException $e){ die($e->getMessage());}
            $res[]= $codigo;
        }
        return response()->json($res,200);
    }

    public function replyUser(Request $request){
        $replys = $request->all();
        $respu=[];
        foreach($replys as $reply){
            $id = $reply['CODIGO'];
            $suc = $reply['SUCUSSALES'];

            if($suc == "all"){
                $sucursales = DB::table('stores')->WhereNotIn('id',[2,1])->get();
            }else{
                $ira =  explode(",",$suc);
                $sucursales = DB::table('stores')->WhereIn('alias',$ira)->get();
            }

            //inicio de usuario
            $user = "SELECT CODUSU,NOMUSU,CLAUSU,EMPUSU,GESUSU,CONUSU,LABUSU,ALMARTUSU,APPUSU,ALBUSU,FACUSU,PREUSU,PPRUSU,FREUSU,PCLUSU,RECUSU,ENTUSU,FABUSU,FRDUSU,IDIUSU,ELIUSU FROM F_USU WHERE CODUSU = $id";
            $exec = $this->con->prepare($user);
            $exec -> execute();
            $use =$exec->fetch(\PDO::FETCH_ASSOC);

            $permiso = "SELECT * FROM F_CFG WHERE CODCFG IN ('PermisosFactuSOL_$id','PermisosTipoFactuSOL_$id')";
            $exec = $this->con->prepare($permiso);
            $exec -> execute();
            $permi =$exec->fetchall(\PDO::FETCH_ASSOC);


            $datos = [
                "usuario"=>$use,
                "permiso"=>$permi,
            ];
            return mb_convert_encoding($datos,'UTF-8');
            foreach($sucursales as $sucursal){
                // $ip = $sucursal->ip_address;
                $ip = '192.168.10.177:1619';
                $envusu = Http::post($ip.'/storetools/public/api/Users/insuc', mb_convert_encoding($datos,'UTF-8'));
                $simon = $envusu->json();
            }

            $respu[] = [
                "send"=>$simon,
                "usuario"=>$id
            ];
        }

        return $respu;
    }

    public function insuc(Request $request){
        $user = $request->usuario;
        $codusu = $user['CODUSU'];
        $permisos = $request->permiso;

        $existus = "SELECT * FROM F_USU WHERE CODUSU = $codusu";
        $exec = $this->con->prepare($existus);
        $exec -> execute();
        $exist =$exec->fetch(\PDO::FETCH_ASSOC);
        if($exist){
            $upcon = "UPDATE F_USU SET CLAUSU = "."'".$user['CLAUSU']."'"." WHERE CODUSU = $codusu";
            $exec = $this->con->prepare($upcon);
            $inss = $exec -> execute();
        }else{
            $column = array_keys($user);
            $values = array_values($user);
            $cols = implode(',',$column);
            $signos = implode(',',array_fill(0,count($column),'?'));
            try{
                $ins = "INSERT INTO F_USU ($cols) VALUES ($signos)";
                $exec = $this->con->prepare($ins);
                $inss =$exec -> execute($values);
            }catch(\PDOException $e){ die($e->getMessage()); }
        }
        $permiso = "SELECT * FROM F_CFG WHERE CODCFG IN ('PermisosFactuSOL_$codusu','PermisosTipoFactuSOL_$codusu')";
        $exec = $this->con->prepare($permiso);
        $exec -> execute();
        $permi =$exec->fetchall(\PDO::FETCH_ASSOC);
        if($permi){
            $permisodel = "DELETE FROM F_CFG WHERE CODCFG IN ('PermisosFactuSOL_$codusu','PermisosTipoFactuSOL_$codusu')";
            $exec = $this->con->prepare($permisodel);
            $exec -> execute();
        }else{
            foreach($permisos as $permiso){
                $column = array_keys($permiso);
                $values = array_values($permiso);
                $cols = implode(',',$column);
                $signos = implode(',',array_fill(0,count($column),'?'));
                try{
                    $ins = "INSERT INTO F_CFG ($cols) VALUES ($signos)";
                    $exec = $this->con->prepare($ins);
                    $inss =$exec -> execute($values);
                }catch(\PDOException $e){ die($e->getMessage()); }
            }

        }

        return response()->json('OK');
    }
}
