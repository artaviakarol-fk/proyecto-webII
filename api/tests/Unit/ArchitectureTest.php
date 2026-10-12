<?php

arch('la capa de aplicación no depende de Laravel')
    ->expect('App\Application')
    ->not->toUse('Illuminate');

arch('la capa de dominio no depende de Laravel')
    ->expect('App\Domain')
    ->not->toUse('Illuminate');
