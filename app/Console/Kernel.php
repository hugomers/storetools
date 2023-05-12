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
        $schedule->call([ZktecoController::class,'replyAssist'])->everyfiveMinutes()->between('9:00','11:00');//cada 5 min de 8 a 10 de la manana
        $schedule->call([ZktecoController::class,'replyAssist'])->hourly()->between('11:00', '22:00');;//cada hora entre las 10:01 de la manana hasta las 9:00 de la noche
        $schedule->call(function () {
            $controller = new accessController();
            $controller->OpeningBox();
        })->dailyAt('8:00');//apertura de cajas a las 7:00 de la manana

        $schedule->call(function () {
            $controller = new BackupsController();
            $controller->backupsnigth();
        })->everySixHour()->between('7:00','22:00');//Respaldo completo entre las 7 a las 10 cada 6 horas

        $schedule->call(function () {
            $controller = new BackupsController();
            $controller->Backups();
        })->everyTwoHours()->between('10:00', '22:00');;//Respaldo solo de el ejercico actual

        $schedule->call(function () {
            $controller = new accessController();
            $controller->regeneration();
        })->dailyAt('4:00');//Regeneracion de stock a las 3 de la manana

        $schedule->call(function () {
            $controller = new accessController();
            $controller->regeneration();
        })->everyTenMinutes()->between('8:00','9:00');//Retiradas de sucursal
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
