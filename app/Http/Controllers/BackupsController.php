<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use Illuminate\Support\Facades\File;

class BackupsController extends Controller
{

    public function backupsnigth(){
        $sucursal = env('STORE');
        $store = env('SUCURSAL');
        $pathcomplete = env('RESPALDOCOMPLETO');
        $factusol = env('FACTUSOL');
        $constasol = env('CONTASOL');
        $pathdat = env('DATOS');

        $datetime = date('Y-m-d_his');
        $filename = 'RES'.$store.'_'.$datetime.'.zip';
        $archivosCopiados = [];
        $archivosNoCopiados =[];

        if($sucursal == 1){

            $archivos = [
                $factusol.'\Datos generales'=>$pathdat.'\General\FS',
                $constasol.'\Datos generales'=>$pathdat.'\General\CS',
                $factusol.'\FS'=>$pathdat.'\Datos\FS',
                $constasol.'\CS'=>$pathdat.'\Datos\CS'
            ];

            foreach($archivos as $rutaOrigen => $rutaDestino) {
                foreach(glob("$rutaOrigen/*.accdb") as $archivo) {
                    $nombreArchivo = basename($archivo);
                    $rutaDestinoArchivo = $rutaDestino . '/' . $nombreArchivo;
                    if(!copy($archivo, $rutaDestinoArchivo)) {
                        // Manejo de error
                        $archivosNoCopiados[] = $nombreArchivo;
                    } else {
                        $archivosCopiados[] = $nombreArchivo;
                    }
                }
            }

            $res = [
                "fail"=>$archivosNoCopiados,
                "goal"=>$archivosCopiados
            ];
            // // Ruta de la carpeta a comprimir
            $comprimir = $pathdat;

            // // Ruta donde se guardara el archivo
            $guardar = $pathcomplete.$filename;//aqui entra el de google para

            // // Crea una instancia de ZipArchive
            $zip = new ZipArchive();

            // Abre el archivo ZIP en modo escritura
            if ($zip->open($guardar, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {

                // Agrega todos los archivos y subcarpetas de la carpeta a comprimir al archivo ZIP
                $dir = new RecursiveDirectoryIterator($comprimir);
                $archivos = new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::LEAVES_ONLY);

                foreach ($archivos as $archivo) {
                    if (!$archivo->isDir()) {
                        $archivo_real = $archivo->getRealPath();
                        $archivo_relativo = substr($archivo_real, strlen($comprimir) + 1);
                        $zip->addFile($archivo_real, $archivo_relativo);
                    }
                }

                // Cierra el archivo ZIP
                $zip->close();

                return response()->json("respaldo generado");
            } else {

                return response()->json('No se pudo crear el archivo RAR.');
            }
        }else {
            $archivos = [
                $factusol.'\Datos generales'=>$pathdat.'\General\FS',
                $factusol.'\FS'=>$pathdat.'\Datos\FS',
            ];

            foreach($archivos as $rutaOrigen => $rutaDestino) {
                foreach(glob("$rutaOrigen/*.accdb") as $archivo) {
                    $nombreArchivo = basename($archivo);
                    $rutaDestinoArchivo = $rutaDestino . '/' . $nombreArchivo;
                    if(!copy($archivo, $rutaDestinoArchivo)) {
                        // Manejo de error
                        $archivosNoCopiados[] = $nombreArchivo;
                    } else {
                        $archivosCopiados[] = $nombreArchivo;
                    }
                }
            }

            $res = [
                "fail"=>$archivosNoCopiados,
                "goal"=>$archivosCopiados
            ];
            // // Ruta de la carpeta a comprimir
            $comprimir = $pathdat;

            // // Ruta donde se guardara el archivo
            $guardar = $pathcomplete.$filename;//aqui entra el de google para

            // // Crea una instancia de ZipArchive
            $zip = new ZipArchive();

            // Abre el archivo ZIP en modo escritura
            if ($zip->open($guardar, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {

                // Agrega todos los archivos y subcarpetas de la carpeta a comprimir al archivo ZIP
                $dir = new RecursiveDirectoryIterator($comprimir);
                $archivos = new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::LEAVES_ONLY);

                foreach ($archivos as $archivo) {
                    if (!$archivo->isDir()) {
                        $archivo_real = $archivo->getRealPath();
                        $archivo_relativo = substr($archivo_real, strlen($comprimir) + 1);
                        $zip->addFile($archivo_real, $archivo_relativo);
                    }
                }

                // Cierra el archivo ZIP
                $zip->close();

                return response()->json("respaldo generado");
            } else {
                return response()->json('No se pudo crear el archivo RAR.');
            }
        }
    }

    public function Backups(){
        $store = env('SUCURSAL');
        $sucursal = env('STORE');
        $respaldodiario = env('RESPALDODIARIO');
        $factusol = env('FACTUSOL');
        $constasol = env('CONTASOL');
        $reseje = env('RESEJERCICIO');
        $datetime = date('Y-m-d_his');
        $filename = 'RES'.$store.'_'.$datetime.'.zip';
        $copiados = [];
        $nocopiados = [];
        $year = date('Y');
        $db = env('DBALIAS');
        $database = $db.$year.'.accdb';
        if($sucursal == 1){

            $archivos = [
                $factusol.'/Datos generales/General.accdb' => $reseje.'/GENERAL/FS/General.accdb',
                $factusol.'/Datos generales/Modelos.accdb' => $reseje.'/GENERAL/FS/Modelos.accdb',
                $constasol.'/Datos generales/General.accdb' => $reseje.'/GENERAL/CS/General.accdb',
                $constasol.'/Datos generales/Modelos.accdb' => $reseje.'/GENERAL/CS/Modelos.accdb',
                $factusol.'/FS/'.$database =>$reseje.'/DATOS/FS/'.$database,
                $constasol.'/CS/'.$database =>$reseje.'/DATOS/CS/'.$database
            ];

            foreach ($archivos as $archivo => $destino) {
                if (File::copy($archivo, $destino)) {
                    $copiados[] = $archivo;
                }else {
                    $nocopiados[] = $archivo;
                }
            }
            $res = [
                "copiados"=>$copiados,
                "no_copiados"=>$nocopiados
            ];
            // // Ruta de la carpeta a comprimir
            $comprimir = $reseje;

            // // Ruta donde se guardara el archivo
            $guardar = $respaldodiario.$filename;//AQUI ENTRA GOOGLE EN ESTE CASO RESPALDO DIARIO CADA 3 HORAS

            // // Crea una instancia de ZipArchive
            $zip = new ZipArchive();

            // Abre el archivo ZIP en modo escritura
            if ($zip->open($guardar, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {

                // Agrega todos los archivos y subcarpetas de la carpeta a comprimir al archivo ZIP
                $dir = new RecursiveDirectoryIterator($comprimir);
                $archivos = new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::LEAVES_ONLY);

                foreach ($archivos as $archivo) {
                    if (!$archivo->isDir()) {
                        $archivo_real = $archivo->getRealPath();
                        $archivo_relativo = substr($archivo_real, strlen($comprimir) + 1);
                        $zip->addFile($archivo_real, $archivo_relativo);
                    }
                }

                // Cierra el archivo ZIP
                $zip->close();

                return response()->json("respaldo generado");
            } else {
                return response()->json('No se pudo crear el archivo RAR.');
            }
        }else {
            $archivos = [
                $factusol.'/Datos generales/General.accdb' => $reseje.'/GENERAL/FS/General.accdb',
                $factusol.'/Datos generales/Modelos.accdb' => $reseje.'/GENERAL/FS/Modelos.accdb',
                $factusol.'/FS/'.$database =>$reseje.'/DATOS/FS/'.$database,
            ];

            foreach ($archivos as $archivo => $destino) {
                if (File::copy($archivo, $destino)) {
                    $copiados[] = $archivo;
                }else {
                    $nocopiados[] = $archivo;
                }
            }
            $res = [
                "copiados"=>$copiados,
                "no_copiados"=>$nocopiados
            ];
                // // Ruta de la carpeta a comprimir
                $comprimir = $reseje;

                // // Ruta donde se guardara el archivo
                $guardar = $respaldodiario.$filename;//AQUI ENTRA GOOGLE EN ESTE CASO RESPALDO DIARIO CADA 3 HORAS

                // // Crea una instancia de ZipArchive
                $zip = new ZipArchive();

                // Abre el archivo ZIP en modo escritura
                if ($zip->open($guardar, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {

                    // Agrega todos los archivos y subcarpetas de la carpeta a comprimir al archivo ZIP
                    $dir = new RecursiveDirectoryIterator($comprimir);
                    $archivos = new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::LEAVES_ONLY);

                    foreach ($archivos as $archivo) {
                        if (!$archivo->isDir()) {
                            $archivo_real = $archivo->getRealPath();
                            $archivo_relativo = substr($archivo_real, strlen($comprimir) + 1);
                            $zip->addFile($archivo_real, $archivo_relativo);
                        }
                    }

                    // Cierra el archivo ZIP
                    $zip->close();

                    return response()->json("respaldo generado");
                } else {
                    return response()->json('No se pudo crear el archivo RAR.');
                }
        }
    }

}
