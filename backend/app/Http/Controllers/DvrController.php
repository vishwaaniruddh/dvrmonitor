<?php

namespace App\Http\Controllers;

use App\Models\Dvr;
use Illuminate\Http\Request;

class DvrController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $dvrs = Dvr::all();
        return response()->json($dvrs);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'dvr_name' => 'required|string|max:255',
            'ip' => 'required|ip',
            'port' => 'required|integer|min:1|max:65535',
            'username' => 'required|string|max:255',
            'password' => 'required|string|max:255',
            'is_active' => 'boolean'
        ]);

        $dvr = Dvr::create($validated);
        return response()->json($dvr, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Dvr $dvr)
    {
        return response()->json($dvr);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Dvr $dvr)
    {
        $validated = $request->validate([
            'dvr_name' => 'sometimes|string|max:255',
            'ip' => 'sometimes|ip',
            'port' => 'sometimes|integer|min:1|max:65535',
            'username' => 'sometimes|string|max:255',
            'password' => 'sometimes|string|max:255',
            'is_active' => 'sometimes|boolean'
        ]);

        $dvr->update($validated);
        return response()->json($dvr);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Dvr $dvr)
    {
        $dvr->delete();
        return response()->json(['message' => 'DVR deleted successfully']);
    }
}
