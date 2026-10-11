<?php

it('responde correctamente en la ruta principal', function () {
    $this->get('/')->assertStatus(200);
});
