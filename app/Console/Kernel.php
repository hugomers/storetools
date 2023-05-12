<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Http\Controllers\ZktecoController;
use App\Http\Controllers\accessController;
use App\Http\Controllers\BackupsController;


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
        $schedule->call([ZktecoController::class,'replyAssist'])->everyfiveMinutes()->between('9:00','11:00')->name("Replica Asistencias de 8 a 10");//cada 5 min de 8 a 10 de la manana
        $schedule->call([ZktecoController::class,'replyAssist'])->hourly()->between('11:00', '22:00')->name("Replica asistencias de 10 a 9");//cada hora entre las 10:01 de la manana hasta las 9:00 de la noche
        $schedule->call(function () {
            $controller = new accessController();
            $controller->OpeningBox();
        })->dailyAt('8:00')->name("Realiza la apertura de Cajas");//apertura de cajas a las 7:00 de la manana

        $schedule->call(function () {
            $controller = new BackupsController();
            $controller->backupsnigth();
        })->everySixHours()->between('7:00','22:00')->name("Realiza respaldos cada 6 horas Nube");//Respaldo completo entre las 7 a las 10 cada 6 horas

        $schedule->call(function () {
            $controller = new BackupsController();
            $controller->Backups();
        })->everyTwoHours()->between('10:00', '22:00')->name("Se genera respaldo cada 2 horas en local");//Respaldo solo de el ejercico actual

        $schedule->call(function () {
            $controller = new accessController();
            $controller->regeneration();
        })->dailyAt('4:00')->name("Realiza la regeneracion de stock de la sucursal");//Regeneracion de stock a las 3 de la manana

        $schedule->call(function () {
            $controller = new accessController();
            $controller->Withdrawals();
        })->everyTenMinutes()->between('8:00','9:00')->name('Envia mensaje sobre exedente efe');//Retiradas de sucursal
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
