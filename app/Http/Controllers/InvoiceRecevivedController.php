<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class InvoiceRecevivedController extends Controller

{
    private $conn = null;

    public function __construct(Request $request){
        $ruta = "C:\Software DELSOL\FACTUSOL\Datos\FS";
        $preflijo = '\VPA';
        $anio = $request->anio;
        $access = $ruta.$preflijo.$anio.'.accdb';
        if(file_exists($access)){
        try{  $this->conn  = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
            }catch(\PDOException $e){ die($e->getMessage()); }
        }else{ die("$access no es un origen de datos valido."); }
    }

    public function getIndex(Request $request){
        $select = "SELECT
        TIPFRE as serie,
        CODFRE as code,
        REFFRE as ref,
        PROFRE as _provider,
        FACFRE as description,
        TOTFRE as total,
        Format(FECFRE, 'YYYY/MM/DD HH:MM:SS') as created_at
        FROM F_FRE";
        // return $select;
        $exec = $this->conn->prepare($select);
        $exec->execute();
        $compras = $exec->fetchall(\PDO::FETCH_ASSOC);

        foreach($compras as &$compra){
            $tipo = "'".$compra['serie']."'";
            $codigo = $compra['code'];
            $prod = "SELECT
            ARTLFR as _product,
            CANLFR as amount,
            PRELFR as price,
            TOTLFR as total
            FROM F_LFR
            WHERE TIPLFR = ".$tipo." AND CODLFR = ".$codigo;
            $exec = $this->conn->prepare($prod);
            $exec->execute();
            $productos = $exec->fetchall(\PDO::FETCH_ASSOC);
            $compra['products'] = $productos;
        }
        $compras = $this->utf8ize($compras);
        return response()->json(mb_convert_encoding($compras, 'UTF-8'));
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

    public function replyInvoices(Request $request){
        $date = $request->date;
        $select = "SELECT
        TIPFRE as serie,
        CODFRE as code,
        REFFRE as ref,
        PROFRE as _provider,
        FACFRE as description,
        TOTFRE as total,
        Format(FECFRE, 'YYYY/MM/DD HH:MM:SS') as created_at
        FROM F_FRE
        WHERE FECFRE  >= "."#".$date."#";
        // return $select;
        $exec = $this->conn->prepare($select);
        $exec->execute();
        $compras = $exec->fetchall(\PDO::FETCH_ASSOC);

        foreach($compras as &$compra){
            $tipo = "'".$compra['serie']."'";
            $codigo = $compra['code'];
            $prod = "SELECT
            ARTLFR as _product,
            SUM(CANLFR) as amount,
            PRELFR as price,
            SUM(TOTLFR) as total
            FROM F_LFR
            WHERE TIPLFR = ".$tipo." AND CODLFR = ".$codigo." GROUP BY ARTLFR,PRELFR";
            $exec = $this->conn->prepare($prod);
            $exec->execute();
            $productos = $exec->fetchall(\PDO::FETCH_ASSOC);
            $compra['products'] = $productos;
        }
        $compras = $this->utf8ize($compras);

        return response()->json($compras);

    }
}
