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
                                'barcode'=>$ean
                            ];

                            $updmys = DB::connection('vizapi')->table('products')->update($updms);
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
            $url = $store['dominio']."/storestools/public/api/Stores/regisproduct";//se optiene el inicio del dominio de la sucursal
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
                                                "MAYOREO"=>$mayoreo
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
        $pripub = json_encode(["prices" => $prduct_prices]);//se codifica el arreglo de los proveedores
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
                }
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

}
//
