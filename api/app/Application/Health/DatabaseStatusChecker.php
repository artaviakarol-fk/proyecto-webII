<?php

namespace App\Application\Health;

/**
 * Puerto: permite saber si la base de datos responde,
 * sin que la capa de aplicación dependa de Laravel.
 */
interface DatabaseStatusChecker
{
    public function isAvailable(): bool;
}
