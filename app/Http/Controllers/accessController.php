<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

class accessController extends Controller
{
    public function __construct()
    {
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

    public function msg($msg, $number)
    {
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

    public function OpeningBox()
    {
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

    public function Withdrawals()
    {

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

    public function regeneration()
    {
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

    public function createClient(Request $request)
    {
        $client = $request->all();
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
            // "name" => mb_convert_encoding((string)$provider['NOFPRO'], "UTF-8", "Windows-1252"),
            $ins = [
                intval($idmax['ID']),
                intval($idmax['ID']),
                utf8_decode($client['nom_cli']),
                utf8_decode($client['nom_cli']),
                utf8_decode($client['street'] . " " . $client['num_int'] . " " . $client['num_ext']),
                utf8_decode($client['estado']),
                $client['cp'],
                utf8_decode($client['mun']),
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

    public function getsal()
    {
        $salid = "SELECT  TIPFAC&'-'&CODFAC AS SALIDA, REFFAC AS REFERENCIA, CNOFAC AS CLIENTE, FECFAC  AS FECHA, OB2FAC AS ENTRADA FROM F_FAC WHERE TIPFAC <> '8' AND REFFAC NOT LIKE '%TRASPASO%' AND FECFAC = DATE()";
        $exec = $this->conn->prepare($salid);
        $exec->execute();
        $salidas = $exec->fetchall(\PDO::FETCH_ASSOC);
        return   mb_convert_encoding($salidas, 'UTF-8');
    }

    public function updsal(Request $request)
    {
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

    public function getclient(Request $request)
    {
        $search = $request->query('q');
        $existid = "SELECT CODCLI, NOFCLI, TELCLI, EMACLI, TARCLI FROM F_CLI WHERE CODCLI = " . intval($search);
        $exec = $this->conn->prepare($existid);
        $exec->execute();
        $idex = $exec->fetchall(\PDO::FETCH_ASSOC);
        if ($idex) {
            return mb_convert_encoding($idex, 'UTF-8');
        } else {
            $exis = "SELECT CODCLI, NOFCLI, TELCLI, EMACLI, TARCLI FROM F_CLI WHERE NOFCLI LIKE " . "'%" . $search . "%'" . " OR EMACLI LIKE " . "'%" . $search . "%'" . " OR TELCLI LIKE " . "'%" . $search . "%'";
            $exec = $this->conn->prepare($exis);
            $exec->execute();
            $clients = $exec->fetchall(\PDO::FETCH_ASSOC);
            return mb_convert_encoding($clients, 'UTF-8');
        }
    }

    public function createClientSuc(Request $request)
    {
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
            utf8_decode($client['nom_cli']),
            utf8_decode($client['nom_cli']),
            utf8_decode($client['street'] . " " . $client['num_int'] . " " . $client['num_ext']),
            utf8_decode($client['estado']),
            $client['cp'],
            utf8_decode($client['mun']),
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
                "err" => false,
                "id" => $client['fs_id'],
                "nombre" => $client['nom_cli']
            ];
            return response()->json($res, 201);
        } else {
            $res = [
                "err" => true,
                "id" => $client['fs_id'],
                "nombre" => $client['nom_cli']
            ];
            return response()->json($res, 400);
        }
    }

    public function getdev()
    {
        $salid = "SELECT  TIPFRD&'-'&CODFRD AS DEVOLUCION, REFFRD AS REFERENCIA, PROFRD AS PROVEEDOR, FECFRD  AS FECHA, OB2FRD AS ABONO FROM F_FRD WHERE PROFRD = 5";
        $exec = $this->conn->prepare($salid);
        $exec->execute();
        $salidas = $exec->fetchall(\PDO::FETCH_ASSOC);
        if ($salidas) {
            $res = mb_convert_encoding($salidas, 'UTF-8');
            return response()->json($res, 200);
        } else {
            $res = [];
            return response()->json($res, 404);
        }
    }

    public function upddev(Request $request)
    {
        $upd = [
            $request->abono,
            $request->devolucion
        ];
        $upds = "UPDATE F_FRD SET OB2FRD = ? WHERE TIPFRD&'-'&CODFRD = ?";
        $exec = $this->conn->prepare($upds);
        $res = $exec->execute($upd);
        if ($res) {
            return response()->json("devolucion actualizada", 200);
        } else {
            return response()->json("no se pudo actualizar la devolucion", 401);
        }
    }

    public function gettras()
    {
        $salid = "SELECT  TIPFRD&'-'&CODFRD AS DEVOLUCION, REFFRD AS REFERENCIA, PROFRD AS PROVEEDOR, FECFRD  AS FECHA, COMFRD AS TRAZABILIDAD FROM F_FRD WHERE PROFRD >= 250";
        $exec = $this->conn->prepare($salid);
        $exec->execute();
        $salidas = $exec->fetchall(\PDO::FETCH_ASSOC);
        if ($salidas) {
            $res = mb_convert_encoding($salidas, 'UTF-8');
            return response()->json($res, 200);
        } else {
            $res = [];
            return response()->json($res, 404);
        }
    }

    public function returndev(Request $request)
    {
        $data = $request[0];
        $dev = "SELECT * FROM F_FRD WHERE TIPFRD&'-'&CODFRD = " . "'" . $data . "'";
        $exec = $this->conn->prepare($dev);
        $exec->execute();
        $devs = $exec->fetch(\PDO::FETCH_ASSOC);
        if ($devs) {
            $prodev = "SELECT * FROM F_LFD WHERE TIPLFD&'-'&CODLFD = " . "'" . $data . "'";
            $exec = $this->conn->prepare($prodev);
            $exec->execute();
            $psdevs = $exec->fetchall(\PDO::FETCH_ASSOC);
            foreach ($psdevs as $pros) {
                $products[] = [
                    "ARTLTR" => $pros['ARTLFD'],
                    "DES" => mb_convert_encoding($pros['DESLFD'], 'UTF-8'),
                    "CANLTR" => $pros['CANLFD'],
                    "PRE" => $pros['PRELFD'],
                    "TOTAL" => $pros['TOTLFD']
                ];
            }
            $res = [
                "devolucion" => $devs['TIPFRD'] . "-" . $devs['CODFRD'],
                "referencia" => $devs['REFFRD'],
                "total" => $devs['TOTFRD'],
                "productos" => $products
            ];
            return response()->json($res, 200);
        } else {
            return response()->json("No existe la devolucion", 401);
        }
    }

    public function createAbono(Request $request)
    { //abono
        $datos = $request;
        $products = $datos['products'];
        $datcli =  "SELECT CODCLI,NOFCLI,DOMCLI,POBCLI,CPOCLI,PROCLI, DOCCLI, TELCLI FROM F_CLI WHERE CODCLI = " . $datos['cliente'];
        $exec = $this->conn->prepare($datcli);
        $exec->execute();
        $client = $exec->fetch(\PDO::FETCH_ASSOC);
        $nextid = "SELECT MAX(CODFAB) + 1 AS ID FROM F_FAB WHERE TIPFAB = " . "'" . $client['DOCCLI'] . "'";
        $exec = $this->conn->prepare($nextid);
        $exec->execute();
        $id = $exec->fetch(\PDO::FETCH_ASSOC);
        if (is_null($id['ID'])) {
            $id = ['ID' => "1"];
        }
        $insa = [
            $client['DOCCLI'],
            $id['ID'],
            $datos['referencia'],
            'GEN',
            500,
            $client['CODCLI'],
            $client['NOFCLI'],
            $client['DOMCLI'],
            $client['POBCLI'],
            $client['CPOCLI'],
            $client['PROCLI'],
            $client['TELCLI'],
            $datos['total'],
            $datos['total'],
            $datos['total'],
            'C30',
            484,
            0,
            1,
            2,
            2023,
            '01/01/1900'
        ];
        $insabo = "INSERT INTO F_FAB (TIPFAB,CODFAB,REFFAB,FECFAB,ALMFAB,AGEFAB,CLIFAB,CNOFAB,CDOFAB,CPOFAB,CCPFAB,CPRFAB,TELFAB,NET1FAB,BAS1FAB,TOTFAB,FOPFAB,CPAFAB,TIVA1FAB,TIVA2FAB,TIVA3FAB,EDRFAB,FUMFAB) VALUES (?,?,?,date(),?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $exec = $this->conn->prepare($insabo);
        $yes = $exec->execute($insa);
        if ($yes) {
            $pos = 1;
            // foreach($products as $product){
            // $pco =  "SELECT PCOART AS COSTO FROM F_ART WHERE CODART = "."'".$product['ARTLTR']."'";
            // $exec = $this->conn->prepare($pco);
            // $exec -> execute();
            // $pcos =$exec->fetch(\PDO::FETCH_ASSOC);
            $inspro = [
                $client['DOCCLI'],
                $id['ID'],
                $pos,
                'TRASPASO',
                'TRASPASO ENTRE SUCURSALES',
                1,
                $datos['total'],
                $datos['total'],
                $datos['total']
            ];
            $inspab = "INSERT INTO F_LFB (TIPLFB,CODLFB,POSLFB,ARTLFB,DESLFB,CANLFB,PRELFB,TOTLFB,COSLFB) VALUES (?,?,?,?,?,?,?,?,?)";
            $exec = $this->conn->prepare($inspab);
            $art = $exec->execute($inspro);

            $updsto = "UPDATE F_STO SET ACTSTO = ACTSTO + 1 , DISSTO = DISSTO + 1  WHERE ARTSTO = 'TRASPASO' AND ALMSTO = 'GEN'";
            $exec = $this->conn->prepare($updsto);
            $art = $exec->execute();
            $pos++;
            // }
            $res = $client['DOCCLI'] . "-" . $id['ID'];
            return response()->json($res, 200);
        } else {
            return response()->json("No se genero el abono", 401);
        }
    }

    public function createSalidas(Request $request)
    {
        $datos = $request;
        $products = $datos['products'];
        $datcli =  "SELECT CODCLI,NOFCLI,DOMCLI,POBCLI,CPOCLI,PROCLI, DOCCLI, TELCLI FROM F_CLI WHERE CODCLI = " . $datos['cliente'];
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
            $datos['referencia'],
            'GEN',
            500,
            $client['CODCLI'],
            $client['NOFCLI'],
            $client['DOMCLI'],
            $client['POBCLI'],
            $client['CPOCLI'],
            $client['PROCLI'],
            $client['TELCLI'],
            $datos['total'],
            $datos['total'],
            $datos['total'],
            'C30',
            0,
            1,
            2,
            2023,
            '01/01/1900',
            $datos['observacion'],
            1
        ];
        $insabo = "INSERT INTO F_FAC (TIPFAC,CODFAC,REFFAC,FECFAC,ALMFAC,AGEFAC,CLIFAC,CNOFAC,CDOFAC,CPOFAC,CCPFAC,CPRFAC,TELFAC,NET1FAC,BAS1FAC,TOTFAC,FOPFAC,TIVA1FAC,TIVA2FAC,TIVA3FAC,EDRFAC,FUMFAC,OB1FAC,USUFAC) VALUES (?,?,?,date(),?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $exec = $this->conn->prepare($insabo);
        $yes = $exec->execute($insa);
        if ($yes) {
            $pos = 1;
            // foreach($products as $product){
            // $cos =  "SELECT PCOART AS COSTO FROM F_ART WHERE CODART = "."'".$product['ARTLTR']."'";
            // $exec = $this->conn->prepare($cos);
            // $exec -> execute();
            // $pcos =$exec->fetch(\PDO::FETCH_ASSOC);
            $inspro = [
                $client['DOCCLI'],
                $id['ID'],
                $pos,
                'TRASPASO',
                'TRASPASOS ENTRE SUCURSALES',
                1,
                $datos['total'],
                $datos['total'],
                $datos['total']
            ];
            $inspab = "INSERT INTO F_LFA (TIPLFA,CODLFA,POSLFA,ARTLFA,DESLFA,CANLFA,PRELFA,TOTLFA,COSLFA) VALUES (?,?,?,?,?,?,?,?,?)";
            $exec = $this->conn->prepare($inspab);
            $art = $exec->execute($inspro);

            $updsto = "UPDATE F_STO SET ACTSTO = ACTSTO - 1 , DISSTO = DISSTO - 1 WHERE ARTSTO = 'TRASPASO' AND ALMSTO = 'GEN'";
            $exec = $this->conn->prepare($updsto);
            $art = $exec->execute();
            // $pos++;
            // }

            $res = $client['DOCCLI'] . "-" . str_pad($id['ID'], 6, "0", STR_PAD_LEFT);
            return response()->json($res);
        } else {
            return response()->json('No se genero la salida', 401);
        }
    }

    public function createEntradas(Request $request)
    { //factura recibida
        $datos = $request->all();
        $products = $datos['products'];
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
        $insa = [
            '1',
            $id['ID'],
            $datos['referencia'],
            $datos['referencia'],
            $provider['CODPRO'],
            $provider['NOFPRO'],
            $provider['DOMPRO'],
            $provider['POBPRO'],
            $provider['CPOPRO'],
            $provider['PROPRO'],
            $datos['total'],
            $datos['total'],
            $datos['total'],
            1,
            1,
            'GEN',
            '01/01/1900',
            $datos['observacion'],
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
                    $product['ARTLTR'],
                    $product['DES'],
                    $product['CANLTR'],
                    $product['PRE'],
                    $product['TOTAL'],
                ];
                $inspab = "INSERT INTO F_LFR (TIPLFR,CODLFR,POSLFR,ARTLFR,DESLFR,CANLFR,PRELFR,TOTLFR) VALUES (?,?,?,?,?,?,?,?)";
                $exec = $this->conn->prepare($inspab);
                $art = $exec->execute($inspro);

                $updsto = "UPDATE F_STO SET ACTSTO = ACTSTO + " . $product['CANLTR'] . ", DISSTO = DISSTO + " . $product['CANLTR'] . " WHERE ARTSTO = " . "'" . $product['ARTLTR'] . "'" . " AND ALMSTO = 'GEN'";
                $exec = $this->conn->prepare($updsto);
                $art = $exec->execute();
                $pos++;
            }
            $res = "1" . "-" . $id['ID'];
            return response()->json($res, 200);
        } else {
            return response()->json("No se genero la factura recibida", 401);
        }
    }

    public function returnFac(Request $request)
    {
        $data =  $request->salida;
        $dev = "SELECT
        F_FAC.TIPFAC&'-'&FORMAT(F_FAC.CODFAC,'000000') AS FACTURA,
        F_FAC.REFFAC AS FOLIO,
        F_FAC.CNOFAC AS CLIENTE,
        F_FAC.FECFAC AS FECHA,
        F_FAC.ALMFAC AS AMACEN,
        F_FAC.AGEFAC AS AGENTE,
        F_FAC.FOPFAC AS FPAGO
        FROM F_FAC
        WHERE F_FAC.TIPFAC&'-'&FORMAT(F_FAC.CODFAC,'000000') =" . "'" . $data . "'";
        $exec = $this->conn->prepare($dev);
        $exec->execute();
        $devs = $exec->fetch(\PDO::FETCH_ASSOC);
        if ($devs) {
            $prodev = "SELECT F_LFA.ARTLFA AS ARTLFA, F_LFA.BULLFA AS BULLFA, F_ART.UPPART AS PXC, F_LFA.CANLFA AS CANLFA, F_LFA.DESLFA AS DESLFA FROM F_LFA INNER JOIN F_ART ON F_ART.CODART = F_LFA.ARTLFA  WHERE TIPLFA&'-'&FORMAT(CODLFA,'000000') = " . "'" . $data . "'";
            $exec = $this->conn->prepare($prodev);
            $exec->execute();
            $psdevs = $exec->fetchall(\PDO::FETCH_ASSOC);
            foreach ($psdevs as $pros) {
                $products[] = [
                    "ARTLTR" => $pros['ARTLFA'],
                    "BULLFA" => $pros['BULLFA'] == null ? 0 : $pros['BULLFA'],
                    "PXC" => $pros['PXC'],
                    "CANLTR" => $pros['CANLFA'],
                    "DES" => mb_convert_encoding($pros['DESLFA'], 'UTF-8'),
                ];
            }
            $res = [
                "salida" => $devs,
                "productos" => $products
            ];
            return response()->json($res, 200);
        } else {
            return response()->json("No existe la devolucion", 401);
        }
    }

    public function returnTra(Request $request)
    {
        $data =  $request->traspaso;
        $hed = "SELECT
            F_EMP.DENEMP,
            F_EMP.DOMEMP,
            F_EMP.NUMEMP,
            F_EMP.CPOEMP,
            F_EMP.POBEMP,
            F_EMP.PROEMP,
            F_EMP.TELEMP,
            F_EMP.FAXEMP
            FROM F_EMP";
        $exec = $this->conn->prepare($hed);
        $exec->execute();
        $header = $exec->fetch(\PDO::FETCH_ASSOC);


        $dev = "SELECT
                AOR.CODALM AS ALMOR,
                AOR.NOMALM AS ALDMOR,
                ADE.CODALM AS ALMDES,
                ADE.NOMALM AS ALDDES,
                F_TRA.DOCTRA AS TRASPASO,
                F_TRA.FECTRA AS FECHA,
                F_TRA.COMTRA AS COMENTARIO
                FROM ((F_TRA
                INNER JOIN F_ALM AS AOR ON AOR.CODALM = F_TRA.AORTRA)
                INNER JOIN F_ALM AS ADE ON ADE.CODALM = F_TRA.ADETRA)
                WHERE F_TRA.DOCTRA =  $data";
        $exec = $this->conn->prepare($dev);
        $exec->execute();
        $devs = $exec->fetch(\PDO::FETCH_ASSOC);
        if ($devs) {
            $prodev = "SELECT
            F_LTR.ARTLTR AS ARTLTR,
            F_LTR.BULLTR AS BULLTR,
            F_ART.UPPART AS PXC,
            F_LTR.CANLTR AS CANLTR,
            F_ART.DESART AS DESCRIPCION
            FROM F_LTR
            INNER JOIN F_ART ON F_ART.CODART = F_LTR.ARTLTR
            WHERE F_LTR.DOCLTR = $data ";
            $exec = $this->conn->prepare($prodev);
            $exec->execute();
            $psdevs = $exec->fetchall(\PDO::FETCH_ASSOC);
            foreach ($psdevs as $pros) {
                $products[] = [
                    "ARTLTR" => $pros['ARTLTR'],
                    "BULLFA" => $pros['BULLTR'] == null ? 0 : $pros['BULLTR'],
                    "PXC" => $pros['PXC'],
                    "CANLTR" => $pros['CANLTR'],
                    "DES" => mb_convert_encoding($pros['DESCRIPCION'], 'UTF-8'),
                ];
            }
            $res = [
                "heades" => $header,
                "traspaso" => $devs,
                "productos" => $products
            ];
            return response()->json($res, 200);
        } else {
            return response()->json("No existe la devolucion", 401);
        }
    }

    public function Invoices()
    {

        $sql = "SELECT
        F_FAC.TIPFAC&'-'&FORMAT(F_FAC.CODFAC,'000000')  AS FACTURA,
                F_FAC.REFFAC,
                F_FAC.ALMFAC,
                F_FAC.CLIFAC,
                F_FAC.CNOFAC,
                F_FAC.OB1FAC,
                F_FAC.TOTFAC,
                COUNT(F_LFA.ARTLFA) AS ARTICULOS,
                SUM(F_LFA.CANLFA) AS CANTIDAD,
                Format(F_FAC.HORFAC, 'hh:nn:ss') AS HORFAC
                FROM F_FAC
                INNER JOIN F_LFA ON F_LFA.TIPLFA&'-'&F_LFA.CODLFA =  F_FAC.TIPFAC&'-'&F_FAC.CODFAC
                WHERE FECFAC = DATE() AND TIPFAC <> '8'
               GROUP BY
        F_FAC.TIPFAC&'-'&FORMAT(F_FAC.CODFAC,'000000'),
                F_FAC.REFFAC,
                F_FAC.ALMFAC,
                F_FAC.CLIFAC,
                F_FAC.CNOFAC,
                F_FAC.OB1FAC,
                F_FAC.TOTFAC,
           Format(F_FAC.HORFAC, 'hh:nn:ss');";
        // $exec = $this->conn->prepare($sql);
        $exec = $this->conn->prepare($sql); //comentar

        $exec->execute();
        $facs = $exec->fetchall(\PDO::FETCH_ASSOC);



        return mb_convert_encoding($facs, 'UTF-8');
    }

    public function Entries()
    {
        $sql = "SELECT
        F_FRE.TIPFRE&'-'&F_FRE.CODFRE  AS FACTURA,
        F_FRE.FACFRE,
        F_FRE.REFFRE,
        F_FRE.ALMFRE,
        F_FRE.OB1FRE,
        F_FRE.TOTFRE,
        COUNT(F_LFR.ARTLFR) AS ARTICULOS,
        SUM(F_LFR.CANLFR) AS CANTIDAD
        FROM F_FRE
        INNER JOIN F_LFR ON F_LFR.TIPLFR&'-'&F_LFR.CODLFR = F_FRE.TIPFRE&'-'&F_FRE.CODFRE
        WHERE FECFRE = DATE()
        GROUP BY
        F_FRE.TIPFRE&'-'&F_FRE.CODFRE  ,
        F_FRE.FACFRE,
        F_FRE.REFFRE,
        F_FRE.ALMFRE,
        F_FRE.OB1FRE,
        F_FRE.TOTFRE";
        $exec = $this->conn->prepare($sql);
        $exec->execute();
        $facs = $exec->fetchall(\PDO::FETCH_ASSOC);

        return mb_convert_encoding($facs, 'UTF-8');
    }

    public function getInvoices(Request $request){

        if(isset($request->fechas['from'])){
            $desde = $request->fechas['from'];
            $hasta = $request->fechas['to'];
            $condicion = "#".$desde."#"." AND "."#".$hasta."#";
        }else{
            $desde = $request->fechas;
            $hasta = $request->fechas;
            $condicion = "#".$desde."#"." AND "."#".$hasta."#";
        }

        $sql = "SELECT
        F_FAC.TIPFAC&'-'&FORMAT(F_FAC.CODFAC,'000000')  AS FACTURA,
        F_FAC.FECFAC&' '&Format(F_FAC.HORFAC, 'hh:nn:ss') AS FECHAYHORA,
        F_FAC.REFFAC AS REFERENCIA,
        F_FAC.ALMFAC AS ALMACEN,
        F_FAC.CLIFAC AS CLIENTE,
        F_FAC.CNOFAC AS NOMBRECLIENTE,
        F_FAC.OB1FAC AS OBSERVACION,
        F_LFA.ARTLFA AS ARTICULOS,
        F_LFA.DESLFA AS DESCRIPCION,
        F_LFA.CANLFA AS CANTIDAD,
        F_LFA.PRELFA AS PRECIO,
        F_LFA.TOTLFA AS TOTAL
        FROM F_FAC
        INNER JOIN F_LFA ON F_LFA.TIPLFA&'-'&F_LFA.CODLFA =  F_FAC.TIPFAC&'-'&F_FAC.CODFAC
        WHERE FECFAC BETWEEN $condicion AND F_FAC.CLIFAC = $request->_client AND REFFAC NOT LIKE '%TRASPASO%'";
        $exec = $this->conn->prepare($sql);
        $exec->execute();
        $facs = $exec->fetchall(\PDO::FETCH_ASSOC);
        return mb_convert_encoding($facs, 'UTF-8');

    }
    public function getEntries(Request $request){
        $invoices = $request->invoices;
        $sql = "SELECT
        F_FRE.TIPFRE&'-'&F_FRE.CODFRE  AS FACTURA,
        F_FRE.FECFRE AS FECHA,
        F_FRE.FACFRE AS SALIDA,
        F_FRE.REFFRE AS REFERENCIA,
        F_FRE.ALMFRE AS ALMACEN,
        F_FRE.OB1FRE AS OBSERVACION,
        F_LFR.ARTLFR AS ARTICULOS,
        F_LFR.DESLFR AS DESCRIPCION,
        F_LFR.CANLFR AS CANTIDAD,
        F_LFR.PRELFR AS PRECIO,
        F_LFR.TOTLFR AS TOTAL
        FROM F_FRE
        INNER JOIN F_LFR ON F_LFR.TIPLFR&'-'&F_LFR.CODLFR = F_FRE.TIPFRE&'-'&F_FRE.CODFRE
        WHERE F_FRE.FACFRE IN ($invoices)";
        $exec = $this->conn->prepare($sql);
        $exec->execute();
        $facs = $exec->fetchall(\PDO::FETCH_ASSOC);
        return response()->json(mb_convert_encoding($facs, 'UTF-8'));
    }

    public function getInvoiceBudget(Request $request){
        $folio = $request->number;
        $price = $request->client;
        $store = $request->store;
        $client = DB::table('stores')->where('id',$store)->first();
        $select = "SELECT TIPFAC&'-'&CODFAC AS FOLIO, REFFAC ,Format(FECFAC, 'YYYY-mm-dd') AS FECHA  FROM F_FAC WHERE TIPFAC&'-'&CODFAC = "."'".$folio."'"." AND CLIFAC = ".$client->_client;
        $exec = $this->conn->prepare($select);
        $exec->execute();
        $fac = $exec->fetch(\PDO::FETCH_ASSOC);
        if($fac){
            $existclient = "SELECT CODCLI, NOFCLI, DOMCLI,POBCLI,CPOCLI,PROCLI,TARCLI , NVCCLI FROM F_CLI WHERE  CODCLI = $price";
            $exec = $this->conn->prepare($existclient);
            $exec->execute();
            $excli = $exec->fetch(\PDO::FETCH_ASSOC);
            if($excli){
                if($excli['NVCCLI']== 0){
                    $linfac = "SELECT
                    F_LFA.ARTLFA,
                    F_LFA.DESLFA,
                    F_LFA.CANLFA,
                    F_LTA.PRELTA,
                    F_LTA.PRELTA * F_LFA.CANLFA  AS TOTAL,
                    F_LFA.PRELFA AS COSLFA
                    FROM F_LFA
                    INNER JOIN F_LTA ON F_LTA.ARTLTA = F_LFA.ARTLFA
                    WHERE F_LFA.TIPLFA&'-'&F_LFA.CODLFA = "."'".$folio."'"." AND F_LTA.TARLTA = ".$excli['TARCLI'];
                $exec = $this->conn->prepare($linfac);
                $exec->execute();
                $lnsfac = $exec->fetchall(\PDO::FETCH_ASSOC);
                $res = [
                    "factura"=>mb_convert_encoding($fac, 'UTF-8'),
                    "cliente"=>mb_convert_encoding($excli, 'UTF-8'),
                    "productos"=>mb_convert_encoding($lnsfac, 'UTF-8'),
                ];
                return response()->json($res,200);
                }else{
                    return response()->json(['message'=>'No se permite vender al cliente '.$price], 404);
                }
            }else{
                return response()->json(['message'=>'No se encuentra el cliente '.$price], 404);
            }
        }else{

            return response()->json(['message'=>'No se encuentra la Salida '.$folio], 404);
        }
    }

    public function getCommand(Request $request){
        $store = $request->store;
        $comanda = $request->number;
        $price = $request->client;
        $coman =  DB::connection('vizapi')->table('orders')->where([['_status','>',1],['_workpoint_from',$store],['id',$comanda]])->select('id AS FOLIO','name AS REFFAC')->selectRaw("DATE_FORMAT(created_at, '%Y-%m-%d') as FECHA")->first();
        if($coman){
            $existclient = "SELECT CODCLI, NOFCLI, DOMCLI,POBCLI,CPOCLI,PROCLI,TARCLI , NVCCLI FROM F_CLI WHERE  CODCLI = $price";
            $exec = $this->conn->prepare($existclient);
            $exec->execute();
            $excli = $exec->fetch(\PDO::FETCH_ASSOC);
            if($excli){
                if($excli['NVCCLI']== 0){
                $products  = DB::connection('vizapi')->table('product_ordered AS OP')->join('products AS P','P.id','OP._product')->join('product_prices AS PP','PP._product','P.id')
                ->where([['PP._type',$excli['TARCLI']],['OP._order',$comanda]])
                ->select('P.code AS ARTLFA',
                        'P.description AS DESLFA',
                        'OP.units AS CANLFA',
                        'PP.price AS PRELTA',
                        'P.cost AS COSLFA')
                ->selectRaw('PP.price * OP.units AS TOTAL')
                ->get();
                $res = [
                    "factura"=>$coman,
                    "cliente"=>$excli,
                    "productos"=>$products
                ];
                return response()->json($res,200);
                }else{
                    return response()->json(['message'=>'No se permite vender al cliente '.$price], 404);
                }
            }else{
                return response()->json(['message'=>'No se encuentra el cliente '.$price], 404);
            }
        }else{
            return response()->json(['message'=>'No se encuentra el pedido'],404);
        }
    }

    public function createBudget(Request $request){
        $client = $request->client;
        $factura = $request->factura;
        $products = $request->products;

        $date = date("Y/m/d H:i");//se gerera la fecha de el dia de hoy con  formato de fecha y hora
        $date_format = Carbon::now()->format('d/m/Y');//se obtiene el dia que ocurre
        $hour = "01/01/1900 ".explode(" ", $date)[1];//se formatea la fecha de el dia de hoy poniendo solo la hora en la que se genera
        $max = "SELECT max(CODPRE) as CODIGO FROM F_PRE WHERE TIPPRE = '8'";//query para sacar el numero de factura maximo de el tipo(serie)
        $exec = $this->conn->prepare($max);
        $exec -> execute();
        $maxcode=$exec->fetch(\PDO::FETCH_ASSOC);//averS
        $codigo = $maxcode['CODIGO'] + 1;

        $fac = [
            "8",
            $codigo,
            "REF ".$factura['REFFAC'],
            $date_format,
            500,
            $client['CODCLI'],
            $client['NOFCLI'],
            $client['DOMCLI'],
            $client['POBCLI'],
            $client['CPOCLI'],
            $client['PROCLI'],
            'GEN',
            $factura['TOTAL'],
            $factura['TOTAL'],
            $factura['TOTAL'],
            'EFE',
            'Creado Por Vhelpers',
            27,
            27,
            $hour,
            $date_format
        ];
        $sql = "INSERT INTO F_PRE (TIPPRE,CODPRE,REFPRE,FECPRE,AGEPRE,CLIPRE,CNOPRE,CDOPRE,CPOPRE,CCPPRE,CPRPRE,ALMPRE,NET1PRE,BAS1PRE,TOTPRE,FOPPRE,OB1PRE,USUPRE,USMPRE,HORPRE,FUMPRE) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";//se crea el query para insertar en la tabla
        $exec = $this->conn->prepare($sql);
        $res = $exec -> execute($fac);

        if($res){
            $pos = 1;
            foreach($products as $product){
                $values = [//se genera el arreglo para la insercion a factusol
                    "8",
                    $codigo,
                     $pos,//posision de la linea
                     $product['ARTLFA'],
                     $product['DESLFA'],
                     $product['CANLFA'],
                     $product['PRELTA'],
                     $product['TOTAL'],
                     $product['COSLFA']
                 ];
                 $insert = "INSERT INTO F_LPS (TIPLPS,CODLPS,POSLPS,ARTLPS,DESLPS,CANLPS,PRELPS,TOTLPS,COSLPS) VALUES (?,?,?,?,?,?,?,?,?)";//query para insertar las lineas de la factura creada en factusol
                 $exec = $this->conn->prepare($insert);
                 $res = $exec -> execute($values);//envia el arreglo
                 if($res){
                    $pos ++;
                 }
            }
            $res = [
                "message"=>"Presupuesto Creado",
                "folio"=>"8-".$codigo,
                "productos"=>$pos - 1,
                "status"=>true
            ];
            return response()->json($res,200);
        }else{
            return response()->json(['message'=>'No se genero el presupuesto ', 'status'=>false], 500);
        }
    }

}
