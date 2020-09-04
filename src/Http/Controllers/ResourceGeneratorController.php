<?php

namespace Day4\TreeView\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ResourceGeneratorController extends Controller
{

    public function getResource()
    {
        $query = \App\Skill::all();
        return response()->json([
            'data' => $query
        ]);
    }
}
