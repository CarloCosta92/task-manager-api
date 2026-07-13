<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;

use function Laravel\Prompts\title;

class TaskController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // 1. prendi l'utente autenticato
        $user = auth('api')->user();

        // 2. restituisci solo i suoi task (relazione tasks())
        $tasks = $user->tasks;

        // 3. return response()->json(...) con status/data
        return response()->json([
            "status" => "success",
            "data" => $tasks,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // 1. valida i dati in input (title obbligatorio, description opzionale)
        $validated = $request->validate([
            'title' => 'required',
            'description' => 'nullable',

        ]);
        // 2. crea il task collegato all'utente autenticato
        //    usa la relazione tasks() così user_id viene settato automaticamente
        /** @var \App\Models\User $user */
        $user = auth('api')->user();
        $task = $user->tasks()->create($validated);
        // 3. return response()->json(...) con status 201 (created) e il task creato
        return response()->json([
            "status" => "success",
            "message" => "Task creato con successo",
            "data" => $task,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Task $task)
    {
        $this->authorize('view', $task);

        // 2. return response()->json(...) con il task
        return response()->json([
            "status" => "success",
            "data" => $task,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Task $task)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Task $task)
    {
        $this->authorize('update', $task);

        $validated = $request->validate([
            'title' => 'sometimes',
            'description' => 'sometimes',
        ]);

        $task->update($validated);

        return response()->json([
            "status" => "success",
            "data" => $task,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Task $task)
    {
        $this->authorize('delete', $task);

        $task->delete();

        return response()->json([
            "status" => "success",
            "message" => "Task eliminato con successo",
        ]);
    }
}
