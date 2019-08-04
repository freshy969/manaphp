<?php
namespace App;

use App\Controllers\TimeController;

class Router extends \ManaPHP\Router
{
    public function __construct()
    {
        parent::__construct(true);
        $this->add('/', [TimeController::class, 'current']);
        $this->add('/time/current', [TimeController::class, 'current']);
        $this->add('/time/timestamp', [TimeController::class, 'timestamp']);
    }
}