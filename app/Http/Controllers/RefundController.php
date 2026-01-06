<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

class RefundController extends Controller
{
    public function __construct(){
      $access = env("ACCESS");
      if(file_exists($access)){
      try{  $this->conn  = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
          }catch(\PDOException $e){ die($e->getMessage()); }
      }else{ die("$access no es un origen de datos valido."); }
    }

    public function addRefund(Request $request){
        $date = date("Y/m/d H:i");//se gerera la fecha de el dia de hoy con  formato de fecha y hora
        $date_format = Carbon::now()->format('d/m/Y');
        // $date_format = date("d/m/Y");//se formatea la fecha de el dia con el formato solo de fecha
        $hour = "01/01/1900 ".explode(" ", $date)[1];
        $refund = $request->all();
        $type = $refund['_type'];
        $serie  = $type == 1 ? 1 : 2;
        $observacion= "devolucion creada por ".$refund['createdby']['complete_name'];

        $maxCode = "SELECT MAX(CODFRD) AS CODMAX FROM F_FRD WHERE TIPFRD = "."'".$serie."'";
        $exec = $this->conn->prepare($maxCode);
        $exec -> execute();
        $code=$exec->fetch(\PDO::FETCH_ASSOC);
        $codigo =  $code['CODMAX']  + 1;

        $mosProv = "SELECT *  FROM F_PRO WHERE CODPRO = ".$refund['_provider'];
        $exec = $this->conn->prepare($mosProv);
        $exec -> execute();
        $provider=$exec->fetch(\PDO::FETCH_ASSOC);
        // $total = 0;
        $products = $refund['bodie'];
        $total = array_reduce($products, function ($acc, $val) {
            return $acc + ($val['price'] * $val['to_delivered']);
        }, 0);

        $insert = [
            $serie,//TIPFRD
            $codigo,//CODFRD
            $refund['reference'],//FACFRD
            $refund['reference'],//REFFRD
            $date_format,//FECFRD
            $provider['CODPRO'],//PROFRD
            $provider['NOFPRO'],//PNOFRD
            $provider['DOMPRO'],//PDOFRD
            $provider['POBPRO'],//PPOFRD
            $provider['CPOPRO'],//PCPFRD
            $provider['PROPRO'],//PPRFRD
            $total,//NET1FRD
            $total,//BAS1FRD
            $total,//TOTFRD
            $observacion,//OB1FRD,
            $hour,//HORFRD,
            'GEN',//ALMFRD,
            27,//USUFRD
            27,//USMFRD
            2026,//EFDFRD
        ];

        $insertRefund = "INSERT INTO F_FRD (TIPFRD,CODFRD,FACFRD,REFFRD,FECFRD,PROFRD,PNOFRD,PDOFRD,PPOFRD,PCPFRD,PPRFRD,NET1FRD,BAS1FRD,TOTFRD,OB1FRD,HORFRD,ALMFRD,USUFRD,USMFRD,EFDFRD) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $exec = $this->conn->prepare($insertRefund);
        $res = $exec -> execute($insert);
        if($res){
            $pos = 1;
            foreach($products as $product){
                $precio = $product['price'];//se optiene el precio de cada producto
                $total = $precio * $product['to_delivered'] ;//se obtiene el total de la linea
                $values = [//se genera el arreglo para la insercion a factusol
                    $serie,//TIPLFD
                    $codigo,//CODLFD
                    $pos,//POSLFD
                    $product['product'],//ARTLFD
                    $product['description'],//DESLFD
                    $product['to_delivered'],//CANLFD
                    $product['price'],//PRELFD
                    $total,//TOTLFD
                ];
                $insert = "INSERT INTO F_LFD (TIPLFD,CODLFD,POSLFD,ARTLFD,DESLFD,CANLFD,PRELFD,TOTLFD) VALUES (?,?,?,?,?,?,?,?)";//query para insertar las lineas de la factura creada en factusol
                $exec = $this->conn->prepare($insert);
                $exec -> execute($values);//envia el arreglo

                $updatestock = "UPDATE F_STO SET ACTSTO = ACTSTO - ? , DISSTO = DISSTO - ?  WHERE  ARTSTO = ? AND ALMSTO = ?";//query para actualizar los stock de el almacen recordemos que solo es general
                $exec = $this->conn->prepare($updatestock);
                $exec -> execute([$product['to_delivered'],$product['to_delivered'],$product['product'],'GEN']);
                $pos++;//contador
            }
            $folio = $serie.'-'. str_pad($codigo, 6, '0', STR_PAD_LEFT);

            return $folio;

        }else{
            return 'No se pudo generar la devolucion';
        }
    }

    public function genAbono(Request $request){//abono
        $datos = $request->all();
        $products = $datos['bodie'];
        $datcli =  "SELECT CODCLI,NOFCLI,DOMCLI,POBCLI,CPOCLI,PROCLI, DOCCLI, TELCLI FROM F_CLI WHERE CODCLI = ".$datos['storefrom']['_client'];
        $exec = $this->conn->prepare($datcli);
        $exec -> execute();
        $client =$exec->fetch(\PDO::FETCH_ASSOC);
        $nextid = "SELECT MAX(CODFAB) + 1 AS ID FROM F_FAB WHERE TIPFAB = "."'".$client['DOCCLI']."'";
        $exec = $this->conn->prepare($nextid);
        $exec -> execute();
        $id =$exec->fetch(\PDO::FETCH_ASSOC);
        if(is_null($id['ID'])){
            $id = ['ID'=>"1"];
        }
        $total = array_reduce($products, function ($acc, $val) {
            return $acc + ($val['price'] * $val['to_received']);
        }, 0);
        $insa = [
            $client['DOCCLI'],
            $id['ID'],
            'Devolucion -'.$datos['fs_id'],
            'GEN',
            500,
            $client['CODCLI'],
            $client['NOFCLI'],
            $client['DOMCLI'],
            $client['POBCLI'],
            $client['CPOCLI'],
            $client['PROCLI'],
            $client['TELCLI'],
            $total,
            $total,
            $total,
            'C30',
            484,
            0,
            1,
            2,
            2026,
            '01/01/1900',
            $datos['reference']
        ];
        $insabo = "INSERT INTO F_FAB (TIPFAB,CODFAB,REFFAB,FECFAB,ALMFAB,AGEFAB,CLIFAB,CNOFAB,CDOFAB,CPOFAB,CCPFAB,CPRFAB,TELFAB,NET1FAB,BAS1FAB,TOTFAB,FOPFAB,CPAFAB,TIVA1FAB,TIVA2FAB,TIVA3FAB,EDRFAB,FUMFAB,OB1FAB) VALUES (?,?,?,date(),?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $exec = $this->conn->prepare($insabo);
        $yes = $exec -> execute($insa);
        if($yes){
            $pos = 1;
            foreach($products as $product){
                // $pco =  "SELECT PCOART AS COSTO FROM F_ART WHERE CODART = "."'".$product['ARTLTR']."'";
                // $exec = $this->conn->prepare($pco);
                // $exec -> execute();
                // $pcos =$exec->fetch(\PDO::FETCH_ASSOC);
                $inspro = [
                    $client['DOCCLI'],
                    $id['ID'],
                    $pos,
                    $product['product'],
                    $product['description'],
                    $product['to_received'],
                    $product['price'],
                    $product['to_received']*$product['price'],
                    $product['price']
                ];
                $inspab = "INSERT INTO F_LFB (TIPLFB,CODLFB,POSLFB,ARTLFB,DESLFB,CANLFB,PRELFB,TOTLFB,COSLFB) VALUES (?,?,?,?,?,?,?,?,?)";
                $exec = $this->conn->prepare($inspab);
                $art = $exec -> execute($inspro);

                $updsto = "UPDATE F_STO SET ACTSTO = ACTSTO + ".$product['to_received'].", DISSTO = DISSTO + ".$product['to_received']." WHERE ARTSTO = "."'".$product['product']."'"." AND ALMSTO = 'GEN'";
                $exec = $this->conn->prepare($updsto);
                $art = $exec -> execute();
                $pos++;
            }
            $res =$client['DOCCLI']."-".str_pad($id['ID'], 6, '0', STR_PAD_LEFT);

            return $res;
        }else{
            return response()->json("No se genero el abono",401);
        }
    }

    public function genAbonoTras(Request $request){//abono
        $datos = $request->all();
        $products = $datos['bodie'];
        $datcli =  "SELECT CODCLI,NOFCLI,DOMCLI,POBCLI,CPOCLI,PROCLI, DOCCLI, TELCLI FROM F_CLI WHERE CODCLI = ".$datos['storefrom']['_client'];
        $exec = $this->conn->prepare($datcli);
        $exec -> execute();
        $client =$exec->fetch(\PDO::FETCH_ASSOC);
        $nextid = "SELECT MAX(CODFAB) + 1 AS ID FROM F_FAB WHERE TIPFAB = "."'".$client['DOCCLI']."'";
        $exec = $this->conn->prepare($nextid);
        $exec -> execute();
        $id =$exec->fetch(\PDO::FETCH_ASSOC);
        if(is_null($id['ID'])){
            $id = ['ID'=>"1"];
        }
        $total = array_reduce($products, function ($acc, $val) {
            return $acc + ($val['price'] * $val['to_received']);
        }, 0);
        $insa = [
            $client['DOCCLI'],
            $id['ID'],
            'Devolucion -'.$datos['fs_id'],
            'GEN',
            500,
            $client['CODCLI'],
            $client['NOFCLI'],
            $client['DOMCLI'],
            $client['POBCLI'],
            $client['CPOCLI'],
            $client['PROCLI'],
            $client['TELCLI'],
            $total,
            $total,
            $total,
            'C30',
            484,
            0,
            1,
            2,
            2026,
            '01/01/1900',
            'Referencia '.$datos['reference'].'Traspaso de '.$datos['storefrom']['name'].' a '.$datos['storeto']['name']
        ];
        $insabo = "INSERT INTO F_FAB (TIPFAB,CODFAB,REFFAB,FECFAB,ALMFAB,AGEFAB,CLIFAB,CNOFAB,CDOFAB,CPOFAB,CCPFAB,CPRFAB,TELFAB,NET1FAB,BAS1FAB,TOTFAB,FOPFAB,CPAFAB,TIVA1FAB,TIVA2FAB,TIVA3FAB,EDRFAB,FUMFAB,OB1FAB) VALUES (?,?,?,date(),?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $exec = $this->conn->prepare($insabo);
        $yes = $exec -> execute($insa);
        if($yes){
            $pos = 1;
            $inspro = [
                $client['DOCCLI'],
                $id['ID'],
                $pos,
                'TRASPASO',
                'TRASPASOS ENTRE SUCURSALES',
                1,
                $total,
                $total,
                $total
            ];
            $inspab = "INSERT INTO F_LFB (TIPLFB,CODLFB,POSLFB,ARTLFB,DESLFB,CANLFB,PRELFB,TOTLFB,COSLFB) VALUES (?,?,?,?,?,?,?,?,?)";
            $exec = $this->conn->prepare($inspab);
            $art = $exec -> execute($inspro);

            $updsto = "UPDATE F_STO SET ACTSTO = ACTSTO + 1 , DISSTO = DISSTO + 1  WHERE ARTSTO = 'TRASPASO' AND ALMSTO = 'GEN'";
            $exec = $this->conn->prepare($updsto);
            $art = $exec->execute();

            $abono =$client['DOCCLI']."-".str_pad($id['ID'], 6, "0", STR_PAD_LEFT);
            $salida =  $this->genSalidaTras($datos,$abono,$total);
            $res = [
                'salida'=>null,
                'abono'=>$abono,
            ];
            if($salida){
                $res['salida']=$salida;
            }else{
                $res['salida']=null;
            }
            return $res;
        }else{
            return response()->json("No se genero el abono",401);
        }
    }

    public function genSalidaTras($refund,$abono,$total){
        $datos = $refund;
        $products = $datos['bodie'];
        $datcli =  "SELECT CODCLI,NOFCLI,DOMCLI,POBCLI,CPOCLI,PROCLI, DOCCLI, TELCLI FROM F_CLI WHERE CODCLI = " .$datos['storeto']['_client'];
        $exec = $this->conn->prepare($datcli);
        $exec->execute();
        $client = $exec->fetch(\PDO::FETCH_ASSOC);
        $nextid = "SELECT MAX(CODFAC) + 1 AS ID FROM F_FAC WHERE TIPFAC = " . "'" . $client['DOCCLI'] . "'";
        $exec = $this->conn->prepare($nextid);
        $exec->execute();
        $id = $exec->fetch(\PDO::FETCH_ASSOC);
        $insa = [
            $client['DOCCLI'],
            $id['ID'],
            'Abono TR '.$abono,
            'GEN',
            500,
            $client['CODCLI'],
            $client['NOFCLI'],
            $client['DOMCLI'],
            $client['POBCLI'],
            $client['CPOCLI'],
            $client['PROCLI'],
            $client['TELCLI'],
            $total,
            $total,
            $total,
            'C30',
            0,
            1,
            2,
            2026,
            '01/01/1900',
            'Refencia '.$datos['reference'].'Traspaso de '.$datos['storefrom']['name'].' a '.$datos['storeto']['name'].' Devolucion '.$datos['fs_id'],
            1
        ];
        $insabo = "INSERT INTO F_FAC (TIPFAC,CODFAC,REFFAC,FECFAC,ALMFAC,AGEFAC,CLIFAC,CNOFAC,CDOFAC,CPOFAC,CCPFAC,CPRFAC,TELFAC,NET1FAC,BAS1FAC,TOTFAC,FOPFAC,TIVA1FAC,TIVA2FAC,TIVA3FAC,EDRFAC,FUMFAC,OB1FAC,USUFAC) VALUES (?,?,?,date(),?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $exec = $this->conn->prepare($insabo);
        $yes = $exec->execute($insa);
        if ($yes) {
            $pos = 1;
            $inspro = [
                $client['DOCCLI'],
                $id['ID'],
                $pos,
                'TRASPASO',
                'TRASPASOS ENTRE SUCURSALES',
                1,
                $total,
                $total,
                $total
            ];
            $inspab = "INSERT INTO F_LFA (TIPLFA,CODLFA,POSLFA,ARTLFA,DESLFA,CANLFA,PRELFA,TOTLFA,COSLFA) VALUES (?,?,?,?,?,?,?,?,?)";
            $exec = $this->conn->prepare($inspab);
            $art = $exec->execute($inspro);

            $updsto = "UPDATE F_STO SET ACTSTO = ACTSTO - 1 , DISSTO = DISSTO - 1 WHERE ARTSTO = 'TRASPASO' AND ALMSTO = 'GEN'";
            $exec = $this->conn->prepare($updsto);
            $art = $exec->execute();

            $res = $client['DOCCLI'] . "-" . str_pad($id['ID'], 6, "0", STR_PAD_LEFT);
            return $res;
        } else {
            return false;
        }
    }

    public function genEntry(Request $request){ //factura recibida
        $datos = $request->all();
        $products = $datos['bodie'];
        $datprov =  "SELECT CODPRO,NOFPRO,DOMPRO,POBPRO,CPOPRO,PROPRO FROM F_PRO WHERE CODPRO = 5";
        $exec = $this->conn->prepare($datprov);
        $exec->execute();
        $provider = $exec->fetch(\PDO::FETCH_ASSOC);
        $nextid = "SELECT MAX(CODFRE) + 1 AS ID FROM F_FRE WHERE TIPFRE = '1'";
        $exec = $this->conn->prepare($nextid);
        $exec->execute();
        $id = $exec->fetch(\PDO::FETCH_ASSOC);
        if (is_null($id['ID'])) {
            $id['ID'] = 1;
        }
        $total = array_reduce($products, function ($acc, $val) {
            return $acc + ($val['price'] * $val['to_received']);
        }, 0);

        $insa = [
            '1',
            $id['ID'],
            'DEV '.$datos['fs_id'],
            'FAC '.$datos['invoice'],
            $provider['CODPRO'],
            $provider['NOFPRO'],
            $provider['DOMPRO'],
            $provider['POBPRO'],
            $provider['CPOPRO'],
            $provider['PROPRO'],
            $total,
            $total,
            $total,
            1,
            1,
            'GEN',
            '01/01/1900',
            'Referencia '.$datos['reference'].'Traspaso de '.$datos['storefrom']['name'].' a '.$datos['storeto']['name'],
        ];
        $insabo = "INSERT INTO F_FRE (TIPFRE,CODFRE,FACFRE,REFFRE,FECFRE,PROFRE,PNOFRE,PDOFRE,PPOFRE,PCPFRE,PPRFRE,NET1FRE,BAS1FRE,TOTFRE,USUFRE,USMFRE,ALMFRE,FUMFRE,OB1FRE) VALUES (?,?,?,?,date(),?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $exec = $this->conn->prepare($insabo);
        $yes = $exec->execute($insa);
        if ($yes) {
            $pos = 1;
            foreach ($products as $product) {
                $inspro = [
                    '1',
                    $id['ID'],
                    $pos,
                    $product['product'],
                    $product['description'],
                    $product['to_received'],
                    $product['price'],
                    $product['to_received']*$product['price'],
                ];
                $inspab = "INSERT INTO F_LFR (TIPLFR,CODLFR,POSLFR,ARTLFR,DESLFR,CANLFR,PRELFR,TOTLFR) VALUES (?,?,?,?,?,?,?,?)";
                $exec = $this->conn->prepare($inspab);
                $art = $exec->execute($inspro);

                $updsto = "UPDATE F_STO SET ACTSTO = ACTSTO + " . $product['to_received'] . ", DISSTO = DISSTO + " . $product['to_received'] . " WHERE ARTSTO = " . "'" . $product['product'] . "'" . " AND ALMSTO = 'GEN'";
                $exec = $this->conn->prepare($updsto);
                $art = $exec->execute();
                $pos++;
            }
            $res = "1" . "-" . str_pad($id['ID'], 6, "0", STR_PAD_LEFT);;
            return $res;
        } else {
            return response()->json("No se genero la factura recibida", 401);
        }
    }

    public function editRefund(Request $request){
        $products = $request->all();
        $total = 0;
        $folio = '';
        foreach($products as $product){
            $folio = "'".$product['refund']."'";
            $id = "'".$product['product']."'";
            $select = "SELECT  *  FROM F_LFD WHERE F_LFD.TIPLFD&'-'&FORMAT(F_LFD.CODLFD,'000000')  = ".$folio." AND F_LFD.ARTLFD = ".$id;
            $exec = $this->conn->prepare($select);
            $is = $exec -> execute();
            $prupd = $exec->fetch(\PDO::FETCH_ASSOC);// se obtiene el articulo que se modificara
            if($prupd){//se actualilza la tabla de stock para agregarle el stock que se cambiara
                $updatestock = "UPDATE F_STO SET ACTSTO = ACTSTO + ? , DISSTO = DISSTO + ?  WHERE  ARTSTO = ? AND ALMSTO = ?";//query para actualizar los stock de el almacen recordemos que solo es general
                $exec = $this->conn->prepare($updatestock);
                $exec -> execute([$prupd['CANLFD'],$prupd['CANLFD'],$prupd['ARTLFD'], 'GEN']);
            }
            $cantidad = $product['to'];
            $updateSal = "UPDATE  F_LFD SET F_LFD.CANLFD = ".$cantidad.", F_LFD.TOTLFD =".$cantidad * $prupd['PRELFD']." WHERE F_LFD.TIPLFD&'-'&FORMAT(F_LFD.CODLFD,'000000')  = ".$folio." AND F_LFD.ARTLFD = ".$id;
            $exec = $this->conn->prepare($updateSal);
            $exec -> execute();// se actualiza el producto

            if($is){// se actualiza la tabla de stock real
                $updatestock = "UPDATE F_STO SET ACTSTO = ACTSTO - ? , DISSTO = DISSTO - ?  WHERE  ARTSTO = ? AND ALMSTO = ?";//query para actualizar los stock de el almacen recordemos que solo es general
                $exec = $this->conn->prepare($updatestock);
                $exec -> execute([$cantidad,$cantidad,$id, 'GEN']);

            }
            // $total += $cantidad * $prupd['PRELFD'];
        }
        $totis = "SELECT  SUM(TOTLFD) AS TOTAL  FROM F_LFD WHERE F_LFD.TIPLFD&'-'&FORMAT(F_LFD.CODLFD,'000000')  = ".$folio;
        $exec = $this->conn->prepare($totis);
        $exec -> execute();
        $total = $exec->fetch(\PDO::FETCH_ASSOC);


        $modify = "UPDATE F_FRD SET TOTFRD = ".$total['TOTAL']." WHERE  TIPFRD&'-'&FORMAT(CODFRD,'000000')  = ".$folio;
        $exec = $this->conn->prepare($modify);
        $exec -> execute();

        return response()->json('Productos Cambiados',201);//se retorna el folio de la factura
    }

    public function editEntry(Request $request){
        $products = $request->all();
        $total = 0;
        $folio = '';
        foreach($products as $product){
            $folio = "'".$product['ent']."'";
            $id = "'".$product['product']."'";
            $select = "SELECT  *  FROM F_LFR WHERE F_LFR.TIPLFR&'-'&FORMAT(F_LFR.CODLFR,'000000')  = ".$folio." AND F_LFR.ARTLFR = ".$id;
            $exec = $this->conn->prepare($select);
            $is = $exec -> execute();
            $prupd = $exec->fetch(\PDO::FETCH_ASSOC);// se obtiene el articulo que se modificara
            if($prupd){//se actualilza la tabla de stock para agregarle el stock que se cambiara
                $updatestock = "UPDATE F_STO SET ACTSTO = ACTSTO + ? , DISSTO = DISSTO + ?  WHERE  ARTSTO = ? AND ALMSTO = ?";//query para actualizar los stock de el almacen recordemos que solo es general
                $exec = $this->conn->prepare($updatestock);
                $exec -> execute([$prupd['CANLFR'],$prupd['CANLFR'],$prupd['ARTLFR'], 'GEN']);
            }
            $cantidad = $product['to'];
            $updateSal = "UPDATE  F_LFR SET F_LFR.CANLFR = ".$cantidad.", F_LFR.TOTLFR =".$cantidad * $prupd['PRELFR']." WHERE F_LFR.TIPLFR&'-'&FORMAT(F_LFR.CODLFR,'000000')  = ".$folio." AND F_LFR.ARTLFR = ".$id;
            $exec = $this->conn->prepare($updateSal);
            $exec -> execute();// se actualiza el producto

            if($is){// se actualiza la tabla de stock real
                $updatestock = "UPDATE F_STO SET ACTSTO = ACTSTO - ? , DISSTO = DISSTO - ?  WHERE  ARTSTO = ? AND ALMSTO = ?";//query para actualizar los stock de el almacen recordemos que solo es general
                $exec = $this->conn->prepare($updatestock);
                $exec -> execute([$cantidad,$cantidad,$id, 'GEN']);

            }
        }

        $totis = "SELECT  SUM(TOTLFR) AS TOTAL  FROM F_LFR WHERE F_LFR.TIPLFR&'-'&FORMAT(F_LFR.CODLFR,'000000')  = ".$folio;
        $exec = $this->conn->prepare($totis);
        $exec -> execute();
        $total = $exec->fetch(\PDO::FETCH_ASSOC);



        $modify = "UPDATE F_FRE SET TOTFRE = ".$total['TOTAL']." WHERE  TIPFRE&'-'&FORMAT(CODFRE,'000000')  = ".$folio;
        $exec = $this->conn->prepare($modify);
        $exec -> execute();
        $return = [
            "total"=>$total,
            "mssg"=>"exito"
        ];

        return response()->json($return ,201);//se retorna el folio de la factura

    }

    public function editSeason(Request $request){
        $total = $request->total;
        $folioAbono = "'".$request->folioAbono."'";
        $folioFactura = "'".$request->folioFactura."'";

        $modifylfr = "UPDATE F_LFB SET PRELFB = ".$total.", TOTLFB = ".$total." WHERE ARTLFB = 'TRASPASO' AND TIPLFB&'-'&FORMAT(CODLFB,'000000')  = ".$folioAbono;
        $exec = $this->conn->prepare($modifylfr);
        $exec -> execute();

        $modifyfac = "UPDATE F_FAC SET TOTFAC = ".$total." WHERE  TIPFAC&'-'&FORMAT(CODFAC,'000000')  = ".$folioFactura;
        $exec = $this->conn->prepare($modifyfac);
        $exec -> execute();

        $modifylfa = "UPDATE F_LFA SET PRELFA = ".$total.", TOTLFA = ".$total." WHERE ARTLFA = 'TRASPASO' AND TIPLFA&'-'&FORMAT(CODLFA,'000000')  = ".$folioFactura;
        $exec = $this->conn->prepare($modifylfa);
        $exec -> execute();


        return response()->json('SE MODIFICO CORRECTAMENTE' ,201);//se retorna el folio de la factura

    }
}
