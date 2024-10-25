<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Http\Controllers\ZktecoController;
use App\Http\Controllers\accessController;
use App\Http\Controllers\BackupsController;
use App\Http\Controllers\StateController;
use Illuminate\Support\Facades\DB;


class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // envir las asistencias cada min de 9 a 10
        // $device = DB::table('assist_devices')->where('_store',env('STORE'))->first();
        // if($device){
        //     $schedule->call([ZktecoController::class,'replyAssist'])->everyfiveMinutes()->between('9:00','11:00')->name("Replicador Asistencias cada 5 min");//cada 5 min de 8 a 10 de la manana
        //     $schedule->call([ZktecoController::class,'replyAssist'])->hourly()->between('11:00', '22:00')->name("Replicador Asostemcias cada hora");//cada hora entre las 10:01 de la manana hasta las 9:00 de la noche
        //     if(env('STORE') == 1){
        //         $schedule->call([ZktecoController::class,'replyAssisttexc'])->everyfiveMinutes()->between('9:00','11:00')->name("Replicador Asistencias cada 5 min");//cada 5 min de 8 a 10 de la manana
        //         $schedule->call([ZktecoController::class,'replyAssisttexc'])->hourly()->between('11:00', '22:00')->name("Replicador Asostemcias cada hora");//cada hora entre las 10:01 de la manana hasta las 9:00 de la noche
        //     }
        // }



        $schedule->call(function () {
            $controller = new BackupsController();
            $controller->backupsnigth();
        })->everySixHours()->between('7:00','22:00')->name("Respaldos Nube");//Respaldo completo entre las 7 a las 10 cada 6 horas

        $schedule->call(function () {
            $controller = new BackupsController();
            $controller->Backups();
        })->everyTwoHours()->between('10:00', '22:00')->name("Respaldo Local");//Respaldo solo de el ejercico actual

        $schedule->call(function () {
            $controller = new StateController();
            $controller->bills();
        })->dailyAt(5,20)->name("Replicacion Resultados");//Respaldo solo de el ejercico actual

        // $schedule->call(function () {
        //     $controller = new accessController();
        //     $controller->regeneration();
        // })->dailyAt('4:00')->name("Regeneracion de Stock");//Regeneracion de stock a las 3 de la manana

        if(env('STORE') > 1){
            $schedule->call(function () {
                $controller = new accessController();
                $controller->OpeningBox();
            })->dailyAt('7:00')->name("Apertura de caja");//apertura de cajas a las 7:00 de la manana

            $schedule->call(function () {
                $controller = new accessController();
                $controller->Withdrawals();
            })->everyTenMinutes()->between('8:00','23:00')->name("Revisa EFE");//Retiradas de sucursal
        }
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
