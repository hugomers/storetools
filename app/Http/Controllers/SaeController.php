<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaeController extends Controller
{
    // public function testFirebird() {
    //     $clientes = DB::connection('firebird')
    //         ->select("SELECT * FROM CLIE01 WHERE RFC = 'BRE831202JM2'");
    //     return $clientes;
    // }
    // public function testFirebird() {
    //     $clientes = DB::connection('firebird')
    //         ->select("SELECT * FROM INVE01 WHERE CVE_ART = 'JL1009' ");
    //     return $clientes;
    // }

    public function testFirebird() {
        $clientes = DB::connection('firebird')
            ->select("SELECT MAX(FOLIO) + 1 AS MAX FROM FACTF01 WHERE SERIE = 'A' ");
        return $clientes;
    }
}
