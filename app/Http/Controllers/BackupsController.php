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
        $datetime = date('Y-m-d_his');
        $filename = 'RES'.$store.'_'.$datetime.'.rar';
        $archivosCopiados = [];
        $archivosNoCopiados =[];

        if($sucursal == 1){

            $archivos = [
                'C:\Software DELSOL\FACTUSOL\Datos\Datos generales'=>'C:\DATOS\General\FS',
                'C:\Software DELSOL\CONTASOL\Datos\Datos generales'=>'C:\DATOS\General\CS',
                'C:\Software DELSOL\FACTUSOL\Datos\FS'=>'C:\DATOS\Datos\FS',
                'C:\Software DELSOL\CONTASOL\Datos\CS'=>'C:\DATOS\Datos\CS'
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
            $comprimir = 'C:\DATOS';

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
                'C:\Software DELSOL\FACTUSOL\Datos\Datos generales'=>'C:\DATOS\General\FS',
                'C:\Software DELSOL\FACTUSOL\Datos\FS'=>'C:\DATOS\Datos\FS',
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
            $comprimir = 'C:\DATOS';

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
        $datetime = date('Y-m-d_his');
        $filename = 'RES'.$store.'_'.$datetime.'.rar';
        $copiados = [];
        $nocopiados = [];
        $year = date('Y');
        $db = env('DBALIAS');
        $database = $db.$year.'.accdb';
        if($sucursal == 1){

            $archivos = [
                'C:/Software DELSOL/FACTUSOL/Datos/Datos generales/General.accdb' => 'C:/RESEJERCICIO/GENERAL/FS/General.accdb',
                'C:/Software DELSOL/FACTUSOL/Datos/Datos generales/Modelos.accdb' => 'C:/RESEJERCICIO/GENERAL/FS/Modelos.accdb',
                'C:/Software DELSOL/CONTASOL/Datos/Datos generales/General.accdb' => 'C:/RESEJERCICIO/GENERAL/CS/General.accdb',
                'C:/Software DELSOL/CONTASOL/Datos/Datos generales/Modelos.accdb' => 'C:/RESEJERCICIO/GENERAL/CS/Modelos.accdb',
                'C:/Software DELSOL/FACTUSOL/Datos/FS/'.$database =>'C:/RESEJERCICIO/DATOS/FS/'.$database,
                'C:/Software DELSOL/CONTASOL/Datos/CS/'.$database =>'C:/RESEJERCICIO/DATOS/CS/'.$database
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
            $comprimir = 'C:\RESEJERCICIO';

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
                'C:/Software DELSOL/FACTUSOL/Datos/Datos generales/General.accdb' => 'C:/RESEJERCICIO/GENERAL/FS/General.accdb',
                'C:/Software DELSOL/FACTUSOL/Datos/Datos generales/Modelos.accdb' => 'C:/RESEJERCICIO/GENERAL/FS/Modelos.accdb',
                'C:/Software DELSOL/FACTUSOL/Datos/FS/'.$database =>'C:/RESEJERCICIO/DATOS/FS/'.$database,
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
                $comprimir = 'C:\RESEJERCICIO';

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
