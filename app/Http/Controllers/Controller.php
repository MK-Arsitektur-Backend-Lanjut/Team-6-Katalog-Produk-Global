<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(version: "1.0.0", description: "Dokumentasi API untuk Modul 1", title: "Katalog Produk Global API")]
#[OA\Server(url: 'http://localhost:8000', description: "API Server")]
abstract class Controller
{
    //
}
