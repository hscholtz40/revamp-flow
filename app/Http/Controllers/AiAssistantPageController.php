<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class AiAssistantPageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('ai/Assistant');
    }
}
