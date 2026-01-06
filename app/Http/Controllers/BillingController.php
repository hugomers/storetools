<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillingController extends Controller
{

    public function __construct(){
        $access = env("ACCESS");
        if(file_exists($access)){
        try{  $this->conn  = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
            }catch(\PDOException $e){ die($e->getMessage()); }
        }else{ die("$access no es un origen de datos valido."); }
    }




    public function validateTck(Request $request){
        $folio =  $request->folio;
        $sql = "SELECT
        TIPFAC&'-'&FORMAT(CODFAC,'000000') AS Ticket,
        FECFAC AS Fecha,
        TOTFAC AS Total
        FROM F_FAC WHERE TIPFAC&'-'&FORMAT(CODFAC,'000000') = ?";
        $exec = $this->conn->prepare($sql);
        $exec -> execute([$folio]);
        $ticket = $exec->fetch(\PDO::FETCH_ASSOC);
        if($ticket){
                $fapas = "SELECT FPALCO, CPTLCO, IMPLCO FROM F_LCO WHERE TFALCO&'-'&FORMAT(CFALCO,'000000') = ?";
                $exec = $this->conn->prepare($fapas);
                $exec -> execute([$ticket['Ticket']]);
                $ticket['fpas'] = $exec->fetchall(\PDO::FETCH_ASSOC);
                $ticket = $this->utf8ize($ticket);
                return response()->json($ticket,201);

        }else{
            return response()->json(["message"=>'No se encuentra el Folio'],404);
        }
    }

    private function utf8ize($mixed) {
        if (is_array($mixed)) {
            foreach ($mixed as $key => $value) {
                $mixed[$key] = $this->utf8ize($value);
            }
        } elseif (is_string($mixed)) {
            return mb_convert_encoding($mixed, 'UTF-8', 'ISO-8859-1');
        }
        return $mixed;
    }

    public function getTckBilling(Request $request){
        $folio =  $request->folio;
        $sql = "SELECT
        TIPFAC&'-'&FORMAT(CODFAC,'000000') AS Ticket,
        FECFAC AS Fecha,
        TOTFAC AS Total
        FROM F_FAC WHERE TIPFAC&'-'&FORMAT(CODFAC,'000000') = ?";
        $exec = $this->conn->prepare($sql);
        $exec -> execute([$folio]);
        $ticket = $exec->fetch(\PDO::FETCH_ASSOC);
        if($ticket){
                $prdc = "SELECT ARTLFA, DESLFA, CANLFA, PRELFA, TOTLFA FROM F_LFA WHERE TIPLFA&'-'&FORMAT(CODLFA,'000000') = ?";
                $exec = $this->conn->prepare($prdc);
                $exec -> execute([$ticket['Ticket']]);
                $ticket['products'] = $exec->fetchall(\PDO::FETCH_ASSOC);
                return response()->json($ticket,201);

        }else{
            return response()->json(["message"=>'No se encuentra el Folio'],404);
        }
    }

}
