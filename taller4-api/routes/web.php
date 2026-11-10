<?php

use Illuminate\Support\Facades\Route;

// El front (public/app/index.html) se abre desde la raíz
Route::redirect('/', '/app/index.html');
