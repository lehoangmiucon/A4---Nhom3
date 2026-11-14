<?php

namespace App\Http\Controllers;

use App\Support\OpenApiDocument;
use Illuminate\Http\Request;

class ApiDocumentationController extends Controller
{
    public function index(Request $request, OpenApiDocument $document)
    {
        return response()->view('api-docs', ['spec' => $document->build($request)])
            ->header('Cache-Control', 'no-store');
    }
}
