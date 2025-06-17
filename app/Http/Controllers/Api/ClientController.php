<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     * GET /api/clients
     */
    public function index(Request $request)
    {
        $clients = $request->user()->clients()->with('appointments')->get();

        return response()->json([
            'success' => true,
            'message' => 'Clients retrieved successfully',
            'data' => $clients
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created resource in storage.
     * POST /api/clients
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'phone_number' => 'nullable|string|max:255',
            'timezone' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $client = $request->user()->clients()->create([
            'name' => $request->name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'timezone' => $request->timezone ?? 'UTC',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Client created successfully',
            'data' => $client
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     * GET /api/clients/{client}
     */
    public function show(Request $request, Client $client)
    {
        // Ensure the client belongs to the authenticated user
        if ($client->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Client not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $client->load('appointments');

        return response()->json([
            'success' => true,
            'message' => 'Client retrieved successfully',
            'data' => $client
        ], Response::HTTP_OK);
    }

    /**
     * Update the specified resource in storage.
     * PUT/PATCH /api/clients/{client}
     */
    public function update(Request $request, Client $client)
    {
        // Ensure the client belongs to the authenticated user
        if ($client->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Client not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255',
            'phone_number' => 'nullable|string|max:255',
            'timezone' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $client->update($request->only(['name', 'email', 'phone_number', 'timezone']));

        return response()->json([
            'success' => true,
            'message' => 'Client updated successfully',
            'data' => $client
        ], Response::HTTP_OK);
    }

    /**
     * Remove the specified resource from storage.
     * DELETE /api/clients/{client}
     */
    public function destroy(Request $request, Client $client)
    {
        // Ensure the client belongs to the authenticated user
        if ($client->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Client not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $client->delete();

        return response()->json([
            'success' => true,
            'message' => 'Client deleted successfully'
        ], Response::HTTP_OK);
    }
}
