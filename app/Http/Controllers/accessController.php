<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class accessController extends Controller
{
    public function __construct(){
        $access = env("ACCESS"); //conexion a access de sucursal
        if (file_exists($access)) {
            try {
                $this->conn  = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=" . $access . "; Uid=; Pwd=;");
            } catch (\PDOException $e) {
                die($e->getMessage());
            }
        } else {
            die("$access no es un origen de datos valido.");
        }
    }

    public function msg($msg, $number){
        $token = env('TOKEN_ULTRAMSG');
        $instance = env('ID_INSTANCE');
        $params = array(
            'token' => $token,
            'to' => $number,
            // 'to' => '5215534217709-1549068988@g.us',
            'body' => $msg
        );
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.ultramsg.com/" . $instance . "/messages/chat",
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

    public function OpeningBox(){
        $terminales =  env('TERMINALES');
        $nume = env('GROUP_CAJAS');
        $apertura = "UPDATE T_TER SET ESTTER = 1, EFETER = 0,FECTER = DATE(), SINTER = 5000, HOATER = TiME() WHERE CODTER IN ($terminales)  ";
        $exec = $this->conn->prepare($apertura);
        $result = $exec->execute();
        if ($result) {
            $box = "SELECT DESTER FROM T_TER WHERE CODTER IN ($terminales)";
            $exec = $this->conn->prepare($box);
            $exec->execute();
            $fil = $exec->fetchall(\PDO::FETCH_ASSOC);
            foreach ($fil as $row) {
                $caja[] = $row['DESTER'];
            }
            $boxin = implode(" , ", $caja);
            $emp = "SELECT CODEMP FROM F_EMP";
            $exec = $this->conn->prepare($emp);
            $exec->execute();
            $empresa = $exec->fetch(\PDO::FETCH_ASSOC);
            $sucrusal = $empresa['CODEMP'];
            $mes = "Las cajas " . $boxin . " de la sucursal " . $sucrusal . " estan abiertas";
            $this->msg($mes, $nume);
        }
        echo $mes;
    }

    public function Withdrawals(){

        $maximo = env('MAX_EFE_CASH');
        $num = env('GROUP_RETIRADAS');
        $sql = "SELECT DISTINCT
            T_TER.CODTER,
            T_TER.DESTER AS CAJA,
            COB.FECLCO,
            COB.COBROS,
            RET.RETIRADA,
            COB.COBROS - RET.RETIRADA AS DIFERENCIA
            FROM ((T_TER
            LEFT JOIN  (SELECT
                        FECRET,
                        CAJRET ,
                        IIF(SUM(IMPRET) IS NULL , 0 , SUM(IMPRET)) AS RETIRADA,
                        TPVIDRET
                        FROM F_RET WHERE FECRET = DATE()  GROUP BY FECRET, CAJRET, TPVIDRET) AS RET ON RET.CAJRET = T_TER.CODTER)
            INNER JOIN (SELECT FECLCO,
                        TERLCO,
                        SUM(IMPLCO)AS COBROS,
                        TPVIDLCO FROM F_LCO WHERE FECLCO= DATE() AND FPALCO = 'EFE'GROUP BY FECLCO,TERLCO, TPVIDLCO) AS COB ON COB.TERLCO = T_TER.CODTER)";
        $exec = $this->conn->prepare($sql);
        $exec->execute();
        $rows = $exec->fetchall(\PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            if ($row['DIFERENCIA'] > $maximo) {
                $caja = $row['CAJA'];
                $diferencia = $row['DIFERENCIA'];
                $mensaje  = "El Efectivo de la caja " . $caja . " es mayor a el maximo que puede tener (" . $maximo . ") favor de hacer su retirada";
                $this->msg($mensaje, $num);
                echo "Retiradas Faltantes";
            }
        }
    }

    public function regeneration(){
        $sucursal = env('SUCURSAL');
        $number = env('GROUP_REGENERACION');
        $fecha = [];
        $stock = [];
        $delsto = "DELETE FROM F_STO";
        $exec = $this->conn->prepare($delsto);
        $exec->execute();
        $art = "SELECT CODART, FALART FROM F_ART";
        $exec = $this->conn->prepare($art);
        $exec->execute();
        $articulos = $exec->fetchall(\PDO::FETCH_ASSOC);
        foreach ($articulos as $articulo) {
            $alm = "SELECT CODALM FROM F_ALM";
            $exec = $this->conn->prepare($alm);
            $exec->execute();
            $almacenes = $exec->fetchall(\PDO::FETCH_ASSOC);
            foreach ($almacenes as $almacen) {
                $cons = "SELECT MAX(FECCIN) AS FECHA, ALMCIN, ARTCIN, URECIN FROM F_CIN WHERE ALMCIN = " . "'" . $almacen['CODALM'] . "'" . " AND ARTCIN = " . "'" . $articulo['CODART'] . "'" . " GROUP BY ALMCIN, ARTCIN, URECIN";
                $exec = $this->conn->prepare($cons);
                $exec->execute();
                $consolidacion = $exec->fetch(\PDO::FETCH_ASSOC);
                if ($consolidacion) {
                    $fecha = $consolidacion['FECHA'];
                    $stock = $consolidacion['URECIN'];
                } else {
                    $fecha = $articulo['FALART'];
                    $stock = 0;
                }
                $inser  = [
                    "fecha" => $fecha,
                    "almacen" => $almacen['CODALM'],
                    "articulo" => $articulo['CODART'],
                    "stock" => $stock,
                ];
                $codigo = "'" . $inser['articulo'] . "'";
                $fucon = $inser['fecha'];
                $bod = "'" . $inser['almacen'] . "'";
                $conso = $inser['stock'];
                $salidas = "
                    SELECT
                    ALMACEN,
                    ARTICULO,
                    SUM(CANTIDAD) AS STOCK
                    FROM (
                    SELECT
                    F_FAC.ALMFAC AS ALMACEN,
                    F_LFA.ARTLFA AS ARTICULO,
                    SUM(F_LFA.CANLFA) AS CANTIDAD
                    FROM F_FAC
                    INNER JOIN F_LFA ON F_LFA.TIPLFA&'-'&F_LFA.CODLFA = F_FAC.TIPFAC&'-'&F_FAC.CODFAC
                    WHERE F_LFA.ARTLFA =" . $codigo . " AND F_FAC.ALMFAC = " . $bod . "  AND F_FAC.FECFAC >=#" . $fucon . "#
                    GROUP BY F_FAC.ALMFAC, F_LFA.ARTLFA
                    UNION ALL
                    SELECT
                    F_ALB.ALMALB AS ALMACEN,
                    F_LAL.ARTLAL AS ARTICULO,
                    SUM(F_LAL.CANLAL) AS CANTIDAD
                    FROM F_ALB
                    INNER JOIN F_LAL ON  F_LAL.TIPLAL&'-'&F_LAL.CODLAL = F_ALB.TIPALB&'-'&F_ALB.CODALB
                    WHERE F_LAL.ARTLAL =" . $codigo . " AND F_ALB.ALMALB = " . $bod . "  AND F_ALB.FECALB >=#" . $fucon . "#
                    GROUP BY F_ALB.ALMALB, F_LAL.ARTLAL
                    UNION ALL
                    SELECT
                    F_SAL.ALMSAL AS ALMACEN,
                    F_LSA.ARTLSA AS ARTICULO,
                    SUM(F_LSA.UNILSA) AS CANTIDAD
                    FROM F_SAL
                    INNER JOIN F_LSA ON F_LSA.CODLSA = F_SAL.CODSAL
                    WHERE F_LSA.ARTLSA =" . $codigo . " AND F_SAL.ALMSAL = " . $bod . "  AND F_SAL.FECSAL >=#" . $fucon . "#
                    GROUP BY F_SAL.ALMSAL, F_LSA.ARTLSA
                    UNION ALL
                    SELECT
                    F_TRA.AORTRA AS ALMACEN,
                    F_LTR.ARTLTR AS ARTICULO,
                    SUM(F_LTR.CANLTR) AS CANTIDAD
                    FROM F_TRA
                    INNER JOIN F_LTR ON F_LTR.DOCLTR = F_TRA.DOCTRA
                    WHERE F_LTR.ARTLTR =" . $codigo . " AND F_TRA.AORTRA = " . $bod . "  AND F_TRA.FECTRA >=#" . $fucon . "#
                    GROUP BY F_TRA.AORTRA, F_LTR.ARTLTR
                    UNION ALL
                    SELECT
                    F_FCO.ALMFCO AS ALMACEN,
                    F_COM.ARTCOM AS ARTICULO,
                    SUM(F_LFC.CANLFC * F_COM.UNICOM) AS CANTIDAD
                    FROM ((F_FCO
                    INNER JOIN F_LFC ON F_FCO.CODFCO = F_LFC.CODLFC)
                    INNER JOIN F_COM ON F_COM.CODCOM  = F_LFC.ARTLFC)
                    WHERE F_COM.ARTCOM =" . $codigo . " AND F_FCO.ALMFCO = " . $bod . "  AND F_FCO.FECFCO >=#" . $fucon . "#
                    GROUP BY F_FCO.ALMFCO, F_COM.ARTCOM
                    UNION ALL
                    SELECT
                    F_FRD.ALMFRD AS ALMACEN,
                    F_LFD.ARTLFD AS ARTICULO,
                    SUM(F_LFD.CANLFD) AS CANTIDAD
                    FROM F_FRD
                    INNER JOIN F_LFD ON F_LFD.TIPLFD&'-'&F_LFD.CODLFD = F_FRD.TIPFRD&'-'&F_FRD.CODFRD
                    WHERE F_LFD.ARTLFD =" . $codigo . " AND F_FRD.ALMFRD = " . $bod . "  AND F_FRD.FECFRD >=#" . $fucon . "#
                    GROUP BY F_FRD.ALMFRD, F_LFD.ARTLFD)
                    GROUP BY ALMACEN, ARTICULO
                ";
                $exec = $this->conn->prepare($salidas);
                $exec->execute();
                $sal = $exec->fetch(\PDO::FETCH_ASSOC);
                if ($sal) {
                    $totsal = $sal['STOCK'];
                } else {
                    $totsal = 0;
                }

                $entradas = " SELECT
                    ALMACEN,
                    ARTICULO,
                    SUM(CANTIDAD) AS STOCK
                    FROM (SELECT
                    F_FRE.ALMFRE AS ALMACEN,
                    F_LFR.ARTLFR AS ARTICULO,
                    SUM(F_LFR.CANLFR) AS CANTIDAD
                    FROM F_FRE
                    INNER JOIN F_LFR ON F_LFR.TIPLFR&'-'&F_LFR.CODLFR = F_FRE.TIPFRE&'-'&F_FRE.CODFRE
                    WHERE F_LFR.ARTLFR =" . $codigo . " AND F_FRE.ALMFRE = " . $bod . "  AND F_FRE.FECFRE >=#" . $fucon . "#
                    GROUP BY F_FRE.ALMFRE, F_LFR.ARTLFR
                    UNION ALL
                    SELECT
                    F_ENT.ALMENT AS ALMACEN,
                    F_LEN.ARTLEN AS ARTICULO,
                    SUM(F_LEN.CANLEN) AS CANTIDAD
                    FROM F_ENT
                    INNER JOIN F_LEN ON F_LEN.TIPLEN&'-'&F_LEN.CODLEN = F_ENT.TIPENT&'-'&F_ENT.CODENT
                    WHERE F_LEN.ARTLEN =" . $codigo . " AND F_ENT.ALMENT = " . $bod . "  AND F_ENT.FECENT >=#" . $fucon . "#
                    GROUP BY F_ENT.ALMENT, F_LEN.ARTLEN
                    UNION ALL
                    SELECT
                    F_FAB.ALMFAB AS ALMACEN,
                    F_LFB.ARTLFB AS ARTICULO,
                    SUM(F_LFB.CANLFB) AS CANTIDAD
                    FROM F_FAB
                    INNER JOIN F_LFB ON F_LFB.TIPLFB&'-'&F_LFB.CODLFB = F_FAB.TIPFAB&'-'&F_FAB.CODFAB
                    WHERE F_LFB.ARTLFB =" . $codigo . " AND F_FAB.ALMFAB = " . $bod . "  AND F_FAB.FECFAB >=#" . $fucon . "#
                    GROUP BY F_FAB.ALMFAB, F_LFB.ARTLFB
                    UNION ALL
                    SELECT
                    F_TRA.ADETRA AS ALMACEN,
                    F_LTR.ARTLTR AS ARTICULO,
                    SUM(F_LTR.CANLTR) AS CANTIDAD
                    FROM F_TRA
                    INNER JOIN F_LTR ON F_LTR.DOCLTR = F_TRA.DOCTRA
                    WHERE F_LTR.ARTLTR =" . $codigo . " AND F_TRA.ADETRA = " . $bod . "  AND F_TRA.FECTRA >=#" . $fucon . "#
                    GROUP BY F_TRA.ADETRA, F_LTR.ARTLTR
                    UNION ALL
                    SELECT
                    F_FCO.ALMFCO AS ALMACEN,
                    F_LFC.ARTLFC AS ARTICULO,
                    SUM(F_LFC.CANLFC) AS CANTIDAD
                    FROM F_FCO
                    INNER JOIN F_LFC ON F_LFC.CODLFC = F_FCO.CODFCO
                    WHERE F_LFC.ARTLFC =" . $codigo . " AND F_FCO.ALMFCO = " . $bod . "  AND F_FCO.FECFCO >=#" . $fucon . "#
                    GROUP BY F_FCO.ALMFCO, F_LFC.ARTLFC)
                    GROUP BY ALMACEN, ARTICULO
                ";
                $exec = $this->conn->prepare($entradas);
                $exec->execute();
                $ent = $exec->fetch(\PDO::FETCH_ASSOC);
                if ($ent) {
                    $totent = $ent['STOCK'];
                } else {
                    $totent = 0;
                }
                $total = $conso - $totsal + $totent;

                $siempre  = [
                    $inser['articulo'], //ARTSTO
                    $total, //ACTSTO
                    $total, //DISSTO
                    $inser['almacen'] //ALMSTO
                ];

                $insert = "INSERT INTO F_STO (ARTSTO,ACTSTO,DISSTO,ALMSTO) VALUES (?,?,?,?)";
                $exec = $this->conn->prepare($insert);
                $exec->execute($siempre);
            }
        }
        $msg = "Se hizo la regeneracion de stock de la sucursal " . $sucursal . " satisfactoriamente";
        $this->msg($msg, $number);
        return response()->json("Regeneracion Hecha");
    }

    public function createClient(Request $request){
        $client =  $request->all();
        $celphone = isset($client['celphone']) ? $client['celphone'] : '';
        $email = isset($client['email']) ? $client['email'] : '';
        if ($celphone  === '') {
            if ($email === '') {
                $msg = "No se puede dar de alta sin telefono y sin correo";
                return response()->json(['msg' => $msg], 400);
            } else {
                $query = "SELECT CODCLI, NOFCLI FROM F_CLI WHERE EMACLI = " . "'" . $email . "'";
            }
        } else {
            $query = "SELECT CODCLI, NOFCLI FROM F_CLI WHERE TELCLI = " . "'" . $celphone . "'";
        }

        $existcli = $query;
        $exec = $this->conn->prepare($existcli);
        $exec->execute();
        $clientes = $exec->fetch(\PDO::FETCH_ASSOC);
        if (!$clientes) {
            $maxid = "SELECT MAX(CODCLI) + 1 AS ID FROM F_CLI";
            $exec = $this->conn->prepare($maxid);
            $exec->execute();
            $idmax = $exec->fetch(\PDO::FETCH_ASSOC);
            if (!$idmax) {
                $idmax['ID'] = 1;
            }
            $ins = [
                intval($idmax['ID']),
                intval($idmax['ID']),
                mb_convert_encoding($client['nom_cli'], 'UTF-8'),
                mb_convert_encoding($client['nom_cli'], 'UTF-8'),
                mb_convert_encoding($client['street'] . " " . $client['num_int'] . " " . $client['num_ext'], 'UTF-8'),
                mb_convert_encoding($client['estado'], 'UTF-8'),
                mb_convert_encoding($client['cp'], 'UTF-8'),
                mb_convert_encoding($client['mun'], 'UTF-8'),
                $celphone,
                500,
                'EFE',
                $client['price'],
                'DIS',
                $email,
                8,
                484,
                'ESPAÑA',
                'ESPAÑA',
                'ESPAÑA',
                'ESPAÑA',
            ];
            $inscli = "INSERT INTO F_CLI (CODCLI,CCOCLI,NOFCLI,NOCCLI,DOMCLI,POBCLI,CPOCLI,PROCLI,TELCLI,AGECLI,FPACLI,TARCLI,TCLCLI,EMACLI,DOCCLI,FUMCLI,FALCLI,PAICLI,APA1CLI,APA2CLI,APA3CLI,APA4CLI) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,DATE(),DATE(),?,?,?,?,?)";
            $exec = $this->conn->prepare($inscli);
            $insertado = $exec->execute($ins);
            if ($insertado) {
                $res = [
                    "id" => $idmax['ID'],
                    "nombre" => $client['nom_cli']
                ];
                return response()->json($res);
            } else {
                $msg = "No se pudo generar el cliente";
                return response()->json(['msg' => $msg], 400);
            }
        } else {
            $msg = "El celular o email ya esta en uso favor de intentarlo de nuevo";
            return response()->json(['msg' => $msg], 400);
        }
    }

    public function getsal(){
        $salid = "SELECT  TIPFAC&'-'&CODFAC AS SALIDA, REFFAC AS REFERENCIA, CNOFAC AS CLIENTE, FECFAC  AS FECHA, OB2FAC AS ENTRADA FROM F_FAC WHERE TIPFAC <> '8' AND REFFAC NOT LIKE '%TRASPASO%' AND FECFAC = DATE()";
        $exec = $this->conn->prepare($salid);
        $exec->execute();
        $salidas = $exec->fetchall(\PDO::FETCH_ASSOC);
        return   mb_convert_encoding($salidas, 'UTF-8');
    }

    public function updsal(Request $request){
        $upd = [
            $request->entrada,
            $request->salida
        ];
        $upds = "UPDATE F_FAC SET OB2FAC = ? WHERE TIPFAC&'-'&CODFAC = ?";
        $exec = $this->conn->prepare($upds);
        $res = $exec->execute($upd);
        if ($res) {
            return "salida actualizada";
        } else {
            return "no se pudo acturalizar la salida";
        }
    }

    public function getclient(Request $request){
        $search = $request->query('q');
        $existid = "SELECT CODCLI, NOFCLI, TELCLI, EMACLI, TARCLI FROM F_CLI WHERE CODCLI = ".intval($search);
        $exec = $this->conn->prepare($existid);
        $exec->execute();
        $idex = $exec->fetchall(\PDO::FETCH_ASSOC);
        if($idex){
            return mb_convert_encoding($idex, 'UTF-8');
        }else{
            $exis = "SELECT CODCLI, NOFCLI, TELCLI, EMACLI, TARCLI FROM F_CLI WHERE NOFCLI LIKE "."'%".$search."%'"." OR EMACLI LIKE "."'%".$search."%'"." OR TELCLI LIKE "."'%".$search."%'";
            $exec = $this->conn->prepare($exis);
            $exec->execute();
            $clients = $exec->fetchall(\PDO::FETCH_ASSOC);
            return mb_convert_encoding($clients, 'UTF-8');
        }
    }

    public function createClientSuc(Request $request){
        $client =  $request->all();

            $celphone = isset($client['celphone']) ? $client['celphone'] : '';
            $email = isset($client['email']) ? $client['email'] : '';
            $existcli = "SELECT * FROM F_CLI WHERE CODCLI = " . $client['fs_id'];
            $exec = $this->conn->prepare($existcli);
            $exec->execute();
            $clientes = $exec->fetch(\PDO::FETCH_ASSOC);
            if ($clientes) {
                $delcli = "DELETE FROM F_CLI WHERE CODCLI = " . $client['fs_id'];
                $exec = $this->conn->prepare($delcli);
                $exec->execute();
            }
            $ins = [
                intval($client['fs_id']),
                intval($client['fs_id']),
                $client['nom_cli'],
                $client['nom_cli'],
                $client['street'] . " " . $client['num_int'] . " " . $client['num_ext'],
                $client['estado'],
                $client['cp'],
                $client['mun'],
                $celphone,
                500,
                'EFE',
                $client['price'],
                'DIS',
                $email,
                8,
                484,
                'ESPAÑA',
                'ESPAÑA',
                'ESPAÑA',
                'ESPAÑA',
            ];
            $inscli = "INSERT INTO F_CLI (CODCLI,CCOCLI,NOFCLI,NOCCLI,DOMCLI,POBCLI,CPOCLI,PROCLI,TELCLI,AGECLI,FPACLI,TARCLI,TCLCLI,EMACLI,DOCCLI,FUMCLI,FALCLI,PAICLI,APA1CLI,APA2CLI,APA3CLI,APA4CLI) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,DATE(),DATE(),?,?,?,?,?)";
            $exec = $this->conn->prepare($inscli);
            $insertado = $exec->execute($ins);
            if ($insertado) {
                $res= [
                    "err"=>false,
                    "id" => $client['fs_id'],
                    "nombre" => $client['nom_cli']
                ];
                return response()->json($res,201);
            } else {
                $res = [
                    "err"=>true,
                    "id"=>$client['fs_id'],
                    "nombre" => $client['nom_cli']
                ];
                return response()->json($res,400);
            }
    }
}
