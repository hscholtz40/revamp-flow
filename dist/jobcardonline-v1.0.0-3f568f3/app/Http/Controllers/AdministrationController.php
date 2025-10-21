<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Group;
use App\Models\Product;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class AdministrationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Administration', [
                'stats' => [
                    'users_count' => User::count(),
                    'groups_count' => Group::count(),
                    'contacts_count' => Contact::count(),
                    'products_count' => Product::count(),
                ],
        ]);
    }
}
