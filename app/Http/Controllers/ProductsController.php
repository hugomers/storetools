<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class ProductsController extends Controller
{
    public function __construct(){
        $access = env("ACCESS");//conexion a access de sucursal
        if(file_exists($access)){
        try{  $this->conn  = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
            }catch(\PDOException $e){ die($e->getMessage()); }
        }else{ die("$access no es un origen de datos valido."); }
    }

    public function productRegis(Request $request){
        $insertados = [
            "goals"=>[],
            "fails"=>[]
        ];
        $actualizados = [
            "goals"=>[],
            "fails"=>[]
        ];
        $fail = [
            "categoria"=>[],
            "codigo_barras"=>[],
            "codigo_corto"=>[]
        ];
        $stor = [
            "goals"=>[],
            "fails"=>[]
        ];
        $mysql = [
            "goals"=>[
            "insertados"=>[],
            "acutalizados"=>[]
            ],
            "fail"=>[
                "insertados"=>[],
                "actualizados"=>[]
            ]
            ];

        $almacenes ="SELECT CODALM FROM F_ALM";
        $exec = $this->conn->prepare($almacenes);
        $exec -> execute();
        $fil=$exec->fetchall(\PDO::FETCH_ASSOC);

        $tari ="SELECT CODTAR FROM F_TAR";
        $exec = $this->conn->prepare($tari);
        $exec -> execute();
        $filtar=$exec->fetchall(\PDO::FETCH_ASSOC);
        $suc  = $request->sucursal;
        $tienda = isset($suc) ?  $suc : "all" ;
        if($tienda === "all"){
            $sucursales = DB::connection('vizapi')->table('workpoints')->where('active',1)->where('_type',2)->whereNotIn('id',[18])->get();
            foreach($sucursales as $sucursal){
                $tiendas [] =  [
                    "dominio"=>$sucursal->dominio,
                    "alias"=>$sucursal->alias
                ];
            }
        }else{
            $sucursales =  DB::connection('vizapi')->table('workpoints')->whereIn('id',$tienda)->where('active',1)->where('_type',2)->get();
            foreach($sucursales as $sucursal){
                $tiendas [] =  [
                    "dominio"=>$sucursal->dominio,
                    "alias"=>$sucursal->alias
                ];
            }
        }
        $products = $request->productos;
        foreach($products as $product){
            $codigo = trim($product['CODIGO']);//validar
            $ean = isset($product['CB']) ? trim($product['CB']) : null;//validar
            $fam = trim($product['FAMILIA']);//validar
            $deslarga = trim($product["DESCRIPCION"]);
            $desgen = trim(substr($product["DESCRIPCION"],0,50));
            $deset = trim(substr($product["DESCRIPCION"],0,30));
            $destic = trim(substr($product["DESCRIPCION"],0,20));
            $pro = $product['PROVEEDOR'];//validar
            $ref = trim($product['REFERENCIA']);
            $fab = trim($product['FABRICANTE']);
            $pxc = $product['PXC'];
            $categoria = trim($product['CATEGORIA']);//validar
            $umc = trim($product['UNIDA MED COMPRA']);
            $prores = trim($product['PRO RES']);
            $cco = $product['CODIGO CORTO'];//validar
            $cost = isset($product['COSTO']) ? $product['COSTO'] : 0;
            $luces = isset($product['#LUCES']) ? $product['#LUCES'] : null;
            $menav = isset($product['MEDIDAS NAV']) ? $product['MEDIDAS NAV'] : null;
            $date_format = date("d/m/Y");

            $caty = DB::connection('vizapi')->table('product_categories as PC')->join('product_categories as PF', 'PF.id', '=','PC.root')->where('PC.alias', $categoria)->where('PF.alias', $fam)->value('PC.id');
            if($caty){
                $units = DB::connection('vizapi')->table('product_units')->where('name',$umc)->value('id');
                $esispro = DB::connection('vizapi')->table('providers')->where('id',$pro)->value('id');
                if($esispro){
                    $provider = $esispro;
                }else{
                    $provider = 67;
                }
                $existcco = "SELECT CODART FROM F_ART WHERE CCOART =".$cco;
                $exec = $this->conn->prepare($existcco);
                $exec->execute();
                $ccos = $exec->fetch(\PDO::FETCH_ASSOC);
                $eans = null;
                if(!$ccos){
                    if(!is_null($ean)){
                        $existean = "SELECT * FROM (SELECT CODART AS CODIGO, EANART AS EAN FROM F_ART UNION SELECT ARTEAN AS CODIGO, EANEAN AS EAN FROM F_EAN)  AS EANS WHERE EAN = "."'".$ean."'";
                        // return $existean;
                        $exec = $this->conn->prepare($existean);
                        $exec->execute();
                        $eans = $exec->fetch(\PDO::FETCH_ASSOC);
                    }
                    if(!$eans){
                        $existpro = "SELECT * FROM F_ART WHERE CODART = "."'".$codigo."'";
                        $exec = $this->conn->prepare($existpro);
                        $exec->execute();
                        $pros = $exec->fetch(\PDO::FETCH_ASSOC);
                        if($pros){
                            $updpro = "UPDATE F_ART SET EANART = "."'".$ean."'"." , PCOART = ".$cost." , FUMART = ".$date_format." WHERE CODART = "."'".$codigo."'";
                            $exec = $this->conn->prepare($existpro);
                            $updspro = $exec->execute();
                            if($updspro){
                                $actualizados['goals'][]="El producto ".$codigo." se actualizo correctamente";
                            }else{
                                $actualizados['fails'][]="El producto ".$codigo." no se actualizo correctamente";
                            }
                        }else{
                            $articulofs = [
                                $codigo,
                                $ean,
                                $fam,
                                $desgen,
                                $deset,
                                $destic,
                                $deslarga,
                                $pxc,
                                $cco,
                                $pro,
                                $ref,
                                $fab,
                                $cost,
                                $date_format,
                                $date_format,
                                $pxc,
                                1,
                                1,
                                1,
                                $categoria,
                                $luces,
                                $umc,
                                $prores,
                                $menav,
                                0,
                                "Peso",
                            ];
                            $inspro = "INSERT INTO F_ART (CODART,EANART,FAMART,DESART,DEEART,DETART,DLAART,EQUART,CCOART,PHAART,REFART,FTEART,PCOART,FALART,FUMART,UPPART,CANART,CAEART,UMEART,CP1ART,CP2ART,CP3ART,CP4ART,CP5ART,MPTART,UEQART) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
                            $exec = $this->conn->prepare($inspro);
                            $insspro = $exec->execute($articulofs);
                            if($insspro){
                                foreach($fil as $alm){
                                    $insertsto = "INSERT INTO F_STO (ARTSTO,ALMSTO,MINSTO,MAXSTO,ACTSTO,DISSTO) VALUES (?,?,?,?,?,?) ";
                                    $exec = $this->conn->prepare($insertsto);
                                    $exec -> execute([$codigo,$alm['CODALM'],0,0,0,0]);
                                }
                                foreach($filtar as $price){
                                    $insertlta = "INSERT INTO F_LTA (TARLTA,ARTLTA,MARLTA,PRELTA) VALUES (?,?,?,?) ";
                                    $exec = $this->conn->prepare($insertlta);
                                    $exec -> execute([$price['CODTAR'],$codigo,0,0]);
                                }
                                $insertados['goals'][]="El producto ".$codigo." se Inserto correctamente";
                            }else{
                                $insertados['fails'][]="El producto ".$codigo." no se Inserto correctamente";
                            }
                        }
                        $promsexist = DB::connection('vizapi')->table('products')->where('code',$codigo)->value('id');
                        if($promsexist){
                            $updms = [
                                'name'=>$cco,
                                'description'=>$deslarga,
                                'label'=>$deset,
                                'reference'=>$ref,
                                'pieces'=>$pxc,
                                '_category'=>$caty,
                                '_status'=>1,
                                '_unit'=>$units,
                                '_provider'=>$provider,
                                'updated_at'=>now(),
                                'cost'=>$cost,
                                'barcode'=>$ean,
                                'dimensions'=>json_encode(["length"=>'',"height"=>'',"width"=>''])
                            ];

                            $updmys = DB::connection('vizapi')->table('products')->where('id',$promsexist)->update($updms);
                            if($updmys){
                                $mysql['goals']['actualizados'][] = "Se actualizo el codigo ".$codigo." correctamente ".$updmys;
                            }else{
                                $mysql['fails']['actualizados'][] = "No se pudo actualizar el codigo ".$codigo." correctamente";
                            }
                        }else{
                            $articulosms =[
                                'code'=>$codigo,
                                'name'=>$cco,
                                'description'=>$deslarga,
                                'label'=>$deset,
                                'reference'=>$ref,
                                'pieces'=>$pxc,
                                '_category'=>$caty,
                                '_status'=>1,
                                '_unit'=>$units,
                                '_provider'=>$provider,
                                'created_at'=>now(),
                                'updated_at'=>now(),
                                'cost'=>$cost,
                                'barcode'=>$ean,
                                'refillable'=>1,
                            ];
                            $insms = DB::connection('vizapi')->table('products')->insert($articulosms);
                            if($insms){
                                $mysql['goals']['insertados'][] = "Se inserto el codigo ".$codigo." correctamente";
                            }else{
                                $mysql['fails']['insertados'][] = "No se pudo insertar el codigo ".$codigo." correctamente";
                            }
                        }
                    }else{
                        $fail["codigo_barras"][] = "El codigo de barras ".$ean." ya esta en uso en el modelo ".$eans['CODIGO'];
                    }
                }else{
                    $fail["codigo_corto"][] = "El codigo corto ".$cco." ya esta asignado al modelo ".$ccos['CODART'];
                }
            }else{
                $fail["categoria"][] = "La categoria ".$categoria." no existe en la familia ".$fam." en el codigo ".$codigo;
            }
        }
        $stores = $tiendas;//se obtienen sucursales de mysql
        foreach($stores as $store){//inicio de foreach de sucursales
            $url = $store['dominio']."/storetools/public/api/Stores/regisproduct";//se optiene el inicio del dominio de la sucursal
            $ch = curl_init($url);//inicio de curl
            $data = json_encode(["productos" => $products]);//se codifica el arreglo de los proveedores
            //inicio de opciones de curl
            curl_setopt($ch,CURLOPT_POSTFIELDS,$data);//se envia por metodo post
            curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
            //fin de opciones e curl
            $exec = curl_exec($ch);//se executa el curl
            $exc = json_decode($exec);//se decodifican los datos decodificados
            if(is_null($exc)){//si me regresa un null
                $stor['fails'][] =["sucursal"=>$store['alias'], "mssg"=>$exc];
            }else{
                // $stor['goals'][] = $store->alias." cambios hechos";//de lo contrario se almacenan en sucursales
                $stor['goals'][] =["sucursal"=>$store['alias'], "mssg"=>$exc];;//la sucursal se almacena en sucursales fallidas
            }
            curl_close($ch);//cirre de curl
        }//fin de foreach de sucursales


        $res = [
            "insertados"=>$insertados,
            "actualizados"=>$actualizados,
            "fails"=>$fail,
            "stores"=>$stor,
            "mysql"=>$mysql
        ];
        return response()->json($res);
    }

    public function regisProstores(Request $request){
        $insertados = [
            "goals"=>[],
            "fails"=>[]
        ];
        $actualizados = [
            "goals"=>[],
            "fails"=>[]
        ];
        $fail = [
            "categoria"=>[],
            "codigo_barras"=>[],
            "codigo_corto"=>[]
        ];

        $almacenes ="SELECT CODALM FROM F_ALM";
        $exec = $this->conn->prepare($almacenes);
        $exec -> execute();
        $fil=$exec->fetchall(\PDO::FETCH_ASSOC);

        $tari ="SELECT CODTAR FROM F_TAR";
        $exec = $this->conn->prepare($tari);
        $exec -> execute();
        $filtar=$exec->fetchall(\PDO::FETCH_ASSOC);

        $products = $request->productos;
        foreach($products as $product){
            $codigo = trim($product['CODIGO']);//validar
            $ean = isset($product['CB']) ? trim($product['CB']) : null;//validar
            $fam = trim($product['FAMILIA']);//validar
            $deslarga = trim($product["DESCRIPCION"]);
            $desgen = trim(substr($product["DESCRIPCION"],0,50));
            $deset = trim(substr($product["DESCRIPCION"],0,30));
            $destic = trim(substr($product["DESCRIPCION"],0,20));
            $pro = $product['PROVEEDOR'];//validar
            $ref = trim($product['REFERENCIA']);
            $fab = trim($product['FABRICANTE']);
            $pxc = $product['PXC'];
            $categoria = trim($product['CATEGORIA']);//validar
            $umc = trim($product['UNIDA MED COMPRA']);
            $prores = trim($product['PRO RES']);
            $cco = $product['CODIGO CORTO'];//validar
            $cost = isset($product['COSTO']) ? $product['COSTO'] : 0;
            $luces = isset($product['#LUCES']) ? $product['#LUCES'] : null;
            $menav = isset($product['MEDIDAS NAV']) ? $product['MEDIDAS NAV'] : null;
            $date_format = date("d/m/Y");

            $caty = DB::connection('vizapi')->table('product_categories as PC')->join('product_categories as PF', 'PF.id', '=','PC.root')->where('PC.alias', $categoria)->where('PF.alias', $fam)->value('PC.id');
            if($caty){
                $existcco = "SELECT CODART FROM F_ART WHERE CCOART =".$cco;
                $exec = $this->conn->prepare($existcco);
                $exec->execute();
                $ccos = $exec->fetch(\PDO::FETCH_ASSOC);
                $eans = null;
                if(!$ccos){
                    if(!is_null($ean)){
                    $existean = "SELECT * FROM (SELECT CODART AS CODIGO, EANART AS EAN FROM F_ART UNION SELECT ARTEAN AS CODIGO, EANEAN AS EAN FROM F_EAN)  AS EANS WHERE EAN = "."'".$ean."'";
                    $exec = $this->conn->prepare($existean);
                    $exec->execute();
                    $eans = $exec->fetch(\PDO::FETCH_ASSOC);
                    }
                    if(!$eans){
                        $existpro = "SELECT * FROM F_ART WHERE CODART = "."'".$codigo."'";
                        $exec = $this->conn->prepare($existpro);
                        $exec->execute();
                        $pros = $exec->fetch(\PDO::FETCH_ASSOC);
                        if($pros){
                            $updpro = "UPDATE F_ART SET EANART = "."'".$ean."'"." , PCOART = ".$cost." , FUMART = ".$date_format." WHERE CODART = "."'".$codigo."'";
                            $exec = $this->conn->prepare($existpro);
                            $updspro = $exec->execute();
                            if($updspro){
                                $actualizados['goals'][]="El producto ".$codigo." se actualizo correctamente";
                            }else{
                                $actualizados['fails'][]="El producto ".$codigo." no se actualizo correctamente";
                            }
                        }else{
                            $articulofs = [
                                $codigo,
                                $ean,
                                $fam,
                                $desgen,
                                $deset,
                                $destic,
                                $deslarga,
                                $pxc,
                                $cco,
                                $pro,
                                $ref,
                                $fab,
                                $cost,
                                $date_format,
                                $date_format,
                                $pxc,
                                1,
                                1,
                                1,
                                $categoria,
                                $luces,
                                $umc,
                                $prores,
                                $menav,
                                0,
                                "Peso",
                            ];
                            $inspro = "INSERT INTO F_ART (CODART,EANART,FAMART,DESART,DEEART,DETART,DLAART,EQUART,CCOART,PHAART,REFART,FTEART,PCOART,FALART,FUMART,UPPART,CANART,CAEART,UMEART,CP1ART,CP2ART,CP3ART,CP4ART,CP5ART,MPTART,UEQART) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
                            $exec = $this->conn->prepare($inspro);
                            $insspro = $exec->execute($articulofs);
                            if($insspro){
                                foreach($fil as $alm){
                                    $insertsto = "INSERT INTO F_STO (ARTSTO,ALMSTO,MINSTO,MAXSTO,ACTSTO,DISSTO) VALUES (?,?,?,?,?,?) ";
                                    $exec = $this->conn->prepare($insertsto);
                                    $exec -> execute([$codigo,$alm['CODALM'],0,0,0,0]);
                                }
                                foreach($filtar as $price){
                                    $insertlta = "INSERT INTO F_LTA (TARLTA,ARTLTA,MARLTA,PRELTA) VALUES (?,?,?,?) ";
                                    $exec = $this->conn->prepare($insertlta);
                                    $exec -> execute([$price['CODTAR'],$codigo,0,0]);
                                }
                                $insertados['goals'][]="El producto ".$codigo." se Inserto correctamente";
                            }else{
                                $insertados['fails'][]="El producto ".$codigo." no se Inserto correctamente";
                            }
                        }
                        $promsexist = DB::connection('vizapi')->table('products')->where('code',$codigo)->value('id');
                    }else{
                        $fail["codigo_barras"][] = "El codigo de barras ".$ean." ya esta en uso en el modelo ".$eans['CODIGO'];
                    }
                }else{
                    $fail["codigo_corto"][] = "El codigo corto ".$cco." ya esta asignado al modelo ".$ccos['CODART'];
                }
            }else{
                $fail["categoria"][] = "La categoria ".$categoria." no existe en la familia ".$fam." en el codigo ".$codigo;
            }
        }

        $res = [
            "insertados"=>$insertados,
            "actualizados"=>$actualizados,
            "fails"=>$fail
        ];
        return response()->json($res);
    }

    public function regisprice(Request $request){
        $date_format = date("d/m/Y");
        $date_time = date("Y-m-d H:m:s");
        $actualizados = [
            "goals"=>[],
            "fails"=>[]
        ];
        $stor = [
            "goals"=>[],
            "fails"=>[]
        ];
        $mysql = [
            "goals"=>[
                "actualizados"=>[]
            ],
            "fail"=>[
                "actualizados"=>[]
            ]
        ];
        $pub = [
            "goals"=>[],
            "fails"=>[]
        ];
        $suc  = $request->sucursal;
        $tienda = isset($suc) ?  $suc : "all" ;
        if($tienda === "all"){
            $sucursales = DB::connection('vizapi')->table('workpoints')->where('active',1)->where('_type',2)->whereNotIn('id',[18])->get();
            foreach($sucursales as $sucursal){
                $tiendas [] =  [
                    "dominio"=>$sucursal->dominio,
                    "alias"=>$sucursal->alias
                ];
            }
        }else{
            $sucursales =  DB::connection('vizapi')->table('workpoints')->whereIn('id',$tienda)->where('active',1)->where('_type',2)->get();
            foreach($sucursales as $sucursal){
                $tiendas [] =  [
                    "dominio"=>$sucursal->dominio,
                    "alias"=>$sucursal->alias
                ];
            }
        }
        $prices = $request->prices;
        foreach($prices as $price){
            $codigo = $price['MODELO'];
            $vercod = "SELECT PCOART, PRELTA FROM F_ART INNER JOIN F_LTA ON F_ART.CODART = F_LTA.ARTLTA WHERE TARLTA = 7 AND CODART = "."'".$codigo."'";
            $exec = $this->conn->prepare($vercod);
            $exec->execute();
            $codver = $exec->fetch(\PDO::FETCH_ASSOC);
            if($codver){
                $costo = isset($price['COSTO']) ? round($price['COSTO'],2): intval($codver['PCOART']) ;
                $aaa = isset($price['AAA']) ? round($price['AAA'],2) : intval($codver['PRELTA']);
                $centro = round($price['CENTRO'],0);
                $especial = round($price['ESPECIAL'],0);
                $caja = round($price['CAJA'],0);
                $docena = round($price['DOCENA'],0);
                $mayoreo = round($price['MAYOREO'],0);
                if(isset($price['MENUDEO'])){
                    $menudeo = round($price['MENUDEO'],0);
                }else if($mayoreo == $centro){
                    $menudeo = $caja;
                }elseif(($mayoreo >= 0) && ($mayoreo <= 49)){
                    $menudeo = $mayoreo + 5;
                }elseif(($mayoreo >= 50) && ($mayoreo <= 99)){
                    $menudeo = $mayoreo + 10;
                }elseif(($mayoreo >= 100) && ($mayoreo <= 499)){
                    $menudeo = $mayoreo + 20;
                }elseif(($mayoreo >= 500) && ($mayoreo <= 999)){
                    $menudeo = $mayoreo + 50;
                }elseif($mayoreo >= 1000){
                    $menudeo =  $mayoreo + 100;
                }
                if($costo <= $aaa){
                    if($aaa <= $centro){
                        if($centro <= $especial){
                            if($especial <= $caja){
                                if($caja <= $docena){
                                    if($docena <= $mayoreo){
                                        if($mayoreo <= $menudeo){
                                            $costoupd = $this->conn->prepare("UPDATE F_ART SET PCOART = ?, FUMART = ? WHERE CODART = ?")->execute([$costo,$date_format,$codigo]);
                                            $aaaupd = $this->conn->prepare("UPDATE F_LTA SET PRELTA = ? WHERE ARTLTA = ? AND TARLTA = ?")->execute([$aaa,$codigo,7]);
                                            $centroupd = $this->conn->prepare("UPDATE F_LTA SET PRELTA = ? WHERE ARTLTA = ? AND TARLTA = ?")->execute([$centro,$codigo,6]);
                                            $especiaupd = $this->conn->prepare("UPDATE F_LTA SET PRELTA = ? WHERE ARTLTA = ? AND TARLTA = ?")->execute([$especial,$codigo,5]);
                                            $cajaupd = $this->conn->prepare("UPDATE F_LTA SET PRELTA = ? WHERE ARTLTA = ? AND TARLTA = ?")->execute([$caja,$codigo,4]);
                                            $docenaupd = $this->conn->prepare("UPDATE F_LTA SET PRELTA = ? WHERE ARTLTA = ? AND TARLTA = ?")->execute([$docena,$codigo,3]);
                                            $mayoreoupd = $this->conn->prepare("UPDATE F_LTA SET PRELTA = ? WHERE ARTLTA = ? AND TARLTA = ?")->execute([$mayoreo,$codigo,2]);
                                            $menudeoupd = $this->conn->prepare("UPDATE F_LTA SET PRELTA = ? WHERE ARTLTA = ? AND TARLTA = ?")->execute([$menudeo,$codigo,1]);
                                            if($costoupd && $aaaupd && $centroupd && $especiaupd && $cajaupd && $docenaupd && $mayoreoupd && $menudeoupd){
                                                $actualizados['goals']= ['product'=>$codigo,'prices'=>['factusol' => 7]];
                                            }else{
                                                $actualizados['fails']= ['product'=>$codigo,'prices'=>['factusol' => 0]];
                                            }
                                            $cosms = DB::connection('vizapi')->table('products')->where('code',$codigo)->update(['cost'=>$costo,'updated_at'=>$date_time]);
                                            $aaams = DB::connection('vizapi')->table('product_prices as PP')->join('products as P','P.id','PP._product')->where('P.code',$codigo)->where('PP._type',7)->update(['PP.price'=>$aaa]);
                                            $centroms = DB::connection('vizapi')->table('product_prices as PP')->join('products as P','P.id','PP._product')->where('P.code',$codigo)->where('PP._type',6)->update(['PP.price'=>$centro]);
                                            $especialms = DB::connection('vizapi')->table('product_prices as PP')->join('products as P','P.id','PP._product')->where('P.code',$codigo)->where('PP._type',5)->update(['PP.price'=>$especial]);
                                            $cajams = DB::connection('vizapi')->table('product_prices as PP')->join('products as P','P.id','PP._product')->where('P.code',$codigo)->where('PP._type',4)->update(['PP.price'=>$caja]);
                                            $docenams = DB::connection('vizapi')->table('product_prices as PP')->join('products as P','P.id','PP._product')->where('P.code',$codigo)->where('PP._type',3)->update(['PP.price'=>$docena]);
                                            $mayoreoms = DB::connection('vizapi')->table('product_prices as PP')->join('products as P','P.id','PP._product')->where('P.code',$codigo)->where('PP._type',2)->update(['PP.price'=>$mayoreo]);
                                            $menudeoms = DB::connection('vizapi')->table('product_prices as PP')->join('products as P','P.id','PP._product')->where('P.code',$codigo)->where('PP._type',1)->update(['PP.price'=>$menudeo]);
                                            if($cosms || $aaams || $centroms || $especialms || $cajams || $docenams || $mayoreoms || $menudeoms){
                                                $mysql['goals']['actualizados']= ['product'=>$codigo,'prices'=>['mysql' => 7]];
                                            }else{
                                                $mysql['fail']['actualizados']= ['product'=>$codigo,'prices'=>['mysql' => 0]];
                                            }
                                            $prduct_prices [] = [
                                                "MODELO"=>$codigo,
                                                "COSTO"=>$aaa,
                                                "CENTRO"=>$centro,
                                                "ESPECIAL"=>$especial,
                                                "CAJA"=>$caja,
                                                "DOCENA"=>$docena,
                                                "MAYOREO"=>$mayoreo,
                                                "MENUDEO"=>$menudeo,
                                            ];
                                        }else{$actualizados['fails'][]= $codigo." precio Mayoreo mayor que Menudeo";}
                                    }else{$actualizados['fails'][]= $codigo." precio Docena mayor que Mayoreo";}
                                }else{$actualizados['fails'][]= $codigo." precio Caja mayor que Docena";}
                            }else{$actualizados['fails'][]= $codigo." precio Especia mayor que Caja";}
                        }else{$actualizados['fails'][]= $codigo." precio Centro mayor que Especial";}
                    }else{$actualizados['fails'][]= $codigo." precio AAA mayor que Centro";}
                }else{$actualizados['fails'][]= $codigo." precio Costo mayor que AAA";}
            }else{$actualizados['fails'][] = "El codigo ".$codigo." No existe";}
        }

        $stores = $tiendas;//se obtienen sucursales de mysql
        foreach($stores as $store){//inicio de foreach de sucursales
            $url = $store['dominio']."/storetools/public/api/Stores/regispricesproduct";//se optiene el inicio del dominio de la sucursal
            $ch = curl_init($url);//inicio de curl
            $data = json_encode(["prices" => $prduct_prices]);//se codifica el arreglo de los proveedores
            //inicio de opciones de curl
            curl_setopt($ch,CURLOPT_POSTFIELDS,$data);//se envia por metodo post
            curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
            //fin de opciones e curl
            $exec = curl_exec($ch);//se executa el curl
            $exc = json_decode($exec);//se decodifican los datos decodificados
            if(is_null($exc)){//si me regresa un null
                $stor['fails'][] =["sucursal"=>$store['alias'], "mssg"=>$exc];
            }else{
                // $stor['goals'][] = $store->alias." cambios hechos";//de lo contrario se almacenan en sucursales
                $stor['goals'][] =["sucursal"=>$store['alias'], "mssg"=>$exc];;//la sucursal se almacena en sucursales fallidas
            }
            curl_close($ch);//cirre de curl
        }//fin de foreach de sucursales

        $puebla = DB::connection('vizapi')->table('workpoints')->where('id',18)->first();
        $urlpub = $puebla->dominio."/storetools/public/api/Stores/regispricespub";//se optiene el inicio del dominio de la sucursal
        $sucpub = curl_init($urlpub);//inicio de curl
        $pripub = json_encode(["prices" => $prices]);//se codifica el arreglo de los proveedores
        //inicio de opciones de curl
        curl_setopt($sucpub,CURLOPT_POSTFIELDS,$pripub);//se envia por metodo post
        curl_setopt($sucpub,CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($sucpub, CURLOPT_HEADER, 0);
        curl_setopt($sucpub, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($sucpub, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($sucpub, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        //fin de opciones e curl
        $cuex = curl_exec($sucpub);//se executa el curl
        $filpub = json_decode($cuex);//se decodifican los datos decodificados
        if(is_null($filpub)){//si me regresa un null
            $pub['fails'][] =["sucursal"=>$puebla->alias, "mssg"=>$filpub];
        }else{
            // $stor['goals'][] = $store->alias." cambios hechos";//de lo contrario se almacenan en sucursales
            $pub['goals'][] =["sucursal"=>$puebla->alias, "mssg"=>$filpub];;//la sucursal se almacena en sucursales fallidas
        }
        curl_close($sucpub);//cirre de curl


        $res = [
            "actualizados"=>$actualizados,
            "mysql"=>$mysql,
            "stores"=>$stor,
            "storefor"=>$pub,
        ];
        return response()->json($res);
    }

    public function regispricesstores(Request $request){
        $date_format = date("d/m/Y");
        $date_time = date("Y-m-d H:m:s");
        $actualizados = [
            "goals"=>[],
            "fails"=>[]
        ];
        $prices = $request->prices;
        foreach($prices as $price){
            $codigo = $price['MODELO'];
            $vercod = "SELECT PCOART FROM F_ART WHERE  CODART = "."'".$codigo."'";
            $exec = $this->conn->prepare($vercod);
            $exec->execute();
            $codver = $exec->fetch(\PDO::FETCH_ASSOC);
            if($codver){
                $costo = isset($price['COSTO']) ? round($price['COSTO'],2): intval($codver['PCOART']) ;
                $centro = round($price['CENTRO'],0);
                $especial = round($price['ESPECIAL'],0);
                $caja = round($price['CAJA'],0);
                $docena = round($price['DOCENA'],0);
                $mayoreo = round($price['MAYOREO'],0);
                if(isset($price['MENUDEO'])){
                    $menudeo = round($price['MENUDEO'],0);
                }else if($mayoreo == $centro){
                    $menudeo = $caja;
                }elseif(($mayoreo >= 0) && ($mayoreo <= 49)){
                    $menudeo = $mayoreo + 5;
                }elseif(($mayoreo >= 50) && ($mayoreo <= 99)){
                    $menudeo = $mayoreo + 10;
                }elseif(($mayoreo >= 100) && ($mayoreo <= 499)){
                    $menudeo = $mayoreo + 20;
                }elseif(($mayoreo >= 500) && ($mayoreo <= 999)){
                    $menudeo = $mayoreo + 50;
                }elseif($mayoreo >= 1000){
                    $menudeo =  $mayoreo + 100;
                }
                    if($costo <= $centro){
                        if($centro <= $especial){
                            if($especial <= $caja){
                                if($caja <= $docena){
                                    if($docena <= $mayoreo){
                                        if($mayoreo <= $menudeo){
                                            $costoupd = $this->conn->prepare("UPDATE F_ART SET PCOART = ?, FUMART = ? WHERE CODART = ?")->execute([$costo,$date_format,$codigo]);
                                            $centroupd = $this->conn->prepare("UPDATE F_LTA SET PRELTA = ? WHERE ARTLTA = ? AND TARLTA = ?")->execute([$centro,$codigo,6]);
                                            $especiaupd = $this->conn->prepare("UPDATE F_LTA SET PRELTA = ? WHERE ARTLTA = ? AND TARLTA = ?")->execute([$especial,$codigo,5]);
                                            $cajaupd = $this->conn->prepare("UPDATE F_LTA SET PRELTA = ? WHERE ARTLTA = ? AND TARLTA = ?")->execute([$caja,$codigo,4]);
                                            $docenaupd = $this->conn->prepare("UPDATE F_LTA SET PRELTA = ? WHERE ARTLTA = ? AND TARLTA = ?")->execute([$docena,$codigo,3]);
                                            $mayoreoupd = $this->conn->prepare("UPDATE F_LTA SET PRELTA = ? WHERE ARTLTA = ? AND TARLTA = ?")->execute([$mayoreo,$codigo,2]);
                                            $menudeoupd = $this->conn->prepare("UPDATE F_LTA SET PRELTA = ? WHERE ARTLTA = ? AND TARLTA = ?")->execute([$menudeo,$codigo,1]);
                                            if($costoupd && $centroupd && $especiaupd && $cajaupd && $docenaupd && $mayoreoupd && $menudeoupd){
                                                $actualizados['goals']= ['product'=>$codigo,'prices'=>['factusol' => 7]];
                                            }else{
                                                $actualizados['fails']= ['product'=>$codigo,'prices'=>['factusol' => 0]];
                                            }
                                        }else{$actualizados['fails'][]= $codigo." precio Mayoreo mayor que Menudeo";}
                                    }else{$actualizados['fails'][]= $codigo." precio Docena mayor que Mayoreo";}
                                }else{$actualizados['fails'][]= $codigo." precio Caja mayor que Docena";}
                            }else{$actualizados['fails'][]= $codigo." precio Especia mayor que Caja";}
                        }else{$actualizados['fails'][]= $codigo." precio Centro mayor que Especial";}
                    }else{$actualizados['fails'][]= $codigo." precio AAA mayor que Centro";}
            }else{$actualizados['fails'][] = "El codigo ".$codigo." No existe";}
        }
        $res = [
            "actualizados"=>$actualizados,
        ];
        return response()->json($res);
    }

    public function regispricepub(Request $request){
        $date_format = date("d/m/Y");
        $date_time = date("Y-m-d H:m:s");
        $actualizados = [
            "goals"=>[],
            "fails"=>[]
        ];
        $prices = $request->prices;
        $margin = 1.05;
        foreach($prices as $price){
            $codigo = $price['MODELO'];
            $vercod = "SELECT F_ART.PCOART AS COSTO, F_FAM.SECFAM AS SECCION FROM F_ART INNER JOIN F_FAM ON F_FAM.CODFAM = F_ART.FAMART  WHERE F_ART.CODART = "."'".$codigo."'";
            $exec = $this->conn->prepare($vercod);
            $exec->execute();
            $codver = $exec->fetch(\PDO::FETCH_ASSOC);
            if($codver){
                if($codver['SECCION'] == "MOC"){
                    $costo = isset($price['COSTO']) ? round($price['COSTO'],2): intval($codver['COSTO']) ;
                    $centro = round($price['CENTRO'],0);
                    $especial = round($price['ESPECIAL'],0);
                    $caja = round($price['CAJA'],0);
                    $docena = round($price['DOCENA'],0);
                    $mayoreo = round($price['MAYOREO'],0);
                    if(isset($price['MENUDEO'])){
                        $menudeo = round($price['MENUDEO'],0);
                    }else if($mayoreo == $centro){
                        $menudeo = $caja;
                    }elseif(($mayoreo >= 0) && ($mayoreo <= 49)){
                        $menudeo = $mayoreo + 5;
                    }elseif(($mayoreo >= 50) && ($mayoreo <= 99)){
                        $menudeo = $mayoreo + 10;
                    }elseif(($mayoreo >= 100) && ($mayoreo <= 499)){
                        $menudeo = $mayoreo + 20;
                    }elseif(($mayoreo >= 500) && ($mayoreo <= 999)){
                        $menudeo = $mayoreo + 50;
                    }elseif($mayoreo >= 1000){
                        $menudeo =  $mayoreo + 100;
                    }
                }else{
                    $costo = isset($price['COSTO']) ? round($price['COSTO']*$margin,2): intval($codver['COSTO']) ;
                    $centro = round($price['CENTRO']*$margin,0);
                    $especial = round($price['ESPECIAL']*$margin,0);
                    $caja = round($price['CAJA']*$margin,0);
                    $docena = round($price['DOCENA']*$margin,0);
                    $mayoreo = round($price['MAYOREO']*$margin,0);
                    if(isset($price['MENUDEO'])){
                        $menudeo = round($price['MENUDEO']*$margin,0);
                    }else if($mayoreo == $centro){
                        $menudeo = $caja;
                    }elseif(($mayoreo >= 0) && ($mayoreo <= 49)){
                        $menudeo = $mayoreo + 5;
                    }elseif(($mayoreo >= 50) && ($mayoreo <= 99)){
                        $menudeo = $mayoreo + 10;
                    }elseif(($mayoreo >= 100) && ($mayoreo <= 499)){
                        $menudeo = $mayoreo + 20;
                    }elseif(($mayoreo >= 500) && ($mayoreo <= 999)){
                        $menudeo = $mayoreo + 50;
                    }elseif($mayoreo >= 1000){
                        $menudeo =  $mayoreo + 100;
                    }
                }
                    if($costo <= $centro){
                        if($centro <= $especial){
                            if($especial <= $caja){
                                if($caja <= $docena){
                                    if($docena <= $mayoreo){
                                        if($mayoreo <= $menudeo){
                                            $costoupd = $this->conn->prepare("UPDATE F_ART SET PCOART = ?, FUMART = ? WHERE CODART = ?")->execute([$costo,$date_format,$codigo]);
                                            $centroupd = $this->conn->prepare("UPDATE F_LTA SET PRELTA = ? WHERE ARTLTA = ? AND TARLTA = ?")->execute([$centro,$codigo,6]);
                                            $especiaupd = $this->conn->prepare("UPDATE F_LTA SET PRELTA = ? WHERE ARTLTA = ? AND TARLTA = ?")->execute([$especial,$codigo,5]);
                                            $cajaupd = $this->conn->prepare("UPDATE F_LTA SET PRELTA = ? WHERE ARTLTA = ? AND TARLTA = ?")->execute([$caja,$codigo,4]);
                                            $docenaupd = $this->conn->prepare("UPDATE F_LTA SET PRELTA = ? WHERE ARTLTA = ? AND TARLTA = ?")->execute([$docena,$codigo,3]);
                                            $mayoreoupd = $this->conn->prepare("UPDATE F_LTA SET PRELTA = ? WHERE ARTLTA = ? AND TARLTA = ?")->execute([$mayoreo,$codigo,2]);
                                            $menudeoupd = $this->conn->prepare("UPDATE F_LTA SET PRELTA = ? WHERE ARTLTA = ? AND TARLTA = ?")->execute([$menudeo,$codigo,1]);

                                            $cosms = DB::connection('vizapub')->table('products')->where('code',$codigo)->update(['cost'=>$costo,'updated_at'=>$date_time]);
                                            $centroms = DB::connection('vizapub')->table('product_prices as PP')->join('products as P','P.id','PP._product')->where('P.code',$codigo)->where('PP._type',6)->update(['PP.price'=>$centro]);
                                            $especialms = DB::connection('vizapub')->table('product_prices as PP')->join('products as P','P.id','PP._product')->where('P.code',$codigo)->where('PP._type',5)->update(['PP.price'=>$especial]);
                                            $cajams = DB::connection('vizapub')->table('product_prices as PP')->join('products as P','P.id','PP._product')->where('P.code',$codigo)->where('PP._type',4)->update(['PP.price'=>$caja]);
                                            $docenams = DB::connection('vizapub')->table('product_prices as PP')->join('products as P','P.id','PP._product')->where('P.code',$codigo)->where('PP._type',3)->update(['PP.price'=>$docena]);
                                            $mayoreoms = DB::connection('vizapub')->table('product_prices as PP')->join('products as P','P.id','PP._product')->where('P.code',$codigo)->where('PP._type',2)->update(['PP.price'=>$mayoreo]);
                                            $menudeoms = DB::connection('vizapub')->table('product_prices as PP')->join('products as P','P.id','PP._product')->where('P.code',$codigo)->where('PP._type',1)->update(['PP.price'=>$menudeo]);

                                            if($costoupd  && $centroupd && $especiaupd && $cajaupd && $docenaupd && $mayoreoupd && $menudeoupd){
                                                $actualizados['goals']= ['product'=>$codigo,'prices'=>['factusol' => 7]];
                                            }else{
                                                $actualizados['fails']= ['product'=>$codigo,'prices'=>['factusol' => 0]];
                                            }
                                        }else{$actualizados['fails'][]= $codigo." precio Mayoreo mayor que Menudeo";}
                                    }else{$actualizados['fails'][]= $codigo." precio Docena mayor que Mayoreo";}
                                }else{$actualizados['fails'][]= $codigo." precio Caja mayor que Docena";}
                            }else{$actualizados['fails'][]= $codigo." precio Especia mayor que Caja";}
                        }else{$actualizados['fails'][]= $codigo." precio Centro mayor que Especial";}
                    }else{$actualizados['fails'][]= $codigo." precio AAA mayor que Centro";}
            }else{$actualizados['fails'][] = "El codigo ".$codigo." No existe";}
        }
        $res = [
            "actualizados"=>$actualizados,
        ];
        return response()->json($res);
    }

    public function highPueblaInvoice(Request $request){
        $date = $request->date;
        $failstores=[];
        $stor=[];


        $products = "SELECT F_ART.CODART,F_ART.EANART,F_ART.FAMART,F_ART.DESART,F_ART.DEEART,F_ART.DETART,F_ART.DLAART,F_ART.EQUART,F_ART.CCOART,F_ART.PHAART,F_ART.REFART,F_ART.FTEART,F_ART.PCOART,F_ART.UPPART,F_ART.CANART,F_ART.CAEART,F_ART.UMEART,F_ART.CP1ART,F_ART.CP2ART,F_ART.CP3ART,F_ART.CP4ART,F_ART.CP5ART,F_ART.FALART,F_ART.FUMART,F_ART.MPTART,F_ART.UEQART FROM ((F_ART  INNER JOIN F_LFA ON F_LFA.ARTLFA = F_ART.CODART) INNER JOIN F_FAC ON F_FAC.TIPFAC = F_LFA.TIPLFA AND F_FAC.CODFAC = F_LFA.CODLFA) WHERE F_FAC.CLIFAC = 20  AND  F_FAC.FECFAC >= #".$date."#";
        $exec = $this->conn->prepare($products);
        $exec -> execute();
        $articulos=$exec->fetchall(\PDO::FETCH_ASSOC);
        if($articulos){
        $dat =$this->highPricesPueInvoice($date);

        $colsTabProds = array_keys($articulos[0]);

        foreach($articulos as $art){
            foreach($colsTabProds as $col){ $art[$col] = utf8_encode($art[$col]); }
            $arti[]=$art;
        }

        $stores = DB::connection('vizapi')->table('workpoints')->where('id',18)->get();//se obtienen sucursales de mysql
        foreach($stores as $store){//inicio de foreach de sucursales
            $url = $store->local_domain."/StoresTools/public/api/puebla/inspub";//se optiene el inicio del dominio de la sucursal
            $ch = curl_init($url);//inicio de curl
            $data = json_encode(["articulos" => $arti]);//se codifica el arreglo de los proveedores
            //inicio de opciones de curl
            curl_setopt($ch, CURLOPT_POSTFIELDS,$data);//se envia por metodo post
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
            //fin de opciones e curl
            $exec = curl_exec($ch);//se executa el curl
            $exc = json_decode($exec);//se decodifican los datos decodificados
            if(is_null($exc)){//si me regresa un null
                $failstores[] =$store->alias." sin conexion";//la sucursal se almacena en sucursales fallidas
            }else{
                $stor[] =["sucursal"=>$store->alias, "mssg"=>$exc];
            }
            curl_close($ch);//cirre de curl
        }//fin de foreach de sucursales


        return response()->json(["articulos"=>[
                                    "goals"=>$stor,
                                    "fail"=>$failstores
                                ],
                                 "precios" => $dat
        ]);
        }
            else{return response()->json("no hay articulos que exportar");}
    }

    public function highPricesPueInvoice($date){
        $failstores=[];
        $stor=[];
        // $prices = "SELECT F_LTA.* FROM ((F_LTA  INNER JOIN F_LFA ON F_LFA.ARTLFA = F_LTA.ARTLTA) INNER JOIN F_FAC ON F_FAC.TIPFAC = F_LFA.TIPLFA AND F_FAC.CODFAC = F_LFA.CODLFA) WHERE F_FAC.CLIFAC = 20 AND F_LTA.TARLTA NOT IN (7) AND  F_FAC.FECFAC >= #".$date."#";
       $prices = "SELECT
       F_LTA.ARTLTA AS CODIGO,
       F_FAM.SECFAM AS SECCION,
       MAX(iif(F_LTA.TARLTA = 6 , F_LTA.PRELTA ,0 )) AS CENTRO,
       MAX(iif(F_LTA.TARLTA = 5 , F_LTA.PRELTA ,0 )) AS ESPECIAL,
       MAX(iif(F_LTA.TARLTA = 4 , F_LTA.PRELTA ,0 )) AS CAJA,
       MAX(iif(F_LTA.TARLTA = 3 , F_LTA.PRELTA ,0 )) AS DOCENA,
       MAX(iif(F_LTA.TARLTA = 2 , F_LTA.PRELTA ,0 )) AS MAYOREO
       FROM
       ((F_LTA
       INNER JOIN F_LFA ON F_LFA.ARTLFA = F_LTA.ARTLTA)
       INNER JOIN F_FAC ON F_FAC.TIPFAC = F_LFA.TIPLFA AND F_FAC.CODFAC = F_LFA.CODLFA)
       INNER JOIN F_ART ON F_ART.CODART = F_LTA.ARTLTA
       INNER JOIN F_FAM ON F_ART.FAMART = F_FAM.CODFAM
       WHERE F_FAC.CLIFAC = 20 AND F_LTA.TARLTA NOT IN (7) AND  F_FAC.FECFAC >= #".$date."#
       GROUP BY F_LTA.ARTLTA;";
        $exec = $this->conn->prepare($prices);
        $exec -> execute();
        $precios=$exec->fetchall(\PDO::FETCH_ASSOC);
        foreach($precios as $pre){
            $pri[]= $pre;
        }
        $stores = DB::connection('vizapi')->table('workpoints')->where('id',18)->get();//se obtienen sucursales de mysql
        foreach($stores as $store){//inicio de foreach de sucursales
            $url = $store->local_domain."/StoresTools/public/api/puebla/insertPricesPub";//se optiene el inicio del dominio de la sucursal
            $ch = curl_init($url);//inicio de curl
            $data = json_encode(["prices" => $pri]);//se codifica el arreglo de los proveedores
            //inicio de opciones de curl
            curl_setopt($ch, CURLOPT_POSTFIELDS,$data);//se envia por metodo post
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
            //fin de opciones e curl
            $exec = curl_exec($ch);//se executa el curl
            $exc = json_decode($exec);//se decodifican los datos decodificados
            if(is_null($exc)){//si me regresa un null
                $failstores[] =$store->alias." sin conexion";//la sucursal se almacena en sucursales fallidas
            }else{
                $stor[] =["sucursal"=>$store->alias, "mssg"=>$exc];
            }
            curl_close($ch);//cirre de curl
        }//fin de foreach de sucursales
         $res = [
            "goals"=>$stor,
            "fail"=>$failstores
        ];
        return $res;
    }

    public function insertPub(Request $request){
        $actualizados = [];
        $insertados = [];
        $date = date("Y/m/d H:i");
        $date_format = date("d/m/Y");
        $articulos = $request->articulos;
        $margen = 1.05;
        $almacenes ="SELECT CODALM FROM F_ALM";
        $exec = $this->conn->prepare($almacenes);
        $exec -> execute();
        $fil=$exec->fetchall(\PDO::FETCH_ASSOC);
        // $tarifas ="SELECT CODTAR FROM F_TAR";
        // $exec = $this->conn->prepare($tarifas);
        // $exec -> execute();
        // $tar=$exec->fetchall(\PDO::FETCH_ASSOC);
        foreach($articulos as $art){

            $artexs = "SELECT CODART FROM F_ART WHERE CODART = ?";
            $exec = $this->conn->prepare($artexs);
            $exec -> execute([$art['CODART']]);
            $cossis=$exec->fetch(\PDO::FETCH_ASSOC);

            if($cossis){
                $articulo=$art['CODART'];
                $costo = round($art['PCOART']*$margen,2);
                $barcode = $art['EANART'];
                $familia = $art['FAMART'];
                $palets =$art['UPPART'];
                $categoria = $art['CP1ART'];
                $actualizar =[
                    $date_format,
                    $costo,
                    $barcode,
                    $familia,
                    $palets,
                    $categoria,
                    $articulo
                ];

                $updaxs = "UPDATE F_ART SET FUMART = ?, PCOART = ?, EANART = ?, FAMART = ?,  UPPART = ?, CP1ART = ? WHERE CODART = ?";
                $exec = $this->conn->prepare($updaxs);
                $exec -> execute($actualizar);
                $actualizados[] ="Se actualizo el modelo ".$cossis["CODART"];
            }else{
                $product = [
                    $art["CODART"],
                    $art["EANART"],
                    $art["FAMART"],
                    $art["DESART"],
                    $art["DEEART"],
                    $art["DETART"],
                    $art["DLAART"],
                    $art["EQUART"],
                    $art["CCOART"],
                    $art["PHAART"],
                    $art["REFART"],
                    $art["FTEART"],
                    ($art["PCOART"]*$margen),
                    $art["UPPART"],
                    $art["CANART"],
                    $art["CAEART"],
                    $art["UMEART"],
                    $art["CP1ART"],
                    $art["CP2ART"],
                    $art["CP3ART"],
                    $art["CP4ART"],
                    $art["CP5ART"],
                    $date_format,
                    $date_format,
                    $art["MPTART"],
                    $art["UEQART"]
                ];
            $artid = "INSERT INTO  F_ART (CODART,EANART,FAMART,DESART,DEEART,DETART,DLAART,EQUART,CCOART,PHAART,REFART,FTEART,PCOART,UPPART,CANART,CAEART,UMEART,CP1ART,CP2ART,CP3ART,CP4ART,CP5ART,FALART,FUMART,MPTART,UEQART
            ) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $exec = $this->conn->prepare($artid);
            $exec -> execute($product);
            foreach($fil as $row){
                $alm=$row['CODALM'];
                $insertsto = "INSERT INTO F_STO (ARTSTO,ALMSTO,MINSTO,MAXSTO,ACTSTO,DISSTO) VALUES (?,?,?,?,?,?) ";
                $exec = $this->conn->prepare($insertsto);
                $exec -> execute([$art["CODART"],$alm,0,0,0,0]);
            }
            // foreach($tar as $tari){
            //     $tarifa=$tari['CODTAR'];
            //     $inserttar = "INSERT INTO F_LTA (TARLTA,ARTLTA,MARLTA,PRELTA) VALUES (?,?,?,?) ";
            //     $exec = $this->conn->prepare($inserttar);
            //     $exec -> execute([$tarifa,$art["CODART"],0,0,0,0]);
            // }
            $insertados[]="Se inserto el modelo ".$art["CODART"];
            }

       }
        $res = [
            "insertados"=>$insertados,
            "actualizados"=>$actualizados
        ];
        return response()->json($res);

    }

    public function insertPricesPub(request $request){
        $actualizados = [];
        $insertados = [];

        $prices = $request->prices;


        $margen = 1.05;
        foreach($prices as $price){

            $articulo = $price['CODIGO'];
            $centro = round($price['CENTRO']*$margen,0);
            $especial = round($price['ESPECIAL']*$margen,0);
            $caja = round($price['CAJA']*$margen,0);
            $docena = round($price['DOCENA']*$margen,0);
            $mayoreo = round($price['MAYOREO']*$margen,0);

            if($mayoreo == $centro){
                $menudeo = $caja;
            }elseif(($mayoreo >= 0) && ($mayoreo <= 49)){
                $menudeo = $mayoreo + 5;
            }elseif(($mayoreo >= 50) && ($mayoreo <= 99)){
                $menudeo = $mayoreo + 10;
            }elseif(($mayoreo >= 100) && ($mayoreo <= 499)){
                $menudeo = $mayoreo + 20;
            }elseif(($mayoreo >= 500) && ($mayoreo <= 999)){
                $menudeo = $mayoreo + 50;
            }elseif($mayoreo >= 1000){
                $menudeo =  $mayoreo + 100;
            }

            $exispr = "SELECT ARTLTA FROM F_LTA WHERE ARTLTA = ?";
            $exec = $this->conn->prepare($exispr);
            $exec -> execute([$articulo]);
            $cossis=$exec->fetch(\PDO::FETCH_ASSOC);
            if($cossis){

                $cen = "UPDATE F_LTA SET PRELTA = ".$centro." WHERE ARTLTA = ?  AND TARLTA = 6";
                $exec = $this->conn->prepare($cen);
                $exec -> execute([$articulo]);

                $espe = "UPDATE F_LTA SET PRELTA = ".$especial." WHERE ARTLTA = ? AND TARLTA = 5";
                $exec = $this->conn->prepare($espe);
                $exec -> execute([$articulo]);

                $caj = "UPDATE F_LTA SET PRELTA = ".$caja." WHERE ARTLTA = ? AND TARLTA = 4";
                $exec = $this->conn->prepare($caj);
                $exec -> execute([$articulo]);

                $doc = "UPDATE F_LTA SET PRELTA = ".$docena." WHERE ARTLTA = ? AND TARLTA = 3";
                $exec = $this->conn->prepare($doc);
                $exec -> execute([$articulo]);

                $mayo = "UPDATE F_LTA SET PRELTA = ".$mayoreo." WHERE ARTLTA = ?  AND TARLTA = 2";
                $exec = $this->conn->prepare($mayo);
                $exec -> execute([$articulo]);

                $menu = "UPDATE F_LTA SET PRELTA = ".$menudeo." WHERE ARTLTA = ? AND TARLTA = 1";
                $exec = $this->conn->prepare($menu);
                $exec -> execute([$articulo]);
                $actualizados[]="Se actuzalizaron precios de el articulo ".$articulo;
            }else{

                $inscen = "INSERT INTO F_LTA (TARLTA,ARTLTA,MARLTA,PRELTA) VALUES  (?,?,?,?)";
                $exec = $this->conn->prepare($inscen);
                $exec -> execute([6,$articulo,0,$centro]);

                $inscen = "INSERT INTO F_LTA (TARLTA,ARTLTA,MARLTA,PRELTA) VALUES  (?,?,?,?)";
                $exec = $this->conn->prepare($inscen);
                $exec -> execute([5,$articulo,0,$especial]);

                $inscen = "INSERT INTO F_LTA (TARLTA,ARTLTA,MARLTA,PRELTA) VALUES  (?,?,?,?)";
                $exec = $this->conn->prepare($inscen);
                $exec -> execute([4,$articulo,0,$caja]);

                $inscen = "INSERT INTO F_LTA (TARLTA,ARTLTA,MARLTA,PRELTA) VALUES  (?,?,?,?)";
                $exec = $this->conn->prepare($inscen);
                $exec -> execute([3,$articulo,0,$docena]);

                $inscen = "INSERT INTO F_LTA (TARLTA,ARTLTA,MARLTA,PRELTA) VALUES  (?,?,?,?)";
                $exec = $this->conn->prepare($inscen);
                $exec -> execute([2,$articulo,0,$mayoreo]);

                $inscen = "INSERT INTO F_LTA (TARLTA,ARTLTA,MARLTA,PRELTA) VALUES  (?,?,?,?)";
                $exec = $this->conn->prepare($inscen);
                $exec -> execute([1,$articulo,0,$menudeo]);
                $insertados[]="Precios insertados del articulo ".$articulo;
            }
        }
        $res = [
            "actualizados"=>$actualizados,
            "insertados"=>$insertados
        ];
        return response()->json($res);
    }

    public function translateWarehouses(Request $request){
        $data = $request->data;
        $origen = $data['AORTRA'];
        $destino = $data['ADETRA'];
        $nota = $data['COMTRA'];
        $products = $data['products'];

        $nextid = "SELECT MAX(DOCTRA) + 1 AS ID FROM F_TRA";
        $exec = $this->conn->prepare($nextid);
        $exec -> execute();
        $id =$exec->fetch(\PDO::FETCH_ASSOC);
        $instra = [
            $id['ID'],
            $origen,
            $destino,
            $nota
        ];
        $insertra = "INSERT INTO F_TRA (FECTRA,DOCTRA,AORTRA,ADETRA,COMTRA) VALUES(date(),?,?,?,?)";
        $exec = $this->conn->prepare($insertra);
        $result = $exec -> execute($instra);
        if($instra){
            $lin = 1;
            foreach($products as $product){
                $codigo = $product['ARTLTR'];
                $cantidad = $product['CANLTR'];
                $insltr = [
                    $id['ID'],
                    $lin,
                    $codigo,
                    $cantidad
                ];
                $inspro = "INSERT INTO F_LTR (DOCLTR,LINLTR,ARTLTR,CANLTR) VALUES (?,?,?,?)";
                $exec = $this->conn->prepare($inspro);
                $ltres = $exec -> execute($insltr);
                $lin++;
                $updori = "UPDATE F_STO SET ACTSTO = ACTSTO - ".$cantidad.", DISSTO = DISSTO - ".$cantidad." WHERE ARTSTO = "."'".$codigo."'"." AND ALMSTO = "."'".$origen."'";
                $exec = $this->conn->prepare($updori)->execute();
                $updes = "UPDATE F_STO SET ACTSTO = ACTSTO + ".$cantidad.", DISSTO = DISSTO + ".$cantidad." WHERE ARTSTO = "."'".$codigo."'"." AND ALMSTO = "."'".$destino."'";
                $exec = $this->conn->prepare($updes)->execute();

            }
        }else{
            return "no se genero el traspaso ";
        }
        $res = "El traspaso ".$id['ID']." se genero con exito";
        return response()->json($res);
    }

    public function refund(Request $request){//devolucion de la sucursal
        $datos = $request->data;
        $referencia = $datos['referencia'];
        $observacion = $datos['observacion'];
        $tot = $datos['total'];
        $products = $datos['products'];
        $nextid = "SELECT MAX(CODFRD) + 1 AS ID FROM F_FRD WHERE TIPFRD =  '1' ";
        $exec = $this->conn->prepare($nextid);
        $exec -> execute();
        $id =$exec->fetch(\PDO::FETCH_ASSOC);
        $datprov =  "SELECT CODPRO,NOFPRO,DOMPRO,POBPRO,CPOPRO,PROPRO FROM F_PRO WHERE CODPRO = 5";
        $exec = $this->conn->prepare($datprov);
        $exec -> execute();
        $provider =$exec->fetch(\PDO::FETCH_ASSOC);
        $insdev = [
            1,
            $id['ID'],
            $referencia,
            $referencia,
            $provider['CODPRO'],
            0,
            $provider['NOFPRO'],
            $provider['DOMPRO'],
            $provider['POBPRO'],
            $provider['CPOPRO'],
            $provider['PROPRO'],
            $tot,
            $tot,
            $tot,
            '01/01/1900',
            1,
            "GEN",
            1,
            1,
            "MEXICO",
            0,
            1,
            2,
            "2023",
            "01/01/1900",
            0,
            $observacion
        ];
        $creat = "INSERT INTO F_FRD (TIPFRD,CODFRD,FACFRD,REFFRD,FECFRD,PROFRD,ESTFRD,PNOFRD,PDOFRD,PPOFRD,PCPFRD,PPRFRD,NET1FRD,BAS1FRD,TOTFRD,FENFRD,CFDFRD,ALMFRD,USUFRD,USMFRD,PPAFRD,TIVA1FRD,TIVA2FRD,TIVA3FRD,EFDFRD,FUMFRD,EERFRD,OB1FRD) VALUES (?,?,?,?,date(),?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $exec = $this->conn->prepare($creat);
        $yes = $exec -> execute($insdev);
        $pos = 1;
        if($yes){
            foreach($products as $product){
                $insepro = [
                    1,
                    $id['ID'],
                    $pos,
                    $product['ARTLTR'],
                    $product['DES'],
                    $product['CANLTR'],
                    $product['PRE'],
                    $product['TOTAL']
                ];

                $inspr = "INSERT INTO F_LFD (TIPLFD,CODLFD,POSLFD,ARTLFD,DESLFD,CANLFD,PRELFD,TOTLFD) VALUES (?,?,?,?,?,?,?,?)";
                $exec = $this->conn->prepare($inspr);
                $art = $exec -> execute($insepro);

                $updsto = "UPDATE F_STO SET ACTSTO = ACTSTO - ".$product['CANLTR'].", DISSTO = DISSTO - ".$product['CANLTR']." WHERE ARTSTO = "."'".$product['ARTLTR']."'"." AND ALMSTO = 'GEN'";
                $exec = $this->conn->prepare($updsto);
                $art = $exec -> execute();
                $pos++;
            }
            $res ="1-".$id['ID'];
            return response()->json($res);
        }else{
            return "No se genero la devolucion";
        }

    }

    public function abono(Request $request){//abono
        $datos = $request->data;
        $products = $datos['products'];
        $datcli =  "SELECT CODCLI,NOFCLI,DOMCLI,POBCLI,CPOCLI,PROCLI, DOCCLI, TELCLI FROM F_CLI WHERE CODCLI = ".$datos['cliente'];
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
        $yes = $exec -> execute($insa);
        if($yes){
            $pos = 1;
            foreach($products as $product){
                $pco =  "SELECT PCOART AS COSTO FROM F_ART WHERE CODART = "."'".$product['ARTLTR']."'";
                $exec = $this->conn->prepare($pco);
                $exec -> execute();
                $pcos =$exec->fetch(\PDO::FETCH_ASSOC);
                $inspro = [
                    $client['DOCCLI'],
                    $id['ID'],
                    $pos,
                    $product['ARTLTR'],
                    $product['DES'],
                    $product['CANLTR'],
                    $product['PRE'],
                    $product['TOTAL'],
                    $pcos['COSTO']
                ];
                $inspab = "INSERT INTO F_LFB (TIPLFB,CODLFB,POSLFB,ARTLFB,DESLFB,CANLFB,PRELFB,TOTLFB,COSLFB) VALUES (?,?,?,?,?,?,?,?,?)";
                $exec = $this->conn->prepare($inspab);
                $art = $exec -> execute($inspro);

                $updsto = "UPDATE F_STO SET ACTSTO = ACTSTO + ".$product['CANLTR'].", DISSTO = DISSTO + ".$product['CANLTR']." WHERE ARTSTO = "."'".$product['ARTLTR']."'"." AND ALMSTO = 'GEN'";
                $exec = $this->conn->prepare($updsto);
                $art = $exec -> execute();
                $pos++;
            }
            $res =$client['DOCCLI']."-".$id['ID'];
            return response()->json($res,200);
        }else{
            return response()->json("No se genero el abono",401);
        }


    }

    public function invice(Request $request){//salida cedis
        $datos = $request->data;
        $products = $datos['products'];
        $datcli =  "SELECT CODCLI,NOFCLI,DOMCLI,POBCLI,CPOCLI,PROCLI, DOCCLI, TELCLI FROM F_CLI WHERE CODCLI = ".$datos['cliente'];
        $exec = $this->conn->prepare($datcli);
        $exec -> execute();
        $client =$exec->fetch(\PDO::FETCH_ASSOC);
        $nextid = "SELECT MAX(CODFAC) + 1 AS ID FROM F_FAC WHERE TIPFAC = "."'".$client['DOCCLI']."'";
        $exec = $this->conn->prepare($nextid);
        $exec -> execute();
        $id =$exec->fetch(\PDO::FETCH_ASSOC);
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
        $yes = $exec -> execute($insa);
        if($yes){
            $pos = 1;
            foreach($products as $product){
                $cos =  "SELECT PCOART AS COSTO FROM F_ART WHERE CODART = "."'".$product['ARTLTR']."'";
                $exec = $this->conn->prepare($cos);
                $exec -> execute();
                $pcos =$exec->fetch(\PDO::FETCH_ASSOC);
                $inspro = [
                    $client['DOCCLI'],
                    $id['ID'],
                    $pos,
                    $product['ARTLTR'],
                    $product['DES'],
                    $product['CANLTR'],
                    $product['PRE'],
                    $product['TOTAL'],
                    $pcos['COSTO']
                ];
                $inspab = "INSERT INTO F_LFA (TIPLFA,CODLFA,POSLFA,ARTLFA,DESLFA,CANLFA,PRELFA,TOTLFA,COSLFA) VALUES (?,?,?,?,?,?,?,?,?)";
                $exec = $this->conn->prepare($inspab);
                $art = $exec -> execute($inspro);

                $updsto = "UPDATE F_STO SET ACTSTO = ACTSTO - ".$product['CANLTR'].", DISSTO = DISSTO - ".$product['CANLTR']." WHERE ARTSTO = "."'".$product['ARTLTR']."'"." AND ALMSTO = 'GEN'";
                $exec = $this->conn->prepare($updsto);
                $art = $exec -> execute();
                $pos++;
            }

            $res =$client['DOCCLI']."-".str_pad($id['ID'], 6, "0", STR_PAD_LEFT);
            return response()->json($res);
        }else{
            return "No se genero el abono";
        }
    }

    public function invoiceReceived(Request $request){//factura recibida
        $datos = $request->all();
        $products = $datos['products'];
        $datprov =  "SELECT CODPRO,NOFPRO,DOMPRO,POBPRO,CPOPRO,PROPRO FROM F_PRO WHERE CODPRO = 5";
        $exec = $this->conn->prepare($datprov);
        $exec -> execute();
        $provider =$exec->fetch(\PDO::FETCH_ASSOC);
        $nextid = "SELECT MAX(CODFRE) + 1 AS ID FROM F_FRE WHERE TIPFRE = '1'";
        $exec = $this->conn->prepare($nextid);
        $exec -> execute();
        $id =$exec->fetch(\PDO::FETCH_ASSOC);
        if(is_null($id['ID'])){
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
        $yes = $exec -> execute($insa);
        if($yes){
            $pos = 1;
            foreach($products as $product){
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
                $art = $exec -> execute($inspro);

                $updsto = "UPDATE F_STO SET ACTSTO = ACTSTO + ".$product['CANLTR'].", DISSTO = DISSTO + ".$product['CANLTR']." WHERE ARTSTO = "."'".$product['ARTLTR']."'"." AND ALMSTO = 'GEN'";
                $exec = $this->conn->prepare($updsto);
                $art = $exec -> execute();
                $pos++;
            }
            $res ="1"."-".$id['ID'];
        return response()->json($res,200);
        }else{
            return response()->json("No se genero la factura recibida",401);
        }
    }

    public function reportDepure(){
        $prod = [];
        $invoice = [];
        $received = [];
        $include = [];
        $return = [];
        $translate = [];
        $aibonos = [];
        $sli = [];
        $alb = [];
        $cin = [];
        $fab = [];
        $stc = [];

        $pros ="SELECT CODART FROM F_ART WHERE YEAR(FALART) < YEAR(DATE())";
        $exec = $this->conn->prepare($pros);
        $exec -> execute();
        $products =$exec->fetchall(\PDO::FETCH_ASSOC);
        foreach($products AS $product){
            $prod [] = $product['CODART'];
        }

        $fac ="SELECT DISTINCT ARTLFA FROM F_LFA";
        $exec = $this->conn->prepare($fac);
        $exec -> execute();
        $facturas =$exec->fetchall(\PDO::FETCH_ASSOC);
        foreach($facturas as $factura){
            $invoice[] = $factura['ARTLFA'];
        }
        $fre ="SELECT DISTINCT ARTLFR FROM F_LFR";
        $exec = $this->conn->prepare($fre);
        $exec -> execute();
        $recibidas =$exec->fetchall(\PDO::FETCH_ASSOC);
        foreach($recibidas as $recibida){
            $received[] = $recibida['ARTLFR'];
        }
        $ent ="SELECT DISTINCT ARTLEN FROM F_LEN";
        $exec = $this->conn->prepare($ent);
        $exec -> execute();
        $entradas =$exec->fetchall(\PDO::FETCH_ASSOC);
        foreach($entradas as $entrada){
            $include[] = $entrada['ARTLEN'];
        }
        $dev ="SELECT DISTINCT ARTLFD FROM F_LFD";
        $exec = $this->conn->prepare($dev);
        $exec -> execute();
        $devoluciones =$exec->fetchall(\PDO::FETCH_ASSOC);
        foreach($devoluciones as $devolucion){
            $return[] = $devolucion['ARTLFD'];
        }
        $tra ="SELECT DISTINCT ARTLTR FROM F_LTR";
        $exec = $this->conn->prepare($tra);
        $exec -> execute();
        $traspasos =$exec->fetchall(\PDO::FETCH_ASSOC);
        foreach($traspasos as $traspaso){
            $translate[] = $traspaso['ARTLTR'];
        }
        $abo ="SELECT DISTINCT ARTLFB FROM F_LFB";
        $exec = $this->conn->prepare($abo);
        $exec -> execute();
        $abonos =$exec->fetchall(\PDO::FETCH_ASSOC);
        foreach($abonos as $abono){
            $aibonos[] = $abono['ARTLFB'];
        }

        $lsa ="SELECT DISTINCT ARTLSA FROM F_LSA";
        $exec = $this->conn->prepare($lsa);
        $exec -> execute();
        $salidas =$exec->fetchall(\PDO::FETCH_ASSOC);
        foreach($salidas as $salida){
            $sli[] = $salida['ARTLSA'];
        }

        $lal ="SELECT DISTINCT ARTLAL FROM F_LAL";
        $exec = $this->conn->prepare($lal);
        $exec -> execute();
        $albaranes =$exec->fetchall(\PDO::FETCH_ASSOC);
        foreach($albaranes as $albaran){
            $alb[] = $albaran['ARTLAL'];
        }

        $arcin ="SELECT DISTINCT ARTCIN FROM F_CIN WHERE URECIN <> 0";
        $exec = $this->conn->prepare($arcin);
        $exec -> execute();
        $consolidaciones =$exec->fetchall(\PDO::FETCH_ASSOC);
        foreach($consolidaciones as $consolidacion){
            $cin[] = $consolidacion['ARTCIN'];
        }

        $lfc ="SELECT DISTINCT ARTLFC FROM F_LFC";
        $exec = $this->conn->prepare($lfc);
        $exec -> execute();
        $fabricaciones =$exec->fetchall(\PDO::FETCH_ASSOC);
        foreach($fabricaciones as $fabricacion){
            $fab[] = $fabricacion['ARTLFC'];
        }

        $sto ="SELECT DISTINCT ARTSTO FROM F_STO WHERE ACTSTO <> 0";
        $exec = $this->conn->prepare($sto);
        $exec -> execute();
        $stocks =$exec->fetchall(\PDO::FETCH_ASSOC);
        foreach($stocks as $stock){
            $stc[] = $stock['ARTSTO'];
        }

        $union = array_unique(array_merge($invoice,$received,$include,$return,$translate,$aibonos,$sli,$alb,$cin,$fab,$stc));
        $diff = array_diff($prod,$union);
        if($diff){
            $respuesta = mb_convert_encoding(array_values($diff), 'UTF-8','UTF-8');

            return response()->json($respuesta,200);
        }else{
            return response()->json("No se encontraron diferencias",404);
        }
    }

    public function replacecode(Request $request){
        $fails = [];
        $goal = [];

        $products =  $request['data'];
        foreach($products as $product){
            $nvo = "'".$product['NUEVO']."'";
            $ant = "'".$product['ANTERIOR']."'";
        //EAN ARTICULOS FAMILIARIZADOS
            $enean = "SELECT * FROM F_EAN WHERE EANEAN = ".$nvo;
            $exec = $this->conn->prepare($enean);
            $exec -> execute();
            $eneans =$exec->fetchall(\PDO::FETCH_ASSOC);
            if(count($eneans) > 1){
                $fails['asoc'][] = ['msg'=>"se tienen que revisar ya que cuentan con mas familiarizaciones",'ANT'=>$ant,'NVO'=>$nvo];
            }else{
                if(count($eneans) == 1){
                    // EAN ARTICULOS FAMILIARIZADOS\
                    $desart = "SELECT DESART, DEEART, DETART, DLAART FROM F_ART WHERE CODART = ".$ant;
                    $exec = $this->conn->prepare($desart);
                    $exec -> execute();
                    $descriptions =$exec->fetch(\PDO::FETCH_ASSOC);
                    if($descriptions){
                        $des = str_replace($product['NUEVO'], $product['ANTERIOR'], $descriptions['DESART']);
                        $tic = str_replace($product['NUEVO'], $product['ANTERIOR'], $descriptions['DETART']);
                        $eti = str_replace($product['NUEVO'], $product['ANTERIOR'], $descriptions['DEEART']);
                        $lar = str_replace($product['NUEVO'], $product['ANTERIOR'], $descriptions['DLAART']);
                        $updes = "UPDATE F_ART SET DESART = ? , DEEART = ?, DLAART = ?, DETART = ? WHERE CODART = ".$ant;
                        $exec = $this->conn->prepare($updes);
                        $exec -> execute([$des,$eti,$lar,$tic]);

                        $updfam = "UPDATE F_EAN SET ARTEAN = ".$nvo."WHERE ARTEAN = ".$ant;
                        $exec = $this->conn->prepare($updfam);
                        $exec -> execute();

                        $updfamvis = "UPDATE F_EAN SET EANEAN = ".$ant."WHERE EANEAN = ".$nvo;
                        $exec = $this->conn->prepare($updfamvis);
                        $exec -> execute();

                        $goal['Familiarizados'][]=[
                            "codigo"=>$nvo,
                            "descripcion"=>$lar
                        ];
                    }else{
                        $fails['DES'] = "No existen descripcioines de el articulo ". $ant;
                    }
                }
                //ART ARTICULOS
                $art = "SELECT CODART FROM  F_ART WHERE CODART = ".$ant;
                $exec = $this->conn->prepare($art);
                $exec -> execute();
                $cod =$exec->fetch(\PDO::FETCH_ASSOC);
                if($cod){
                    $upart = "UPDATE F_ART SET CODART = ".$nvo.", FUMART = DATE()  WHERE CODART = ".$ant;
                    $exec = $this->conn->prepare($upart);
                    $exec -> execute();
                    //STO STOCK DE ARTICULOS
                    $exsto = "SELECT ARTSTO FROM F_STO WHERE ARTSTO = ".$nvo;
                    $exec = $this->conn->prepare($exsto);
                    $exec -> execute();
                    $existo =$exec->fetch(\PDO::FETCH_ASSOC);
                    if($existo){
                        $upsto = "DELETE FROM  F_STO  WHERE ARTSTO = ".$ant;
                        $exec = $this->conn->prepare($upsto);
                        $exec -> execute();
                    }else{
                        $upsto = "UPDATE F_STO SET ARTSTO = ".$nvo." WHERE ARTSTO = ".$ant;
                        $exec = $this->conn->prepare($upsto);
                        $exec -> execute();
                    }
                    //LTA PRECIOS
                    $exlta = "SELECT ARTLTA FROM F_LTA WHERE ARTLTA = ".$ant;
                    $exec = $this->conn->prepare($exlta);
                    $exec -> execute();
                    $exilta =$exec->fetch(\PDO::FETCH_ASSOC);
                    if($exilta){
                        $uplta = "UPDATE F_LTA SET ARTLTA = ".$nvo." WHERE ARTLTA = ".$ant;
                        $exec = $this->conn->prepare($uplta);
                        $exec -> execute();
                    }
                    //LFA FACTURAS
                    $exlfa = "SELECT ARTLFA FROM F_LFA WHERE ARTLFA = ".$ant;
                    $exec = $this->conn->prepare($exlfa);
                    $exec -> execute();
                    $exilfa =$exec->fetch(\PDO::FETCH_ASSOC);
                    if($exilfa){
                        $upfac = "UPDATE F_LFA SET ARTLFA = ".$nvo." WHERE ARTLFA = ".$ant;
                        $exec = $this->conn->prepare($upfac);
                        $exec -> execute();
                    }
                    //LFR FACTURAS RECIBIDAS
                    $exlfr = "SELECT ARTLFR  FROM F_LFR WHERE ARTLFR = ".$ant;
                    $exec = $this->conn->prepare($exlfr);
                    $exec -> execute();
                    $exilfr =$exec->fetch(\PDO::FETCH_ASSOC);
                    if($exilfr){
                        $uplfr = "UPDATE F_LFR SET ARTLFR = ".$nvo." WHERE ARTLFR = ".$ant;
                        $exec = $this->conn->prepare($uplfr);
                        $exec -> execute();
                    }
                    //LEN ENTRADAS
                    $exlen = "SELECT ARTLEN FROM F_LEN WHERE ARTLEN = ".$ant;
                    $exec = $this->conn->prepare($exlen);
                    $exec -> execute();
                    $exilen =$exec->fetch(\PDO::FETCH_ASSOC);
                    if($exilen){
                        $uplen = "UPDATE F_LEN SET ARTLEN = ".$nvo." WHERE ARTLEN = ".$ant;
                        $exec = $this->conn->prepare($uplen);
                        $exec -> execute();
                    }
                    //LFD DEVOLUCIONES
                    $exlfd = "SELECT ARTLFD FROM F_LFD WHERE ARTLFD = ".$ant;
                    $exec = $this->conn->prepare($exlfd);
                    $exec -> execute();
                    $exilfd =$exec->fetch(\PDO::FETCH_ASSOC);
                    if($exilfd){
                        $uplfd = "UPDATE F_LFD SET ARTLFD = ".$nvo." WHERE ARTLFD = ".$ant;
                        $exec = $this->conn->prepare($uplfd);
                        $exec -> execute();
                    }
                    //LTR TRASPASOS
                    $exltr = "SELECT ARTLTR FROM F_LTR WHERE ARTLTR = ".$ant;
                    $exec = $this->conn->prepare($exltr);
                    $exec -> execute();
                    $exiltr =$exec->fetch(\PDO::FETCH_ASSOC);
                    if($exiltr){
                        $upltr = "UPDATE F_LTR SET ARTLTR = ".$nvo." WHERE ARTLTR = ".$ant;
                        $exec = $this->conn->prepare($upltr);
                        $exec -> execute();
                    }
                    //LFB ABONOS
                    $exlfb = "SELECT ARTLFB FROM F_LFB WHERE ARTLFB = ".$ant;
                    $exec = $this->conn->prepare($exlfb);
                    $exec -> execute();
                    $exilfb =$exec->fetch(\PDO::FETCH_ASSOC);
                    if($exilfb){
                        $uplfb = "UPDATE F_LFB SET ARTLFB = ".$nvo." WHERE ARTLFB = ".$ant;
                        $exec = $this->conn->prepare($uplfb);
                        $exec -> execute();
                    }
                    //LSA SALIDAS INT
                    $exlsa = "SELECT ARTLSA FROM F_LSA WHERE ARTLSA = ".$ant;
                    $exec = $this->conn->prepare($exlsa);
                    $exec -> execute();
                    $exilsa =$exec->fetch(\PDO::FETCH_ASSOC);
                    if($exilsa){
                        $uplsa = "UPDATE F_LSA SET ARTLSA = ".$nvo." WHERE ARTLSA = ".$ant;
                        $exec = $this->conn->prepare($uplsa);
                        $exec -> execute();
                    }
                    //LAL ALBARANES
                    $exlal = "SELECT ARTLAL FROM F_LAL WHERE ARTLAL = ".$ant;
                    $exec = $this->conn->prepare($exlal);
                    $exec -> execute();
                    $exilal =$exec->fetch(\PDO::FETCH_ASSOC);
                    if($exilal){
                        $uplal = "UPDATE F_LAL SET ARTLAL = ".$nvo." WHERE ARTLAL = ".$ant;
                        $exec = $this->conn->prepare($uplal);
                        $exec -> execute();
                    }
                    //CIN CONSOLIDACIONES
                    $excin = "SELECT ARTCIN FROM F_CIN WHERE ARTCIN = ".$nvo;
                    $exec = $this->conn->prepare($excin);
                    $exec -> execute();
                    $exicin =$exec->fetch(\PDO::FETCH_ASSOC);
                    if(!$exicin){
                        $upcin = "UPDATE F_CIN SET ARTCIN = ".$nvo." WHERE ARTCIN = ".$ant;
                        $exec = $this->conn->prepare($upcin);
                        $exec -> execute();
                    }
                    //LFC FABRICACION COMPUESTOS
                    $exlfc = "SELECT ARTLFC FROM F_LFC WHERE ARTLFC = ".$ant;
                    $exec = $this->conn->prepare($exlfc);
                    $exec -> execute();
                    $exilfc =$exec->fetch(\PDO::FETCH_ASSOC);
                    if($exilfc){
                        $uplfc = "UPDATE F_LFC SET ARTLFC = ".$nvo." WHERE ARTLFC = ".$ant;
                        $exec = $this->conn->prepare($uplfc);
                        $exec -> execute();
                    }
                    $goal['Actualizados'][]="Modelo ".$ant." actualizado por ".$nvo;
                }else{
                    $fails['Cod'] = "El articulo ".$ant." no existe";
                }
            }
        }
        $res = [
            "goals"=>$goal,
            "fails"=>$fails

        ];
        return $res;

    }

    public function getdev(Request $request){
        $data = $request->data;
        $dev = "SELECT * FROM F_FRD WHERE TIPFRD&'-'&CODFRD = "."'".$data['dev']."'";
        $exec = $this->conn->prepare($dev);
        $exec -> execute();
        $devs =$exec->fetch(\PDO::FETCH_ASSOC);
        if($devs){
            $prodev = "SELECT * FROM F_LFD WHERE TIPLFD&'-'&CODLFD = "."'".$data['dev']."'";
            $exec = $this->conn->prepare($prodev);
            $exec -> execute();
            $psdevs =$exec->fetchall(\PDO::FETCH_ASSOC);
            foreach($psdevs as $pros){
                $products[] = [
                    "ARTLTR"=>$pros['ARTLFD'],
                    "DES"=>mb_convert_encoding($pros['DESLFD'],'UTF-8'),
                    "CANLTR"=>$pros['CANLFD'],
                    "PRE"=>$pros['PRELFD'],
                    "TOTAL"=>$pros['TOTLFD']
                ];
            }
            $res = [
                "devolucion"=>$devs['TIPFRD']."-".$devs['CODFRD'],
                "referencia"=>$devs['REFFRD'],
                "total"=>$devs['TOTFRD'],
                "productos"=>$products
            ];
            return response()->json($res,200);
        }else{
            return response()->json("No existe la devolucion",404);
        }

    }

    public function getinvoice(Request $request){
        $data = $request->fac;
        $dev = "SELECT * FROM F_FAC WHERE TIPFAC&'-'&CODFAC = "."'".$data."'";
        $exec = $this->conn->prepare($dev);
        $exec -> execute();
        $devs =$exec->fetch(\PDO::FETCH_ASSOC);
        if($devs){
            $prodev = "SELECT * FROM F_LFA WHERE TIPLFA&'-'&CODLFA = "."'".$data."'";
            $exec = $this->conn->prepare($prodev);
            $exec -> execute();
            $psdevs =$exec->fetchall(\PDO::FETCH_ASSOC);
            foreach($psdevs as $pros){
                $products[] = [
                    "ARTLTR"=>$pros['ARTLFA'],
                    "DES"=>mb_convert_encoding($pros['DESLFA'],'UTF-8'),
                    "CANLTR"=>$pros['CANLFA'],
                    "PRE"=>$pros['PRELFA'],
                    "TOTAL"=>$pros['TOTLFA']
                ];
            }
            $res = [
                "factura"=>$devs['TIPFAC']."-".str_pad($devs['CODFAC'],6,"0",STR_PAD_LEFT),
                "client"=>mb_convert_encoding($devs['CLIFAC'],'UTF-8'),
                "referencia"=>mb_convert_encoding($devs['REFFAC'],'UTF-8'),
                "total"=>$devs['TOTFAC'],
                "productos"=>$products,
                ];
            return response()->json($res,200);
        }else{
            return response()->json("No existe la factura",404);
        }
    }
}
