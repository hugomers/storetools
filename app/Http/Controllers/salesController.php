<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;
use Carbon\CarbonImmutable;


class salesController extends Controller
{
    public function __construct(){
        $access = env("ACCESS");//conexion a access de sucursal
        if(file_exists($access)){
        try{  $this->conn  = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
            }catch(\PDOException $e){ die($e->getMessage()); }
        }else{ die("$access no es un origen de datos valido."); }
    }

    public function addSale(Request $request){
        $addWith = null;
        $addAdvance = null;
        $order =  $request->order;
        $cash = $request->cashier;
        $config = $request->config;
        $payments = $order['payments'];
        $change = floatval($order['change']);
        $resCli = $order['client']['id'] == 0 ? 1 : $order['client']['id'];
        $client =  "SELECT CODCLI, NOFCLI, DOMCLI, POBCLI, CPOCLI, PROCLI, TELCLI FROM F_CLI WHERE CODCLI = ".$resCli;
        $exec = $this->conn->prepare($client);
        $exec->execute();
        $ncli = $exec->fetch(\PDO::FETCH_ASSOC);


        $filtered = array_filter($payments, function($val) {
            return isset($val['id']) && !is_null($val['id']) && $val['val'] > 0;
        });
        if ($change > 0) {
            foreach ($filtered as $key => &$payment) {
                if (isset($payment['id']['alias']) && $payment['id']['alias'] === 'EFE') {
                    $original = floatval($payment['val']);
                    $adjusted = $original - $change;
                    $payment['val'] = $adjusted >= 0 ? $adjusted : 0;
                    break;
                }
            }
        }else if ( isset($payments['conditions']['super']) && $payments['conditions']['super']){
            $cambio = ($payments['PFPA']['val'] + $payments['SFPA']['val'] + $payments['VALE']['val']) - $order['total'];
            if($cambio > 0){
                foreach ($filtered as $key => &$payment) {
                    if (isset($payment['id']['id']) && $payment['id']['id'] === 5 ) {

                        $original = floatval($payment['val']);
                        $adjusted = $original - $cambio;
                        $payment['val'] = $adjusted >= 0 ? $adjusted : 0;
                        $changeVale = ['total'=>$adjusted,'state'=>true];
                        break;
                    }
                }
            }
        }

        $cobmax = "SELECT MAX(CODCOB) as maxi FROM F_COB";
        $exec = $this->conn->prepare($cobmax);
        $exec->execute();
        $maxcob = $exec->fetch(\PDO::FETCH_ASSOC);
        $cobro = $maxcob['maxi'] + 1;

        $termi = "SELECT * FROM T_TER INNER JOIN T_DOC ON T_DOC.CODDOC = T_TER.DOCTER WHERE CODTER = ". $cash['cashier']['cash']['_terminal'];
        $exec = $this->conn->prepare($termi);
        $exec->execute();
        $codter = $exec->fetch(\PDO::FETCH_ASSOC);
        $nomter = $codter['DESTER'];
        $idterminal = str_pad($codter['CODTER'], 4, "0", STR_PAD_LEFT)."00".Carbon::parse($cash['cashier']['open_date'])->format('ymd');

        $codmax = "SELECT MAX(CODFAC) as maxi FROM F_FAC WHERE TIPFAC = "."'".$codter['TIPDOC']."'";
        $exec = $this->conn->prepare($codmax);
        $exec->execute();
        $max = $exec->fetch(\PDO::FETCH_ASSOC);
        $codigo = $max['maxi'] + 1;

        $column = ["TIPFAC","CODFAC","FECFAC", "ALMFAC","AGEFAC","CLIFAC","CNOFAC","CDOFAC","CPOFAC","CCPFAC","CPRFAC","TELFAC","NET1FAC","BAS1FAC","PIVA1FAC","IIVA1FAC","TOTFAC","FOPFAC","OB1FAC","REAFAC","VENFAC","HORFAC","USUFAC","USMFAC","TIVA2FAC","TIVA3FAC","EDRFAC","FUMFAC","BCOFAC","TPVIDFAC","ESTFAC","TERFAC","DEPFAC","EFEFAC","CAMFAC","EFSFAC","EFVFAC"];
        $factura = [
            $codter['TIPDOC'],//
            $codigo,//
            now()->format('d/m/Y'),
            "GEN",
            $order['dependiente']['id_tpv'],
            $ncli['CODCLI'],
            $ncli['NOFCLI'],
            $ncli['DOMCLI'],
            $ncli['POBCLI'],
            $ncli['CPOCLI'],
            $ncli['PROCLI'],
            $ncli['TELCLI'],
            isset($order['subtotal']) ? $order['subtotal'] : $order['total'],
            isset($order['subtotal']) ? $order['subtotal'] : $order['total'],
            $config['option'] ? $config['value']  : 0,
            $config['option'] ? $order['impuesto']  : 0,
            $order['total'],
            $order['payments']['PFPA']['id']['alias'] ,
            isset($order['observation']) ? $order['observation'] : null,
            isset($order['order']) ? $order['order'] : null,
            now()->format('d/m/Y'),
            now()->format('H:i'),
            27,
            27,
            1,
            2,
            date('Y'),
            now()->format('d/m/Y'),
            1,
            $idterminal,
            2,
            intval($codter['CODTER']),
            $order['dependiente']['id_tpv'],
            $order['payments']['PFPA']['val'] ,
            $order['payments']['change'],
            $order['payments']['SFPA']['val'] ,
            $order['payments']['VALE']['val'] ,
        ];
        // $valeCode = isset($order['payments']['VALE']['id']['id']) ?  : null;

        if(isset($order['payments']['VALE']['id']['id'])){
            $nva = $order['payments']['VALE']['id']['code'];
            $exec = $this->conn->prepare("UPDATE F_ANT SET ESTANT = 1, TDOANT = ". $codter['TIPDOC']. " , CDOANT = ". $codigo ."  WHERE CODANT = ".$nva)->execute();
        }


        $impcol = implode(",",$column);
        $signos = implode(",",array_fill(0, count($column),'?'));
        $sql = "INSERT INTO F_FAC ($impcol) VALUES ($signos)";//se crea el query para insertar en la tabla
        $exec = $this->conn->prepare($sql);
        $res = $exec->execute($factura);
        // return $res;
        if($res){
            $contap = 1;
            foreach($order['products'] as $product){
                $upd = [
                    $product['pivot']['units'],
                    $product['pivot']['units'],
                    $product['code'],
                ];

                $inspro = [
                    $codter['TIPDOC'],
                    $codigo,
                    $contap,
                    $product['code'],
                    $product['description'],
                    intval($product['pivot']['units']),
                    doubleval($product['pivot']['price']),
                    doubleval($product['pivot']['total']),
                    $product['cost']
                ];

                $insertapro = "INSERT INTO F_LFA (TIPLFA,CODLFA,POSLFA,ARTLFA,DESLFA,CANLFA,PRELFA,TOTLFA,COSLFA) VALUES(?,?,?,?,?,?,?,?,?)";
                $exec = $this->conn->prepare($insertapro);
                $exec->execute($inspro);

                $updatesto = "UPDATE F_STO SET DISSTO = DISSTO - ? , ACTSTO = ACTSTO - ? WHERE ALMSTO = 'GEN' AND ARTSTO = ?";
                $exec = $this->conn->prepare($updatesto);
                $exec -> execute($upd);
                $contap++;
            }
            $count = 1;
            foreach($filtered as $fip){
                $mosPag = $fip['id']['name'] == 'VALE' ? true   : false;
                $inspg = [
                    $codter['TIPDOC'],
                    $codigo,
                    $count,
                    now()->format('d/m/Y'),
                    $fip['val'],
                    $fip['id']['name'],
                    $fip['id']['alias'],
                    $cobro,
                    $idterminal,
                    $codter['CODTER']
                ];
                // return $inspg;
                $faclco = "INSERT INTO F_LCO (TFALCO,CFALCO,LINLCO,FECLCO,IMPLCO,CPTLCO,FPALCO,MULLCO,TPVIDLCO,TERLCO) VALUES (?,?,?,?,?,?,?,?,?,?) ";
                $exec = $this->conn->prepare($faclco);
                $exec->execute($inspg);

                $inscob = [
                    $cobro,
                    now()->format('d/m/Y'),
                    $fip['val'],
                    $fip['id']['name']
                ];
                $instcob = "INSERT INTO F_COB (CODCOB,FECCOB, IMPCOB CPTCOB) VALUES (?,?,?,?)";
                $exec = $this->conn->prepare($instcob);
                $exec->execute($inscob);
                $count++;
                $cobro++;
            }
        }

        $folio = $codter['TIPDOC']."-".str_pad($codigo, 6, "0", STR_PAD_LEFT);
        if($payments['conditions']['super']){
            $cambio = ($payments['PFPA']['val'] + $payments['SFPA']['val'] + $payments['VALE']['val']) - $order['total'];
            if($payments['conditions']['createWithdrawal']){
                $envio = [
                    "cash"=>$cash,
                    "withdrawal"=>[
                        "concept"=>"Devolucion Sobrante pagos ticket ".$folio,
                        "import"=>$cambio,
                        "providers"=>[
                            "val"=>["id"=>833,"name"=>"DEVOLUCIONES DE EFECTIVO PARA CLIENTES"]
                        ]
                    ]
                ];
                $addWith = $this->addWithrawalSobrante($envio);
            }else{
                $envio = [
                    "sale"=>$folio,
                    "cash"=>$cash,
                    "advance"=>[
                        "client"=>[
                            "id"=>$ncli['CODCLI'],
                            "name"=>$ncli['NOFCLI']
                        ],
                    "import"=>$cambio,
                    "observacion"=>"Vale Sobrante de la venta ".$folio,
                    ]
                ];
                $addAdvance = $this->addAdvancesSobrante($envio);
            }
        }
        $order['created_at'] = now()->format('d/m/Y');
        $cellerPrinter = new PrinterController();
        $printed = $cellerPrinter->printck($order,$cash,$folio,$config);
        if($printed){
            return response()->json(['folio'=>$folio,'retirada'=>$addWith,'vale'=>$addAdvance]);
        }
    }

    public function addWithrawalSobrante($envio){
        $date_format = Carbon::now()->format('d/m/Y');//formato fecha factusol
        $date = date("Y/m/d H:i");//horario para la hora
        $hour = "01/01/1900 ".explode(" ", $date)[1];//hora para el ticket
        $horad = explode(" ", $date)[1];
        $idano = Carbon::now()->format('ymd');
        $cash = $envio['cash'];
        $with = $envio['withdrawal'];
        $codmax = "SELECT MAX(CODRET) as max FROM F_RET";
        $exec = $this->conn->prepare($codmax);
        $exec->execute();
        $cod=$exec->fetch(\PDO::FETCH_ASSOC);
        $codigo = $cod['max'] + 1;

        $cajaob = "SELECT CODTER FROM T_TER WHERE CODTER = ". $cash['_terminal'];
        $exec = $this->conn->prepare($cajaob);
        $exec->execute();
        $cajat=$exec->fetch(\PDO::FETCH_ASSOC);

        $idterminal = str_pad($cajat['CODTER'], 4, "0", STR_PAD_LEFT)."00".$idano;
        $insert = [
            $codigo,
            $cash['_terminal'],
            $date_format,
            $horad,
            $with['concept'],
            $with['import'],
            $with['providers']['val']['id'],
            $idterminal
        ];
        $ins = "INSERT INTO F_RET (CODRET,CAJRET,FECRET,HORRET,CONRET,IMPRET,PRORET,TPVIDRET) VALUES (?,?,?,?,?,?,?,?)";
        $exec = $this->conn->prepare($ins);
        $res = $exec->execute($insert);
        if($res){
            $envio['withdrawal']['fs_id'] = $codigo;
            $cellerPrinter = new PrinterController();
            $printed = $cellerPrinter->printret($envio);
            return $codigo;
        }else{
            return response()->json('No se logro realizar la retirada',500);
        }
    }

    public function addAdvancesSobrante($envio){
        $date_format = Carbon::now()->format('d/m/Y');//formato fecha factusol
        $date = date("Y/m/d H:i");//horario para la hora
        $hour = "01/01/1900 ".explode(" ", $date)[1];//hora para el ticket
        $horad = explode(" ", $date)[1];
        $idano = Carbon::now()->format('ymd');
        $cash = $envio['cash'];
        $advance = $envio['advance'];

        $codmax = "SELECT MAX(CODANT) as max FROM F_ANT";
        $exec = $this->conn->prepare($codmax);
        $exec->execute();
        $cod=$exec->fetch(\PDO::FETCH_ASSOC);
        $codigo = $cod['max'] + 1;

        $cajaob = "SELECT CODTER FROM T_TER WHERE CODTER = ". $cash['_terminal'];
        $exec = $this->conn->prepare($cajaob);
        $exec->execute();
        $cajat=$exec->fetch(\PDO::FETCH_ASSOC);

        $idterminal = str_pad($cajat['CODTER'], 4, "0", STR_PAD_LEFT)."00".$idano;

        $insert = [
            $codigo,//codant
            $date_format,//fecant
            $advance['client']['id'],//cliant
            $advance['import'],//impant
            0,//estant
            $advance['observacion'],//obsant,
            1,//CRIANT
            $cash['_terminal'],//cajant
            $idterminal//tpvidant
        ];

        $ins = "INSERT INTO F_ANT (CODANT,FECANT,CLIANT,IMPANT,ESTANT,OBSANT,CRIANT,CAJANT,TPVIDANT) VALUES (?,?,?,?,?,?,?,?,?)";
        $exec = $this->conn->prepare($ins);
        $res = $exec->execute($insert);

        if($res){
            $envio['advance']['fs_id'] = $codigo;
            $cellerPrinter = new PrinterController();
            $printed = $cellerPrinter->printAdvance($envio);
            return $codigo;
        }else{
            return response()->json('No se logro realizar el ingreso',500);
        }
    }

    public function getWithdrawals(Request $request){
        $sql = "SELECT
            Format(R.FECRET, 'YYYY-MM-DD') as FECHA,
            Format(R.HORRET, 'HH:mm:ss') as HORA,
            R.CODRET,
            R.CAJRET,
            TR.DESTER,
            R.CONRET,
            R.IMPRET,
            R.PRORET,
            FP.NOFPRO
            FROM ((F_RET AS R
            INNER JOIN T_TER AS TR ON  TR.CODTER = R.CAJRET)
            INNER JOIN F_PRO AS FP ON FP.CODPRO = R.PRORET)
            WHERE R.FECRET = DATE() AND R.CAJRET = ".$request->cash['_terminal'];
        $exec = $this->conn->prepare($sql);
        $exec -> execute();
        $cuts = $exec->fetchall(\PDO::FETCH_ASSOC);
        return response()->json(mb_convert_encoding(["withdrawals"=>$cuts], 'UTF-8'));
    }

    public function printWitrawal(Request $request){
        $cash = $request->cash;
        $code = $request->withdrawal;
        $sql = "SELECT
            Format(R.FECRET, 'YYYY-MM-DD') as FECHA,
            Format(R.HORRET, 'HH:mm:ss') as HORA,
            R.CODRET,
            R.CAJRET,
            TR.DESTER,
            R.CONRET,
            R.IMPRET,
            R.PRORET,
            FP.NOFPRO
            FROM ((F_RET AS R
            INNER JOIN T_TER AS TR ON  TR.CODTER = R.CAJRET)
            INNER JOIN F_PRO AS FP ON FP.CODPRO = R.PRORET)
        WHERE R.CODRET  = " .$code;

        $exec = $this->conn->prepare($sql);
        $exec -> execute();
        $cuts = $exec->fetch(\PDO::FETCH_ASSOC);
        $envio = [
            "cash"=>$cash,
            "withdrawal"=>[
                "fs_id"=>$cuts['CODRET'],
                "concept"=>$cuts['CONRET'],
                "import"=>$cuts['IMPRET'],
                "providers"=>[
                    "val"=>["id"=>$cuts['PRORET'],"name"=>$cuts['NOFPRO']]
                ]
            ]
        ];
        $cellerPrinter = new PrinterController();
        $printed = $cellerPrinter->printret($envio);
        return $printed;
    }

    public function addWithdrawal(Request $request){
        $cash = $request->cash;
        $withdrawal = $request->withdrawal;
        $envio = [
            "cash"=>$cash,
            "withdrawal"=>[
                "concept"=>$withdrawal['concept'],
                "import"=>$withdrawal['import'],
                "providers"=>[
                    "val"=>["id"=>$withdrawal['providers']['val']['id'],"name"=>$withdrawal['providers']['val']['name']]
                ]
            ]
        ];
        $created = $this->addWithrawalSobrante($envio);
        return response()->json($created);
    }

    public function reprintSale(Request $request){
        $type = $request->type;
        $cash = $request->cash;

        $id = isset($request->val) ? $request->val : null;
        if($type == 1){
            $sale = "SELECT F_FAC.TIPFAC&'-'&FORMAT(F_FAC.CODFAC,'000000') AS folio ,
                Format(F_FAC.FECFAC)&' '&Format(F_FAC.HORFAC, 'hh:nn:ss') AS created_at,
                F_FAC.CLIFAC,
                F_FAC.CNOFAC,
                F_FAC.CODFAC,
                F_FAC.CPOFAC,
                F_FAC.CCPFAC,
                F_FAC.CPRFAC,
                F_FAC.NET1FAC,
                F_FAC.PIVA1FAC,
                F_FAC.IIVA1FAC,
                F_FAC.TOTFAC,
                F_FAC.EFEFAC,
                F_FAC.CAMFAC,
                F_FAC.EFSFAC,
                F_FAC.EFVFAC,
                T_DEP.NOMDEP,
                F_FAC.OB1FAC
             FROM F_FAC
            INNER JOIN T_DEP ON T_DEP.CODDEP = F_FAC.DEPFAC
            WHERE F_FAC.TIPFAC&'-'&FORMAT(F_FAC.CODFAC,'000000') = "."'".$id."'";
            if (!$sale) {
                return response()->json(['error' => 'Sale not found'], 404);
            }
        }else if($type == 2){
            $sale = "SELECT F_FAC.TIPFAC&'-'&FORMAT(F_FAC.CODFAC,'000000') AS folio ,
                Format(F_FAC.FECFAC)&' '&Format(F_FAC.HORFAC, 'hh:nn:ss') AS created_at,
                F_FAC.CLIFAC,
                F_FAC.CNOFAC,
                F_FAC.CODFAC,
                F_FAC.CPOFAC,
                F_FAC.CCPFAC,
                F_FAC.CPRFAC,
                F_FAC.NET1FAC,
                F_FAC.PIVA1FAC,
                F_FAC.IIVA1FAC,
                F_FAC.TOTFAC,
                F_FAC.EFEFAC,
                F_FAC.CAMFAC,
                F_FAC.EFSFAC,
                F_FAC.EFVFAC,
                T_DEP.NOMDEP,
                F_FAC.OB1FAC
                FROM F_FAC
                INNER JOIN T_DEP ON T_DEP.CODDEP = F_FAC.DEPFAC
                WHERE FECFAC = DATE() AND TERFAC = ".$cash['cashier']['cash']['_terminal']. " ORDER BY CODFAC DESC " ;
        }
        // return $sale;
        $exec = $this->conn->prepare($sale);
        $exec->execute();
        $factura = $exec->fetch(\PDO::FETCH_ASSOC);
        if(!$factura){
            return response()->json('No se encontro el ticket',404);
        }

        $obtProducts = "SELECT ARTLFA, DESLFA, CANLFA, PRELFA, TOTLFA FROM F_LFA WHERE TIPLFA&'-'&FORMAT(CODLFA,'000000') = "."'".$factura['folio']."'";
        $exec = $this->conn->prepare($obtProducts);
        $exec->execute();
        $products = $exec->fetchall(\PDO::FETCH_ASSOC);

        $mapedProducts = array_map(function($val) {
            return [
                "code"=>$val['ARTLFA'],
                "description"=>$val['DESLFA'],
                "pivot"=>[
                    "units"=>intval($val['CANLFA']),
                    "price"=>number_format((float)$val['PRELFA'], 2, '.', ''),
                    "total"=>number_format((float)$val['TOTLFA'], 2, '.', '')
                ]
            ];
        },$products);

        $obtPaymnets = "SELECT * FROM F_LCO WHERE TFALCO&'-'&FORMAT(CFALCO,'000000') = "."'".$factura['folio']."'";
        $exec = $this->conn->prepare($obtPaymnets);
        $exec->execute();
        $payments = $exec->fetchall(\PDO::FETCH_ASSOC);
        $exchange = $factura['CAMFAC'];

        $mapedPayments = $this->mapPaymentsFromDb($payments,$exchange);
        if (!empty($mapedPayments['VALE']['id']) && $mapedPayments['VALE']['val'] > 0) {
            $obtRet = "SELECT CODANT FROM F_ANT WHERE TDOANT&'-'&FORMAT(CDOANT,'000000') = "."'".$factura['folio']."'";
            $exec = $this->conn->prepare($obtRet);
            $exec->execute();
            $advance = $exec->fetch(\PDO::FETCH_ASSOC);
           $mapedPayments['VALE']['id']['code'] = $advance['CODANT'] ; // o 'code' si lo tienes
        }

        $order = [
            "change"=>number_format((float)$factura['CAMFAC'], 2, '.', ''),
            "client"=>[
                "id"=>intval($factura['CLIFAC']),
                "name"=>$factura['CNOFAC']
            ],
            "dependiente"=>[
                "complete_name"=>$factura['NOMDEP']
            ],
            "created_at"=>$factura['created_at'],
            "impuesto"=>number_format((float)$factura['IIVA1FAC'], 2, '.', ''),
            "iva"=>intval($factura['PIVA1FAC']),
            "subtotal"=>number_format((float)$factura['NET1FAC'], 2, '.', ''),
            "total"=>number_format((float)$factura['TOTFAC'], 2, '.', ''),
            "payments"=>$mapedPayments,
            "products"=>$mapedProducts,
            "observation"=>$factura['OB1FAC']
        ];
        $config = [
            "amount"=>0,
            "name"=>"IVA",
            "option"=>intval($factura['PIVA1FAC']) == 0 ? false : true,
            "value"=>intval($factura['PIVA1FAC']) == 0 ? false : intval($factura['PIVA1FAC'])
        ];
        $cellerPrinter = new PrinterController();
        $printed = $cellerPrinter->printck($order,$cash,$factura['folio'],$config);
        return $order;
    }

    public function mapPaymentsFromDb(array $pagosDb, float $change = 0): array {
        $payments = [
            "PFPA" => ["id" => null, "val" => 0],
            "SFPA" => ["id" => null, "val" => 0],
            "VALE" => ["id" => null, "val" => 0],
            "conditions" => [
                "createWithdrawal" => false,
                "super" => false
            ],
            "change" => number_format($change, 2, '.', '')
        ];

        // definimos los grupos de alias
        $mapAliasGroups = [
            "PFPA" => ["C30", "EFE", "TDB","TSA"],
            "SFPA" => ["C30", "EFE", "TDB", "TSA"],
            "VALE" => ["[V]"]
        ];

        foreach ($pagosDb as $pago) {
            $alias   = $pago['FPALCO'];
            $importe = (float) $pago['IMPLCO'];

            // buscar a qué grupo pertenece el alias
            $key = null;
            foreach ($mapAliasGroups as $grupo => $aliases) {
                if (in_array($alias, $aliases)) {
                    $key = $grupo;
                    break;
                }
            }

            if ($key) {
                // si es efectivo, sumamos el cambio
                if ($key === 'PFPA') {
                    $importe += $change;
                }

                // si ya existe valor, sumamos al acumulado
                if ($payments[$key]['id']) {
                    $payments[$key]['val'] += $importe;
                } else {
                    $payments[$key] = [
                        "id" => [
                            "id"    => $pago['LINLCO'],
                            "alias" => $alias,
                            "name"  => $pago['CPTLCO']
                        ],
                        "val" => $importe
                    ];
                }
            }
        }

        return $payments;
    }

    public function closeCash(Request $request){
        $bullet = $request->close;
        $billetes = array_reduce($bullet['Billetes'], function($carry, $val) {
            switch ($val['key']) {
                case 500:   $key = 'BI0ATE'; break;
                case 200:   $key = 'BI1ATE'; break;
                case 100:   $key = 'BI2ATE'; break;
                case 50:    $key = 'BI3ATE'; break;
                case 20:    $key = 'BI4ATE'; break;
                default:  $key = null;
            }
            if ($key !== null) {
                $carry[$key] = $val['val'];
            }
            return $carry;
        }, []);

        $monedas = array_reduce($bullet['Monedas'], function($carry, $val) {
            switch ($val['key']) {
                case 10:  $key = 'BI5ATE'; break;
                case 5:   $key = 'BI6ATE'; break;
                case 2:   $key = 'MO0ATE'; break;
                case 1:   $key = 'MO1ATE'; break;
                case 0.5: $key = 'MO2ATE'; break;
                default:  $key = null;
            }
            if ($key !== null) {
                $carry[$key] = $val['val'];
            }
            return $carry;
        }, []);

        $caja = $request->cash;
        $declarec = $request->total;
        $uid = $request->uid;
        $idterminal = str_pad($caja['_terminal'], 4, "0", STR_PAD_LEFT)."00".Carbon::parse($caja['cashier']['open_date'])->format('ymd');
        $formatdato = "'".$idterminal."'";
        //ingresos de efectivo
        $ingresos = "SELECT * FROM F_ING WHERE TPVIDING = ".$formatdato;
        $exec = $this->conn->prepare($ingresos);
        $exec -> execute();
        $ings = $exec->fetchall(\PDO::FETCH_ASSOC);

        $totalIngs = array_reduce($ings, function ($carry, $item) {
            return $carry + ((float) $item["IMPING"]);
        }, 0);
        //retiradas de efectivo
        $retiradas = "SELECT * FROM F_RET WHERE TPVIDRET = ".$formatdato;
        $exec = $this->conn->prepare($retiradas);
        $exec -> execute();
        $rets = $exec->fetchall(\PDO::FETCH_ASSOC);

        $totalRets = array_reduce($rets, function ($carry, $item) {
            return $carry + ((float) $item["IMPRET"]);
        }, 0);

        //vales realizados
        $vales = "SELECT * FROM F_ANT WHERE TPVIDANT = ".$formatdato;
        $exec = $this->conn->prepare($vales);
        $exec -> execute();
        $vls = $exec->fetchall(\PDO::FETCH_ASSOC);
        //totals de cobro
        $totales = "SELECT FPALCO ,CPTLCO, SUM(IMPLCO) AS IMPORTE FROM F_LCO WHERE TPVIDLCO = ".$formatdato."  GROUP BY FPALCO, CPTLCO";
        $exec = $this->conn->prepare($totales);
        $exec -> execute();
        $tots = $exec->fetchall(\PDO::FETCH_ASSOC);

        $index = array_search("EFE", array_column($tots, "FPALCO"));
        $efeImporte = $index !== false ? (float) $tots[$index]["IMPORTE"] : 0;

        //movimientos
        $movimientos = "SELECT SUM(TOTFAC) AS TOTAL, COUNT(CODFAC) AS MOVIMIENTOS FROM F_FAC WHERE TPVIDFAC = ".$formatdato;
        $exec = $this->conn->prepare($movimientos);
        $exec -> execute();
        $mov = $exec->fetch(\PDO::FETCH_ASSOC);
        //empresa
        $empresa = "SELECT DENEMP  FROM F_EMP";
        $exec = $this->conn->prepare($empresa);
        $exec -> execute();
        $emp = $exec->fetch(\PDO::FETCH_ASSOC);

        $terminal = "SELECT *  FROM T_TER WHERE CODTER = ".$caja['_terminal'];
        $exec = $this->conn->prepare($terminal);
        $exec -> execute();
        $mosTer = $exec->fetch(\PDO::FETCH_ASSOC);

        $impdc = "SELECT SUM(F.CAMFAC)  * -1 AS IMPDC  FROM F_FAC AS F  WHERE  F.ESTFAC =  0  OR   F.ESTFAC = 1 AND TPVIDFAC = ".$formatdato;
        $exec = $this->conn->prepare($impdc);
        $exec -> execute();
        $impFac = $exec->fetch(\PDO::FETCH_ASSOC);

        //total efectivo
        $efetot = (floatval($efeImporte) +  floatval($totalIngs) +   floatval($caja['cashier']['cash_start']) ) - floatval($totalRets);

        $inscut = [
            $caja['_terminal'],//terminal
            Carbon::parse($caja['cashier']['open_date'])->format('d/m/Y'),//fecha,
            $idterminal,//id de terminal
            $caja['cashier']['cash_start'],//efe inicial
            $declarec,
            // $efetot,//total efectivo
            $billetes['BI0ATE'],//500
            $billetes['BI1ATE'],//200
            $billetes['BI2ATE'],//100
            $billetes['BI3ATE'],//50
            $billetes['BI4ATE'],//20
            $monedas['BI5ATE'],//10
            $monedas['BI6ATE'],//5
            $monedas['MO0ATE'],//2
            $monedas['MO1ATE'],//1
            $monedas['MO2ATE'],//.50
            1,//aperturas depues de cierre
            Carbon::parse($caja['cashier']['open_date'])->format('H:i:s'),//hora apertura
            now()->format('H:i:s')//hora cierre
        ];
        $colums =  ["TERATE","FECATE","IDEATE","SINATE","EFEATE","BI0ATE","BI1ATE","BI2ATE","BI3ATE","BI4ATE","BI5ATE","BI6ATE","MO0ATE","MO1ATE","MO2ATE","NCIATE","HOAATE","HOCATE"];
        $impcol = implode(",",$colums);
        $signos = implode(",",array_fill(0, count($colums),'?'));
        $sql = "INSERT INTO T_ATE ($impcol) VALUES ($signos)";//se crea el query para insertar en la tabla
        $exec = $this->conn->prepare($sql);
        $res = $exec->execute($inscut);
        if($res){
            $cierre = "UPDATE T_TER SET ESTTER = 0 WHERE CODTER = ".$caja['_terminal'];
            $exec = $this->conn->prepare($cierre);
            $cirr = $exec->execute();
            $header = [
                "print"=>$caja['cashier']['print']['ip_address'],
                "emp"=>$emp,
                "corte"=>[
                    "DESTER"=>$mosTer['DESTER'],
                    "FECHA"=>Carbon::parse($caja['cashier']['open_date'])->format('d/m/Y'),
                    "HORA"=>now()->format('H:i:s'),
                    "SINATE"=>$caja['cashier']['cash_start'],
                    "VENTASEFE"=>$efeImporte,
                    "INGRESOS"=>$totalIngs,
                    "RETIRADAS"=>$totalRets,
                    "EFEATE"=>$declarec,
                    "IMPDC"=>$impFac['IMPDC'],
                    "MO0ATE"=>$monedas['MO0ATE'],
                    "MO1ATE"=>$monedas['MO1ATE'],
                    "MO2ATE"=>$monedas['MO2ATE'],
                    "MO3ATE"=>0,
                    "MO4ATE"=>0,
                    "MO5ATE"=>0,
                    "MO6ATE"=>0,
                    "MO7ATE"=>0,
                    "BI6ATE"=> $monedas['BI6ATE'],
                    "BI5ATE"=> $monedas['BI5ATE'],
                    "BI4ATE"=> $billetes['BI4ATE'],
                    "BI3ATE"=> $billetes['BI3ATE'],
                    "BI2ATE"=> $billetes['BI2ATE'],
                    "BI1ATE"=> $billetes['BI1ATE'],
                    "BI0ATE"=> $billetes['BI0ATE'],
                ],
                "totalEfe"=>$efetot,
                "ingresos"=>$ings,
                "retiradas"=>$rets,
                "vales"=>$vls,
                "totales"=>$tots,
                "movimientos"=>$mov,
            ];
            $cellerPrinter = new PrinterController();
            $printed = $cellerPrinter->printCut($header);
            $printed = $cellerPrinter->printCut($header);
            return response()->json($header,200);
        }else{
            return response()->json('No se pudo crear el cierre de la terminal',401);
        }
    }

    public function openCash(Request $request){
        $cashier = $request->all();
        $apertura = "UPDATE T_TER SET ESTTER = 1, EFETER = 0,FECTER = DATE(), SINTER = ".$cashier['cashier']['cash_start']." , HOATER = TiME() WHERE CODTER = ".$cashier['_terminal'];
        $exec = $this->conn->prepare($apertura);
        $result = $exec->execute();
        if ($result) {
            return true;
        }else{
            return false;
        }
    }

    public function getCredits(Request $request){
        $date = $request->date;
        $sales = "SELECT
        F_FAC.CLIFAC AS _client,
        F_FAC.CNOFAC AS cliente,
        F_FAC.TIPFAC & '-' & Format(F_FAC.CODFAC, '000000') AS ticket,
        Format(F_FAC.FECFAC, 'yyyy-mm-dd') & ' ' & Format(F_FAC.HORFAC, 'hh:nn:ss') AS created_at,
        Round(F_LCO.IMPLCO, 2) AS total
        FROM F_LCO
        INNER JOIN F_FAC ON F_FAC.TIPFAC & '-' & F_FAC.CODFAC = F_LCO.TFALCO & '-' & F_LCO.CFALCO
        WHERE F_LCO.FPALCO = 'C30' AND F_LCO.FECLCO = "."#".$date."#";
        $exec = $this->conn->prepare($sales);
        $exec -> execute();
        $credits = $exec->fetchall(\PDO::FETCH_ASSOC);
        return $credits;
    }

    public function addCredit(Request $request){
        $store = $request->store;
        $client = $request->_client;
        $ticket = $request->ticket;
        $total = $request->total;
        $date = $request->created_at;

        $termi = "SELECT * FROM T_TER INNER JOIN T_DOC ON T_DOC.CODDOC = T_TER.DOCTER WHERE CODTER = ". $cash['cashier']['cash']['_terminal'];
        $exec = $this->conn->prepare($termi);
        $exec->execute();
        $codter = $exec->fetch(\PDO::FETCH_ASSOC);
        $nomter = $codter['DESTER'];
        $idterminal = str_pad($codter['CODTER'], 4, "0", STR_PAD_LEFT)."00".Carbon::parse($cash['cashier']['open_date'])->format('ymd');

        $codmax = "SELECT MAX(CODFAC) as maxi FROM F_FAC WHERE TIPFAC = "."'".$codter['TIPDOC']."'";
        $exec = $this->conn->prepare($codmax);
        $exec->execute();
        $max = $exec->fetch(\PDO::FETCH_ASSOC);
        $codigo = $max['maxi'] + 1;
    }

    public function getTicket(Request $request){
        $folio = $request->folio;
        // return $folio;
        $query = "SELECT
        TIPLFA & '-' & Format(CODLFA, '000000') AS ticket,
        ARTLFA as code,
        CANLFA as req
        FROM F_LFA
        WHERE TIPLFA&'-'&Format(CODLFA, '000000') = ?";
        $exec = $this->conn->prepare($query);
        $exec->execute([$folio]);
        $rows = $exec->fetchAll(\PDO::FETCH_ASSOC);
        if(count($rows)==0){
            return response()->json(["msg" => "El folio no existe o no tiene productos"]);
        }
        return response()->json(["products" => $rows]);
    }
}
