<?php

namespace App\Http\Controllers\Api;

use App\Models\Trip;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;


class TripController extends Controller
{
    /**
     * Display a listing of the resource.
     */
 public function index(Request $request)
{
    return response()->json($request->user()->trips()->get());
}

    /**
     * Store a newly created resource in storage.
     */
   public function store(Request $request)
{
    $data = $request->validate([
        'origin' => ['required', 'string', 'max:255'],
        'destination' => ['required', 'string', 'max:255'],
        'departure_at' => ['required', 'date'],
        'price' => ['required', 'numeric', 'min:0'],
    ]);

    $trip = new Trip();
    $trip->fill($data);
   $trip->user_id = $request->user()->id;
    $trip->status = 'pending';
    $trip->save();

    return response()->json($trip, 201);
}
    /**
     * Display the specified resource.
     */
   public function show(Request $request, string $id)
{
    $trip = $request->user()->trips()->findOrFail($id);

    return response()->json($trip);
}

    /**
     * Update the specified resource in storage.
     */
   public function update(Request $request, string $id)
{
    $trip = $request->user()->trips()->findOrFail($id);

    $data = $request->validate([
        'origin' => ['sometimes', 'required', 'string', 'max:255'],
        'destination' => ['sometimes', 'required', 'string', 'max:255'],
        'departure_at' => ['sometimes', 'required', 'date'],
        'price' => ['sometimes', 'required', 'numeric', 'min:0'],
        'status' => ['sometimes', 'required', 'in:pending,completed,cancelled'],
    ]);

    $trip->update($data);

    return response()->json($trip);
}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
{
    $trip = $request->user()->trips()->findOrFail($id);
    $trip->delete();

    return response()->json([
        'message' => 'Viaje eliminado correctamente',
    ]);
}
}
